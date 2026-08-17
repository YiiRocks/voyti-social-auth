<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\tests\Service\Auth;

use YiiRocks\Voyti\SocialAuth\Model\UserSocialAccount;
use YiiRocks\Voyti\SocialAuth\Service\Auth\UserSocialAccountConnectService;
use YiiRocks\Voyti\SocialAuth\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\SocialAuth\tests\Support\SocialAuthTranslatorFactory;
use YiiRocks\Voyti\SocialAuth\tests\Support\UserFactoryTrait;

final class UserSocialAccountConnectServiceTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    public function testRun(): void
    {
        $user = $this->createUser('test', 'test@example.com');
        $this->createConnectedAccount('github', 'client123', (int) $user->getId());

        // Existing connected account for the same user: no-op success.
        $result = $this->createService()->run('github', 'client123', ['email' => 'test@example.com'], (int) $user->getId());
        self::assertTrue($result->isSuccess());
        $saved = UserSocialAccount::findByProviderAndClientId('github', 'client123');
        self::assertNotNull($saved);
        self::assertSame((int) $user->getId(), $saved->getUserId());

        // Existing connected account for a different user: failure.
        $result = $this->createService()->run('github', 'client123', ['email' => 'test@example.com'], 42);
        self::assertTrue($result->isFailure());
        self::assertSame('This account has already been connected to another user', $result->getMessage());

        // Existing unconnected account: updated and connected, stale identity cleared.
        $target = $this->createUser('target', 'target@example.com');
        $this->createUnconnectedAccount('existing_unconnected');
        $result = $this->createService()->run('github', 'existing_unconnected', ['email' => 'new@example.com'], (int) $target->getId());
        self::assertTrue($result->isSuccess());
        $saved = UserSocialAccount::findByProviderAndClientId('github', 'existing_unconnected');
        self::assertNotNull($saved);
        self::assertSame((int) $target->getId(), $saved->getUserId());
        self::assertNull($saved->getCode());
        self::assertNull($saved->getUsername());
        self::assertNull($saved->getEmail());

        // New account: created and connected, raw attributes retained.
        $attributes = ['email' => 'new@example.com'];
        $result = $this->createService()->run('github', 'new_client', $attributes, 100);
        self::assertTrue($result->isSuccess());
        $saved = UserSocialAccount::findByProviderAndClientId('github', 'new_client');
        self::assertNotNull($saved);
        self::assertSame(100, $saved->getUserId());
        self::assertSame(json_encode($attributes, JSON_THROW_ON_ERROR), $saved->getData());
        self::assertGreaterThan(0, $saved->getCreatedAt());
        self::assertNull($saved->getEmail());
        self::assertNull($saved->getUsername());
        self::assertNull($saved->getCode());
    }

    public function testRunSecondProviderIdentity(): void
    {
        $user = $this->createUser('test', 'test@example.com');
        $this->createConnectedAccount('github', 'first_client', (int) $user->getId());

        // Multiple accounts allowed: a second identity of the same provider links.
        $result = $this->createService(allowMultipleAccountsPerProvider: true)
            ->run('github', 'second_client', ['email' => 'other@example.com'], (int) $user->getId());
        self::assertTrue($result->isSuccess());
        $saved = UserSocialAccount::findByProviderAndClientId('github', 'second_client');
        self::assertNotNull($saved);
        self::assertSame((int) $user->getId(), $saved->getUserId());

        // Multiple accounts disabled: a second identity of the same provider is refused.
        $result = $this->createService(allowMultipleAccountsPerProvider: false)
            ->run('github', 'third_client', ['email' => 'other@example.com'], (int) $user->getId());
        self::assertTrue($result->isFailure());
        self::assertSame('You already have an account connected via this provider', $result->getMessage());
        self::assertNull(UserSocialAccount::findByProviderAndClientId('github', 'third_client'));

        // Multiple accounts disabled: a different provider still connects.
        $result = $this->createService(allowMultipleAccountsPerProvider: false)
            ->run('google', 'google_client', [], (int) $user->getId());
        self::assertTrue($result->isSuccess());
        $saved = UserSocialAccount::findByProviderAndClientId('google', 'google_client');
        self::assertNotNull($saved);
        self::assertSame((int) $user->getId(), $saved->getUserId());
    }

    private function createConnectedAccount(string $provider, string $clientId, int $userId): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setProvider($provider);
        $account->setClientId($clientId);
        $account->setData('{}');
        $account->setUserId($userId);
        $account->setCreatedAt(time());
        $account->save();

        return $account;
    }

    private function createService(bool $allowMultipleAccountsPerProvider = false): UserSocialAccountConnectService
    {
        return new UserSocialAccountConnectService(
            SocialAuthTranslatorFactory::create(),
            $allowMultipleAccountsPerProvider,
        );
    }

    private function createUnconnectedAccount(string $clientId, string $provider = 'github'): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setProvider($provider);
        $account->setClientId($clientId);
        $account->setCode('old_code');
        $account->setUsername('olduser');
        $account->setEmail('old@example.com');
        $account->setData('{}');
        $account->setCreatedAt(time());
        $account->save();

        return $account;
    }
}
