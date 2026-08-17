<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\Service\Auth;

use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Event\Auth\AfterLoginEvent;
use YiiRocks\Voyti\Helper\LoginMetadataHelper;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\ServiceResult;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\SocialAuth\Model\UserSocialAccount;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Json\Json;
use Yiisoft\Security\Random;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;

/**
 * Handles social login: looks up or creates the {@see UserSocialAccount} for a provider callback,
 * logging in an already-connected user, auto-registering a new one when enabled and the email is
 * free, or otherwise deferring to {@see PendingSocialAccountService} until the account is
 * connected manually.
 */
final readonly class UserSocialAuthenticateService
{
    private const int MAX_USERNAME_SUFFIX = 1000;
    private const string SESSION_KEY = 'oauth_client_data';

    public function __construct(
        private VoytiConfig $config,
        private bool $enableSocialNetworkRegistration,
        private CurrentUser $currentUser,
        private SessionInterface $session,
        private EventDispatcherInterface $eventDispatcher,
        private UserCreationHelper $userCreationHelper,
        private PendingSocialAccountService $pendingSocialAccountService,
        private TranslatorInterface $translator,
    ) {}

    /**
     * @param array<array-key, mixed> $userAttributes
     * @param array<array-key, mixed> $serverParams
     */
    public function run(
        string $provider,
        string $clientId,
        array $userAttributes,
        array $serverParams = [],
    ): ServiceResult {
        if (!$this->enableSocialNetworkRegistration) {
            return ServiceResult::failure(
                $this->translator->translate('voyti.social.registration_disabled', category: 'voyti-social-auth'),
            );
        }

        if ($clientId === '') {
            /** @var mixed $oauthData */
            $oauthData = $this->session->get(self::SESSION_KEY);
            if ($oauthData !== null && is_array($oauthData)) {
                /** @infection-ignore-all Defensive string coercion of the session-stored user id; identical for the string ids the flow stores. */
                $clientId = (string) ($oauthData['user_id'] ?? '');
                $userAttributes = array_merge($oauthData, $userAttributes);
            }
        }

        if ($clientId === '') {
            return ServiceResult::failure(
                $this->translator->translate('voyti.social.client_id_unknown', category: 'voyti-social-auth'),
            );
        }

        $account = UserSocialAccount::findByProviderAndClientId($provider, $clientId);

        if ($account === null) {
            $account = $this->createAccount($provider, $clientId, $userAttributes, $serverParams);
        }

        if ($account->getUserId() !== null) {
            $user = User::findById($account->getUserId());
            if ($user === null) {
                return ServiceResult::failure(
                    $this->translator->translate('voyti.social.associated_user_not_found', category: 'voyti-social-auth'),
                );
            }
            if ($user->isBlocked()) {
                return ServiceResult::failure(
                    $this->translator->translate('voyti.social.account_blocked', category: 'voyti-social-auth'),
                );
            }

            $previousSessionId = $this->session->getId();
            $this->currentUser->login($user);
            LoginMetadataHelper::recordLogin($user, $serverParams);
            $this->eventDispatcher->dispatch(
                new AfterLoginEvent($user, previousSessionId: $previousSessionId, serverParams: $serverParams),
            );

            $this->session->remove(self::SESSION_KEY);

            return ServiceResult::success();
        }

        $code = $account->getCode();
        if ($code === null || $code === '') {
            return ServiceResult::failure(
                $this->translator->translate('voyti.social.connection_unavailable', category: 'voyti-social-auth'),
            );
        }

        $this->pendingSocialAccountService->remember($account);

        return ServiceResult::success();
    }

    private function buildUniqueUsername(?string $usernameHint, string $email): string
    {
        /** @infection-ignore-all The explode limit is immaterial - [0] is the part before the first '@' for any limit >= 1; the hint/email-prefix/'user' fallback chain is covered behaviourally. */
        $base = $this->sanitizeUsername($usernameHint) ?? $this->sanitizeUsername(explode('@', $email, 2)[0]) ?? 'user';

        $username = $base;
        $suffix = 2;
        // Free bases return immediately, and the search below only runs when the base is taken. The
        // bound caps the sequential suffix search so the loop always terminates, even under an inverted
        // condition, instead of spinning forever on an already-saturated username space.
        if (User::findByUsername($username) !== null) {
            while (User::findByUsername($username) !== null && $suffix <= self::MAX_USERNAME_SUFFIX) {
                $username = $base . '_' . $suffix;
                $suffix++;
            }
        }

        return $username;
    }

    /**
     * @param array $attributes
     * @param array<array-key, mixed> $serverParams
     */
    private function createAccount(
        string $provider,
        string $clientId,
        array $attributes,
        array $serverParams,
    ): UserSocialAccount {
        $account = new UserSocialAccount();
        $account->setProvider($provider);
        $account->setClientId($clientId);
        $username = $this->stringAttribute($attributes, 'username') ?? $this->stringAttribute($attributes, 'name');
        $email = $this->stringAttribute($attributes, 'email');
        $account->setUsername($username);
        $account->setEmail($email);
        $account->setData(Json::encode($attributes));
        $account->setCreatedAt(time());

        $email = $account->getEmail();
        if ($email !== null && $this->config->enableRegistration && User::findByEmail($email) === null) {
            $user = $this->registerUser($email, $account->getUsername(), $serverParams);
            $account->setUserId((int) $user->getId());
            $account->setUsername(null);
            $account->setEmail(null);
        } else {
            $account->setCode(Random::string(32));
        }

        $account->save();

        return $account;
    }

    /**
     * @param array<array-key, mixed> $serverParams
     */
    private function registerUser(string $email, ?string $usernameHint, array $serverParams): User
    {
        $username = $this->buildUniqueUsername($usernameHint, $email);
        /** @infection-ignore-all The random password's exact length is unobservable once hashed. */
        $password = Random::string(24);

        $user = $this->userCreationHelper->buildUser($email, $username, $password);
        $user->setRegistrationIp(LoginMetadataHelper::remoteAddr($serverParams));

        $this->userCreationHelper->persistAndNotifySkippingConfirmation($user);

        return $user;
    }

    private function sanitizeUsername(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        /** @infection-ignore-all The cast is defensive: preg_replace only returns null on a malformed pattern, which this constant one never is. */
        $sanitized = (string) preg_replace('/[^-a-zA-Z0-9_.@]/', '', $value);

        return $sanitized !== '' ? substr($sanitized, 0, 250) : null;
    }

    /**
     * @param array<array-key, mixed> $attributes
     *
     * @return null|string
     */
    private function stringAttribute(array $attributes, string $key): ?string
    {
        /** @var mixed $value */
        $value = $attributes[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
