<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\tests\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use YiiRocks\Voyti\SocialAuth\Http\AuthActionRequestHolder;
use YiiRocks\Voyti\SocialAuth\Middleware\CaptureAuthActionRequestMiddleware;

#[AllowMockObjectsWithoutExpectations]
final class CaptureAuthActionRequestMiddlewareTest extends TestCase
{
    public function testProcessStoresRequestInHolderAndDelegatesToHandler(): void
    {
        $holder = new AuthActionRequestHolder();
        $middleware = new CaptureAuthActionRequestMiddleware($holder);

        $request = new ServerRequest('GET', 'https://example.test/auth/github');
        $response = new Response();
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $result = $middleware->process($request, $handler);

        self::assertSame($response, $result);
        self::assertSame($request, $holder->getRequest());
    }
}
