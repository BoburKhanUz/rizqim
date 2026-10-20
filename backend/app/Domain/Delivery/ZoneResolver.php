<?php

namespace App\Domain\Delivery;

use App\Enums\BranchStatus;
use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Models\Store;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Which delivery zone a map point falls in, and which branch serves it. Deterministic: the same data and the same point
 * always give the same answer (zones tie-break on priority, then area, then id; branches on distance, then id).
 *
 * Checkout prices delivery orders through it (DeliveryQuoter, CreateOrderAction; docs/ZONES.md).
 */
class ZoneResolver
{
    private const DEFAULT_TIMEZONE = 'Asia/Tashkent';

    /**
     * The active zone of the store that covers the point: highest priority first, then the smallest, then the oldest.
     * The indexed bounding box narrows the candidates in SQL; the exact test (ray casting, holes and edges handled) runs in PHP.
     *
     * With $branchId (checkout with a branch the customer chose, P1-32): only the zones that branch may serve, i.e. the
     * zones bound to it and the zones bound to no branch; the same order applies among them.
     */
    public function resolve(Store|int $store, float $lat, float $lng, ?int $branchId = null): ?DeliveryZone
    {
        $zones = DeliveryZone::query()
            ->where('store_id', $store instanceof Store ? $store->id : $store)
            ->where('active', true)
            ->when($branchId !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $branchId)))
            ->where('min_lat', '<=', $lat)->where('max_lat', '>=', $lat)
            ->where('min_lng', '<=', $lng)->where('max_lng', '>=', $lng)
            ->orderByDesc('priority')
            ->orderBy('area_km2')
            ->orderBy('id')
            ->cursor();

        foreach ($zones as $zone) {
            if ($zone->contains($lat, $lng)) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * Whether the store has any active zone at all (a store without zones has not set up zone delivery).
     */
    public function hasZones(Store|int $store): bool
    {
        return DeliveryZone::query()->where('store_id', $store instanceof Store ? $store->id : $store)->where('active', true)->exists();
    }

    /**
     * The closest branch of the store that can take an order at $at: active, with coordinates, inside its opening hours.
     */
    public function nearestOpenBranch(Store $store, float $lat, float $lng, ?CarbonInterface $at = null): ?NearestBranch
    {
        $at ??= CarbonImmutable::now();
        $best = null;

        $branches = Branch::query()->where('store_id', $store->id)->orderBy('id')->get();
        foreach ($branches as $branch) {
            if (!$this->isBranchOpen($branch, $at, $store) || $branch->latitude === null || $branch->longitude === null) {
                continue;
            }

            $distance = Geometry::haversineKm($lat, $lng, $branch->latitude, $branch->longitude);
            // Branches come in id order, so a tie keeps the lower id
            if ($best === null || $distance < $best->distanceKm) {
                $best = new NearestBranch($branch, $distance);
            }
        }

        return $best;
    }

    /**
     * Active branch whose opening hours cover $at (in the store's timezone). No hours = always open; equal opening and
     * closing time = round the clock; closing before opening = open overnight. Opening time is inclusive, closing exclusive.
     */
    public function isBranchOpen(Branch $branch, CarbonInterface $at, ?Store $store = null): bool
    {
        if (!($branch->is_active ?? true) || $branch->status !== BranchStatus::ACTIVE) {
            return false;
        }

        $open = $this->time($branch->open_time);
        $close = $this->time($branch->close_time);
        if ($open === null || $close === null || $open === $close) {
            return true;
        }

        $now = CarbonImmutable::instance($at)->setTimezone($store?->timezone ?: self::DEFAULT_TIMEZONE)->format('H:i:s');

        return $open < $close
            ? ($now >= $open && $now < $close)
            : ($now >= $open || $now < $close);
    }

    private function time(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = (string) $value;

        return strlen($value) === 5 ? $value . ':00' : substr($value, 0, 8);
    }
}
