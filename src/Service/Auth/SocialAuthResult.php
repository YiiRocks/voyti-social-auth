<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\Service\Auth;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Service\Auth\LoginCompletionService;
use YiiRocks\Voyti\Service\ServiceResult;

/**
 * Outcome shared by {@see UserSocialAuthenticateService::run()} and
 * {@see UserSocialAccountConnectService::run()}. Mirrors core's {@see ServiceResult} shape
 * (`isSuccess()`/`isFailure()`/`getMessage()`) rather than reusing it directly, since this also
 * needs to carry the `ResponseInterface` {@see LoginCompletionService::complete()} returned when a
 * run logged a user in directly - always `null` for `UserSocialAccountConnectService` (connecting
 * an already-authenticated user never calls `complete()`), and `null` for every
 * `UserSocialAuthenticateService` outcome except a direct login.
 */
final readonly class SocialAuthResult
{
    private function __construct(
        private bool $success,
        private string $message,
        public ?ResponseInterface $loginResponse = null,
    ) {}

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public static function success(?ResponseInterface $loginResponse = null): self
    {
        return new self(true, '', $loginResponse);
    }
}
