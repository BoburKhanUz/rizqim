<?php

namespace App\Services\Catalog;

use App\Domain\Pos\Menu\BatchPlans;
use App\Domain\Pos\Menu\DailyMenuService;
use App\Domain\Pos\Menu\MealSets;
use App\Enums\BranchStatus;
use App\Enums\StopListSubject;
use App\Models\Branch;
use App\Models\BranchProductVariant;
use App\Models\MenuBatch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StopListEntry;
use App\Support\Quantity;
use Illuminate\Support\Collection;

/**
 * What is unavailable on a store's branches right now (P1-21): the stop-list (products, variants, modifiers, until a
 * time or removed), the older per-branch variant switch (branch_product_variants.is_active), and batch stock: a
 * variant is "tugadi" while its open batch cannot give one more unit, or when its product is batch-tracked and the
 * branch has no open batch for it. Checkout (CreateOrderAction) enforces the same rules under row locks.
 */
class MenuAvailability
{
    /**
     * The availability of every active branch of the store (or just the one given), for the catalog API.
     *
     * @return list<array<string, mixed>>
     */
    public function forStore(int $storeId, ?int $branchId = null): array
    {
        $models = Branch::query()
            ->where('store_id', $storeId)
            ->where('status', BranchStatus::ACTIVE->value)
            ->when($branchId !== null, fn ($query) => $query->whereKey($branchId))
            ->orderBy('id')
            ->get(['id', 'store_id', 'business_day_starts_at', 'pos_settings'])
            ->keyBy('id');
        $branches = $models->keys()->map(fn ($id) => (int) $id)->all();

        if ($branches === []) {
            return [];
        }

        // P6-15: "keyingi partiya HH:MM" of the dishes that are sold out
        $nextBatches = app(BatchPlans::class)->upcoming($branches);

        $entries = StopListEntry::query()
            ->whereIn('branch_id', $branches)
            ->active()
            ->orderBy('id')
            ->get()
            ->groupBy('branch_id');

        $overrides = BranchProductVariant::query()
            ->whereIn('branch_id', $branches)
            ->where('is_active', false)
            ->get(['branch_id', 'variant_id', 'hidden_reason'])
            ->groupBy('branch_id');

        $batches = MenuBatch::query()
            ->whereIn('branch_id', $branches)
            ->open()
            ->orderBy('id')
            ->get()
            ->groupBy('branch_id');

        // Variants of the store's batch-tracked products and of products with an open batch: the only ones a batch decides
        $productIds = Product::query()->where('store_id', $storeId)->where('batch_tracked', true)->pluck('id')
            ->merge(MenuBatch::query()->whereIn('branch_id', $branches)->open()->pluck('product_id'))
            ->unique()
            ->values()
            ->all();
        $variants = $productIds === [] ? collect() : ProductVariant::query()
            ->whereIn('product_id', $productIds)
            ->get(['id', 'product_id', 'batch_consumption']);
        $tracked = Product::query()->whereIn('id', $productIds)->where('batch_tracked', true)->pluck('id')->flip();

        $result = [];
        foreach ($branches as $id) {
            $result[] = $this->branchAvailability(
                $id,
                $entries->get($id, collect()),
                $overrides->get($id, collect()),
                $batches->get($id, collect()),
                $variants,
                $tracked,
                $models->get($id),
                $nextBatches[$id] ?? [],
            );
        }

        return $result;
    }

    /**
     * The open batch an order line of this variant takes from on the branch: one for the variant itself, else one
     * for the whole product.
     *
     * @param  Collection<int, MenuBatch>  $openBatches  the branch's open batches of the variant's product
     */
    public static function batchFor(ProductVariant $variant, Collection $openBatches): ?MenuBatch
    {
        return $openBatches->first(fn (MenuBatch $batch) => (int) $batch->product_variant_id === (int) $variant->id)
            ?? $openBatches->first(fn (MenuBatch $batch) => $batch->product_variant_id === null && (int) $batch->product_id === (int) $variant->product_id);
    }

    /**
     * How much of a batch one unit of the variant uses (1 portion unless set, e.g. 0.7).
     */
    public static function consumptionOf(ProductVariant $variant): Quantity
    {
        $consumption = Quantity::of($variant->batch_consumption ?? 1);

        return $consumption->isPositive() ? $consumption : Quantity::of(1);
    }

    /**
     * The variants a batch decides that cannot give one more unit now: the open batch has less left than one unit
     * takes, or the product is batch-tracked and there is no open batch.
     *
     * @param  Collection<int, MenuBatch>  $batches  the branch's open batches
     * @param  Collection<int, ProductVariant>  $variants
     * @param  Collection<int|string, int>  $tracked  batch-tracked product ids (as keys)
     * @return list<int>
     */
    private static function soldOutIn(Collection $batches, Collection $variants, Collection $tracked): array
    {
        $soldOut = [];
        foreach ($variants as $variant) {
            $batch = self::batchFor($variant, $batches->where('product_id', $variant->product_id));

            if ($batch === null) {
                if ($tracked->has($variant->product_id)) {
                    $soldOut[] = (int) $variant->id;
                }
                continue;
            }

            if ($batch->left()->isLessThan(self::consumptionOf($variant))) {
                $soldOut[] = (int) $variant->id;
            }
        }

        return $soldOut;
    }

