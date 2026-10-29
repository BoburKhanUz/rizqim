<?php

namespace App\Domain\Pos\Menu;

use App\Models\DailySet;
use App\Models\DailySetItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Kompleks" (P6-15, stolovaya): a menu product sold at its own fixed price as ONE order line (and one fiscal line) whose
 * parts the kitchen sees listed. The set is a normal product (price, MXIK code, channel prices, daily menu all work as
 * for any dish); this class only keeps its composition and snapshots it onto the sold line (`order_items.set_parts`,
 * like modifiers: later edits never rewrite a sold line).
 *
 * A set is closed while one of its parts is on the stop-list, off today's menu or sold out (closedByParts), so the till never sells
 * a complex whose soup is gone. The parts' own batch counts and stock are NOT reduced by selling a set (open item for
 * the next wave); the yield report lists the portions sold inside sets next to the batches.
 */
class MealSets
{
    public const MAX_PARTS = 12;

    /** @var array<int, array<int, array<string, mixed>>|null> product id => parts */
    private array $cache = [];

    public function __construct(
        protected AuditLogger $audit,
    ) {}

    /**
     * Whether the store has any active kompleks (checkout skips the part checks when it has none).
     */
    public function hasSets(int $storeId): bool
    {
        return DailySet::query()->where('store_id', $storeId)->where('is_active', true)->exists();
    }

    /**
     * The parts of a sold set line, for `order_items.set_parts`: null when the variant's product is not an active set.
     *
     * @return list<array{variant_id: int, variant_uuid: string, product_id: int, name: string, variant_name: string|null, quantity: string, unit: string, label: string|null}>|null
     */
    public function partsFor(ProductVariant $variant): ?array
    {
        $productId = (int) $variant->product_id;
        if (!array_key_exists($productId, $this->cache)) {
            $set = DailySet::query()->where('product_id', $productId)->where('is_active', true)->first();
            $this->cache[$productId] = $set === null ? null : $this->snapshot($set);
        }

        return $this->cache[$productId] ?: null;
    }

    /**
     * The parts of every active set of the store, by the set's product id (the POS catalog lists them on the product).
     *
     * @return array<int, list<array{variant_id: int, variant_uuid: string, product_id: int, name: string, variant_name: string|null, quantity: string, unit: string, label: string|null}>>
     */
    public function partsByProduct(int $storeId): array
    {
        $result = [];
        foreach (DailySet::query()->where('store_id', $storeId)->where('is_active', true)->get() as $set) {
            $result[(int) $set->product_id] = $this->snapshot($set);
        }

        return $result;
    }

    /**
     * @return list<array{variant_id: int, variant_uuid: string, product_id: int, name: string, variant_name: string|null, quantity: string, unit: string, label: string|null}>
     */
    public function snapshot(DailySet $set): array
    {
        $parts = [];
        foreach ($set->items()->with(['variant' => fn ($query) => $query->withTrashed()->with(['product' => fn ($q) => $q->withTrashed()])])->get() as $item) {
            $variant = $item->variant;
            if ($variant === null || $variant->product === null) {
                continue;
            }
            $parts[] = [
                'variant_id' => (int) $variant->id,
                'variant_uuid' => (string) $variant->uuid,
                'product_id' => (int) $variant->product_id,
                'name' => (string) $variant->product->name,
                'variant_name' => $variant->name !== null && $variant->name !== '' ? (string) $variant->name : null,
                'quantity' => Quantity::of($item->quantity)->toString(),
                'unit' => $variant->unit->value,
                'label' => $item->label,
            ];
        }

        return $parts;
    }

    /**
     * Product ids of the store's active sets that cannot be sold now because a part is stopped, off today's menu or
     * sold out (batch stock), with the reason shown.
     *
     * @param  array<int, ?string>  $stoppedProducts  product id => reason (stop-list and off-menu dishes)
     * @param  array<int, ?string>  $stoppedVariants  variant id => reason (stop-list, switched off on the branch, sold out)
     * @return array<int, string> set product id => reason
     */
    public function closedByParts(int $storeId, array $stoppedProducts, array $stoppedVariants): array
    {
        if ($stoppedProducts === [] && $stoppedVariants === []) {
            return [];
        }

        $closed = [];
        $sets = DailySet::query()->where('store_id', $storeId)->where('is_active', true)->with(['items.variant' => fn ($query) => $query->withTrashed()->with(['product' => fn ($q) => $q->withTrashed()])])->get();
        foreach ($sets as $set) {
            foreach ($set->items as $item) {
                $variant = $item->variant;
                if ($variant === null) {
                    continue;
                }
                if (array_key_exists((int) $variant->product_id, $stoppedProducts) || array_key_exists((int) $variant->id, $stoppedVariants)) {
                    $closed[(int) $set->product_id] = 'Tarkibdagi "' . $variant->product->name . '" hozir mavjud emas';
                    break;
                }
            }
        }

        return $closed;
    }

