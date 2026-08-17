<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\tests\Support;

use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

/**
 * An event dispatcher that throws on dispatch, letting tests drive a service's persist/notify
 * failure branch (which catches the RuntimeException) with real collaborators instead of mocking a
 * final service class.
 */
final readonly class ThrowingEventDispatcher implements EventDispatcherInterface
{
    public function __construct(private string $message = 'Simulated failure') {}

    public function dispatch(object $event): object
    {
        throw new RuntimeException($this->message);
    }
}