    /**
     * Ids of the variants that are sold out ("tugadi") on one branch right now (batch stock, see soldOutIn). Checkout uses
     * it to close a kompleks whose batch-tracked part is gone; the batch rows themselves are locked and rechecked later.
     *
     * @return list<int>
     */
    public function soldOutVariantIds(Branch $branch): array
    {
        $batches = MenuBatch::query()->where('branch_id', $branch->id)->open()->get();
        $productIds = Product::query()->where('store_id', $branch->store_id)->where('batch_tracked', true)->pluck('id')
            ->merge($batches->pluck('product_id'))
            ->unique()
            ->values()
            ->all();
        if ($productIds === []) {
            return [];
        }
        $variants = ProductVariant::query()->whereIn('product_id', $productIds)->get(['id', 'product_id', 'batch_consumption']);
        $tracked = Product::query()->whereIn('id', $productIds)->where('batch_tracked', true)->pluck('id')->flip();

        return self::soldOutIn($batches, $variants, $tracked);
    }

    /**
     * @param  Collection<int, StopListEntry>  $entries
     * @param  Collection<int, BranchProductVariant>  $overrides
     * @param  Collection<int, MenuBatch>  $batches
     * @param  Collection<int, ProductVariant>  $variants
     * @param  Collection<int|string, int>  $tracked  batch-tracked product ids (as keys)
     * @param  list<array<string, mixed>>  $nextBatches  the branch's planned batches still ahead (BatchPlans::upcoming)
     * @return array<string, mixed>
     */
    private function branchAvailability(int $branchId, Collection $entries, Collection $overrides, Collection $batches, Collection $variants, Collection $tracked, ?Branch $branch = null, array $nextBatches = []): array
    {
        $stopped = ['products' => [], 'variants' => [], 'modifiers' => []];
        $list = [];

        foreach ($entries as $entry) {
            $key = match ($entry->subject_type) {
                StopListSubject::PRODUCT => 'products',
                StopListSubject::VARIANT => 'variants',
                StopListSubject::MODIFIER => 'modifiers',
            };
            $stopped[$key][] = (int) $entry->subject_id;
            $list[] = [
                'id' => (int) $entry->id,
                'type' => $entry->subject_type->value,
                'subject_id' => (int) $entry->subject_id,
                'reason' => $entry->reason,
                'until' => $entry->until?->toJSON(),
            ];
        }

        foreach ($overrides as $override) {
            $stopped['variants'][] = (int) $override->variant_id;
            $list[] = [
                'id' => null,
                'type' => StopListSubject::VARIANT->value,
                'subject_id' => (int) $override->variant_id,
                'reason' => $override->hidden_reason,
                'until' => null,
            ];
        }

        $soldOut = self::soldOutIn($batches, $variants, $tracked);

        // P6-15: dishes off today's "Bugungi menyu", and kompleks with a stopped or sold-out part: stopped like the stop-list
        if ($branch !== null) {
            $stoppedProducts = array_fill_keys($stopped['products'], null);
            foreach (app(DailyMenuService::class)->offMenuProductIds($branch) as $productId) {
                if (!array_key_exists($productId, $stoppedProducts)) {
                    $stoppedProducts[$productId] = DailyMenuService::OFF_MENU_REASON;
                    $stopped['products'][] = $productId;
                    $list[] = ['id' => null, 'type' => StopListSubject::PRODUCT->value, 'subject_id' => $productId, 'reason' => DailyMenuService::OFF_MENU_REASON, 'until' => null];
                }
            }
            $blockedVariants = array_fill_keys(array_merge($stopped['variants'], $soldOut), null);
            // P6-31: a combo whose required slot has no available option closes like a kompleks with a stopped part
            $closed = app(MealSets::class)->closedByParts((int) $branch->store_id, $stoppedProducts, $blockedVariants)
                + app(\App\Domain\Pos\Menu\Combos::class)->closedBySlots((int) $branch->store_id, $stoppedProducts, $blockedVariants, array_fill_keys($stopped['modifiers'], null));
            foreach ($closed as $productId => $reason) {
                if (!array_key_exists($productId, $stoppedProducts)) {
                    $stoppedProducts[$productId] = $reason;
                    $stopped['products'][] = $productId;
                    $list[] = ['id' => null, 'type' => StopListSubject::PRODUCT->value, 'subject_id' => $productId, 'reason' => $reason, 'until' => null];
                }
            }
        }

        return [
            'branch_id' => $branchId,
            'stopped' => array_map(fn (array $ids) => array_values(array_unique($ids)), $stopped),
            'sold_out' => ['variants' => $soldOut],
            'stop_list' => $list,
            // P6-15: "keyingi partiya HH:MM", earliest first: {id, uuid, product_id, product_variant_id, planned_at, time, quantity, label}
            'next_batches' => $nextBatches,
            'batches' => $batches->map(fn (MenuBatch $batch) => [
                'id' => (int) $batch->id,
                'uuid' => $batch->uuid,
                'product_id' => (int) $batch->product_id,
                'product_variant_id' => $batch->product_variant_id !== null ? (int) $batch->product_variant_id : null,
                'label' => $batch->label,
                'quantity_produced' => Quantity::normalize($batch->quantity_produced),
                'quantity_left' => Quantity::normalize($batch->quantity_left),
                'started_at' => $batch->started_at->toJSON(),
            ])->values()->all(),
        ];
    }
}