    /**
     * Creates the composition of a set product, or replaces it. 422 for a part outside the store, the set's own product,
     * another set, or an inactive/deleted variant.
     *
     * @param  list<array{variant_id: int, quantity?: mixed, label?: ?string}>  $parts  (list, keys are positions)
     */
    public function save(int $storeId, Product $product, array $parts, bool $active, ?User $actor): DailySet
    {
        if ((int) $product->store_id !== $storeId) {
            throw ValidationException::withMessages(['product_id' => 'Mahsulot topilmadi.']);
        }
        if ($parts === [] || count($parts) > self::MAX_PARTS) {
            throw ValidationException::withMessages(['parts' => 'Kompleksda 1 tadan ' . self::MAX_PARTS . ' tagacha taom bo\'lishi kerak.']);
        }

        $variantIds = array_map(fn (array $part) => (int) $part['variant_id'], $parts);
        $variants = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('store_id', $storeId))
            ->get()
            ->keyBy('id');
        $otherSets = DailySet::query()->where('store_id', $storeId)->where('product_id', '!=', $product->id)->pluck('product_id')->map(fn ($id) => (int) $id)->all();

        $rows = [];
        foreach ($parts as $position => $part) {
            $variant = $variants->get((int) $part['variant_id']);
            if ($variant === null) {
                throw ValidationException::withMessages(["parts.{$position}.variant_id" => 'Taom topilmadi yoki faol emas.']);
            }
            if ((int) $variant->product_id === (int) $product->id || in_array((int) $variant->product_id, $otherSets, true)) {
                throw ValidationException::withMessages(["parts.{$position}.variant_id" => "Kompleks o'zini yoki boshqa kompleksni o'z ichiga ololmaydi."]);
            }
            $quantity = Quantity::tryOf($part['quantity'] ?? 1);
            if ($quantity === null || !$quantity->isPositive() || $quantity->isGreaterThan(99)) {
                throw ValidationException::withMessages(["parts.{$position}.quantity" => "Miqdor 0 dan katta, 99 dan oshmagan son bo'lishi kerak."]);
            }
            $rawLabel = $part['label'] ?? null;
            $label = is_string($rawLabel) && trim($rawLabel) !== '' ? mb_substr(trim($rawLabel), 0, 60) : null;
            $rows[] = ['product_variant_id' => (int) $variant->id, 'quantity' => $quantity->toString(), 'label' => $label, 'sort_order' => $position];
        }

        $set = DB::transaction(function () use ($storeId, $product, $rows, $active, $actor) {
            $set = DailySet::query()->where('product_id', $product->id)->lockForUpdate()->first();
            $created = $set === null;
            $before = $created ? [] : ['is_active' => (bool) $set->is_active, 'parts' => $this->snapshot($set)];

            if ($created) {
                $set = DailySet::create(['store_id' => $storeId, 'product_id' => $product->id, 'is_active' => $active, 'created_by' => $actor?->id]);
            } else {
                $set->forceFill(['is_active' => $active])->save();
                DailySetItem::query()->where('daily_set_id', $set->id)->delete();
            }
            foreach ($rows as $row) {
                DailySetItem::create($row + ['daily_set_id' => $set->id]);
            }

            $this->audit->record('menu.meal_set_saved', $set, $before, ['is_active' => $active, 'parts' => $this->snapshot($set)], [
                'product' => $product->name,
                'created' => $created,
            ], ['actor' => $actor, 'store_id' => $storeId]);

            return $set;
        });

        unset($this->cache[(int) $product->id]);

        return $set;
    }

    public function delete(DailySet $set, ?User $actor): void
    {
        $product = Product::withTrashed()->find($set->product_id);
        $this->audit->record('menu.meal_set_deleted', $set, ['parts' => $this->snapshot($set)], [], [
            'product' => $product?->name,
        ], ['actor' => $actor, 'store_id' => (int) $set->store_id]);

        unset($this->cache[(int) $set->product_id]);
        $set->delete();
    }
}
