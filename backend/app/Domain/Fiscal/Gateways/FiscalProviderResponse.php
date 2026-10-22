<?php

namespace App\Domain\Fiscal\Gateways;

use Carbon\CarbonImmutable;

/**
 * The provider's answer for a registered receipt. Advance and credit receipts have no fiscal sign and no QR (VM 943,
 * 1-ilova, 7-band); `nonFiscal` marks a receipt that has no legal force (null and sandbox drivers): the printout
 * says "Bu fiskal chek emas".
 */
final readonly class FiscalProviderResponse
{
    /**
     * @param  array<string, mixed>  $payload  the provider's raw answer, kept in fiscal_receipts.provider_payload
     */
    public function __construct(
        public ?string $fiscalSign = null,
        public ?string $receiptNumber = null,
        public ?string $qrUrl = null,
        public ?string $terminalId = null,
        public bool $nonFiscal = false,
        public array $payload = [],
        public ?CarbonImmutable $issuedAt = null,
    ) {}
}
