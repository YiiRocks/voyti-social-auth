<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\Service\Auth;

use Override;
use YiiRocks\Voyti\Auth\PostLoginHookInterface;
use YiiRocks\Voyti\Auth\PostRegistrationHookInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\ServiceResult;
use YiiRocks\Voyti\SocialAuth\Model\UserSocialAccount;
use Yiisoft\Session\SessionInterface;

/**
 * Tracks, in the session, a {@see UserSocialAccount} awaiting connection to a user account
 * (e.g. after a social sign-in for an email that doesn't match an existing user), and connects it
 * once the user is known - via core's `voyti.post-login-hook`/`voyti.post-registration-hook` seams
 * ({@see PostLoginHookInterface}, {@see PostRegistrationHookInterface}), whichever completes first.
 */
final readonly class PendingSocialAccountService implements PostLoginHookInterface, PostRegistrationHookInterface
{
    public const string SESSION_KEY = 'social_network_account_code';

    public function __construct(
        private SessionInterface $session,
        private bool $allowMultipleAccountsPerProvider,
    ) {}

    public function clear(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }

    public function connect(User $user): ServiceResult
    {
        $account = $this->getPendingAccount();
        if ($account === null) {
            return ServiceResult::success();
        }

        if (
            !$this->allowMultipleAccountsPerProvider
            && UserSocialAccount::findByUserIdAndProvider((int) $user->getId(), $account->getProvider()) !== null
        ) {
            $this->clear();

            return ServiceResult::failure();
        }

        $account->connect($user);
        $this->clear();

        return ServiceResult::success();
    }

    public function getPendingAccount(): ?UserSocialAccount
    {
        $code = $this->code();
        if ($code === null) {
            return null;
        }

        $account = UserSocialAccount::findByCode($code);
        if ($account === null || $account->isConnected()) {
            $this->clear();
            return null;
        }

        return $account;
    }

    #[Override]
    public function handle(User $user): void
    {
        $this->connect($user);
    }

    public function remember(UserSocialAccount $account): void
    {
        $code = $account->getCode();
        if ($code !== null && $code !== '') {
            $this->session->set(self::SESSION_KEY, $code);
        }
    }

    public function useCode(string $code): ?UserSocialAccount
    {
        $account = UserSocialAccount::findByCode($code);
        if ($account === null || $account->isConnected()) {
            $this->clear();
            return null;
        }

        $this->session->set(self::SESSION_KEY, $code);

        return $account;
    }

    private function code(): ?string
    {
        /** @var mixed $code */
        $code = $this->session->get(self::SESSION_KEY);

        return is_string($code) && $code !== '' ? $code : null;
    }
}
