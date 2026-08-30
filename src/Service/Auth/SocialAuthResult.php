<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\SocialAuth\Service\Auth;

use Psr\Http\Message\ResponseInterface;
use YiiRocks\Voyti\Model\User;
use YiiRocks\Voyti\Service\Auth\LoginCompletionService;
use YiiRocks\Voyti\Service\ServiceResult;

/**
 * Outcome shared by {@see UserSocialAuthenticateService::run()} and
 * {@see UserSocialAccountConnectService::run()}. Mirrors core's {@see ServiceResult} shape
 * rather than reusing it directly, since this also carries the `ResponseInterface`
 * {@see LoginCompletionService::complete()} built on a direct login, and the {@see User} it logged
 * in - for callers that need the identity itself rather than the HTML redirect/cookie response.
 */
final readonly class SocialAuthResult
{
    private function __construct(
        private bool $success,
        private string $message,
        public ?ResponseInterface $loginResponse = null,
        public ?User $user = null,
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

    public static function success(?ResponseInterface $loginResponse = null, ?User $user = null): self
    {
        return new self(true, '', $loginResponse, $user);
    }
}
