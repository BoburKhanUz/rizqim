<?php

namespace App\Domain\Fiscal\Gateways;

use RuntimeException;
use Throwable;

/**
 * A driver could not register a receipt. `retryable`: the provider was unreachable or timed out, so the receipt stays
 * `pending` and is tried again; otherwise the provider refused it and the receipt is `failed`. Any other exception
 * a driver throws counts as retryable. The message is stored on the receipt and shown in the panel (Uzbek).
 */
class FiscalGatewayException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = true, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public static function unavailable(string $message, ?Throwable $previous = null): self
    {
        return new self($message, true, $previous);
    }

    public static function rejected(string $message, ?Throwable $previous = null): self
    {
        return new self($message, false, $previous);
    }
}
