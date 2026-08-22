<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\SocialAuth\Http\AuthActionRequestHolder;

/**
 * Stores the real incoming request into {@see AuthActionRequestHolder} so it survives past
 * `yiisoft/yii-auth-client`'s `AuthAction`, which never forwards it to its success/cancel callbacks.
 * Wrapped around this package's whole route group in `config/routes.php`, so it always runs before
 * `AuthAction` does.
 */
final readonly class CaptureAuthActionRequestMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthActionRequestHolder $requestHolder,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->requestHolder->setRequest($request);

        return $handler->handle($request);
    }
}
