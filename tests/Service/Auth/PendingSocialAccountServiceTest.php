<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\tests\Service\Auth;

use PHPUnit\Framework\Attributes\DataProvider;
use YiiRocks\Voyti\SocialAuth\Model\UserSocialAccount;
use YiiRocks\Voyti\SocialAuth\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\SocialAuth\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\SocialAuth\tests\Support\FakeSession;
use YiiRocks\Voyti\SocialAuth\tests\Support\UserFactoryTrait;

final class PendingSocialAccountServiceTest extends DatabaseTestCase
{
    use UserFactoryTrait;

    private PendingSocialAccountService $service;

    private FakeSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = new FakeSession();
        $this->session->open();
        $this->service = $this->createService();
    }

    public static function rememberCodeProvider(): iterable
    {
        yield 'empty string code does not store' => ['', null];
        yield 'non-null non-empty code stores' => ['remember_code', 'remember_code'];
        yield 'null code does not store' => [null, null];
    }

    public function testClearRemovesSessionKey(): void
    {
        $this->session->set('social_auth_account_code', 'some_code');
        self::assertTrue($this->session->has('social_auth_account_code'));

        $this->service->clear();
        self::assertFalse($this->session->has('social_auth_account_code'));
    }

    public function testConnect(): void
    {
        // No pending account: success with nothing to connect.
        $user = $this->createUser(username: 'test');
        $result = $this->service->connect($user);
        self::assertTrue($result->isSuccess());

        // Pending account: connected and the session is cleared.
        $user = $this->createUser(username: 'linker', email: 'linker@example.com');
        $this->createPendingAccount('123', 'pending_code');
        $this->session->set('social_auth_account_code', 'pending_code');
        $result = $this->service->connect($user);
        self::assertTrue($result->isSuccess());
        self::assertNull(UserSocialAccount::findByCode('pending_code'));
        self::assertFalse($this->session->has('social_auth_account_code'));

        // Different provider already connected, multiple accounts disabled: still links.
        $user = $this->createUser(username: 'crossprovider', email: 'cross@example.com');
        $this->createPendingAccount('124', 'other_provider_code');
        $this->session->set('social_auth_account_code', 'other_provider_code');
        $this->createConnectedAccount('google', 'google_client', (int) $user->getId());
        $result = $this->service->connect($user);
        self::assertTrue($result->isSuccess());
        self::assertNull(UserSocialAccount::findByCode('other_provider_code'));
        self::assertFalse($this->session->has('social_auth_account_code'));

        // Same provider already connected, multiple accounts disabled: refused and the session is
        // cleared, leaving the pending account unconnected.
        $user = $this->createUser(username: 'refused', email: 'refused@example.com');
        $this->createPendingAccount('125', 'refused_code');
        $this->session->set('social_auth_account_code', 'refused_code');
        $this->createConnectedAccount('github', 'existing_client', (int) $user->getId());
        $result = $this->service->connect($user);
        self::assertTrue($result->isFailure());
        $loaded = UserSocialAccount::findByCode('refused_code');
        self::assertNotNull($loaded);
        self::assertFalse($loaded->isConnected());
        self::assertFalse($this->session->has('social_auth_account_code'));

        // Same provider already connected, multiple accounts allowed: links anyway.
        $this->createPendingAccount('126', 'allowed_code');
        $this->session->set('social_auth_account_code', 'allowed_code');
        $result = $this->createService(allowMultipleAccountsPerProvider: true)->connect($user);
        self::assertTrue($result->isSuccess());
        self::assertNull(UserSocialAccount::findByCode('allowed_code'));
        self::assertFalse($this->session->has('social_auth_account_code'));
    }

    public function testGetPendingAccount(): void
    {
        // Session code without a matching account: null and the session is cleared.
        $this->session->set('social_auth_account_code', 'missing');
        $result = $this->service->getPendingAccount();
        self::assertNull($result);
        self::assertFalse($this->session->has('social_auth_account_code'));

        // Invalid session code (empty string, non-string): null.
        $this->session->set('social_auth_account_code', '');
        self::assertNull($this->service->getPendingAccount());
        $this->session->set('social_auth_account_code', 5);
        self::assertNull($this->service->getPendingAccount());

        // Connected account code: null and the session is cleared.
        $user = $this->createUser(username: 'test');
        $this->createPendingAccount('106', 'connected_get_code', (int) $user->getId());
        $this->session->set('social_auth_account_code', 'connected_get_code');
        $result = $this->service->getPendingAccount();
        self::assertNull($result);
        self::assertFalse($this->session->has('social_auth_account_code'));

        // Pending account: returned and the session is retained.
        $this->createPendingAccount('107', 'pending_get_code');
        $this->session->set('social_auth_account_code', 'pending_get_code');
        $result = $this->service->getPendingAccount();
        self::assertNotNull($result);
        self::assertSame('pending_get_code', $result->getCode());
        self::assertTrue($this->session->has('social_auth_account_code'));
    }

    public function testHandleConnectsPendingAccount(): void
    {
        // handle() is the PostLoginHookInterface/PostRegistrationHookInterface entrypoint core
        // consults; it must behave exactly like connect() for a pending account.
        $user = $this->createUser(username: 'test');
        $this->createPendingAccount('123', 'handle_code');
        $this->session->set('social_auth_account_code', 'handle_code');

        $this->service->handle($user);

        self::assertNull(UserSocialAccount::findByCode('handle_code'));
        self::assertFalse($this->session->has('social_auth_account_code'));
    }

    #[DataProvider('rememberCodeProvider')]
    public function testRememberStoresCodeInSession(?string $code, ?string $expectedStored): void
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('101');
        $account->setCode($code);
        $account->setData('{}');
        $account->setCreatedAt(time());

        $this->service->remember($account);

        if ($expectedStored === null) {
            self::assertFalse($this->session->has('social_auth_account_code'));
        } else {
            self::assertSame($expectedStored, $this->session->get('social_auth_account_code'));
        }
    }

    public function testUseCode(): void
    {
        // Unknown code: null and the session is cleared.
        $this->session->set('social_auth_account_code', 'unknown_code');
        $result = $this->service->useCode('unknown_code');
        self::assertNull($result);
        self::assertFalse($this->session->has('social_auth_account_code'));

        // Connected account code: null and the session is cleared.
        $user = $this->createUser(username: 'test');
        $this->createPendingAccount('107', 'connected_use_clear', (int) $user->getId());
        $this->session->set('social_auth_account_code', 'connected_use_clear');
        $result = $this->service->useCode('connected_use_clear');
        self::assertNull($result);
        self::assertFalse($this->session->has('social_auth_account_code'));

        // Unconnected account code: stored in the session and returned.
        $this->createPendingAccount('104', 'use_code');
        $result = $this->service->useCode('use_code');
        self::assertNotNull($result);
        self::assertSame('use_code', $result->getCode());
        self::assertSame('use_code', $this->session->get('social_auth_account_code'));
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

    private function createPendingAccount(string $clientId, ?string $code, ?int $userId = null): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId($clientId);
        $account->setCode($code);
        $account->setData('{}');
        if ($userId !== null) {
            $account->setUserId($userId);
        }
        $account->setCreatedAt(time());
        $account->save();

        return $account;
    }

    private function createService(bool $allowMultipleAccountsPerProvider = false): PendingSocialAccountService
    {
        return new PendingSocialAccountService($this->session, $allowMultipleAccountsPerProvider);
    }
}
