<?php

namespace App\Domain\Fiscal\Gateways;

use App\Domain\Fiscal\FiscalDocument;
use App\Models\FiscalReceipt;
use App\Models\Store;
use Carbon\CarbonImmutable;

/**
 * No provider: demo stores and stores whose fiscal mode is off. Every receipt is `issued` at once, with no fiscal
 * sign, number or QR, and marked non_fiscal ("Bu fiskal chek emas").
 */
class NullFiscalGateway implements FiscalGateway
{
    public function register(FiscalReceipt $receipt, FiscalDocument $document, Store $store): FiscalProviderResponse
    {
        return new FiscalProviderResponse(
            nonFiscal: true,
            payload: ['driver' => 'null', 'note' => 'Fiskal emas'],
            issuedAt: CarbonImmutable::now(),
        );
    }

    public function status(FiscalReceipt $receipt, Store $store): ?FiscalProviderResponse
    {
        return null;
    }
}
