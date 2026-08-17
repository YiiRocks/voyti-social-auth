<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\Service\Auth;

use Composer\InstalledVersions;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\RememberMeCookieService;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Session\Flash\FlashInterface;
use Yiisoft\Session\SessionInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\User\Guest\GuestIdentityInterface;
use Yiisoft\Yii\AuthClient\AuthAction;
use Yiisoft\Yii\AuthClient\AuthClientInterface;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Replaces `SessionController::auth()`'s body as the success/cancel callbacks wired into
 * {@see AuthAction}: normalizes the provider's attributes, then either logs
 * a guest in via {@see UserSocialAuthenticateService} or links the account to the current user via
 * {@see UserSocialAccountConnectService}.
 *
 * `AuthAction`'s callbacks only receive the {@see AuthClientInterface}, never the incoming
 * `ServerRequestInterface` (it's `final`, so there's no hook to change that) - login metadata that
 * used to come from `$request->getServerParams()` is read from PHP's `$_SERVER` superglobal instead,
 * which is equivalent under the traditional php-fpm/apache SAPI this repo's tooling targets.
 */
final readonly class SocialAuthCallbackService
{
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private VoytiConfig $config,
        private SessionInterface $session,
        private FlashInterface $flash,
        private CurrentUser $currentUser,
        private RememberMeCookieService $rememberMeCookieService,
        private PendingSocialAccountService $pendingSocialAccountService,
        private UserSocialAuthenticateService $socialAuthenticateService,
        private UserSocialAccountConnectService $socialAccountConnectService,
        private SocialUserAttributesNormalizer $normalizer,
    ) {}

    public function handleCancel(AuthClientInterface $client): ResponseInterface
    {
        return $this->popupAwareRedirect($this->url->generate('voyti/session-login'), false);
    }

    public function handleSuccess(AuthClientInterface $client): ResponseInterface
    {
        $provider = $client->getName();
        $attributes = $this->normalizer->normalize($provider, $client);

        $identity = $this->currentUser->getIdentity();
        $isGuest = $identity instanceof GuestIdentityInterface;

        try {
            $result = $isGuest
                ? $this->socialAuthenticateService->run($provider, $attributes['id'], $attributes, $_SERVER)
                : $this->socialAccountConnectService->run($provider, $attributes['id'], $attributes, (int) $identity->getId());
        } catch (RuntimeException $exception) {
            return $this->renderMessage($exception->getMessage());
        }

        if ($result->isFailure()) {
            return $this->renderMessage($result->getMessage());
        }

        if (!$isGuest) {
            return $this->popupAwareRedirect($this->url->generate('voyti/user-social-network'));
        }

        $account = $this->pendingSocialAccountService->getPendingAccount();
        if ($account !== null) {
            return $this->popupAwareRedirect(
                $this->url->generate('voyti/registration-connect', ['code' => $account->getCode() ?? 'connect']),
            );
        }

        // A guest-success flow with no pending account always logged a User in above.
        /** @var User $user */
        $user = $this->currentUser->getIdentity();

        return $this->rememberMeCookieService->addCookie(
            $user,
            $this->popupAwareRedirect($this->homeUrl()),
            $this->session->getId() ?? '',
        );
    }

    /**
     * Redirects to the given URL, handling popups opened by OAuth widget.
     * If called from a popup, closes it and — when $enforceRedirect is true — navigates the
     * opener to $url; otherwise just focuses the opener. If not called from a popup, redirects
     * the current window normally.
     */
    private function popupAwareRedirect(string $url, bool $enforceRedirect = true): ResponseInterface
    {
        /**
         * @psalm-suppress PossiblyNullOperand Unreachable while this class is loaded at all: it
         * type-hints AuthClientInterface from the same package, so yiisoft/yii-auth-client is
         * always installed whenever this runs.
         */
        $redirectViewFile = InstalledVersions::getInstallPath('yiisoft/yii-auth-client')
            . '/resources/views/redirect.php';

        return $this->viewRenderer->renderPartial($redirectViewFile, [
            'url' => $url,
            'enforceRedirect' => $enforceRedirect,
            'appName' => 'app',
        ]);
    }

    private function renderMessage(string $title): ResponseInterface
    {
        return $this->renderView('shared/message', [
            'data' => ['title' => $title, 'homeUrl' => $this->homeUrl()],
        ]);
    }
}
