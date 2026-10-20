<?php

namespace App\Services\Geo;

use RuntimeException;
use Throwable;

/**
 * The geocoding provider could not answer (timeout, HTTP error, rate limited, garbage payload, no free request slot).
 * Never cached: the next call should try again, after $retryAfter seconds when that is known.
 */
class GeocodingException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
