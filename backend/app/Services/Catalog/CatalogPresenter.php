<?php

namespace App\Services\Catalog;

use App\Enums\OrderChannel;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * The menu as the customer catalog shows it on one channel (P1-21), on top of the existing product/variant fields:
 * channel prices (`price` is the channel's, `base_price` the variant's own) with hidden products/variants left out,
 * each variant's `unit`, `quantity_step`, `min_quantity`, and each product's active `modifier_groups` with their
 * effective rules for that product (min_select, max_select, is_required) and active modifiers.
 */
class CatalogPresenter
{
    /**
     * @param  EloquentCollection<int, Product>  $products  with variants loaded
     * @return EloquentCollection<int, Product>
     */
    public function present(EloquentCollection $products, OrderChannel $channel = OrderChannel::TELEGRAM): EloquentCollection
    {
        if ($products->isEmpty()) {
            return $products;
        }

        $products->load(['modifierGroups' => fn ($query) => $query->where('is_active', true)->with([
            'modifiers' => fn ($query) => $query->where('is_active', true),
        ])]);

        $storeIds = $products->pluck('store_id')->unique()->map(fn ($id) => (int) $id)->values()->all();
        $products = ChannelMenu::for($storeIds, $channel)->apply($products);

        $products->each(function (Product $product) {
            $product->modifierGroups->each(function (ModifierGroup $group) {
                $pivot = $group->getRelation('pivot');
                $min = $pivot?->getAttribute('min_select') ?? $group->min_select;
                $required = (bool) ($pivot?->getAttribute('is_required') ?? $group->is_required);

                // The rules as they apply to this product (CreateOrderAction checks the same)
                $group->setAttribute('min_select', $required ? max(1, (int) $min) : (int) $min);
                $group->setAttribute('max_select', $pivot?->getAttribute('max_select') ?? $group->max_select);
                $group->setAttribute('is_required', $required || (int) $min > 0);
                $group->setAttribute('sort_order', (int) ($pivot?->getAttribute('sort_order') ?? $group->sort_order));
                $group->makeHidden(['pivot', 'store_id', 'created_at', 'updated_at', 'deleted_at']);
                $group->modifiers->each(fn (Modifier $modifier) => $modifier->makeHidden(['created_at', 'updated_at', 'deleted_at']));
            });
        });

        return $products;
    }
}
