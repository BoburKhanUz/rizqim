<?php

namespace App\Domain\Pos\Kitchen;

use App\Domain\Pos\Contracts\KitchenStationResolver;
use App\Domain\Pos\Setup\KitchenStationRouter;
use App\Enums\KitchenItemStatus;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * P6-33 (B6): the time target of a kitchen line and whether it is late, for the kitchen display (the ticket's
 * `target_seconds` and `late` per line) and the prep time report.
 *
 *   target  = the dish's own target (products.kds_target_seconds) ?? its station's (kitchen_stations.target_seconds)
 *             ?? null (no target: never late). The station is the line's own (POS), else the one its dish routes to
 *             (an online line).
 *   started = when the line reached the kitchen: sent_at (POS), else the order's acceptance (an online line; one for a
 *             later pickup: its kitchen time, kitchen_fire_at, if later), else the order's creation.
 *   late    = still cooking and now − started > target; a done line: ready_at − started > target.
 *
 * Instances keep what they read (stations, dishes) for their own life only (one ticket listing, one report).
 */
class KitchenTargets
{
    /** @var array<int, int|null> station id => target seconds */
    private array $stationTargets = [];

    private const NO_DISH = ['product_id' => null, 'category_id' => null, 'target' => null, 'name' => null];

    /** @var array<int, array{product_id: int|null, category_id: int|null, target: int|null, name: string|null}> variant id => dish */
    private array $dishes = [];

    /**
     * @param  iterable<OrderItem>  $items
     */
    public function prefetch(iterable $items): void
    {
        $variants = [];
        foreach ($items as $item) {
            if ($item->product_variant_id !== null) {
                $variants[] = (int) $item->product_variant_id;
            }
        }
        $this->prefetchVariants($variants);
    }

    /**
     * Reads the dishes (product, category, own target, name) of these variants in one query, deleted ones included
     * (a line is a snapshot).
     *
     * @param  list<int>  $variantIds
     */
    public function prefetchVariants(array $variantIds): void
    {
        $missing = array_values(array_unique(array_filter($variantIds, fn (int $id) => !isset($this->dishes[$id]))));
        if ($missing === []) {
            return;
        }
        foreach ($missing as $id) {
            $this->dishes[$id] = self::NO_DISH;
        }
        foreach (array_chunk($missing, 1000) as $chunk) {
            ProductVariant::withTrashed()
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->whereIn('product_variants.id', $chunk)
                ->get(['product_variants.id', 'products.id as dish_product_id', 'products.category_id as dish_category_id', 'products.kds_target_seconds as dish_target', 'products.name as dish_name'])
                ->each(function (ProductVariant $row) {
                    $category = $row->getAttribute('dish_category_id');
                    $target = $row->getAttribute('dish_target');
                    $this->dishes[(int) $row->id] = [
                        'product_id' => (int) $row->getAttribute('dish_product_id'),
                        'category_id' => $category !== null ? (int) $category : null,
                        'target' => $target !== null ? (int) $target : null,
                        'name' => (string) $row->getAttribute('dish_name'),
                    ];
                });
        }
    }

    /**
     * The line's station: its own, else (an online line) the one its dish routes to on the order's branch.
     */
    public function stationOf(OrderItem $item, ?int $branchId): ?int
    {
        if ($item->station_id !== null) {
            return (int) $item->station_id;
        }
        if ($branchId === null || $item->product_variant_id === null || $item->fire_mode !== null) {
            return null;
        }

        return $this->routedStation($branchId, (int) $item->product_variant_id);
    }

    /**
     * The station a dish routes to on a branch now (its own route, else its category's); null: none.
     */
    public function routedStation(int $branchId, int $variantId): ?int
    {
        $dish = $this->dish($variantId);
        $resolver = app()->bound(KitchenStationResolver::class) ? app(KitchenStationResolver::class) : null;
        if ($dish['product_id'] === null || !$resolver instanceof KitchenStationRouter) {
            return null;
        }

        return $resolver->stationIdForProduct($branchId, $dish['product_id'], $dish['category_id']);
    }

