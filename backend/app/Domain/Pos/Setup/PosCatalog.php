<?php

namespace App\Domain\Pos\Setup;

use App\Enums\OrderChannel;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Catalog\CatalogPresenter;
use App\Services\Catalog\MenuAvailability;
use Illuminate\Support\Collection;

/**
 * The menu a POS terminal sells from (P3-13, GET /catalog?channel=pos_hall|pos_takeaway): the branch's store menu on
 * that POS channel, with the channel's prices and hidden dishes applied (CatalogPresenter, as the customer catalog
 * does for telegram/mobile), the unit, step and minimum quantity of each variant, the modifiers, and what is
 * unavailable on this branch right now: the stop-list and the kazan (batch) stock.
 *
 * Additive to the customer shape: every product/variant/modifier also carries `is_available` (with
 * `unavailable_reason`: `stopped` or `sold_out` on a variant, `stopped` on a product or modifier), and every
 * product its `station_id` (kitchen routing: its own route, else its category's, else null).
 */
final class PosCatalog
{
    public function __construct(
        private readonly CatalogPresenter $presenter,
        private readonly MenuAvailability $availability,
        private readonly KitchenStationRouter $router,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Branch $branch, OrderChannel $channel): array
    {
        $storeId = (int) $branch->store_id;

        $products = Product::with(['variants' => fn ($query) => $query->where('is_active', true)])
            ->where('store_id', $storeId)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();
        $products = $this->presenter->present($products, $channel);

        $availability = $this->availability->forStore($storeId, (int) $branch->id)[0] ?? [
            'branch_id' => (int) $branch->id,
            'stopped' => ['products' => [], 'variants' => [], 'modifiers' => []],
            'sold_out' => ['variants' => []],
            'stop_list' => [],
            'batches' => [],
        ];

        $this->annotate($products, $availability, (int) $branch->id);

        $categoryIds = $products->pluck('category_id')->unique()->all();
        $columns = ['id', 'uuid', 'name', 'slug', 'parent_id', 'sort_order', 'image', 'image_disk', 'image_thumbs'];
        $own = Category::whereIn('id', $categoryIds)->where('is_active', true)->get($columns);
        $parents = Category::whereIn('id', $own->pluck('parent_id')->filter()->unique()->all())->where('is_active', true)->get($columns);

        return [
            'channel' => $channel->value,
            'menu_version' => MenuVersion::forStore($storeId),
            'branch' => ['id' => (int) $branch->id, 'name' => (string) $branch->name],
            'categories' => $own->merge($parents)->unique('id')->sortBy('sort_order')->values(),
            'products' => $products,
            'availability' => $availability,
        ];
    }

    /**
     * @param  Collection<int, Product>  $products
     * @param  array<string, mixed>  $availability
     */
    private function annotate(Collection $products, array $availability, int $branchId): void
    {
        $stoppedProducts = array_flip($availability['stopped']['products']);
        $stoppedVariants = array_flip($availability['stopped']['variants']);
        $stoppedModifiers = array_flip($availability['stopped']['modifiers']);
        $soldOut = array_flip($availability['sold_out']['variants']);
        $routes = $this->router->routesOf($branchId);

        $products->each(function (Product $product) use ($stoppedProducts, $stoppedVariants, $stoppedModifiers, $soldOut, $routes) {
            $productStopped = isset($stoppedProducts[(int) $product->id]);
            $anyAvailable = false;

            $product->variants->each(function (ProductVariant $variant) use ($productStopped, $stoppedVariants, $soldOut, &$anyAvailable) {
                $reason = match (true) {
                    $productStopped, isset($stoppedVariants[(int) $variant->id]) => 'stopped',
                    isset($soldOut[(int) $variant->id]) => 'sold_out',
                    default => null,
                };
                $variant->setAttribute('is_available', $reason === null);
                $variant->setAttribute('unavailable_reason', $reason);
                $anyAvailable = $anyAvailable || $reason === null;
            });

            $product->modifierGroups->each(fn (ModifierGroup $group) => $group->modifiers->each(function (Modifier $modifier) use ($stoppedModifiers) {
                $stopped = isset($stoppedModifiers[(int) $modifier->id]);
                $modifier->setAttribute('is_available', !$stopped);
                $modifier->setAttribute('unavailable_reason', $stopped ? 'stopped' : null);
            }));

            $product->setAttribute('is_available', !$productStopped && $anyAvailable);
            $product->setAttribute('unavailable_reason', $productStopped ? 'stopped' : ($anyAvailable ? null : 'sold_out'));
            $product->setAttribute('station_id', $routes['products'][(int) $product->id] ?? ($routes['categories'][(int) $product->category_id] ?? null));
        });
    }
}
