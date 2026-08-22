<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\Http;

use Psr\Http\Message\ServerRequestInterface;
use YiiRocks\Voyti\SocialAuth\Middleware\CaptureAuthActionRequestMiddleware;

/**
 * Container-shared holder for the real incoming request, populated by {@see CaptureAuthActionRequestMiddleware}
 * early in the pipeline. Exists because `yiisoft/yii-auth-client`'s `AuthAction` is `final` and never
 * forwards the `ServerRequestInterface` to its success/cancel callbacks - this is the only way those
 * callbacks (via `UserSocialAuthenticateService`) can reach the actual request instead of
 * reconstructing an approximation of it.
 */
final class AuthActionRequestHolder
{
    private ?ServerRequestInterface $request = null;

    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }
}
