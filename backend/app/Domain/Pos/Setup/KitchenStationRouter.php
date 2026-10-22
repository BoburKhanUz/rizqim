<?php

namespace App\Domain\Pos\Setup;

use App\Domain\Pos\Contracts\KitchenStationResolver;
use App\Models\KitchenStationRoute;
use App\Models\OrderItem;
use App\Models\ProductVariant;

/**
 * KitchenStationResolver (P3-13): the station a line goes to, from the branch's routes: a route of the line's product,
 * else a route of the product's category, else null (the branch has no stations, or this dish is not routed: the line
 * goes to the default ticket printer). Only active stations of the order's own branch count.
 *
 * A branch's routes are read once and kept for the request (one query however many lines are sent together); a route
 * written through the model drops them (KitchenStationRoute::booted()).
 */
final class KitchenStationRouter implements KitchenStationResolver
{
    /** @var array<int, array{products: array<int, int>, categories: array<int, int>}> branch id => routes */
    private array $routes = [];

    public function stationIdFor(OrderItem $item): ?int
    {
        $branchId = $item->order?->branch_id;
        if ($branchId === null || $item->product_variant_id === null) {
            return null;
        }

        // The line's dish even when the variant or product was deleted since (the line is a snapshot)
        $dish = ProductVariant::withTrashed()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('product_variants.id', $item->product_variant_id)
            ->first(['products.id as product_id', 'products.category_id']);
        if ($dish === null) {
            return null;
        }

        return $this->stationIdForProduct((int) $branchId, (int) $dish->getAttribute('product_id'), $dish->getAttribute('category_id') !== null ? (int) $dish->getAttribute('category_id') : null);
    }

    /**
     * The station of a product on a branch: its own route, else its category's, else null.
     */
    public function stationIdForProduct(int $branchId, int $productId, ?int $categoryId): ?int
    {
        $routes = $this->routesOf($branchId);

        return $routes['products'][$productId]
            ?? ($categoryId !== null ? ($routes['categories'][$categoryId] ?? null) : null);
    }

    /**
     * The branch's routes by product and by category (active stations only).
     *
     * @return array{products: array<int, int>, categories: array<int, int>}
     */
    public function routesOf(int $branchId): array
    {
        if (isset($this->routes[$branchId])) {
            return $this->routes[$branchId];
        }

        $routes = ['products' => [], 'categories' => []];
        KitchenStationRoute::query()
            ->where('kitchen_station_routes.branch_id', $branchId)
            ->join('kitchen_stations', 'kitchen_stations.id', '=', 'kitchen_station_routes.station_id')
            ->where('kitchen_stations.is_active', true)
            ->where('kitchen_stations.branch_id', $branchId)
            ->get(['kitchen_station_routes.product_id', 'kitchen_station_routes.category_id', 'kitchen_station_routes.station_id'])
            ->each(function (KitchenStationRoute $route) use (&$routes) {
                if ($route->product_id !== null) {
                    $routes['products'][(int) $route->product_id] = (int) $route->station_id;
                } elseif ($route->category_id !== null) {
                    $routes['categories'][(int) $route->category_id] = (int) $route->station_id;
                }
            });

        return $this->routes[$branchId] = $routes;
    }

    public function forget(?int $branchId = null): void
    {
        if ($branchId === null) {
            $this->routes = [];

            return;
        }

        unset($this->routes[$branchId]);
    }
}
