<?php

namespace App\Domain\Marketplace;

use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\Store;
use Exception;

/**
 * Store reachability (Toast's rule), OFF by default: config marketplace.reachability.enabled AND the store's own
 * limit (stores.marketplace_offline_minutes) must both be set.
 *
 * Signal: devices.last_seen_at of the store's active paired terminals that take orders (POS tills and KDS screens;
 * customer displays do not count). The device guard writes it on an authenticated request, at most once a minute
 * (App\Auth\DeviceTokenGuard), so it is a minute coarse. A store is unreachable when it has at least one such terminal
 * and none of them was seen within the limit. A store without paired terminals is never affected.
 */
final class StoreReachability
{
    public const REFUSAL = "Restoran hozir buyurtma qabul qila olmayapti: uning kassasi yoki oshxona ekrani bilan aloqa yo'q. Birozdan keyin urinib ko'ring.";

    public function __construct(private readonly ReadModelCache $cache) {}

    public static function applies(?int $offlineMinutes): bool
    {
        return (bool) config('marketplace.reachability.enabled', false) && $offlineMinutes !== null && $offlineMinutes > 0;
    }

    /**
     * Live, for checkout.
     */
    public function isReachable(int $storeId, ?int $offlineMinutes): bool
    {
        if (!self::applies($offlineMinutes)) {
            return true;
        }

        $row = Device::query()
            ->where('store_id', $storeId)
            ->whereNull('user_id')
            ->whereNull('revoked_at')
            ->whereIn('type', [DeviceType::POS->value, DeviceType::KDS->value])
            ->selectRaw('count(*) as terminals, max(last_seen_at) as last_seen')
            ->toBase()
            ->first();

        if ($row === null || (int) $row->terminals === 0) {
            return true;
        }

        return $row->last_seen !== null && now()->subMinutes((int) $offlineMinutes)->lte(\Illuminate\Support\Carbon::parse($row->last_seen));
    }

    /**
     * For the marketplace screens: the same answer, kept for `reachability.cache_seconds`.
     */
    public function isReachableCached(int $storeId, ?int $offlineMinutes): bool
    {
        if (!self::applies($offlineMinutes)) {
            return true;
        }

        $seconds = max(1, (int) config('marketplace.reachability.cache_seconds', 30));

        return (bool) $this->cache->short('mkt:reach:'.$storeId.':'.$offlineMinutes, $seconds, fn () => $this->isReachable($storeId, $offlineMinutes) ? 1 : 0);
    }

    /**
     * Checkout: refuses an order of an unreachable store (a 400 with the message, like the other store checks).
     *
     * @throws Exception
     */
    public function assertAcceptsOrders(Store $store): void
    {
        $minutes = $store->getAttribute('marketplace_offline_minutes');
        if (!$this->isReachable((int) $store->id, $minutes !== null ? (int) $minutes : null)) {
            throw new Exception(self::REFUSAL);
        }
    }
}
