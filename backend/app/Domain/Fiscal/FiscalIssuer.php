<?php

namespace App\Domain\Fiscal;

/**
 * The one entry point the rest of the backend uses for receipts (P3-11 implements it on top of FiscalGateway and the
 * fiscal_receipts table). Callers never talk to a provider directly.
 */
interface FiscalIssuer
{
    /**
     * Registers a receipt, or returns the receipt already registered under the document's idempotency key.
     */
    public function issue(FiscalDocument $document): FiscalIssueResult;

    /**
     * Records a receipt a payment provider registered itself (Payme SetFiscalData, Click fiscal data).
     *
     * @param  array<string, mixed>  $providerData
     */
    public function recordExternal(FiscalDocument $document, string $provider, array $providerData): FiscalIssueResult;

    /**
     * Whether a receipt for this subject is still waiting for the provider (blocks closing the shift).
     */
    public function hasPending(string $subjectType, int $subjectId): bool;
}
