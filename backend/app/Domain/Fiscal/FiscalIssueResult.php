<?php

namespace App\Domain\Fiscal;

/**
 * What the POS learns after asking for a receipt. `pending` means the provider has not confirmed yet (it will be
 * retried); the sale stays open as "fiscal kutmoqda" until the receipt is `issued`.
 */
final readonly class FiscalIssueResult
{
    public function __construct(
        public string $receiptUuid,
        public string $status,
        public ?string $fiscalSign = null,
        public ?string $qrUrl = null,
        public ?string $error = null,
    ) {}

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }
}
