<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\tests\Controller\SocialAuth;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use YiiRocks\Voyti\Helper\FlashType;
use YiiRocks\Voyti\SocialAuth\Controller\SocialAuth\SocialAuthController;
use YiiRocks\Voyti\SocialAuth\Model\UserSocialAccount;
use YiiRocks\Voyti\SocialAuth\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\SocialAuth\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\SocialAuth\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\SocialAuth\tests\Support\TestPasswordHasherFactory;
use YiiRocks\Voyti\SocialAuth\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\SocialAuth\tests\Support\VoytiConfigFactory;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Assets\AssetBundle;
use Yiisoft\Assets\AssetLoaderInterface;
use Yiisoft\Assets\AssetPublisherInterface;
use Yiisoft\Assets\AssetUtil;
use Yiisoft\Security\PasswordHasher;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\AuthClient\OAuth2Interface;

#[AllowMockObjectsWithoutExpectations]
final class SocialAuthControllerTest extends DatabaseTestCase
{
    use CurrentUserTrait;
    use TestContainerTrait;
    use UserFactoryTrait;

    private CurrentUser $currentUser;
    private FlashInterface&MockObject $flash;
    private PasswordHasher $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->currentUser = $this->createCurrentUser();
        $this->flash = $this->createMock(FlashInterface::class);
        $this->passwordHasher = TestPasswordHasherFactory::create();
    }

    public function testDelete(): void
    {
        // No matching account: not-found message rendered.
        $user = $this->createUser(passwordHash: $this->passwordHasher->hash('secret'));
        $this->currentUser->login($user);
        $html = (string) $this->createController()->delete(999)->getBody();
        self::assertStringContainsString('Account not found', $html);

        // Matching account: deleted, redirected and a success flash set; other users' accounts are
        // untouched. The other user's account is created first (lower id), so a lookup that ignored
        // the id argument would return it instead of the target.
        $this->flash->expects(self::once())->method('set')->with(FlashType::SUCCESS, 'Account has been disconnected');
        $controller = $this->createController();
        $otherAccount = $this->createSocialAccount(888888, provider: 'facebook', username: 'someoneelse');
        $user = $this->createUser(username: 'second', email: 'second@example.com', passwordHash: $this->passwordHasher->hash('secret'));
        $this->currentUser->login($user);
        $account = $this->createSocialAccount((int) $user->getId());

        $result = $controller->delete($account->getId());

        $this->assertSame(302, $result->getStatusCode());
        $this->assertStringContainsString('user-social-auth', $result->getHeaderLine('Location'));
        $this->assertSame([], UserSocialAccount::findByUserId((int) $user->getId()));
        $this->assertNotNull(UserSocialAccount::query()->where(['id' => $otherAccount->getId()])->one());
    }

    public function testIndex(): void
    {
        // Basic listing: the raw provider key is shown with the external client id as the identity.
        $user = $this->createUser();
        $this->currentUser->login($user);
        $account = $this->createSocialAccount((int) $user->getId());

        $html = (string) $this->createController()->index()->getBody();

        self::assertStringContainsString('Social Authentication', $html);
        self::assertStringContainsString('github', $html);
        self::assertStringContainsString('<strong>github</strong> - <span class="text-muted">client123</span>', $html);
        self::assertStringContainsString('//voyti/user-social-auth-delete?id=' . $account->getId(), $html);

        // Identity fallbacks for multiple accounts of the same provider: username, then name, then
        // the external client id; each account gets its own disconnect form.
        $user = $this->createUser(username: 'viewer', email: 'viewer@example.com');
        $this->currentUser->login($user);
        $withUsername = $this->createSocialAccount((int) $user->getId(), clientId: 'personal', data: '{"username":"octocat","name":"Octo Cat"}');
        $withName = $this->createSocialAccount((int) $user->getId(), clientId: 'work', data: '{"name":"Work Cat"}');
        $withClientId = $this->createSocialAccount((int) $user->getId(), clientId: 'school');

        $html = (string) $this->createController()->index()->getBody();

        self::assertStringContainsString('octocat', $html);
        self::assertStringContainsString('Work Cat', $html);
        self::assertStringContainsString('school', $html);
        self::assertStringContainsString('user-social-auth-delete?id=' . $withUsername->getId(), $html);
        self::assertStringContainsString('user-social-auth-delete?id=' . $withName->getId(), $html);
        self::assertStringContainsString('user-social-auth-delete?id=' . $withClientId->getId(), $html);
    }

    public function testIndexWithConfiguredClients(): void
    {
        // Multiple accounts per provider disabled (the default): the connected provider is no longer
        // offered as a connect button, unconnected providers still are, and the account row keeps
        // showing the stored username as the identity.
        $user = $this->createUser();
        $this->currentUser->login($user);
        $this->createSocialAccount((int) $user->getId(), data: '{"username":"octocat","name":"Octo Cat"}');

        $html = (string) $this->createController([
            Collection::class => $this->configuredClients(),
            ...$this->assetStubs(),
        ])->index()->getBody();

        self::assertStringContainsString('<strong>GitHub</strong> - <span class="text-muted">octocat</span>', $html);
        self::assertStringNotContainsString('voyti/session-auth?authclient=github', $html);
        self::assertStringContainsString('voyti/session-auth?authclient=google', $html);

        // Multiple accounts per provider enabled: the connected provider stays offered so additional
        // accounts of the same provider can be linked.
        $user = $this->createUser(username: 'second', email: 'second@example.com');
        $this->currentUser->login($user);
        $this->createSocialAccount((int) $user->getId(), clientId: 'client456', data: '{"username":"octocat","name":"Octo Cat"}');

        $html = (string) $this->createController([
            Collection::class => $this->configuredClients(),
            SocialAuthController::class => [
                'class' => SocialAuthController::class,
                '__construct()' => ['allowMultipleAccountsPerProvider' => true],
            ],
            ...$this->assetStubs(),
        ])->index()->getBody();

        self::assertStringContainsString('<strong>GitHub</strong> - <span class="text-muted">octocat</span>', $html);
        self::assertStringContainsString('voyti/session-auth?authclient=github', $html);
        self::assertStringContainsString('voyti/session-auth?authclient=google', $html);

        // Once every configured provider is connected (multiple accounts disabled), the auth-choice
        // widget has no clients left and the template skips it entirely.
        $user = $this->createUser(username: 'third', email: 'third@example.com');
        $this->currentUser->login($user);
        $this->createSocialAccount((int) $user->getId(), provider: 'github', clientId: 'client789', data: '{"username":"octocat"}');
        $this->createSocialAccount((int) $user->getId(), provider: 'google', data: '{"username":"googler"}');

        $html = (string) $this->createController([
            Collection::class => $this->configuredClients(),
            ...$this->assetStubs(),
        ])->index()->getBody();

        self::assertStringContainsString('<strong>GitHub</strong> - <span class="text-muted">octocat</span>', $html);
        self::assertStringNotContainsString('session-auth', $html);
    }

    public function testIndexWithConfiguredViewPath(): void
    {
        // Configured view path without the template: falls back to the bundled view.
        $customViewPath = sys_get_temp_dir() . '/voyti-social-auth-test-' . uniqid();
        mkdir($customViewPath);
        try {
            $user = $this->createUser();
            $this->currentUser->login($user);

            $html = (string) $this->createController([
                Collection::class => new Collection([]),
                VoytiConfig::class => VoytiConfigFactory::create(viewPath: $customViewPath),
            ])->index()->getBody();

            self::assertStringContainsString('Social Authentication', $html);
        } finally {
            rmdir($customViewPath);
        }

        // Configured view path with the template: the override wins.
        $customViewPath = sys_get_temp_dir() . '/voyti-social-auth-test-' . uniqid();
        mkdir($customViewPath . '/social-auth', recursive: true);
        file_put_contents($customViewPath . '/social-auth/index.php', 'CUSTOM_SOCIAL_AUTH_TEMPLATE');
        try {
            $user = $this->createUser(username: 'second', email: 'second@example.com');
            $this->currentUser->login($user);

            $html = (string) $this->createController([
                Collection::class => new Collection([]),
                VoytiConfig::class => VoytiConfigFactory::create(viewPath: $customViewPath),
            ])->index()->getBody();

            self::assertStringContainsString('CUSTOM_SOCIAL_AUTH_TEMPLATE', $html);
        } finally {
            unlink($customViewPath . '/social-auth/index.php');
            rmdir($customViewPath . '/social-auth');
            rmdir($customViewPath);
        }
    }

    public function testIndexWithNullClientCollectionFallsBackToProviderKey(): void
    {
        $controller = $this->createController([
            SocialAuthController::class => [
                'class' => SocialAuthController::class,
                '__construct()' => [
                    'clientCollection' => null,
                    'allowMultipleAccountsPerProvider' => false,
                ],
            ],
        ]);

        $user = $this->createUser();
        $this->currentUser->login($user);
        $this->createSocialAccount((int) $user->getId());

        $html = (string) $controller->index()->getBody();

        // Without a client collection the raw provider key is shown and no connect widget renders.
        self::assertStringContainsString('github', $html);
        self::assertStringNotContainsString('session-auth', $html);
    }

    /**
     * Stubs for the asset stack so the AuthChoice widget's asset registration can run without
     * real path aliases or filesystem publishing in the test environment.
     *
     * @return array{class-string, object}
     */
    private function assetStubs(): array
    {
        return [
            AssetLoaderInterface::class => new class implements AssetLoaderInterface {
                public function getAssetUrl(AssetBundle $bundle, string $assetPath): string
                {
                    return '/assets/' . $assetPath;
                }

                public function loadBundle(string $name, array $config = []): AssetBundle
                {
                    if ($config !== []) {
                        return AssetUtil::createAsset($name, $config);
                    }

                    return new $name();
                }
            },
            AssetPublisherInterface::class => new class implements AssetPublisherInterface {
                public function publish(AssetBundle $bundle): array
                {
                    return ['', '/assets'];
                }

                public function getPublishedPath(string $sourcePath): ?string
                {
                    return $sourcePath;
                }

                public function getPublishedUrl(string $sourcePath): ?string
                {
                    return '/assets';
                }
            },
        ];
    }

    /**
     * @return Collection
     */
    private function configuredClients(): Collection
    {
        $github = $this->createMock(OAuth2Interface::class);
        $github->method('getName')->willReturn('github');
        $github->method('getTitle')->willReturn('GitHub');
        $google = $this->createMock(OAuth2Interface::class);
        $google->method('getName')->willReturn('google');
        $google->method('getTitle')->willReturn('Google');

        return new Collection(['github' => $github, 'google' => $google]);
    }

    private function createController(array $overrides = []): SocialAuthController
    {
        return $this->getTestContainer([
            CurrentUser::class => $this->currentUser,
            FlashInterface::class => $this->flash,
            ...$overrides,
        ])->get(SocialAuthController::class);
    }

    private function createSocialAccount(
        int $userId,
        string $provider = 'github',
        string $clientId = 'client123',
        string $username = 'octocat',
        ?string $data = null,
    ): UserSocialAccount {
        $account = new UserSocialAccount();
        $account->setUserId($userId);
        $account->setProvider($provider);
        $account->setClientId($clientId);
        $account->setUsername($username);
        $account->setData($data);
        $account->setCreatedAt(time());
        $account->save();

        return $account;
    }
}
