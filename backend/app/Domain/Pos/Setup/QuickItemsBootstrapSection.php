<?php

namespace App\Domain\Pos\Setup;

use App\Models\AutoGuestItem;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

/**
 * The `quick_items` of GET /pos/bootstrap (P3-26): the one-tap buttons of the till and the waiter phone ("choy, non"),
 * so the apps no longer guess them by name. First the store's active products marked `quick_add` in the menu (their
 * first active variant), then the branch's active per-guest ("choy-non") items (AutoGuestItem, a hall's own with its
 * `hall_id`) not listed yet. Prices and availability stay the POS catalog's (GET /catalog?channel=pos_*).
 */
final class QuickItemsBootstrapSection implements PosBootstrapContributor
{
    /** At most this many buttons. */
    public const MAX = 24;

    /**
     * @return array{quick_items: list<array{product_id: int, variant_id: int, variant_uuid: string, name: string, variant_name: string|null, source: string, hall_id: int|null, quantity_per_guest: string|null, sort_order: int}>}
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        $items = [];
        $seen = [];

        $products = Product::query()
            ->with(['variants' => fn ($query) => $query->where('is_active', true)->orderBy('id')])
            ->where('store_id', $branch->store_id)
            ->where('status', 'active')
            ->where('quick_add', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        foreach ($products as $product) {
            $variant = $product->variants->first();
            if (!$variant instanceof ProductVariant) {
                continue;
            }
            $seen[(int) $variant->id] = true;
            $items[] = $this->item($product, $variant, 'quick_add', null, null, (int) $product->sort_order);
        }

        $rules = AutoGuestItem::query()
            ->with(['variant.product'])
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        foreach ($rules as $rule) {
            $variant = $rule->variant;
            $product = $variant?->product;
            if (!$variant instanceof ProductVariant || $variant->trashed() || !$variant->is_active
                || !$product instanceof Product || $product->status !== 'active' || (int) $product->store_id !== (int) $branch->store_id
                || isset($seen[(int) $variant->id])) {
                continue;
            }
            $seen[(int) $variant->id] = true;
            $items[] = $this->item($product, $variant, 'auto_guest', $rule->hall_id !== null ? (int) $rule->hall_id : null, (string) $rule->quantity_per_guest, (int) $rule->sort_order);
        }

        return ['quick_items' => array_slice($items, 0, self::MAX)];
    }

    /**
     * @return array{product_id: int, variant_id: int, variant_uuid: string, name: string, variant_name: string|null, source: string, hall_id: int|null, quantity_per_guest: string|null, sort_order: int}
     */
    private function item(Product $product, ProductVariant $variant, string $source, ?int $hallId, ?string $perGuest, int $sortOrder): array
    {
        return [
            'product_id' => (int) $product->id,
            'variant_id' => (int) $variant->id,
            'variant_uuid' => (string) $variant->uuid,
            'name' => (string) $product->name,
            'variant_name' => $variant->name !== null && $variant->name !== '' ? (string) $variant->name : null,
            'source' => $source,
            'hall_id' => $hallId,
            'quantity_per_guest' => $perGuest,
            'sort_order' => $sortOrder,
        ];
    }
}
