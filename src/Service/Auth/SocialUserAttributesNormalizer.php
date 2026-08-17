<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\Service\Auth;

use Yiisoft\Yii\AuthClient\AuthClientInterface;
use Yiisoft\Yii\AuthClient\Client\X;

/**
 * Maps a yii-auth-client client's raw, provider-specific {@see AuthClientInterface::getUserAttributes()}
 * shape into Voyti's canonical `{id, email, username, name}` attribute shape, rebuilding the mapping
 * logic that used to live in `AbstractAuthClient::normalizeUserAttributes()` and its per-provider
 * overrides. The provider's external user id always comes from here, never from
 * {@see AuthClientInterface::getClientId()} - that method returns the OAuth application's client_id,
 * a different concept entirely. A field a provider doesn't expose (e.g. GitHub/Facebook may omit
 * email for accounts with a private address, X never returns one) is left null rather than backfilled
 * via an extra API call - the user fills it in manually during the registration-connect flow instead.
 */
final readonly class SocialUserAttributesNormalizer
{
    /**
     * @psalm-var list<string>
     */
    private const array EMAIL_KEYS = ['email', 'mail', 'default_email'];

    /**
     * @psalm-var list<string>
     */
    private const array FIRST_NAME_KEYS = ['first_name', 'given_name', 'givenName'];

    /**
     * @psalm-var list<string>
     */
    private const array LAST_NAME_KEYS = ['last_name', 'family_name', 'surname'];

    /**
     * @psalm-var list<string>
     */
    private const array NAME_KEYS = ['name', 'real_name', 'displayName', 'display_name'];

    /**
     * @psalm-var list<string>
     */
    private const array USERNAME_KEYS = [
        'preferred_username',
        'login',
        'username',
        'screen_name',
        'display_name',
        'nickname',
    ];

    /**
     * @return array{id: string, email: ?string, username: ?string, name: ?string}
     */
    public function normalize(string $provider, AuthClientInterface $client): array
    {
        $attributes = $client->getUserAttributes();
        if ($client instanceof X) {
            $attributes = $this->unwrapDataEnvelope($attributes);
        }

        $email = $this->firstString($attributes, self::EMAIL_KEYS);

        return [
            'id' => $this->firstString($attributes, $this->idKeys($provider)) ?? '',
            'email' => $email,
            'username' => $this->username($attributes, $email),
            'name' => $this->name($attributes),
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string> $keys
     */
    private function firstString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            /** @var mixed $value */
            $value = $data[$key];
            if (is_string($value) && $value !== '') {
                return $value;
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function idKeys(string $provider): array
    {
        return match ($provider) {
            'linkedin' => ['sub'],
            'vkontakte' => ['user_id'],
            default => ['id'],
        };
    }

    /**
     * @param array<array-key, mixed> $attributes
     */
    private function name(array $attributes): ?string
    {
        $name = $this->firstString($attributes, self::NAME_KEYS);
        if ($name !== null) {
            return $name;
        }

        $firstName = $this->firstString($attributes, self::FIRST_NAME_KEYS);
        $lastName = $this->firstString($attributes, self::LAST_NAME_KEYS);
        $combined = trim(implode(' ', [$firstName, $lastName]));

        return $combined !== '' ? $combined : null;
    }

    /**
     * @param array<array-key, mixed> $attributes
     *
     * @return array<array-key, mixed>
     */
    private function unwrapDataEnvelope(array $attributes): array
    {
        /** @var mixed $data */
        $data = $attributes['data'] ?? null;

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<array-key, mixed> $attributes
     */
    private function username(array $attributes, ?string $email): ?string
    {
        $username = $this->firstString($attributes, self::USERNAME_KEYS);
        if ($username !== null || $email === null) {
            return $username;
        }

        $prefix = strstr($email, '@', true);

        return is_string($prefix) && $prefix !== '' ? $prefix : $email;
    }
}
