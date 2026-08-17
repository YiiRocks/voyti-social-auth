<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\tests\Service\Auth;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\SocialAuth\Service\Auth\SocialAuthClientReturnUrlConfigurator;
use YiiRocks\Voyti\SocialAuth\tests\Support\FakeHttpClient;
use YiiRocks\Voyti\SocialAuth\tests\Support\FakeSession;
use YiiRocks\Voyti\SocialAuth\tests\Support\FakeUrlGenerator;
use Yiisoft\Factory\Factory;
use Yiisoft\Yii\AuthClient\Client\GitHub;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\OAuth2;
use Yiisoft\Yii\AuthClient\StateStorage\DummyStateStorage;

final class SocialAuthClientReturnUrlConfiguratorTest extends TestCase
{
    public function testConfigureReturnUrl(): void
    {
        $url = new FakeUrlGenerator();
        $url->setUrl('voyti/session-auth', '/auth');

        // No host return URL: filled in from the collection key.
        $client = $this->makeClient(GitHub::class);
        (new SocialAuthClientReturnUrlConfigurator($url, false))->configure(new Collection(['github' => $client]));
        self::assertSame('https://example.com/auth?authclient=github', $client->getOauth2ReturnUrl());

        // Host-configured return URL: preserved.
        $client = $this->makeClient(GitHub::class);
        $client->setOauth2ReturnUrl('https://host-configured.example.com/callback');
        (new SocialAuthClientReturnUrlConfigurator($url, false))->configure(new Collection(['github' => $client]));
        self::assertSame('https://host-configured.example.com/callback', $client->getOauth2ReturnUrl());
    }

    private function makeClient(string $class): OAuth2
    {
        return new $class(
            new FakeHttpClient(new Response(200, [], '{}')),
            new Psr17Factory(),
            new DummyStateStorage(),
            new Factory(),
            new FakeSession(),
        );
    }
}
