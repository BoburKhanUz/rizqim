<?php

namespace Tests\Feature;

use App\Models\DeliveryZone;
use App\Models\Store;

/**
 * Delivery zone helpers for the P1-24 tests (needs OrderLifecycleFixtures for stores and branches).
 */
trait DeliveryZoneFixtures
{
    /** Tashkent centre, a box around Amir Temur Square: lat 41.30..41.32, lng 69.26..69.30 */
    protected const CENTRE_BOX = [69.26, 41.30, 69.30, 41.32];

    /** A wider box that contains CENTRE_BOX: lat 41.25..41.36, lng 69.18..69.35 */
    protected const CITY_BOX = [69.18, 41.25, 69.35, 41.36];

    protected function boxPolygon(array $box): array
    {
        [$minLng, $minLat, $maxLng, $maxLat] = $box;

        return ['type' => 'Polygon', 'coordinates' => [[[$minLng, $minLat], [$maxLng, $minLat], [$maxLng, $maxLat], [$minLng, $maxLat], [$minLng, $minLat]]]];
    }

    protected function makeZone(array $attributes = [], ?Store $store = null, array $box = self::CENTRE_BOX): DeliveryZone
    {
        return DeliveryZone::create(array_merge([
            'store_id' => ($store ?? $this->store)->id,
            'name' => 'Markaz',
            'polygon' => $this->boxPolygon($box),
            'delivery_price' => 12000,
            'min_order_amount' => 30000,
        ], $attributes));
    }
}
