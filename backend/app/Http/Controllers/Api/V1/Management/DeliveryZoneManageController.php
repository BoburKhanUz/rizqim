<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Delivery\Geometry;
use App\Enums\BranchStatus;
use App\Models\DeliveryZone;
use App\Models\Store;
use App\Rules\ValidGeoJsonPolygon;
use App\Support\Money;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P2-12: the owner draws and edits the store's delivery zones from the partner web panel, with the same rules as the
 * panel's zone editor (docs/ZONES.md): GeoJSON Polygon / MultiPolygon validated and cleaned by Geometry::normalize, the
 * bounding box and area recomputed by the model on every save, changes audited (delivery_zone.*). zones.manage only
 * (the owner; it cannot be granted to other roles).
 */
#[Group('Owner: delivery zones', "Yetkazib berish hududlarini yaratish, tahrirlash va o'chirish (ega, zones.manage).")]
class DeliveryZoneManageController extends ManagementController
{
    private const ZONE = 'array{id: int, name: string, branch_id: int|null, branch: array{id: int, name: string}|null, polygon: array{type: string, coordinates: list<mixed>}, bbox: array{min_lat: string, max_lat: string, min_lng: string, max_lng: string}, area_km2: string, delivery_price: string, min_order_amount: string, free_delivery_from: string|null, eta_minutes: int|null, priority: int, active: bool, created_at: string|null, updated_at: string|null}';

    /**
     * The store's delivery zones
     *
     * Every zone of the store, inactive ones included, in the order ZoneResolver tries them (priority, then the
     * smaller area, then the older). A short list: not paginated. Money is a string with two decimals.
     */
    #[QueryParameter('active', description: 'Faqat faol (1) yoki faol bo\'lmagan (0) hududlar.', type: 'bool', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . self::ZONE . '>}')]
    public function index(Request $request): JsonResponse
    {
        $request->validate(['active' => 'nullable|boolean'], UzValidation::messages());

        $zones = DeliveryZone::query()
            ->where('store_id', $this->storeId($request))
            ->when($request->filled('active'), fn ($query) => $query->where('active', $request->boolean('active')))
            ->with('branch:id,name')
            ->orderByDesc('priority')->orderBy('area_km2')->orderBy('id')
            ->get();

        return $this->success($zones->map(fn (DeliveryZone $zone) => $this->present($zone))->values());
    }

