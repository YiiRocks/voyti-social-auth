<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\Controller\Registration;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\Helper\Views\VoytiCommonParametersInjection;
use YiiRocks\Voyti\SocialAuth\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\AuthClient\Collection;
use Yiisoft\Yii\View\Renderer\CsrfViewInjection;
use Yiisoft\Yii\View\Renderer\WebViewRenderer;

/**
 * Shows the "connect your account" interstitial reached from a social sign-in whose email didn't
 * match an existing user - replaces core's `RegistrationController::connect()`, moved out here
 * along with the rest of the pending-social-account flow.
 */
final readonly class SocialConnectController
{
    use RenderTrait;

    public function __construct(
        private TranslatorInterface $translator,
        private WebViewRenderer $viewRenderer,
        private UrlGeneratorInterface $url,
        private VoytiConfig $config,
        private PendingSocialAccountService $pendingSocialAccountService,
        private ?Collection $clientCollection,
    ) {}

    public function connect(#[RouteArgument] string $code): ResponseInterface
    {
        $account = $this->pendingSocialAccountService->useCode($code);
        if ($account === null) {
            return $this->renderView('shared/message', [
                'data' => [
                    'title' => $this->translator->translate('voyti.settings.network_not_found', category: 'voyti-social-auth'),
                    'homeUrl' => $this->homeUrl(),
                ],
            ]);
        }

        $provider = $account->getProvider();
        $providerTitle = $this->clientCollection?->hasClient($provider) === true
            ? $this->clientCollection->getClient($provider)->getTitle()
            : $provider;

        return $this->viewRenderer
            ->withAddedInjections(CsrfViewInjection::class, VoytiCommonParametersInjection::class)
            ->withViewPath($this->resolveOwnViewPath())
            ->render('registration/connect', [
                'data' => [
                    'providerTitle' => $providerTitle,
                    'loginUrl' => $this->url->generate('voyti/session-login'),
                    'registerUrl' => $this->url->generate('voyti/registration-register'),
                ],
            ]);
    }

    /**
     * RenderTrait::resolveViewPath() always resolves relative to core's own package root (a
     * trait's __DIR__ is fixed to the file it's physically defined in, regardless of which class
     * uses it), so it can never find this package's own bundled views. Mirrors the same
     * host-override-then-bundled-fallback logic, rooted at this package's own directory instead.
     */
    private function resolveOwnViewPath(): string
    {
        if ($this->config->viewPath !== null && is_file($this->config->viewPath . '/registration/connect.php')) {
            return $this->config->viewPath;
        }

        return dirname(__DIR__, 3) . '/resources/views/' . $this->config->webTheme->value;
    }
}
