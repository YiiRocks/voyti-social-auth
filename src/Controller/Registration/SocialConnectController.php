<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\Controller\Registration;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Controller\RenderTrait;
use YiiRocks\Voyti\SocialAuth\Service\Auth\PendingSocialAccountService;
use YiiRocks\Voyti\VoytiConfig;
use Yiisoft\Router\HydratorAttribute\RouteArgument;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Translator\TranslatorInterface;
use Yiisoft\Yii\AuthClient\Collection;
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
                    'title' => $this->translator->translate('voyti.settings.account_not_found', category: 'voyti-social-auth'),
                    'homeUrl' => $this->homeUrl(),
                ],
            ]);
        }

        $provider = $account->getProvider();
        $providerTitle = $this->clientCollection?->hasClient($provider) === true
            ? $this->clientCollection->getClient($provider)->getTitle()
            : $provider;

        return $this->renderView('registration/connect', [
            'data' => [
                'providerTitle' => $providerTitle,
                'loginUrl' => $this->url->generate('voyti/session-login'),
                'registerUrl' => $this->url->generate('voyti/registration-register'),
            ],
        ]);
    }
}
