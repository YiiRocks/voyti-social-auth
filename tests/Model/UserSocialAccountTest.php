<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\tests\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\SocialAuth\Model\UserSocialAccount;
use YiiRocks\Voyti\SocialAuth\tests\TestCase;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;

final class UserSocialAccountTest extends TestCase
{
    private ?ConnectionInterface $connection = null;

    protected function setUp(): void
    {
        $connection = $this->createSqliteConnection();
        ConnectionProvider::set($connection);
        $this->connection = $connection;

        $this->connection->createCommand('
            CREATE TABLE "user" (
                "id" INTEGER PRIMARY KEY AUTOINCREMENT,
                "username" VARCHAR(255) NOT NULL,
                "email" VARCHAR(255) NOT NULL,
                "password_hash" VARCHAR(255) NOT NULL,
                "auth_key" VARCHAR(32) NOT NULL,
                "blocked_at" INTEGER,
                "confirmed_at" INTEGER,
                "created_at" INTEGER NOT NULL,
                "flags" INTEGER NOT NULL DEFAULT 0,
                "data_processing_consent_date" INTEGER,
                "anonymized" INTEGER NOT NULL DEFAULT 0,
                "last_login_at" INTEGER,
                "last_login_ip" VARCHAR(45),
                "password_changed_at" INTEGER,
                "registration_ip" VARCHAR(45),
                "unconfirmed_email" VARCHAR(255),
                "updated_at" INTEGER NOT NULL
            )
        ')->execute();

        $this->connection->createCommand('
            CREATE TABLE "user_social_account" (
                "id" INTEGER PRIMARY KEY AUTOINCREMENT,
                "user_id" INTEGER,
                "provider" VARCHAR(255) NOT NULL,
                "client_id" VARCHAR(255) NOT NULL,
                "code" VARCHAR(32),
                "email" VARCHAR(255),
                "username" VARCHAR(255),
                "data" TEXT,
                "created_at" INTEGER NOT NULL
            )
        ')->execute();
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            $this->connection->createCommand('DROP TABLE IF EXISTS "user_social_account"')->execute();
            $this->connection->createCommand('DROP TABLE IF EXISTS "user"')->execute();
        }
        ConnectionProvider::clear();
        $this->connection = null;
    }

    public static function getDecodedDataProvider(): iterable
    {
        yield 'decoded array' => ['{"name":"John","age":30}', ['name' => 'John', 'age' => 30]];
        yield 'null when no data' => [null, null];
    }

    public function testConnect(): void
    {
        // Non-persisted user: connects with user id 0 and clears the account's identity fields.
        $user = new User();
        $user->setUsername('temp');
        $user->setEmail('temp@test.com');
        $user->setPasswordHash('hash');
        $user->setAuthKey('key');
        $user->setCreatedAt(1000);
        $user->setUpdatedAt(1000);

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('abc');
        $account->setCreatedAt(1000);
        self::assertFalse($account->isConnected());

        $account->connect($user);

        self::assertSame(0, $account->getUserId());
        self::assertTrue($account->isConnected());
        self::assertNull($account->getUsername());
        self::assertNull($account->getEmail());
        self::assertNull($account->getCode());

        // Persisted user: connects to the real user id and persists the cleared identity fields.
        $this->connection->createCommand()->insert('user', [
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password_hash' => 'hash',
            'auth_key' => 'key',
            'created_at' => 1000,
            'updated_at' => 1000,
        ])->execute();

        $loadedUser = User::query()->where(['username' => 'testuser'])->one();
        self::assertNotNull($loadedUser);

        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId('abc');
        $account->setCreatedAt(1000);
        $account->setUsername('olduser');
        $account->setEmail('old@test.com');
        $account->setCode('oldcode');

        $account->connect($loadedUser);

        self::assertSame((int) $loadedUser->getId(), $account->getUserId());
        self::assertNull($account->getUsername());
        self::assertNull($account->getEmail());
        self::assertNull($account->getCode());

        $saved = UserSocialAccount::query()->where(['user_id' => $account->getUserId()])->one();
        self::assertNotNull($saved);
        self::assertNull($saved->getUsername());
        self::assertNull($saved->getEmail());
        self::assertNull($saved->getCode());
    }

    public function testFindByCodeReturnsMatch(): void
    {
        $this->createAccount('github', 'client-1', 'code-a');
        $this->createAccount('github', 'client-2', 'code-b');

        $account = UserSocialAccount::findByCode('code-a');

        self::assertNotNull($account);
        self::assertSame('client-1', $account->getClientId());
    }

    public function testFindByProviderAndClientIdReturnsMatch(): void
    {
        $this->createAccount('gitlab', 'client-1', 'code-b');
        $this->createAccount('github', 'client-1', 'code-a');

        $account = UserSocialAccount::findByProviderAndClientId('github', 'client-1');

        self::assertNotNull($account);
        self::assertSame('code-a', $account->getCode());
    }

    public function testFindByUserIdAndProvider(): void
    {
        // Match on user id + provider: returns the matching account.
        $first = $this->createAccount('github', 'client-1', 'code-a');
        $first->setUserId(1);
        $first->save();
        $second = $this->createAccount('github', 'client-2', 'code-c');
        $second->setUserId(1);
        $second->save();

        $account = UserSocialAccount::findByUserIdAndProvider(1, 'github');

        self::assertNotNull($account);
        self::assertSame('client-1', $account->getClientId());

        // No match for the user id + provider combination: null.
        $gitlab = $this->createAccount('gitlab', 'client-1', 'code-b');
        $gitlab->setUserId(2);
        $gitlab->save();

        self::assertNull(UserSocialAccount::findByUserIdAndProvider(2, 'github'));
    }

    public function testFindByUserIdReturnsMatches(): void
    {
        $account = $this->createAccount('github', 'client-1', 'code-a');
        $account->setUserId(1);
        $account->save();
        $secondAccount = $this->createAccount('twitter', 'client-3', 'code-c');
        $secondAccount->setUserId(1);
        $secondAccount->save();
        $this->createAccount('gitlab', 'client-2', 'code-b');

        $accounts = UserSocialAccount::findByUserId(1);

        self::assertCount(2, $accounts);
    }

    #[DataProvider('getDecodedDataProvider')]
    public function testGetDecodedData(?string $data, ?array $expected): void
    {
        $entity = new UserSocialAccount();

        if ($data !== null) {
            $entity->setData($data);
        }

        $decoded = $entity->getDecodedData();
        self::assertSame($expected, $decoded);

        self::assertSame($decoded, $entity->getDecodedData());
    }

    public function testGettersSettersAndDefaults(): void
    {
        // Defaults
        $entity = new UserSocialAccount();
        self::assertSame('', $entity->getClientId());
        self::assertSame('', $entity->getProvider());
        self::assertSame(0, $entity->getCreatedAt());
        self::assertNull($entity->getId());

        // Setters populate the getters
        $entity->setUserId(42);
        $entity->setProvider('github');
        $entity->setClientId('abc123');
        $entity->setCode('code123');
        $entity->setEmail('user@example.com');
        $entity->setUsername('githubuser');
        $entity->setCreatedAt(1000);
        $entity->setData('{"key":"val"}');

        self::assertSame(42, $entity->getUserId());
        self::assertSame('github', $entity->getProvider());
        self::assertSame('abc123', $entity->getClientId());
        self::assertSame('code123', $entity->getCode());
        self::assertSame('user@example.com', $entity->getEmail());
        self::assertSame('githubuser', $entity->getUsername());
        self::assertSame(1000, $entity->getCreatedAt());
        self::assertSame('{"key":"val"}', $entity->getData());

        // Nullable fields default to and can be set back to null
        $entity = new UserSocialAccount();
        self::assertNull($entity->getUserId());
        self::assertNull($entity->getCode());
        self::assertNull($entity->getEmail());
        self::assertNull($entity->getUsername());
        self::assertNull($entity->getData());

        $entity->setUserId(null);
        $entity->setCode(null);
        $entity->setEmail(null);
        $entity->setUsername(null);
        $entity->setData(null);

        self::assertNull($entity->getUserId());
        self::assertNull($entity->getCode());
        self::assertNull($entity->getEmail());
        self::assertNull($entity->getUsername());
        self::assertNull($entity->getData());
    }

    private function createAccount(string $provider, string $clientId, string $code): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setProvider($provider);
        $account->setClientId($clientId);
        $account->setCode($code);
        $account->setCreatedAt(time());
        $account->save();

        return $account;
    }
}
