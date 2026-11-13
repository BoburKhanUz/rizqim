<?php

namespace App\Domain\Pos\QrPayments;

/**
 * What a provider gives the till for a QR: the payload the QR encodes (a checkout URL for Click and Payme, an EMV QR
 * string for UzQR) and, when the provider registers the QR on its side, its id there.
 */
final class QrCheckout
{
    public function __construct(
        public readonly string $payload,
        public readonly ?string $reference = null,
    ) {}
}
