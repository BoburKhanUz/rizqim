<?php

namespace App\Domain\Pos\QrPayments\UzQr;

/**
 * A dynamic UzQR the gateway registered: its id there and the EMV QR string the till shows.
 */
final class UzQrDynamicQr
{
    public function __construct(
        public readonly string $id,
        public readonly string $payload,
    ) {}
}
