<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\tests\Http;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use YiiRocks\Voyti\SocialAuth\Http\AuthActionRequestHolder;

final class AuthActionRequestHolderTest extends TestCase
{
    public function testGetRequestReturnsNullBeforeSet(): void
    {
        $holder = new AuthActionRequestHolder();

        self::assertNull($holder->getRequest());
    }

    public function testSetRequestMakesItAvailableViaGetRequest(): void
    {
        $holder = new AuthActionRequestHolder();
        $request = new ServerRequest('GET', 'https://example.test/auth/github');

        $holder->setRequest($request);

        self::assertSame($request, $holder->getRequest());
    }
}
