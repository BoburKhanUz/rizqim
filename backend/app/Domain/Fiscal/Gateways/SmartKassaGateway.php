<?php

namespace App\Domain\Fiscal\Gateways;

use App\Models\Device;
use App\Models\Store;
use Carbon\CarbonImmutable;

/**
 * A fiscal provider that also runs smart kassas (P6-16, D5 b): Rizqim on an Android smart kassa (Sunmi P2/P3 and the
 * like) whose receipts go through the provider's virtual kassa bound to that device, and whose built-in bank part
 * takes the cards. Receipts of a POS terminal flagged `smart_kassa` go to stores.fiscal_settings.smart_kassa_driver
 * (FiscalSettings::driverFor); the device's `fiscal_kassa_id` is the provider's id of that kassa. Only the sandbox
 * implements it now; a real provider (P3-32) adds its own.
 */
interface SmartKassaGateway extends FiscalGateway
{
    /**
     * Whether the provider knows this device as one of the store's kassas.
     *
     * @throws FiscalGatewayException when the provider cannot be asked
     */
    public function kassaStatus(Device $device, Store $store): SmartKassaStatus;

    /**
     * The card money the kassa's bank part took on this device between $from and $to (sales less refunds), for the
     * end-of-day comparison with the Z; null when the provider does not report it.
     *
     * @throws FiscalGatewayException when the provider cannot be asked
     */
    public function cardTotals(Device $device, Store $store, CarbonImmutable $from, CarbonImmutable $to): ?TerminalCardTotals;
}
