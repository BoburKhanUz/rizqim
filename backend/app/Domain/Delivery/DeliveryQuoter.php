<?php

namespace App\Domain\Delivery;

use App\Models\Branch;
use App\Models\Store;
use App\Support\Money;
use Carbon\CarbonInterface;

/**
 * Prices a delivery to a point from the store's zones (ZoneResolver). Read-only: checkout does not use it until P1-32.
 */
class DeliveryQuoter
{
    public function __construct(private readonly ZoneResolver $zones) {}

    /**
     * @throws DeliveryUnavailableException
     */
    public function quote(Store $store, float $lat, float $lng, Money|string|int|float|null $subtotal = null, ?CarbonInterface $at = null): DeliveryQuote
    {
        $zone = $this->zones->resolve($store, $lat, $lng);
        if ($zone === null) {
            throw $this->zones->hasZones($store) ? DeliveryUnavailableException::outsideZone() : DeliveryUnavailableException::noZones();
        }

        $at ??= now();
        if ($zone->branch_id !== null) {
            // The zone names its branch: that one or nothing
            $branch = Branch::query()->where('store_id', $store->id)->find($zone->branch_id);
            if ($branch === null || !$this->zones->isBranchOpen($branch, $at, $store)) {
                throw DeliveryUnavailableException::branchClosed();
            }
            $distance = $branch->latitude !== null && $branch->longitude !== null
                ? Geometry::haversineKm($lat, $lng, $branch->latitude, $branch->longitude)
                : 0.0;
        } else {
            $nearest = $this->zones->nearestOpenBranch($store, $lat, $lng, $at);
            if ($nearest === null) {
                throw DeliveryUnavailableException::branchClosed();
            }
            [$branch, $distance] = [$nearest->branch, $nearest->distanceKm];
        }

        $subtotal = $subtotal === null ? null : Money::of($subtotal);
        $free = $subtotal !== null && $zone->isFreeFor($subtotal);

        return new DeliveryQuote(
            zone: $zone,
            branch: $branch,
            branchDistanceKm: $distance,
            deliveryPrice: $free ? Money::zero() : $zone->deliveryPrice(),
            minOrderAmount: $zone->minOrderAmount(),
            freeDeliveryApplies: $free,
            meetsMinOrder: $subtotal === null ? null : $subtotal->isGreaterThanOrEqualTo($zone->minOrderAmount()),
        );
    }
}
