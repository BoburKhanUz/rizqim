<?php

namespace App\Domain\Delivery;

use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Support\Money;

/**
 * What delivering to a point costs: the zone, the branch that would cook it, and the money rules that apply.
 */
final class DeliveryQuote
{
    public function __construct(
        public readonly DeliveryZone $zone,
        public readonly Branch $branch,
        public readonly float $branchDistanceKm,
        /** What the customer pays for delivery after the free-delivery rule (zero when it applies) */
        public readonly Money $deliveryPrice,
        public readonly Money $minOrderAmount,
        public readonly bool $freeDeliveryApplies,
        /** Null when no subtotal was given */
        public readonly ?bool $meetsMinOrder,
    ) {}

    /**
     * The API shape (GET /delivery/quote). Money is a string with two decimals.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $zone = $this->zone;

        return [
            'zone' => [
                'id' => $zone->id,
                'name' => $zone->name,
                'delivery_price' => $zone->deliveryPrice()->toString(),
                'min_order_amount' => $zone->minOrderAmount()->toString(),
                'free_delivery_from' => $zone->freeDeliveryFrom()?->toString(),
                'eta_minutes' => $zone->eta_minutes,
            ],
            'branch' => [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
                'distance_km' => round($this->branchDistanceKm, 2),
            ],
            'delivery_price' => $this->deliveryPrice->toString(),
            'min_order_amount' => $this->minOrderAmount->toString(),
            'free_delivery_applies' => $this->freeDeliveryApplies,
            'meets_min_order' => $this->meetsMinOrder,
            'eta' => $zone->eta_minutes === null ? null : ['minutes' => $zone->eta_minutes],
        ];
    }
}
