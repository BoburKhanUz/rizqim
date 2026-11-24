<?php

namespace App\Domain\Marketplace;

use App\Domain\Pos\Brands\BrandMenu;
use App\Enums\OrderChannel;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\Catalog\CatalogPresenter;
use App\Services\Catalog\MenuAvailability;

/**
 * One store's documents for GET /catalog?store_id=... and GET /catalog/availability (customer form):
 *
 * - the store document (the store with its branches and reviews, exactly as the old controller serialized it, plus
 *   its raw schedule), keyed by the store's and the global revision;
 * - the menu document per channel and brand filter (products with variants, channel prices, modifier groups, combos;
 *   their categories; the store's brands), keyed the same way: built once, shared by every customer;
 * - the availability overlay (stop-list, sold out, batches): keyed by the store revision and never older than
 *   `ttl.availability` (5 s fresh, 15 s at most), so a stop-list change shows within seconds without a menu rebuild.
 *
 * A store id is resolved from its uuid through a small key (uuids never change).
 */
final class StoreMenu
{
    public function __construct(
        private readonly ReadModelCache $cache,
        private readonly MarketplaceRevision $revisions,
    ) {}

    /**
     * The store id of a uuid, or null when there is no such store (remembered for a minute).
     */
    public function idOfUuid(string $uuid): ?int
    {
        $id = $this->cache->short('mkt:sid:'.$uuid, 3600, fn () => (int) (Store::query()->where('uuid', $uuid)->value('id') ?? 0));
        if ((int) $id > 0) {
            return (int) $id;
        }

        // A miss is kept for a minute only (a store created meanwhile appears then)
        $this->cache->store()?->put('mkt:sid:'.$uuid, 0, 60);

        return null;
    }

    /**
     * @return array{found: bool, available: bool, active: bool, store: array<string, mixed>|null, schedule: array{flag: bool, open: string|null, close: string|null, tz: string}|null, offline_minutes: int|null}
     */
    public function store(int $storeId): array
    {
        [$family, $version] = $this->storeKey($storeId);

        return $this->cache->remember($family, $version, 'menu', fn () => $this->buildStore($storeId));
    }

    /**
     * @return array{products: list<array<string, mixed>>, categories: list<array<string, mixed>>, brands: list<array<string, mixed>>}
     */
    public function menu(int $storeId, OrderChannel $channel, ?int $brandId = null): array
    {
        [$family, $version] = $this->menuKey($storeId, $channel, $brandId);

        return $this->cache->remember($family, $version, 'menu', fn () => $this->buildMenu($storeId, $channel, $brandId));
    }

    /**
     * MenuAvailability::forStore of every active branch, with the time it was computed.
     *
     * @return array{branches: list<array<string, mixed>>, generated_at: string}
     */
    public function availability(int $storeId): array
    {
        [$family, $version] = $this->availabilityKey($storeId);

        return $this->cache->remember($family, $version, 'availability', fn () => [
            'branches' => app(MenuAvailability::class)->forStore($storeId),
            'generated_at' => now()->toJSON(),
        ]);
    }

    /**
     * The store document's [family, version] (ReadModelCache::remember).
     *
     * @return array{0: string, 1: string}
     */
    public function storeKey(int $storeId): array
    {
        return ['mkt:store:'.$storeId, $this->revisions->store($storeId).':'.$this->revisions->global()];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function menuKey(int $storeId, OrderChannel $channel, ?int $brandId = null): array
    {
        return ['mkt:menu:'.$storeId.':'.$channel->value.':'.($brandId ?? 'all'), $this->revisions->store($storeId).':'.$this->revisions->global()];
    }

    /**
     * The clock decides part of the availability (a stop-list entry's `until`, the daily menu, planned batches): the
     * key also changes every `stale` seconds, so no copy is ever older than that, whatever flexible's refresh does.
     *
     * @return array{0: string, 1: string}
     */
    public function availabilityKey(int $storeId): array
    {
        $stale = max(1, (int) (config('marketplace.ttl.availability')[1] ?? 15));

        return ['mkt:avail:'.$storeId, $this->revisions->store($storeId).':'.intdiv(now()->getTimestamp(), $stale)];
    }

    /**
     * @return array{found: bool, available: bool, active: bool, store: array<string, mixed>|null, schedule: array{flag: bool, open: string|null, close: string|null, tz: string}|null, offline_minutes: int|null}
     */
    private function buildStore(int $storeId): array
    {
        $store = Store::with([
            'branches' => fn ($q) => $q->select('id', 'store_id', 'name', 'latitude', 'longitude', 'radius_km', 'address', 'phone'),
            'ratings' => fn ($q) => $q->with('user:id,name')->orderBy('created_at', 'desc'),
        ])->whereKey($storeId)->first();

        if ($store === null) {
            return ['found' => false, 'available' => false, 'active' => false, 'store' => null, 'schedule' => null, 'offline_minutes' => null];
        }

        $status = $store->status->value ?? $store->status;
        $offline = $store->getAttribute('marketplace_offline_minutes');
        $array = $store->toArray();
        unset($array['marketplace_offline_minutes']);
        // Wave P (receipts): who sells the food (consumer law art. 28-1), only these fields
        $array['seller'] = \App\Domain\Receipts\SellerDetails::of($store, $store->branches->first()?->phone);

        return [
            'found' => true,
            'available' => (bool) $store->is_active && $status !== StoreStatus::PAYMENT_REQUIRED->value,
            'active' => (bool) $store->is_active,
            'store' => $array,
            'schedule' => StoreSchedule::of($store),
            'offline_minutes' => $offline !== null ? (int) $offline : null,
        ];
    }

    /**
     * The old store-mode query, unchanged (CatalogController before wave M2).
     *
     * @return array{products: list<array<string, mixed>>, categories: list<array<string, mixed>>, brands: list<array<string, mixed>>}
     */
    private function buildMenu(int $storeId, OrderChannel $channel, ?int $brandId): array
    {
        $products = Product::with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->where('store_id', $storeId)
            ->where('status', 'active')
            ->when($brandId !== null, fn ($query) => $query->where('brand_id', $brandId))
            ->get();

        $products = app(CatalogPresenter::class)->present($products, $channel);

        $categoryIds = $products->pluck('category_id')->unique()->toArray();
        $childCategories = Category::whereIn('id', $categoryIds)
            ->where('is_active', true)
            ->get(['id', 'uuid', 'name', 'slug', 'parent_id', 'image', 'image_disk', 'image_thumbs']);

        $parentIds = $childCategories->pluck('parent_id')->filter()->unique()->toArray();
        $parentCategories = Category::whereIn('id', $parentIds)
            ->where('is_active', true)
            ->get(['id', 'uuid', 'name', 'slug', 'parent_id', 'image', 'image_disk', 'image_thumbs']);

        $categories = $childCategories->merge($parentCategories)->sortBy('sort_order')->values();

        return [
            'products' => array_values($products->toArray()),
            'categories' => array_values($categories->toArray()),
            'brands' => BrandMenu::catalogBrands($storeId, $channel),
        ];
    }
}
