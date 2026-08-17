<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\tests\Service\Auth;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\SocialAuth\Service\Auth\SocialUserAttributesNormalizer;
use YiiRocks\Voyti\SocialAuth\tests\Support\FakeHttpClient;
use YiiRocks\Voyti\SocialAuth\tests\Support\FakeSession;
use Yiisoft\Factory\Factory;
use Yiisoft\Yii\AuthClient\Client\Facebook;
use Yiisoft\Yii\AuthClient\Client\GitHub;
use Yiisoft\Yii\AuthClient\Client\Google;
use Yiisoft\Yii\AuthClient\Client\LinkedIn;
use Yiisoft\Yii\AuthClient\Client\VKontakte;
use Yiisoft\Yii\AuthClient\Client\X;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\OAuthToken;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;

final class SocialUserAttributesNormalizerTest extends TestCase
{
    public function testNormalizeFacebookReturnsNullEmailWhenNotInResponse(): void
    {
        $client = $this->makeClient(Facebook::class, new Response(200, [], '{"id":444,"name":"Fred"}'));
        $client->setAccessToken($this->token());

        $result = $this->normalizer()->normalize('facebook', $client);

        self::assertSame('444', $result['id']);
        self::assertSame('Fred', $result['name']);
        self::assertNull($result['email']);
    }

    public function testNormalizeGitHubReturnsNullEmailWhenNotInResponse(): void
    {
        $client = $this->makeClient(GitHub::class, new Response(200, [], '{"id":555,"login":"octocat"}'));
        $client->setAccessToken($this->token());

        $result = $this->normalizer()->normalize('github', $client);

        self::assertSame('555', $result['id']);
        self::assertSame('octocat', $result['username']);
        self::assertNull($result['email']);
    }

    public function testNormalizeGoogle(): void
    {
        // Uses the id key and composes the username from the email prefix.
        $client = $this->makeClient(
            Google::class,
            new Response(200, [], '{"id":"1001","email":"alice@example.com","name":"Alice Example"}'),
        );
        $client->setAccessToken($this->token());
        $result = $this->normalizer()->normalize('google', $client);
        self::assertSame('1001', $result['id']);
        self::assertSame('alice@example.com', $result['email']);
        self::assertSame('alice', $result['username']);
        self::assertSame('Alice Example', $result['name']);

        // Uses the given name when the family name is missing.
        $client = $this->makeClient(Google::class, new Response(200, [], '{"id":"1","given_name":"Cher"}'));
        $client->setAccessToken($this->token());
        self::assertSame('Cher', $this->normalizer()->normalize('google', $client)['name']);

        // Uses the whole email as the username when it has no at sign.
        $client = $this->makeClient(Google::class, new Response(200, [], '{"id":"1","email":"not-an-email-address"}'));
        $client->setAccessToken($this->token());
        self::assertSame('not-an-email-address', $this->normalizer()->normalize('google', $client)['username']);

        // Treats a boolean id value as missing.
        $client = $this->makeClient(Google::class, new Response(200, [], '{"id":true,"email":"x@example.com"}'));
        $client->setAccessToken($this->token());
        self::assertSame('', $this->normalizer()->normalize('google', $client)['id']);
    }

    public function testNormalizeLinkedInUsesSubAsIdAndComposesNameFromParts(): void
    {
        $client = $this->makeClient(
            LinkedIn::class,
            new Response(200, [], '{"sub":"linkedin-42","email":"bob@example.com","given_name":"Bob","family_name":"Jones"}'),
        );
        $client->setAccessToken($this->token());

        $result = $this->normalizer()->normalize('linkedin', $client);

        self::assertSame('linkedin-42', $result['id']);
        self::assertSame('Bob Jones', $result['name']);
    }

    public function testNormalizeVKontakteUsesUserIdAndFlattenedNameFields(): void
    {
        $client = $this->makeClient(
            VKontakte::class,
            new Response(200, [], '{"user":{"user_id":"9001","first_name":"Ivan","last_name":"Ivanov","email":"ivan@example.com"}}'),
        );
        $client->setClientId('vk-client-id');
        $client->setAccessToken($this->token());

        $result = $this->normalizer()->normalize('vkontakte', $client);

        self::assertSame('9001', $result['id']);
        self::assertSame('ivan@example.com', $result['email']);
        self::assertSame('Ivan Ivanov', $result['name']);
        // VKontakte's response has no username-like field at all; falls back to the email prefix.
        self::assertSame('ivan', $result['username']);
    }

    public function testNormalizeXUnwrapsDataEnvelopeAndOmitsEmail(): void
    {
        $client = $this->makeClient(X::class, new Response(200, [], '{"data":{"id":"9","username":"jack","name":"Jack"}}'));
        $client->setAccessToken($this->token());

        $result = $this->normalizer()->normalize('x', $client);

        self::assertSame('9', $result['id']);
        self::assertSame('jack', $result['username']);
        self::assertSame('Jack', $result['name']);
        self::assertNull($result['email']);
    }

    private function makeClient(string $class, Response $response): OAuth2
    {
        return new $class(
            new FakeHttpClient($response),
            new Psr17Factory(),
            new DummyStateStorage(),
            new Factory(),
            new FakeSession(),
        );
    }

    private function normalizer(): SocialUserAttributesNormalizer
    {
        return new SocialUserAttributesNormalizer();
    }

    private function token(string $value = 'test-access-token'): OAuthToken
    {
        $token = new OAuthToken();
        // OAuth2 clients read the access token from the 'access_token' param (set by
        // OAuth2::createToken() in the real fetch flow) rather than OAuthToken's default
        // 'oauth_token' param key, so it must be set explicitly here too.
        $token->setTokenParamKey('access_token');
        $token->setToken($value);

        return $token;
    }
}
