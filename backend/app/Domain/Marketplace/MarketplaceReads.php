<?php

namespace App\Domain\Marketplace;

use App\Enums\OrderChannel;
use App\Models\User;
use App\Support\RowId;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The customer browse reads answered from the read model (wave M2). The controllers keep the request parsing they
 * had and call these when config('marketplace.read_model') is on; the JSON is the old one, plus the additive fields
 * documented in docs/API.md (`accepting_orders`, `meta` of a paged store list, view=cards).
 */
final class MarketplaceReads
{
    public function __construct(
        private readonly StoreDirectory $directory,
        private readonly StoreMenu $menus,
        private readonly CatalogFeed $feed,
        private readonly StoreCards $cards,
    ) {}

    /**
     * The request's location: its own parameters, else the user's default address (the old fallback).
     */
    public function areaOf(Request $request, ?User $user): Area
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');
        $regionId = $request->query('region_id');
        $districtId = $request->query('district_id');

        if (!$lat && !$lng && !$regionId && !$districtId && $user) {
            $address = $user->addresses()->where('is_default', true)->first() ?: $user->addresses()->first();
            if ($address) {
                $lat = $address->latitude;
                $lng = $address->longitude;
                $regionId = $address->region_id;
                $districtId = $address->district_id;
            }
        }

        return $this->directory->areaOf($lat, $lng, $regionId, $districtId);
    }

    /**
     * GET /stores: the old list (every store, `meta` null), or one page of it when `page` or `per_page` is sent.
     *
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int, last_page: int}|null}
     */
    public function stores(Request $request, ?User $user): array
    {
        $area = $this->areaOf($request, $user);
        $entries = $this->directory->stores($area);
        $cashBanned = StoreCards::cashBanned($user);

        $paged = $request->has('page') || $request->has('per_page');
        $meta = null;
        if ($paged) {
            $max = max(1, (int) config('marketplace.page_size_max', 50));
            $perPage = (int) $request->query('per_page', (string) config('marketplace.page_size', 20));
            $perPage = min($max, max(1, $perPage));
            $page = max(1, (int) $request->query('page', '1'));
            $total = count($entries);
            $entries = array_slice($entries, ($page - 1) * $perPage, $perPage);
            $meta = ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $perPage))];
        }

        MarketplaceEtag::forStores($request, array_map(fn (array $entry) => $entry['id'], $entries), $user);

        $data = [];
        foreach ($entries as $entry) {
            $card = $this->cards->present($entry['card'], $entry['schedule'], $entry['offline_minutes'], $cashBanned);
            if (array_key_exists('min_distance', $entry)) {
                $card['min_distance'] = $entry['min_distance'];
            }
            $data[] = $card;
        }

        return ['data' => $data, 'meta' => $meta];
    }

    /**
     * GET /catalog without store_id.
     *
     * @return array{categories: list<array<string, mixed>>, products: list<array<string, mixed>>, promos: list<array<string, mixed>>, highlight_product: array<string, mixed>|null}
     */
    public function feed(Request $request, ?User $user, OrderChannel $channel): array
    {
        $area = $this->areaOf($request, $user);
        $category = $request->query('category_id');
        $categoryId = is_string($category) && $category !== '' && ctype_digit($category) ? (RowId::parse($category) ?? 0) : ($category ? 0 : null);

        $cards = $request->query('view') === 'cards';
        $limit = $cards
            ? min((int) config('marketplace.feed_limit', 50), max(1, (int) $request->query('limit', '20')))
            : (int) config('marketplace.feed_limit', 50);

        $products = $categoryId === 0 ? [] : $this->feed->products($area, $categoryId, $channel, $limit, $cards);
        MarketplaceEtag::forStores($request, array_map(fn (array $product) => (int) $product['store_id'], $products), $user);
        if ($cards) {
            $products = array_map(fn (array $product) => CatalogFeed::card($product), $products);
        }

        return [
            'categories' => $this->feed->categories(),
            'products' => $products,
            'promos' => $this->feed->promos(),
            'highlight_product' => $products[0] ?? null,
        ];
    }

    /**
     * Whether GET /catalog?store_id= may show the store (the old 403 otherwise: unknown, inactive, payment required).
     */
    public function storeAvailable(?int $storeId): bool
    {
        if ($storeId === null) {
            return false;
        }
        $doc = $this->menus->store($storeId);

        return $doc['available'] && $doc['store'] !== null && $doc['schedule'] !== null;
    }

    /**
     * GET /catalog?store_id=...: the payload of an available store (storeAvailable() first).
     *
     * @param  int|null  $brandId  the resolved brand filter
     * @return array<string, mixed>
     */
    public function storeMenu(int $storeId, ?User $user, OrderChannel $channel, ?int $brandId): array
    {
        $doc = $this->menus->store($storeId);
        if ($doc['store'] === null || $doc['schedule'] === null) {
            return [];
        }

        $store = $this->cards->present($doc['store'], $doc['schedule'], $doc['offline_minutes'], StoreCards::cashBanned($user));
        $menu = $this->menus->menu($storeId, $channel, $brandId);

        $payload = [
            'store' => $store,
            'categories' => $menu['categories'],
            'products' => $menu['products'],
            'availability' => $this->menus->availability($storeId)['branches'],
        ];
        if ($menu['brands'] !== []) {
            $payload['brands'] = $menu['brands'];
        }

        return $payload;
    }

    /**
     * GET /catalog/availability without branch_id (the Mini App's form): null for an unknown or inactive store.
     *
     * @return array{branches: list<array<string, mixed>>, generated_at: string}|null
     */
    public function availability(?int $storeId): ?array
    {
        $doc = $storeId !== null ? $this->menus->store($storeId) : null;
        if ($doc === null || !$doc['found'] || !$doc['active']) {
            return null;
        }

        return $this->menus->availability((int) $storeId);
    }

    /**
     * A store_id query value: a uuid (resolved through the cache) or a numeric id.
     *
     * @throws ValidationException
     */
    public function resolveStoreId(string $key, bool $numeric): ?int
    {
        if ($numeric) {
            return RowId::parse($key) ?? throw ValidationException::withMessages(['store_id' => "store_id musbat butun son bo'lishi kerak."]);
        }

        return $this->menus->idOfUuid($key);
    }
}
