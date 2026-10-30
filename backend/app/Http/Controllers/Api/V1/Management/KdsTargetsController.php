<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\Permission;
use App\Models\KitchenStation;
use App\Models\Product;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PermissionService;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P6-33 (B6, module `kds_expo`): the kitchen display's time targets (docs/API.md "KDS v2"): each station's default
 * (kitchen_stations.target_seconds, set with PATCH /owner/kitchen-stations/{id}) and a dish's own override
 * (products.kds_target_seconds, store-wide). The KDS ticket carries the target of each line and whether it is late;
 * the prep time report counts the lines over their target. settings.manage, never from a terminal PIN session.
 */
#[Group('Owner: kitchen stations', "Filial sexlari: osh, mangal, salat va bar, choy (P3-13); grill, fritur, ichimliklar, barista va yig'ish (P6-33). Marka va KDS shu sexlarga bo'linadi.")]
class KdsTargetsController extends ManagementController
{
    private const STATION = 'array{id: int, name: string, kind: string, kind_label: string, is_active: bool, target_seconds: int|null, default_target_seconds: int|null}';

    private const PRODUCT = 'array{product_id: int, name: string, category_id: int, target_seconds: int}';

    private const PAGE = 'array{branch_id: int, enabled: bool, limits: array{min: int, max: int}, stations: list<' . self::STATION . '>, products: list<' . self::PRODUCT . '>}';

    /** Most dishes one PUT may change. */
    public const MAX_ITEMS = 200;

    public function __construct(
        PermissionService $permissions,
        protected PosModules $modules,
        protected AuditLogger $audit,
    ) {
        parent::__construct($permissions);
    }

    /**
     * Time targets of a branch's kitchen
     *
     * The branch's stations with their target (`target_seconds`, null: none) and the kind's suggested one
     * (`default_target_seconds`), the store's dishes that have their own target, and whether the branch has the
     * module (`enabled`: setting a target needs it). Seconds; `limits` are the allowed range.
     */
    #[QueryParameter('branch_id', description: 'The branch (a branch-bound caller: their own).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::PAGE . '}')]
    public function show(Request $request): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer|min:1|max:999999999999999999']);
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);

        return $this->success($this->page((int) $branch->id, (int) $branch->store_id, $this->modules->enabled($branch, PosModule::KDS_EXPO)));
    }

    /**
     * Set dishes' own time targets
     *
     * `items`: up to 200 `{product_id, target_seconds}` of the store's dishes; `target_seconds` 10–7200 seconds, or null
     * to drop the dish's own target (its station's applies again). A target is store-wide (every branch). Setting one
     * needs the module `kds_expo` on a branch of the store (403 module_disabled); clearing never does. Audited.
     */
    #[QueryParameter('branch_id', description: 'The branch whose page to answer with (a branch-bound caller: their own).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::PAGE . '}')]
    public function updateProducts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
            'items' => 'required|array|min:1|max:' . self::MAX_ITEMS,
            'items.*.product_id' => 'required|integer|min:1|max:999999999999999999|distinct',
            'items.*.target_seconds' => 'present|nullable|integer|min:' . KitchenStationController::MIN_TARGET . '|max:' . KitchenStationController::MAX_TARGET,
        ], UzValidation::messages([
            'items.*.target_seconds.min' => 'Vaqt maqsadi kamida ' . KitchenStationController::MIN_TARGET . ' soniya bo\'lishi kerak.',
            'items.*.target_seconds.max' => 'Vaqt maqsadi ko\'pi bilan ' . KitchenStationController::MAX_TARGET . ' soniya bo\'lishi mumkin.',
            'items.*.product_id.distinct' => 'Bitta taom ikki marta yuborilgan.',
        ]), ['items' => 'Taomlar', 'items.*.product_id' => 'Taom', 'items.*.target_seconds' => 'Vaqt maqsadi']);
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);
        $storeId = (int) $branch->store_id;

        $wanted = [];
        foreach ($data['items'] as $item) {
            $wanted[(int) $item['product_id']] = $item['target_seconds'] !== null ? (int) $item['target_seconds'] : null;
        }
        $products = Product::query()->where('store_id', $storeId)->whereIn('id', array_keys($wanted))->get(['id', 'name', 'kds_target_seconds']);
        if ($products->count() !== count($wanted)) {
            throw ValidationException::withMessages(['items' => 'Taom topilmadi.']);
        }
        if (array_filter($wanted, fn (?int $target) => $target !== null) !== []) {
            $this->modules->assertEnabledInStore($storeId, PosModule::KDS_EXPO);
        }

        $before = [];
        $after = [];
        DB::transaction(function () use ($products, $wanted, &$before, &$after) {
            foreach ($products as $product) {
                $old = $product->getAttribute('kds_target_seconds');
                $old = $old !== null ? (int) $old : null;
                $new = $wanted[(int) $product->id];
                if ($old === $new) {
                    continue;
                }
                $product->forceFill(['kds_target_seconds' => $new])->save();
                $before[(string) $product->id] = $old;
                $after[(string) $product->id] = $new;
            }
        });
        if ($after !== []) {
            $this->audit->record('kds.product_targets_updated', $branch, ['targets' => $before], ['targets' => $after], [
                'product_ids' => array_map('intval', array_keys($after)),
            ], ['store_id' => $storeId, 'branch_id' => (int) $branch->id]);
        }

        return $this->success($this->page((int) $branch->id, $storeId, $this->modules->enabled($branch, PosModule::KDS_EXPO)), 'Saqlandi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function page(int $branchId, int $storeId, bool $enabled): array
    {
        $stations = KitchenStation::query()->where('branch_id', $branchId)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (KitchenStation $station) => [
                'id' => (int) $station->id,
                'name' => (string) $station->name,
                'kind' => $station->kind->value,
                'kind_label' => $station->kind->label(),
                'is_active' => (bool) $station->is_active,
                'target_seconds' => $station->target_seconds !== null ? (int) $station->target_seconds : null,
                'default_target_seconds' => $station->kind->defaultTargetSeconds(),
            ])->values()->all();

        $products = Product::query()->where('store_id', $storeId)->whereNotNull('kds_target_seconds')->orderBy('name')->orderBy('id')
            ->get(['id', 'name', 'category_id', 'kds_target_seconds'])
            ->map(fn (Product $product) => [
                'product_id' => (int) $product->id,
                'name' => (string) $product->name,
                'category_id' => (int) $product->category_id,
                'target_seconds' => (int) $product->getAttribute('kds_target_seconds'),
            ])->values()->all();

        return [
            'branch_id' => $branchId,
            'enabled' => $enabled,
            'limits' => ['min' => KitchenStationController::MIN_TARGET, 'max' => KitchenStationController::MAX_TARGET],
            'stations' => $stations,
            'products' => $products,
        ];
    }
}
