<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\tests\Support;

use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\SocialAuth\Model\UserSocialAccount;

trait UserFactoryTrait
{
    private function createSocialAccount(
        string $provider = 'github',
        string $clientId = 'gh-123',
        ?int $userId = null,
        ?string $code = null,
        ?string $email = null,
        ?string $username = null,
        ?string $data = null,
    ): UserSocialAccount {
        $account = new UserSocialAccount();
        $account->setProvider($provider);
        $account->setClientId($clientId);
        $account->setUserId($userId);
        $account->setCode($code);
        $account->setEmail($email);
        $account->setUsername($username);
        $account->setData($data);
        $account->setCreatedAt(time());
        $account->save();

        return $account;
    }

    private function createUser(
        string $username = 'testuser',
        string $email = 'test@example.com',
        string $passwordHash = 'hash',
        ?int $createdAt = null,
        ?int $confirmedAt = null,
        ?int $blockedAt = null,
        ?string $lastLoginIp = null,
        ?int $dataProcessingConsentDate = null,
    ): User {
        $timestamp = $createdAt ?? time();

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash($passwordHash);
        $user->setAuthKey('key');
        $user->setCreatedAt($timestamp);
        $user->setUpdatedAt($timestamp);
        if ($confirmedAt !== null) {
            $user->setConfirmedAt($confirmedAt);
        }
        if ($blockedAt !== null) {
            $user->setBlockedAt($blockedAt);
        }
        if ($lastLoginIp !== null) {
            $user->setLastLoginIp($lastLoginIp);
        }
        if ($dataProcessingConsentDate !== null) {
            $user->setDataProcessingConsentDate($dataProcessingConsentDate);
        }
        $user->save();

        return $user;
    }
}
