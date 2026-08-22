<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\tests\Service\Auth;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\EventDispatcher\EventDispatcherInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Auth\LoginCompletionService;
use YiiRocks\Voyti\Service\User\UserCreationHelper;
use YiiRocks\Voyti\SocialAuth\Http\AuthActionRequestHolder;
use YiiRocks\Voyti\SocialAuth\Model\UserSocialAccount;
use YiiRocks\Voyti\SocialAuth\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\SocialAuth\Service\Auth\SocialAuthCallbackService;
use YiiRocks\Voyti\SocialAuth\Service\Auth\UserSocialAuthenticateService;
use YiiRocks\Voyti\SocialAuth\tests\Support\CurrentUserTrait;
use YiiRocks\Voyti\SocialAuth\tests\Support\DatabaseTestCase;
use YiiRocks\Voyti\SocialAuth\tests\Support\FakeSession;
use YiiRocks\Voyti\SocialAuth\tests\Support\TestContainerTrait;
use YiiRocks\Voyti\SocialAuth\tests\Support\ThrowingEventDispatcher;
use YiiRocks\Voyti\SocialAuth\tests\Support\UserFactoryTrait;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Yii\AuthClient\AuthClientInterface;

#[AllowMockObjectsWithoutExpectations]
final class SocialAuthCallbackServiceTest extends DatabaseTestCase
{
    use CurrentUserTrait;
    use TestContainerTrait;
    use UserFactoryTrait;

    private CurrentUser $currentUser;
    private FlashInterface&MockObject $flash;
    private FakeSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->currentUser = $this->createCurrentUser();
        $this->flash = $this->createMock(FlashInterface::class);
        $this->session = new FakeSession();
    }

    public function testHandleCancelRedirectsToLoginWithoutEnforcingRedirect(): void
    {
        $html = (string) $this->createService()->handleCancel($this->client('github'))->getBody();

        self::assertStringContainsString('href="//voyti/session-login"', $html);
        // Cancel must not enforce the opener redirect.
        self::assertStringContainsString(', false)', $html);
    }

    public function testHandleSuccessGuestFailureRendersMessage(): void
    {
        // With social-network registration disabled, the real authenticate service fails.
        $html = (string) $this->createService([
            UserSocialAuthenticateService::class => static fn(
                VoytiConfig $config,
                AuthActionRequestHolder $requestHolder,
                LoginCompletionService $loginCompletionService,
                SessionInterface $session,
                UserCreationHelper $userCreationHelper,
                PendingSocialAccountService $pendingSocialAccountService,
                TranslatorInterface $translator,
            ) => new UserSocialAuthenticateService(
                $config,
                false,
                $requestHolder,
                $loginCompletionService,
                $session,
                $userCreationHelper,
                $pendingSocialAccountService,
                $translator,
            ),
        ])->handleSuccess($this->client('github'))->getBody();

        self::assertStringContainsString('Social network registration is disabled', $html);

        // A RuntimeException from the underlying flow is rendered as the message.
        // The account is already linked to an existing user, so the real authenticate service logs
        // that user in via LoginCompletionService, which dispatches BeforeLoginEvent - a throwing
        // dispatcher turns that into the RuntimeException the callback catches.
        $user = $this->createUser(email: 'linked@example.com');
        $this->createSocialAccount(userId: (int) $user->getId());

        $html = (string) $this->createService([
            EventDispatcherInterface::class => new ThrowingEventDispatcher('state mismatch'),
        ])->handleSuccess($this->client('github'))->getBody();

        self::assertStringContainsString('state mismatch', $html);
    }

    public function testHandleSuccessGuestSuccess(): void
    {
        // The account is linked to an existing user, so the real authenticate service logs them in;
        // the callback then writes the remember-me cookie onto the home redirect.
        $user = $this->createUser(email: 'linked@example.com');
        $this->createSocialAccount(userId: (int) $user->getId());

        // An active session id is embedded in the remember-me cookie payload.
        $this->session->open();
        $this->session->setId('sessionprobe');

        $result = $this->createService()->handleSuccess($this->client('github'));

        // LoginCompletionService::complete() logs the user in via a withAuthTimeout()-cloned
        // CurrentUser (remember-me is always on for social login), so the clone's mutation isn't
        // visible on $this->currentUser's own in-memory identity - check the DB side effect instead.
        $this->assertNotNull(User::findById((int) $user->getId())?->getLastLoginAt());
        $cookie = $result->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('autoLogin', $cookie);
        $this->assertStringContainsString('sessionprobe', $cookie);

        // An unlinked account with a code makes the real authenticate service remember it as pending,
        // so the callback redirects to the pending-connect flow instead of home.
        $this->createSocialAccount(code: 'pending-code', clientId: 'pending-client');

        $html = (string) $this->createService()->handleSuccess($this->client('github', 'pending-client'))->getBody();

        self::assertStringContainsString('registration-connect?code=pending-code', $html);
        self::assertStringContainsString(', true)', $html);
    }

    public function testHandleSuccessLoggedInFailureRendersMessage(): void
    {
        // The provider account is already connected to a different user, so the real connect
        // service fails and the callback renders that message instead of redirecting.
        $otherUser = $this->createUser(username: 'other', email: 'other@example.com');
        $this->createSocialAccount(userId: (int) $otherUser->getId());

        $viewer = $this->createUser(username: 'viewer', email: 'viewer@example.com');
        $this->currentUser->login($viewer);

        $html = (string) $this->createService()->handleSuccess($this->client('github'))->getBody();

        self::assertStringContainsString('This account has already been connected to another user', $html);
    }

    public function testHandleSuccessLoggedInSuccessRedirectsToSocialNetworkIndex(): void
    {
        $viewer = $this->createUser(username: 'viewer', email: 'viewer@example.com');
        $this->currentUser->login($viewer);

        $html = (string) $this->createService()->handleSuccess($this->client('github'))->getBody();

        self::assertStringContainsString('href="//voyti/user-social-network"', $html);
        // The real connect service linked the provider account to the viewer.
        $account = UserSocialAccount::findByProviderAndClientId('github', 'client123');
        $this->assertNotNull($account);
        $this->assertSame((int) $viewer->getId(), $account->getUserId());
    }

    private function attributes(string $clientId = 'client123'): array
    {
        return ['id' => $clientId, 'email' => 'user@example.com', 'username' => 'user', 'name' => 'User Name'];
    }

    private function client(string $name, string $clientId = 'client123'): AuthClientInterface&MockObject
    {
        $client = $this->createMock(AuthClientInterface::class);
        $client->method('getName')->willReturn($name);
        $client->method('getUserAttributes')->willReturn($this->attributes($clientId));

        return $client;
    }

    private function createService(array $overrides = []): SocialAuthCallbackService
    {
        return $this->getTestContainer([
            CurrentUser::class => $this->currentUser,
            FlashInterface::class => $this->flash,
            SessionInterface::class => $this->session,
            ...$overrides,
        ])->get(SocialAuthCallbackService::class);
    }

    private function createSocialAccount(?int $userId = null, ?string $code = null, string $clientId = 'client123'): UserSocialAccount
    {
        $account = new UserSocialAccount();
        $account->setProvider('github');
        $account->setClientId($clientId);
        $account->setUserId($userId);
        $account->setCode($code);
        $account->setCreatedAt(time());
        $account->save();

        return $account;
    }
}
