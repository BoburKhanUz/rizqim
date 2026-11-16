<?php

namespace App\Domain\Marketplace;

use App\Enums\OrderChannel;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promo;
use App\Models\Store;
use App\Services\Catalog\CatalogPresenter;

/**
 * GET /catalog without store_id (the Mini App's home feed and category page) from cached documents:
 *
 * - a product pool per area, category and channel (up to `feed_pool` random products of the area's open stores, with
 *   the old feed's prices, modifiers and combos), rebuilt after `ttl.feed` seconds or when the directory changes;
 *   each request keeps the products of stores that serve its exact point and are open now, shuffles them and shows
 *   `feed_limit` (the old answer was 50 random products per request);
 * - the active categories and the promo banners (global documents; a banner's schedule is checked per request).
 *
 * view=cards (wave M2) answers small product cards instead of full products; see docs/API.md.
 */
final class CatalogFeed
{
    public function __construct(
        private readonly ReadModelCache $cache,
        private readonly MarketplaceRevision $revisions,
        private readonly StoreDirectory $directory,
    ) {}

    /**
     * @return list<array<string, mixed>> the categories of the old feed (active, by sort_order)
     */
    public function categories(): array
    {
        return $this->cache->remember('mkt:categories', $this->revisions->global(), 'global', fn () => array_values(
            Category::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'uuid', 'name', 'slug', 'parent_id', 'image', 'image_disk', 'image_thumbs'])
                ->toArray()
        ));
    }

    /**
     * The live banners (Promo::live at this moment), from every active banner's cached entry and schedule.
     *
     * @return list<array<string, mixed>>
     */
    public function promos(): array
    {
        $all = $this->cache->remember('mkt:promos', $this->revisions->global(), 'global', fn () => Promo::with(['store', 'product.store'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Promo $promo) => [
                'entry' => $promo->toCatalogEntry(),
                'starts_at' => $promo->starts_at?->getTimestamp(),
                'ends_at' => $promo->ends_at?->getTimestamp(),
            ])
            ->values()
            ->all());

        $now = now()->getTimestamp();

        return array_values(array_map(
            fn (array $promo) => $promo['entry'],
            array_filter($all, fn (array $promo) => ($promo['starts_at'] === null || $promo['starts_at'] <= $now)
                && ($promo['ends_at'] === null || $promo['ends_at'] > $now)),
        ));
    }

    /**
     * The products to show: of open stores serving the point, shuffled, at most $limit.
     *
     * @return list<array<string, mixed>> full products (the old shape, `store` attached)
     */
    public function products(Area $area, ?int $categoryId, OrderChannel $channel, int $limit): array
    {
        $pool = $this->pool($area, $categoryId, $channel);
        $served = $this->servedStores($area);

        $products = [];
        foreach ($pool['products'] as $product) {
            $storeId = (int) $product['store_id'];
            if (!isset($served[$storeId], $pool['stores'][$storeId])) {
                continue;
            }
            $store = $pool['stores'][$storeId];
            if (!StoreSchedule::isOpen($store['schedule'])) {
                continue;
            }
            $card = $store['card'];
            $card['is_open'] = true;
            $product['store'] = $card;
            $products[] = $product;
        }

        shuffle($products);

        return array_slice($products, 0, max(0, $limit));
    }

    /**
     * A small card of a full feed product (view=cards).
     *
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public static function card(array $product): array
    {
        $variants = is_array($product['variants'] ?? null) ? $product['variants'] : [];
        $groups = array_filter(is_array($product['modifier_groups'] ?? null) ? $product['modifier_groups'] : [], fn ($group) => is_array($group) && !empty($group['modifiers']));
        $measured = array_filter($variants, fn ($variant) => in_array($variant['unit'] ?? 'piece', ['kg', 'l'], true));
        $prices = array_values(array_filter(array_map(fn ($variant) => (string) ($variant['price'] ?? ''), $variants), fn (string $price) => is_numeric($price) && (float) $price > 0));
        usort($prices, fn (string $a, string $b) => (float) $a <=> (float) $b);
        $needsOptions = count($variants) > 1 || $groups !== [] || $measured !== [] || !empty($product['combo']);
        $store = is_array($product['store'] ?? null) ? $product['store'] : [];
        $variant = !$needsOptions && count($variants) === 1 ? $variants[array_key_first($variants)] : null;

        return [
            'id' => $product['id'] ?? null,
            'uuid' => $product['uuid'] ?? null,
            'store_id' => $product['store_id'] ?? null,
            'category_id' => $product['category_id'] ?? null,
            'name' => $product['name'] ?? null,
            'description' => $product['description'] ?? null,
            'image_url' => $product['image_url'] ?? null,
            'image_thumb_urls' => $product['image_thumb_urls'] ?? null,
            'batch_tracked' => (bool) ($product['batch_tracked'] ?? false),
            'price' => $prices[0] ?? null,
            'needs_options' => $needsOptions,
            'variant' => $variant === null ? null : [
                'id' => $variant['id'] ?? null,
                'uuid' => $variant['uuid'] ?? null,
                'name' => $variant['name'] ?? null,
                'price' => $variant['price'] ?? null,
                'unit' => $variant['unit'] ?? 'piece',
                'stock' => $variant['stock'] ?? null,
            ],
            'store' => [
                'id' => $store['id'] ?? null,
                'uuid' => $store['uuid'] ?? null,
                'name' => $store['name'] ?? null,
                'address' => $store['address'] ?? null,
                'logo_url' => $store['logo_url'] ?? null,
            ],
        ];
    }

    /**
     * @return array<int, true> ids of the stores that serve the request's point (or area)
     */
    private function servedStores(Area $area): array
    {
        $served = [];
        foreach ($this->directory->stores($area) as $entry) {
            $served[$entry['id']] = true;
        }

        return $served;
    }

    /**
     * @return array{products: list<array<string, mixed>>, stores: array<int, array{card: array<string, mixed>, schedule: array{flag: bool, open: string|null, close: string|null, tz: string}}>}
     */
    private function pool(Area $area, ?int $categoryId, OrderChannel $channel): array
    {
        $family = 'mkt:feed:'.$area->key.':'.($categoryId ?? 'all').':'.$channel->value;

        return $this->cache->remember($family, $this->revisions->directory().':'.$this->revisions->global(), 'feed', fn () => $this->buildPool($area, $categoryId, $channel));
    }

    /**
     * The old feed query on the area's candidate stores, without the clock (open now is checked per request).
     *
     * @return array{products: list<array<string, mixed>>, stores: array<int, array{card: array<string, mixed>, schedule: array{flag: bool, open: string|null, close: string|null, tz: string}}>}
     */
    private function buildPool(Area $area, ?int $categoryId, OrderChannel $channel): array
    {
        $storeIds = $this->directory->candidateIds($area);
        if ($storeIds === []) {
            return ['products' => [], 'stores' => []];
        }

        $query = Product::with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->where('status', 'active')
            ->whereIn('store_id', $storeIds)
            ->whereHas('store', fn ($sq) => $sq->where('is_active', true)
                ->where('status', '!=', \App\Enums\StoreStatus::PAYMENT_REQUIRED->value)
                ->where('is_open', true));

        if ($categoryId !== null) {
            $childIds = Category::where('parent_id', $categoryId)->pluck('id')->toArray();
            $query->whereIn('category_id', array_merge([$categoryId], $childIds));
        }

        $products = $query->inRandomOrder()->limit(max(1, (int) config('marketplace.feed_pool', 200)))->get();
        $products = app(CatalogPresenter::class)->present($products, $channel);

        $stores = [];
        foreach (Store::query()->whereIn('id', $products->pluck('store_id')->unique()->values()->all())->get() as $store) {
            $stores[(int) $store->id] = ['card' => $store->toArray(), 'schedule' => StoreSchedule::of($store)];
        }

        return ['products' => array_values($products->toArray()), 'stores' => $stores];
    }
}
