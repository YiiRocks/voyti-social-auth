<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\tests\Support;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 fake returning a fixed response (or one response per call, cycling through a queue),
 * capturing every request it was asked to send.
 */
final class FakeHttpClient implements ClientInterface
{
    /**
     * @var list<RequestInterface>
     */
    private array $requests = [];

    /**
     * @var list<ResponseInterface>
     */
    private array $responses;

    public function __construct(ResponseInterface ...$responses)
    {
        $this->responses = $responses;
    }

    /**
     * @return list<RequestInterface>
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return count($this->responses) > 1 ? array_shift($this->responses) : $this->responses[0];
    }
}
