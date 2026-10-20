<?php

namespace App\Services\Auth;

/**
 * A refresh, pairing or PIN request that is refused. `reason` is a stable machine-readable code for apps; the message
 * never says whether a user, code or device exists.
 */
class AuthTokenException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly int $status = 401,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return array_filter(['reason' => $this->reason, 'retry_after' => $this->retryAfter], fn ($value) => $value !== null);
    }
}
