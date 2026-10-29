<?php

namespace App\Domain\Fiscal\Gateways;

use App\Domain\Fiscal\FiscalSettings;
use App\Models\Device;
use App\Models\Store;
use Illuminate\Support\Facades\Log;

/**
 * Review P6 S#4: the provider kassa a smart kassa's receipts and card totals go to, only once the provider says the
 * kassa is the store's (SmartKassaGateway::kassaBelongsTo with the store's STIR). A till flagged `smart_kassa` whose
 * `fiscal_kassa_id` is missing, or is not the store's, or whose provider cannot be asked, is routed like any other
 * till (FiscalSettings::driverFor) and gets no provider card total: a receipt is never registered on, and totals are
 * never read from, another business's kassa.
 */
final class SmartKassaRouting
{
    /** A provider kassa id as stored: upper-case latin letters, digits and dashes, 6–32 characters. */
    public const KASSA_ID_PATTERN = '/^[A-Z0-9][A-Z0-9-]{5,31}$/';

    public function __construct(private readonly FiscalGatewayManager $gateways) {}

    /**
     * A typed kassa id as stored (trimmed, upper case); null for an empty one.
     */
    public static function normalize(?string $kassaId): ?string
    {
        $id = strtoupper(trim((string) $kassaId));

        return $id === '' ? null : $id;
    }

    /**
     * The store's smart kassa gateway (FiscalSettings::smartKassaDriver), null when that driver runs no smart kassas.
     */
    public function gateway(Store $store): ?SmartKassaGateway
    {
        $gateway = $this->gateways->driver(FiscalSettings::of($store)->smartKassaDriver());

        return $gateway instanceof SmartKassaGateway ? $gateway : null;
    }

    /**
     * Whether $kassaId is a kassa of the store's STIR at its smart kassa provider (false without a STIR or a smart
     * kassa driver).
     *
     * @throws FiscalGatewayException when the provider cannot be asked
     */
    public function belongs(string $kassaId, Store $store): bool
    {
        $gateway = $this->gateway($store);

        return $gateway !== null && filled($store->tin) && $gateway->kassaBelongsTo($kassaId, (string) $store->tin);
    }

    /**
     * The device's provider kassa id when the device is a smart kassa whose kassa is the store's; null otherwise (a
     * provider that cannot be asked counts as no: the receipt goes the ordinary way and the retry finds it).
     */
    public function kassaFor(?Device $device, Store $store): ?string
    {
        if ($device === null || !(bool) $device->getAttribute('smart_kassa')) {
            return null;
        }
        $kassaId = $device->getAttribute('fiscal_kassa_id');
        if (blank($kassaId)) {
            return null;
        }

        try {
            if ($this->belongs((string) $kassaId, $store)) {
                return (string) $kassaId;
            }
        } catch (FiscalGatewayException $e) {
            Log::warning('Smart kassa ownership could not be checked', ['store_id' => $store->id, 'device_id' => $device->id, 'error' => $e->getMessage()]);

            return null;
        }
        Log::warning('Smart kassa id is not a kassa of the store', ['store_id' => $store->id, 'device_id' => $device->id]);

        return null;
    }
}
