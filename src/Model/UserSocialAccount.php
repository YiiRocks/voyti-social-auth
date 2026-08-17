<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\Model;

use YiiRocks\Voyti\Model\User;
use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;
use Yiisoft\Json\Json;

/**
 * ActiveRecord for the `user_social_account` table: a social-provider identity (e.g. Google,
 * GitHub), either already linked to a `user_id` or still pending connection via its one-time
 * `code`.
 */
final class UserSocialAccount extends ActiveRecord
{
    use PrivatePropertiesTrait;
    private string $client_id = '';
    private ?string $code = null;
    private int $created_at = 0;
    private ?string $data = null;

    private ?array $decodedData = null;
    private ?string $email = null;
    private ?int $id = null;
    private string $provider = '';
    private ?int $user_id = null;
    private ?string $username = null;

    public function connect(User $user): void
    {
        $this->setUserId($user->getIdOrZero());
        $this->setUsername(null);
        $this->setEmail(null);
        $this->setCode(null);
        $this->save();
    }

    public static function findByCode(string $code): ?UserSocialAccount
    {
        /** @var ?UserSocialAccount $account */
        $account = self::query()->where(['code' => $code])->one();
        return $account;
    }

    public static function findByProviderAndClientId(string $provider, string $clientId): ?UserSocialAccount
    {
        /** @var ?UserSocialAccount $account */
        $account = self::query()->where(['provider' => $provider, 'client_id' => $clientId])->one();
        return $account;
    }

    /**
     * @psalm-return list<UserSocialAccount>
     */
    public static function findByUserId(int $userId): array
    {
        /** @var list<UserSocialAccount> $accounts */
        $accounts = self::query()->where(['user_id' => $userId])->all();
        return $accounts;
    }

    public static function findByUserIdAndProvider(int $userId, string $provider): ?UserSocialAccount
    {
        /** @var ?UserSocialAccount $account */
        $account = self::query()->where(['user_id' => $userId, 'provider' => $provider])->one();
        return $account;
    }

    public function getClientId(): string
    {
        return $this->client_id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getCreatedAt(): int
    {
        return $this->created_at;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function getDecodedData(): ?array
    {
        if ($this->data !== null && $this->decodedData === null) {
            /** @var ?array $this->decodedData */
            $this->decodedData = Json::decode($this->data);
        }
        return $this->decodedData;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getUserId(): ?int
    {
        return $this->user_id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function isConnected(): bool
    {
        return $this->user_id !== null;
    }

    public function setClientId(string $clientId): void
    {
        $this->client_id = $clientId;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function setCreatedAt(int $createdAt): void
    {
        $this->created_at = $createdAt;
    }

    public function setData(?string $data): void
    {
        $this->data = $data;
        $this->decodedData = null;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
    }

    public function setUserId(?int $userId): void
    {
        $this->user_id = $userId;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }
}
