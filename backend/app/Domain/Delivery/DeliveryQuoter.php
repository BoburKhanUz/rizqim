<?php

namespace App\Domain\Delivery;

use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Models\Store;
use App\Support\Money;
use Carbon\CarbonInterface;

/**
 * Prices a delivery to a point from the store's zones (ZoneResolver). GET /delivery/quote and checkout
 * (CreateOrderAction, P1-32) both go through it, so the quote and the order always agree.
 */
class DeliveryQuoter
{
    public function __construct(private readonly ZoneResolver $zones) {}

    /**
     * With $branch (the customer chose one): the best zone that branch may serve, and that branch; refused when the
     * point is covered only by zones of other branches.
     *
     * @throws DeliveryUnavailableException
     */
    public function quote(Store $store, float $lat, float $lng, Money|string|int|float|null $subtotal = null, ?CarbonInterface $at = null, ?Branch $branch = null): DeliveryQuote
    {
        if ($branch !== null && (int) $branch->store_id !== (int) $store->id) {
            // Another store's branch serves none of this store's zones
            throw DeliveryUnavailableException::branchOutsideZone();
        }

        $zone = $this->zones->resolve($store, $lat, $lng, $branch?->id);
        if ($zone === null) {
            if ($branch !== null && $this->zones->resolve($store, $lat, $lng) !== null) {
                throw DeliveryUnavailableException::branchOutsideZone();
            }
            throw $this->zones->hasZones($store) ? DeliveryUnavailableException::outsideZone() : DeliveryUnavailableException::noZones();
        }

        $at ??= now();
        if ($branch !== null || $zone->branch_id !== null) {
            // The customer's branch, or the one the zone names: that one or nothing
            $branch ??= Branch::query()->where('store_id', $store->id)->find($zone->branch_id);
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

        return $this->priced($zone, $branch, $distance, $subtotal);
    }

    /**
     * The same zone and branch priced for another subtotal (checkout: the branch is chosen before the cart is priced).
     * No database read, so the zone cannot change between the two.
     */
    public function reprice(DeliveryQuote $quote, Money|string|int|float|null $subtotal): DeliveryQuote
    {
        return $this->priced($quote->zone, $quote->branch, $quote->branchDistanceKm, $subtotal);
    }

    private function priced(DeliveryZone $zone, Branch $branch, float $distance, Money|string|int|float|null $subtotal): DeliveryQuote
    {
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
