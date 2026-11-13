<?php

namespace App\Domain\Pos\QrPayments;

/**
 * The provider's answer to a reversal: done (with its reference, if any), or refused with a plain reason.
 */
final class QrRefundResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $reference,
        public readonly ?string $message,
    ) {}

    public static function done(?string $reference = null): self
    {
        return new self(true, $reference, null);
    }

    public static function failed(string $message): self
    {
        return new self(false, null, $message);
    }
}
