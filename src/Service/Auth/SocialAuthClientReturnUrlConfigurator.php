<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\Service\Auth;

use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Yii\AuthClient\AuthAction;
use Yiisoft\Yii\AuthClient\Collection;

/**
 * Finalizes OAuth2 clients right before {@see AuthAction} builds their authorization URL. Fills in
 * the `voyti/session-auth` return URL for clients that don't have one (`OAuth2::getOauth2ReturnUrl()`
 * has no request-derived fallback - an empty `redirect_uri` is rejected outright by strict providers
 * like Google). When {@see SocialAuthClientReturnUrlConfigurator::$allowMultipleAccountsPerProvider}
 * is enabled, also ensures the provider's account-selection screen is requested via
 * `prompt=select_account` - this lets a user pick a different account than the one already
 * connected, without which providers like Google auto-confirm the already-signed-in account and the
 * callback never offers a chance to link a second one. Both are only applied when not already
 * configured by the host, so a hand-set return URL or a custom `prompt` (e.g. `login`) wins.
 */
final readonly class SocialAuthClientReturnUrlConfigurator
{
    public function __construct(
        private UrlGeneratorInterface $url,
        private bool $allowMultipleAccountsPerProvider,
    ) {}

    public function configure(Collection $clientCollection): Collection
    {
        foreach ($clientCollection->getClients() as $authClientKey => $client) {
            if ($client->getOauth2ReturnUrl() === '') {
                $client->setOauth2ReturnUrl(
                    $this->url->generateAbsolute('voyti/session-auth', ['authclient' => $authClientKey]),
                );
            }

            if ($this->allowMultipleAccountsPerProvider) {
                $authParams = $client->getAuthParams();
                if (!isset($authParams['prompt'])) {
                    $authParams['prompt'] = 'select_account';
                    $client->setAuthParams($authParams);
                }
            }
        }

        return $clientCollection;
    }
}