    public function targetOf(OrderItem $item, ?int $branchId): ?int
    {
        return $this->targetFor(
            $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
            $this->stationOf($item, $branchId),
        );
    }

    /**
     * The dish's own target, else its station's; null: none.
     */
    public function targetFor(?int $variantId, ?int $stationId): ?int
    {
        $own = $variantId !== null ? $this->dish($variantId)['target'] : null;
        if ($own !== null) {
            return $own;
        }

        return $stationId !== null ? $this->stationTarget($stationId) : null;
    }

    /**
     * The dish's product id (null for a line without a variant, or whose dish is gone).
     */
    public function productOf(?int $variantId): ?int
    {
        return $variantId !== null ? $this->dish($variantId)['product_id'] : null;
    }

    /**
     * The dish's name as the menu has it now (null when it is gone).
     */
    public function productNameOf(?int $variantId): ?string
    {
        return $variantId !== null ? $this->dish($variantId)['name'] : null;
    }

    public function stationTarget(int $stationId): ?int
    {
        if (!array_key_exists($stationId, $this->stationTargets)) {
            $target = KitchenStation::whereKey($stationId)->value('target_seconds');
            $this->stationTargets[$stationId] = $target !== null ? (int) $target : null;
        }

        return $this->stationTargets[$stationId];
    }

    /**
     * When the line reached the kitchen: a POS line when it was sent, an online line when its order did (orderStart).
     *
     * @param  CarbonInterface|null  $acceptedAt  the order's acceptance (an online line)
     */
    public static function startedAt(OrderItem $item, Order $order, ?CarbonInterface $acceptedAt): ?CarbonInterface
    {
        return $item->sent_at ?? ($item->fire_mode === null ? self::orderStart($order->created_at, $acceptedAt, $order->pickup_at !== null ? $order->kitchen_fire_at : null) : null);
    }

    /**
     * When an order reached the kitchen: its acceptance (else its creation); P6-39: an order for a later pickup at its
     * kitchen time (pickup_at − prep, ScheduledPickup), when the kitchen display starts showing it — not the acceptance
     * long before (it was never late while it waited), unless it was accepted after that time.
     */
    public static function orderStart(?CarbonInterface $createdAt, ?CarbonInterface $acceptedAt, ?CarbonInterface $kitchenFireAt): ?CarbonInterface
    {
        $accepted = $acceptedAt ?? $createdAt;
        if ($kitchenFireAt === null) {
            return $accepted;
        }

        return $accepted === null || $kitchenFireAt->greaterThan($accepted) ? $kitchenFireAt : $accepted;
    }

    /**
     * The ticket's per line `target_seconds` and `late`.
     *
     * @return array{target_seconds: int|null, late: bool}
     */
    public function annotate(OrderItem $item, Order $order, ?CarbonInterface $acceptedAt, ?Carbon $now = null): array
    {
        $target = $this->targetOf($item, $order->branch_id !== null ? (int) $order->branch_id : null);
        $started = self::startedAt($item, $order, $acceptedAt);
        if ($target === null || $started === null || $item->isVoid()) {
            return ['target_seconds' => $target, 'late' => false];
        }
        $done = in_array($item->kitchen_status->value, KitchenItemStatus::doneValues(), true);
        $end = $done ? ($item->ready_at ?? $now ?? now()) : ($now ?? now());

        return ['target_seconds' => $target, 'late' => $started->diffInSeconds($end, false) > $target];
    }

    /**
     * @return array{product_id: int|null, category_id: int|null, target: int|null, name: string|null}
     */
    private function dish(int $variantId): array
    {
        if (!isset($this->dishes[$variantId])) {
            $this->prefetchVariants([$variantId]);
        }

        return $this->dishes[$variantId] ?? self::NO_DISH;
    }
}
