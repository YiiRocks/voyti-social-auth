<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\Service\Auth;

use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\AuthClient\AuthAction;
use Yiisoft\Yii\AuthClient\Collection;

/**
 * Finalizes OAuth2 clients right before {@see AuthAction} builds their authorization URL. Fills in
 * the return URL for clients that don't have one (`OAuth2::getOauth2ReturnUrl()` has no
 * request-derived fallback - an empty `redirect_uri` is rejected outright by strict providers like
 * Google). Only applied when not already configured by the host.
 *
 * `$routeName` defaults to this package's own `voyti/session-auth` route; a caller building its own
 * {@see AuthAction} for a different callback route (e.g. an API bridge) can pass its own instead.
 */
final readonly class SocialAuthClientReturnUrlConfigurator
{
    public function __construct(
        private UrlGeneratorInterface $url,
        private bool $allowMultipleAccountsPerProvider,
    ) {}

    public function configure(Collection $clientCollection, string $routeName = 'voyti/session-auth'): Collection
    {
        foreach ($clientCollection->getClients() as $authClientKey => $client) {
            if ($client->getOauth2ReturnUrl() === '') {
                $client->setOauth2ReturnUrl(
                    $this->url->generateAbsolute($routeName, ['authclient' => $authClientKey]),
                );
            }
        }

        return $clientCollection;
    }
}
