<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\Service\Auth;

use YiiRocks\Voyti\SocialAuth\Model\UserSocialAccount;
use Yiisoft\Json\Json;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Links a social provider identity to an already-authenticated user, creating the
 * {@see UserSocialAccount} row if it doesn't exist yet and failing if it's already connected to
 * a different user. When {@see UserSocialAccountConnectService::$allowMultipleAccountsPerProvider}
 * is disabled, also fails if the user already has a different identity from the same provider.
 */
final readonly class UserSocialAccountConnectService
{
    public function __construct(
        private TranslatorInterface $translator,
        private bool $allowMultipleAccountsPerProvider,
    ) {}

    public function run(string $provider, string $clientId, array $userAttributes, int $userId): SocialAuthResult
    {
        $account = UserSocialAccount::findByProviderAndClientId($provider, $clientId);

        if ($account !== null && $account->getUserId() === $userId) {
            return SocialAuthResult::success();
        }

        if ($account !== null && $account->getUserId() !== null) {
            return SocialAuthResult::failure(
                $this->translator->translate('voyti.social.account_already_connected', category: 'voyti-social-auth'),
            );
        }

        if (
            !$this->allowMultipleAccountsPerProvider
            && UserSocialAccount::findByUserIdAndProvider($userId, $provider) !== null
        ) {
            return SocialAuthResult::failure(
                $this->translator->translate('voyti.social.provider_already_connected', category: 'voyti-social-auth'),
            );
        }

        if ($account === null) {
            $account = new UserSocialAccount();
            $account->setProvider($provider);
            $account->setClientId($clientId);
            $account->setData(Json::encode($userAttributes));
            $account->setCreatedAt(time());
        }

        $account->setUserId($userId);
        $account->setUsername(null);
        $account->setEmail(null);
        $account->setCode(null);

        $account->save();

        return SocialAuthResult::success();
    }
}
