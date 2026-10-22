<?php

namespace App\Domain\Fiscal\Gateways;

use App\Domain\Fiscal\FiscalDocument;
use App\Models\FiscalReceipt;
use App\Models\Store;

/**
 * A fiscal provider driver (virtual kassa / OFD API). FiscalService is its only caller: it writes the receipt row
 * first, then asks the driver, and retries while the driver throws a retryable FiscalGatewayException. The receipt's
 * uuid is the provider-side id of the document, so registering the same receipt twice must not create two.
 * Drivers are listed in config('fiscal.drivers'); a store picks one in stores.fiscal_settings.driver.
 */
interface FiscalGateway
{
    /**
     * Registers the receipt with the provider.
     *
     * @throws FiscalGatewayException when the provider is unreachable (retryable) or refuses the receipt (not)
     */
    public function register(FiscalReceipt $receipt, FiscalDocument $document, Store $store): FiscalProviderResponse;

    /**
     * What the provider knows about this receipt: its registration when it has one, null when it does not (checked
     * before a retry, so a receipt the provider took just before a timeout is not sent twice).
     *
     * @throws FiscalGatewayException when the provider cannot be asked
     */
    public function status(FiscalReceipt $receipt, Store $store): ?FiscalProviderResponse;
}
