<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\tests\Controller\Registration;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use YiiRocks\Voyti\SocialAuth\Controller\Registration\SocialConnectController;
use YiiRocks\Voyti\SocialAuth\Model\UserSocialAccount;
use YiiRocks\Voyti\SocialAuth\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\SocialAuth\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\SocialAuth\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\OAuth2Interface;

#[AllowMockObjectsWithoutExpectations]
final class SocialConnectControllerTest extends DatabaseTestCase
{
    use TestContainerTrait;

    public function testConnect(): void
    {
        // Valid code with an empty client collection: the provider key is shown.
        $this->createPendingAccount('client-1', 'code123');
        $html = (string) $this->createController([
            Collection::class => new Collection([]),
        ])->connect('code123')->getBody();
        self::assertStringContainsString('Connect account', $html);
        self::assertStringContainsString('github', $html);

        // Valid code with a configured client collection: the provider title is shown.
        $this->createPendingAccount('client-2', 'code456');
        $client = $this->createMock(OAuth2Interface::class);
        $client->method('getName')->willReturn('github');
        $client->method('getTitle')->willReturn('GitHub');
        $html = (string) $this->createController([
            Collection::class => new Collection(['github' => $client]),
        ])->connect('code456')->getBody();
        self::assertStringContainsString('GitHub', $html);

        // Valid code with a null client collection: falls back to the provider key.
        $this->createPendingAccount('client-3', 'code789');
        $html = (string) $this->createController([
            SocialConnectController::class => [
                'class' => SocialConnectController::class,
                '__construct()' => ['clientCollection' => null],
            ],
        ])->connect('code789')->getBody();
        self::assertStringContainsString('Connect account', $html);
        self::assertStringContainsString('github', $html);

        // Invalid code: not-found message.
        $html = (string) $this->createController([
            Collection::class => new Collection([]),
        ])->connect('code1234')->getBody();
        self::assertStringContainsString('Network not found', $html);
    }

    public function testConnectWithConfiguredViewPath(): void
    {
        // Configured view path without the template: falls back to the bundled view.
        $this->createPendingAccount('client-5', 'code111');
        $customViewPath = sys_get_temp_dir() . '/voyti-social-connect-test-' . uniqid();
        mkdir($customViewPath);
        try {
            $html = (string) $this->createController([
                Collection::class => new Collection([]),
                VoytiConfig::class => VoytiConfigFactory::create(viewPath: $customViewPath),
            ])->connect('code111')->getBody();

            self::assertStringContainsString('Connect account', $html);
        } finally {
            rmdir($customViewPath);
        }

        // Configured view path with the template: the override wins.
        $this->createPendingAccount('client-4', 'code000');
        $customViewPath = sys_get_temp_dir() . '/voyti-social-connect-test-' . uniqid();
        mkdir($customViewPath . '/registration', recursive: true);
        file_put_contents($customViewPath . '/registration/connect.php', 'CUSTOM_CONNECT_TEMPLATE');
        try {
            $html = (string) $this->createController([
                Collection::class => new Collection([]),
                VoytiConfig::class => VoytiConfigFactory::create(viewPath: $customViewPath),
            ])->connect('code000')->getBody();

            self::assertStringContainsString('CUSTOM_CONNECT_TEMPLATE', $html);
        } finally {
            unlink($customViewPath . '/registration/connect.php');
            rmdir($customViewPath . '/registration');
            rmdir($customViewPath);
        }
    }

    private function createController(array $overrides = []): SocialConnectController
    {
        return $this->getTestContainer($overrides)->get(SocialConnectController::class);
    }

    private function createPendingAccount(string $clientId, string $code): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId($clientId);
        $account->setCode($code);
        $account->setCreatedAt(time());
        $account->save();

        return $account;
    }
}
