<?php

namespace App\Http\Controllers\Api\V1\Delivery;

use App\Domain\Delivery\DeliveryQuoter;
use App\Domain\Delivery\DeliveryUnavailableException;
use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Models\Store;
use App\Traits\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, read-only view of a store's delivery zones and the price of delivering to a point (P1-24). Checkout prices
 * delivery orders through the same DeliveryQuoter (P1-32, docs/ZONES.md).
 */
#[Group('Delivery', "Yetkazib berish hududlari va yetkazib berish narxi (ochiq, faqat o'qish).")]
class DeliveryZoneController extends Controller
{
    use ApiResponse;

    /**
     * Delivery zones of a store
     *
     * Active zones, highest priority first, without internal fields. `{store}` is the store's uuid (or numeric id).
     * `polygon` is a GeoJSON Polygon / MultiPolygon geometry with [lng, lat] positions. Money is a string with two decimals.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<array{id: int, name: string, polygon: array{type: string, coordinates: list<mixed>}, delivery_price: string, min_order_amount: string, free_delivery_from: string|null, eta_minutes: int|null, priority: int}>}')]
    public function index(string $store): JsonResponse
    {
        $found = $this->findStore($store);
        if ($found === null) {
            return $this->error("Do'kon topilmadi.", [], 404);
        }

        $zones = DeliveryZone::query()
            ->where('store_id', $found->id)
            ->where('active', true)
            ->orderByDesc('priority')->orderBy('area_km2')->orderBy('id')
            ->get()
            ->map(fn (DeliveryZone $zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
                'polygon' => $zone->polygon,
                'delivery_price' => $zone->deliveryPrice()->toString(),
                'min_order_amount' => $zone->minOrderAmount()->toString(),
                'free_delivery_from' => $zone->freeDeliveryFrom()?->toString(),
                'eta_minutes' => $zone->eta_minutes,
                'priority' => $zone->priority,
            ])
            ->values();

        return $this->success($zones);
    }

    /**
     * Delivery quote for a point
     *
     * Which zone and branch would serve `lat`/`lng`, what delivery costs (the free-delivery rule applied when `subtotal`
     * is given) and the minimum order. 422 with `data.reason` when the point cannot be served:
     * `outside_zone` (hududdan tashqarida), `zones_not_configured` (the store has not drawn zones) or `branch_closed`.
     * Money is a string with two decimals. Optional `branch`: price as checkout would with that `branch_id` (the best
     * zone that branch serves); `branch_outside_zone` when it serves none covering the point.
     */
    #[QueryParameter('store', description: "Do'kon uuid yoki id.", type: 'string')]
    #[QueryParameter('lat', description: 'Latitude of the delivery point.', type: 'float')]
    #[QueryParameter('lng', description: 'Longitude of the delivery point.', type: 'float')]
    #[QueryParameter('subtotal', description: "Cart total before delivery; enables the free-delivery and minimum-order checks.", type: 'string', required: false)]
    #[QueryParameter('branch', description: "Filial id: the branch the customer chose at checkout (P1-32).", type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{zone: array{id: int, name: string, delivery_price: string, min_order_amount: string, free_delivery_from: string|null, eta_minutes: int|null}, branch: array{id: int, name: string, distance_km: float}, delivery_price: string, min_order_amount: string, free_delivery_applies: bool, meets_min_order: bool|null, eta: array{minutes: int}|null}}')]
    #[ApiDocResponse(422, type: 'array{success: false, message: string, data: array{reason: string}}', description: "Manzilga yetkazib berib bo'lmaydi")]
    public function quote(Request $request, DeliveryQuoter $quoter): JsonResponse
    {
        $input = $request->validate([
            'store' => ['required', 'string', 'max:64'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'subtotal' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'branch' => ['nullable', 'integer', 'min:1'],
        ]);

        $store = $this->findStore($input['store']);
        if ($store === null) {
            return $this->error("Do'kon topilmadi.", [], 404);
        }

        try {
            $branch = null;
            if (isset($input['branch'])) {
                // Unknown or another store's branch: it delivers none of this store's zones (existence is not revealed)
                $branch = Branch::query()->where('store_id', $store->id)->find((int) $input['branch'])
                    ?? throw DeliveryUnavailableException::branchOutsideZone();
            }
            $quote = $quoter->quote($store, (float) $input['lat'], (float) $input['lng'], $input['subtotal'] ?? null, null, $branch);
        } catch (DeliveryUnavailableException $e) {
            return $this->error($e->getMessage(), ['reason' => $e->reason], 422);
        }

        return $this->success($quote->toArray());
    }

    /**
     * A store customers can see (the same visibility as GET /stores), by public uuid or numeric id.
     */
    private function findStore(string $key): ?Store
    {
        $query = Store::query()
            ->where('is_active', true)
            ->where('status', '!=', StoreStatus::PAYMENT_REQUIRED->value);

        if (ctype_digit($key)) {
            return $query->whereKey((int) $key)->first();
        }

        return \Illuminate\Support\Str::isUuid($key) ? $query->where('uuid', strtolower($key))->first() : null;
    }
}
