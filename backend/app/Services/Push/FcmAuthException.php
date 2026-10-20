<?php

namespace App\Services\Push;

use RuntimeException;

/**
 * Getting the Google access token failed (bad key, Google down).
 */
class FcmAuthException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = true)
    {
        parent::__construct($message);
    }
}
