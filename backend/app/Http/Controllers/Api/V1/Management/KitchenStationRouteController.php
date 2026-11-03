<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Enums\Permission;
use App\Models\Category;
use App\Models\KitchenStation;
use App\Models\KitchenStationRoute;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P3-13: which dishes go to which kitchen station of a branch: a route sends a product or a whole category to a
 * station. A product route beats the route of its category; a product or category has one station per branch.
 */
#[Group('Owner: kitchen stations', "Filial sexlari: osh, mangal, salat va bar, choy (P3-13). Marka va KDS shu sexlarga bo'linadi.")]
class KitchenStationRouteController extends ManagementController
{
    private const ROUTE = 'array{id: int, branch_id: int, station_id: int, category_id: int|null, category_name: string|null, product_id: int|null, product_name: string|null}';

    /**
     * Routes of a branch
     *
     * Every route of the branch: `category_id` or `product_id` is set, with the dish's name for the screen.
     */
    #[QueryParameter('branch_id', description: 'The branch (a branch-bound caller: their own).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . self::ROUTE . '>}')]
    public function index(Request $request): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer|min:1|max:999999999999999999']);
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);

        return $this->success($this->routes((int) $branch->id));
    }

    /**
     * Assign dishes to a station (or free them)
     *
     * For `branch_id`: every category in `category_ids` and every product in `product_ids` goes to `station_id`; one
     * that was routed to another station moves. `station_id: null` removes the routes of those categories and
     * products. Send at least one list. Products must be the store's; the station must be the branch's (active or
     * not). `data` is the branch's routes afterwards. P6-33: an `expo` (assembly) station takes no dishes (422).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . self::ROUTE . '>}')]
    public function assign(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
            'station_id' => 'present|nullable|integer|min:1|max:999999999999999999',
            'category_ids' => 'required_without:product_ids|array|max:200',
            'category_ids.*' => 'integer|min:1|max:999999999999999999|distinct',
            'product_ids' => 'required_without:category_ids|array|max:500',
            'product_ids.*' => 'integer|min:1|max:999999999999999999|distinct',
        ], UzValidation::messages([
            'required_without' => "Kategoriyalar yoki mahsulotlardan kamida bittasini yuboring.",
            'station_id.present' => "Sexni tanlang yoki bo'sh (null) yuboring.",
        ]), ['station_id' => 'Sex', 'category_ids' => 'Kategoriyalar', 'product_ids' => 'Mahsulotlar']);
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);

        $station = null;
        if ($data['station_id'] !== null) {
            $station = KitchenStation::where('branch_id', $branch->id)->find((int) $data['station_id']);
            if (!$station instanceof KitchenStation) {
                throw ValidationException::withMessages(['station_id' => 'Sex topilmadi (u shu filialniki bo\'lishi kerak).']);
            }
            // P6-33: the expo (assembly) station puts orders together, it cooks nothing of its own
            if ($station->kind === \App\Enums\KitchenStationKind::EXPO) {
                throw ValidationException::withMessages(['station_id' => "Yig'ish sexiga taom biriktirilmaydi: u boshqa sexlarda tayyorlangan buyurtmani yig'adi."]);
            }
        }

        $categoryIds = array_map('intval', $data['category_ids'] ?? []);
        $productIds = array_map('intval', $data['product_ids'] ?? []);

        if ($categoryIds !== [] && Category::whereIn('id', $categoryIds)->count() !== count($categoryIds)) {
            throw ValidationException::withMessages(['category_ids' => 'Kategoriya topilmadi.']);
        }
        if ($productIds !== [] && Product::where('store_id', $branch->store_id)->whereIn('id', $productIds)->count() !== count($productIds)) {
            throw ValidationException::withMessages(['product_ids' => 'Mahsulot topilmadi.']);
        }

        DB::transaction(function () use ($branch, $station, $categoryIds, $productIds) {
            foreach ([['category_id', $categoryIds], ['product_id', $productIds]] as [$column, $ids]) {
                foreach ($ids as $id) {
                    $existing = KitchenStationRoute::where('branch_id', $branch->id)->where($column, $id)->first();

                    if ($station === null) {
                        $existing?->delete();
                        continue;
                    }

                    if ($existing instanceof KitchenStationRoute) {
                        $existing->update(['station_id' => $station->id]);
                    } else {
                        KitchenStationRoute::create(['store_id' => $branch->store_id, 'branch_id' => $branch->id, 'station_id' => $station->id, $column => $id]);
                    }
                }
            }
        });

        $audit->record(
            $station === null ? 'pos.station_routes_cleared' : 'pos.station_routes_assigned',
            $branch,
            [],
            [],
            ['station_id' => $station?->id, 'category_ids' => $categoryIds, 'product_ids' => $productIds],
            ['store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id],
        );

        return $this->success($this->routes((int) $branch->id), $station === null ? "Yo'naltirish olib tashlandi." : "Yo'naltirish saqlandi.");
    }

    /**
     * Remove one route
     *
     * The dish goes back to the route of its category, or to the default ticket printer without one.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: null}')]
    public function destroy(Request $request, int $id, AuditLogger $audit): JsonResponse
    {
        $route = KitchenStationRoute::where('store_id', $this->storeId($request))->find($id);
        if (!$route instanceof KitchenStationRoute) {
            throw new HttpResponseException($this->error("Yo'naltirish topilmadi.", [], 404));
        }
        $this->authorizeFor($request, Permission::SETTINGS_MANAGE, $route);

        $route->delete();
        $audit->record('pos.station_route_removed', $route, ['station_id' => $route->station_id, 'category_id' => $route->category_id, 'product_id' => $route->product_id], []);

        return $this->success(null, "Yo'naltirish olib tashlandi.");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function routes(int $branchId): array
    {
        return KitchenStationRoute::query()
            ->where('branch_id', $branchId)
            ->with(['category:id,name', 'product' => fn ($query) => $query->select('id', 'name')])
            ->orderBy('station_id')->orderBy('id')
            ->get()
            ->map(fn (KitchenStationRoute $route) => [
                'id' => (int) $route->id,
                'branch_id' => (int) $route->branch_id,
                'station_id' => (int) $route->station_id,
                'category_id' => $route->category_id !== null ? (int) $route->category_id : null,
                'category_name' => $route->category?->name,
                'product_id' => $route->product_id !== null ? (int) $route->product_id : null,
                'product_name' => $route->product?->name,
            ])
            ->values()
            ->all();
    }
}