    /**
     * One delivery zone
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ZONE . '}')]
    #[ApiDocResponse(404, type: 'array{success: false, message: string, data: list<mixed>}', description: 'Hudud topilmadi (yoki boshqa do\'konniki)')]
    public function show(Request $request, int $id): JsonResponse
    {
        return $this->success($this->present($this->zone($request, $id)));
    }

    /**
     * Create a delivery zone
     *
     * `polygon`: a GeoJSON Polygon or MultiPolygon (also a Feature or a FeatureCollection of one feature, as an object
     * or a JSON string), positions [lng, lat]; at most 20 polygons and 500 points; closed rings, no zero area. It is
     * stored cleaned (6 decimals, repeated points dropped). `branch_id`: an active branch of the store that alone
     * takes this zone's orders; empty = the nearest open branch. Money in UZS, up to two decimals.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::ZONE . '}')]
    public function store(Request $request): JsonResponse
    {
        $store = $this->currentStore($request);
        // Literal rules (the OpenAPI document reads them), the store's branch checked after
        $data = $request->validate([
            'name' => 'required|string|max:' . DeliveryZone::NAME_MAX,
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
            'priority' => 'sometimes|integer|between:' . DeliveryZone::PRIORITY_MIN . ',' . DeliveryZone::PRIORITY_MAX,
            'active' => 'sometimes|boolean',
            'delivery_price' => 'sometimes|numeric|min:0|max:' . DeliveryZone::AMOUNT_MAX . '|decimal:0,2',
            'min_order_amount' => 'sometimes|numeric|min:0|max:' . DeliveryZone::AMOUNT_MAX . '|decimal:0,2',
            'free_delivery_from' => 'nullable|numeric|min:0|max:' . DeliveryZone::AMOUNT_MAX . '|decimal:0,2',
            'eta_minutes' => 'nullable|integer|between:' . DeliveryZone::ETA_MIN . ',' . DeliveryZone::ETA_MAX,
            /**
             * GeoJSON Polygon / MultiPolygon geometry ([lng, lat] positions), or the same as a JSON string.
             *
             * @var array{type: string, coordinates: list<mixed>}|string
             */
            'polygon' => ['required', new ValidGeoJsonPolygon()],
        ], $this->messages(), $this->attributeNames());
        $this->checkBranch($request, $store);

        $zone = new DeliveryZone(['store_id' => $store->id]);
        $zone->fill($this->attributes($data + [
            'priority' => 0,
            'active' => true,
            'delivery_price' => 0,
            'min_order_amount' => 0,
        ]));
        $zone->save();

        return $this->success($this->present($zone->load('branch:id,name')), 'Hudud yaratildi.', 201);
    }

    /**
     * Update a delivery zone
     *
     * Any subset of the create fields. Orders already placed keep the price and zone name they were placed with.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ZONE . '}')]
    public function update(Request $request, int $id): JsonResponse
    {
        $zone = $this->zone($request, $id);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:' . DeliveryZone::NAME_MAX,
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
            'priority' => 'sometimes|integer|between:' . DeliveryZone::PRIORITY_MIN . ',' . DeliveryZone::PRIORITY_MAX,
            'active' => 'sometimes|boolean',
            'delivery_price' => 'sometimes|numeric|min:0|max:' . DeliveryZone::AMOUNT_MAX . '|decimal:0,2',
            'min_order_amount' => 'sometimes|numeric|min:0|max:' . DeliveryZone::AMOUNT_MAX . '|decimal:0,2',
            'free_delivery_from' => 'nullable|numeric|min:0|max:' . DeliveryZone::AMOUNT_MAX . '|decimal:0,2',
            'eta_minutes' => 'nullable|integer|between:' . DeliveryZone::ETA_MIN . ',' . DeliveryZone::ETA_MAX,
            /**
             * GeoJSON Polygon / MultiPolygon geometry ([lng, lat] positions), or the same as a JSON string.
             *
             * @var array{type: string, coordinates: list<mixed>}|string
             */
            'polygon' => ['sometimes', 'required', new ValidGeoJsonPolygon()],
        ], $this->messages(), $this->attributeNames());
        $this->checkBranch($request, $this->currentStore($request));

        $zone->fill($this->attributes($data))->save();

        return $this->success($this->present($zone->refresh()->load('branch:id,name')), 'Hudud saqlandi.');
    }

    /**
     * Delete a delivery zone
     *
     * Past orders keep their copy of the zone (only their link to it is cleared). Without any active zone left the
     * store goes back to branch radius delivery (docs/ZONES.md).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: null}')]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->zone($request, $id)->delete();

        return $this->success(null, "Hudud o'chirildi.");
    }

    private function zone(Request $request, int $id): DeliveryZone
    {
        $zone = DeliveryZone::where('store_id', $this->storeId($request))->with('branch:id,name')->find($id);
        if (!$zone instanceof DeliveryZone) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException($this->error('Hudud topilmadi.', [], 404));
        }

        return $zone;
    }

    /**
     * The zone form's choice of branch: an active branch of the store (or none).
     */
    private function checkBranch(Request $request, Store $store): void
    {
        \Illuminate\Support\Facades\Validator::make($request->only('branch_id'), [
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('store_id', $store->id)->where('status', BranchStatus::ACTIVE->value)],
        ], ['branch_id.exists' => 'Filial topilmadi yoki faol emas.'])->validate();
    }

    /**
     * @return array<string, mixed>
     */
    private function messages(): array
    {
        return UzValidation::messages();
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return [
            'name' => 'Nomi',
            'branch_id' => 'Filial',
            'priority' => 'Ustuvorlik',
            'active' => 'Faol',
            'delivery_price' => 'Yetkazib berish narxi',
            'min_order_amount' => 'Minimal buyurtma',
            'free_delivery_from' => 'Bepul yetkazib berish chegarasi',
            'eta_minutes' => 'Taxminiy vaqt',
            'polygon' => 'Hudud',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        if (array_key_exists('polygon', $data)) {
            $data['polygon'] = Geometry::normalize($data['polygon']);
        }
        foreach (['delivery_price', 'min_order_amount', 'free_delivery_from'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $data[$field] = Money::of($data[$field])->toString();
            }
        }
        foreach (['priority', 'eta_minutes', 'branch_id'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $data[$field] = (int) $data[$field];
            }
        }
        if (array_key_exists('active', $data)) {
            $data['active'] = (bool) $data['active'];
        }

        return array_intersect_key($data, array_flip([
            'name', 'branch_id', 'priority', 'active', 'delivery_price', 'min_order_amount', 'free_delivery_from', 'eta_minutes', 'polygon',
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DeliveryZone $zone): array
    {
        $branch = $zone->branch;

        return [
            'id' => (int) $zone->id,
            'name' => (string) $zone->name,
            'branch_id' => $zone->branch_id !== null ? (int) $zone->branch_id : null,
            'branch' => $branch ? ['id' => (int) $branch->id, 'name' => (string) $branch->name] : null,
            'polygon' => $zone->polygon,
            'bbox' => [
                'min_lat' => (string) $zone->min_lat,
                'max_lat' => (string) $zone->max_lat,
                'min_lng' => (string) $zone->min_lng,
                'max_lng' => (string) $zone->max_lng,
            ],
            'area_km2' => (string) $zone->area_km2,
            'delivery_price' => $zone->deliveryPrice()->toString(),
            'min_order_amount' => $zone->minOrderAmount()->toString(),
            'free_delivery_from' => $zone->freeDeliveryFrom()?->toString(),
            'eta_minutes' => $zone->eta_minutes,
            'priority' => (int) $zone->priority,
            'active' => (bool) $zone->active,
            'created_at' => $zone->created_at?->toIso8601String(),
            'updated_at' => $zone->updated_at?->toIso8601String(),
        ];
    }
}
