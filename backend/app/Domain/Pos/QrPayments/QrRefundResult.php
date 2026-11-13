<?php

namespace App\Domain\Pos\QrPayments;

/**
 * The provider's answer to a reversal (or to a question about one): done (with its reference, if any), refused with
 * a plain reason (the money did not go back), or unknown (a timeout, a connection error, a 5xx: it may have gone back;
 * review P6W4 C2).
 */
final class QrRefundResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $reference,
        public readonly ?string $message,
        public readonly bool $unknown = false,
    ) {}

    public static function done(?string $reference = null): self
    {
        return new self(true, $reference, null);
    }

    public static function failed(string $message): self
    {
        return new self(false, null, $message);
    }

    public static function unknown(string $message): self
    {
        return new self(false, null, $message, true);
    }
}
