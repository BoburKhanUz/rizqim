<?php

namespace App\Domain\Marketplace;

use App\Enums\StoreStatus;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;

/**
 * The store cards of an area, as GET /stores lists them, from one cached document per area.
 *
 * An area is a geo cell (a request with coordinates), a district, a region, or "everywhere" (no location at all).
 * The document holds each store's card exactly as the old query serialized it (the same columns and branches) plus
 * what the read needs: the raw schedule (for "open now"), the branch circles (for the exact radius check and the
 * distance) and the reachability limit. It is keyed by the directory and global revisions, so a store, branch or
 * payment-switch change is seen by the next request; without a hook it is rebuilt after `ttl.area` seconds.
 *
 * Review M2 R1-1/R1-4: each entry also records its store's revision (ChangeRevision::store) as read BEFORE the entry
 * was queried. Every read (snapshot()) compares them with the current tokens (one MGET) and re-queries the entries
 * whose store moved, so what only moves a store token (the debt lock flipping, a review) is on the next list, not
 * after `ttl.area`; and the answer's ETag names exactly those recorded tokens, never ones newer than its body.
 */
final class StoreDirectory
{
    /** The columns of the old StoreController::index select (its JSON must not change) */
    public const CARD_COLUMNS = [
        'id', 'uuid', 'name', 'phone', 'address', 'logo', 'banner', 'description', 'is_open', 'is_premium',
        'review_count', 'payment_settings', 'status', 'debt_balance', 'subscription_debt', 'credit_limit', 'open_time',
        'close_time', 'min_order_amount',
    ];

    public const BRANCH_COLUMNS = ['id', 'store_id', 'name', 'latitude', 'longitude', 'radius_km', 'address', 'phone'];

    public function __construct(
        private readonly ReadModelCache $cache,
        private readonly MarketplaceRevision $revisions,
    ) {}

    public static function enabled(): bool
    {
        return (bool) config('marketplace.read_model', true);
    }

    /**
     * The area of a request's location parameters (the old queries' precedence: coordinates, district, region).
     */
    public function areaOf(mixed $lat, mixed $lng, mixed $regionId, mixed $districtId): Area
    {
        if ($lat && $lng && is_numeric($lat) && is_numeric($lng)) {
            $grid = GeoGrid::make();

            return new Area($grid->cellOf((float) $lat, (float) $lng), (float) $lat, (float) $lng);
        }
        if ($districtId) {
            return new Area('d:'.(int) $districtId);
        }
        if ($regionId) {
            return new Area('r:'.(int) $regionId);
        }

        return new Area('all');
    }

    /**
     * The area's entries, each checked against its store's current revision (stale ones re-queried), with the
     * revision each entry is valid for: id => token, read before the entry's data.
     *
     * @return array{entries: list<array{id: int, card: array<string, mixed>, schedule: array{flag: bool, open: string|null, close: string|null, tz: string}, circles: list<array{0: float, 1: float, 2: float|null}>, offline_minutes: int|null}>, tokens: array<int, string>}
     */
    public function snapshot(Area $area): array
    {
        $version = $this->version();
        $doc = $this->cache->remember(self::family($area), $version, 'area', fn () => $this->build($area));

        $ids = array_map(fn (array $entry) => $entry['id'], $doc['entries']);
        $current = $this->revisions->stores($ids);
        $stale = [];
        foreach ($ids as $id) {
            if (($doc['tokens'][$id] ?? '') !== ($current[$id] ?? null)) {
                $stale[] = $id;
            }
        }
        if ($stale === []) {
            return $doc;
        }

        // Re-query the moved stores ($current was read before this query), keep the rest, and store the result so the
        // next request does not repeat it (a newer patch may overwrite it: each entry still names its own token)
        $fresh = [];
        foreach ($this->query($area)->whereIn('id', $stale)->get() as $store) {
            $fresh[(int) $store->id] = self::entry($store);
        }
        $entries = [];
        $tokens = [];
        foreach ($doc['entries'] as $entry) {
            $id = $entry['id'];
            if (in_array($id, $stale, true)) {
                if (!isset($fresh[$id])) {
                    continue; // no longer listed (inactive, payment required, moved away)
                }
                $entry = $fresh[$id];
                $tokens[$id] = (string) $current[$id];
            } else {
                $tokens[$id] = $doc['tokens'][$id];
            }
            $entries[] = $entry;
        }
        $doc = ['entries' => $entries, 'tokens' => $tokens];
        $this->cache->replace(self::family($area), $version, 'area', $doc);

        return $doc;
    }

    /**
     * The area's stores that serve the point (with coordinates: a branch within its radius), nearest first with
     * `min_distance`; otherwise in id order. Each entry: card (the old JSON), schedule, circles, offline_minutes.
     *
     * @param  array{entries: list<array<string, mixed>>, tokens: array<int, string>}|null  $snapshot  snapshot() of this request
     * @return list<array{id: int, card: array<string, mixed>, schedule: array{flag: bool, open: string|null, close: string|null, tz: string}, circles: list<array{0: float, 1: float, 2: float|null}>, offline_minutes: int|null, min_distance?: float}>
     */
    public function stores(Area $area, ?array $snapshot = null): array
    {
        $entries = ($snapshot ?? $this->snapshot($area))['entries'];

        if (!$area->hasPoint()) {
            return $entries;
        }

        $served = [];
        foreach ($entries as $entry) {
            $min = null;
            $reaches = false;
            foreach ($entry['circles'] as [$lat, $lng, $radius]) {
                $distance = GeoGrid::distanceKm((float) $area->lat, (float) $area->lng, $lat, $lng);
                $min = $min === null ? $distance : min($min, $distance);
                if ($radius !== null && $distance <= $radius) {
                    $reaches = true;
                }
            }
            if ($reaches) {
                $entry['min_distance'] = (float) $min;
                $served[] = $entry;
            }
        }

        usort($served, fn (array $a, array $b) => [$a['min_distance'], $a['id']] <=> [$b['min_distance'], $b['id']]);

        return $served;
    }

    /**
     * The ids of the stores the area document lists (with coordinates: every store that may reach the cell).
     *
     * @param  array{entries: list<array<string, mixed>>, tokens: array<int, string>}|null  $snapshot  snapshot() of this request
     * @return list<int>
     */
    public function candidateIds(Area $area, ?array $snapshot = null): array
    {
        return array_map(fn (array $entry) => $entry['id'], ($snapshot ?? $this->snapshot($area))['entries']);
    }

    private static function family(Area $area): string
    {
        return 'mkt:area:'.$area->key;
    }

    private function version(): string
    {
        return $this->revisions->directory().':'.$this->revisions->global();
    }

    /**
     * The listed stores, each with its store revision read before its row (a store that appeared between the two
     * reads gets no token: the next read re-queries it).
     *
     * @return array{entries: list<array{id: int, card: array<string, mixed>, schedule: array{flag: bool, open: string|null, close: string|null, tz: string}, circles: list<array{0: float, 1: float, 2: float|null}>, offline_minutes: int|null}>, tokens: array<int, string>}
     */
    private function build(Area $area): array
    {
        $ids = array_map('intval', $this->query($area)->reorder()->pluck('id')->all());
        $before = $this->revisions->stores($ids);

        $entries = [];
        $tokens = [];
        foreach ($this->query($area)->get() as $store) {
            $entry = self::entry($store);
            $entries[] = $entry;
            $tokens[$entry['id']] = (string) ($before[$entry['id']] ?? '');
        }

        return ['entries' => $entries, 'tokens' => $tokens];
    }

    /**
     * The old StoreController::index query of the area's listed stores.
     *
     * @return Builder<Store>
     */
    private function query(Area $area): Builder
    {
        $query = Store::query()
            ->select([...self::CARD_COLUMNS, 'marketplace_offline_minutes'])
            ->where('is_active', true)
            ->where('status', '!=', StoreStatus::PAYMENT_REQUIRED->value)
            ->with(['branches' => fn ($q) => $q->select(self::BRANCH_COLUMNS)->orderBy('id')])
            ->orderBy('id');

        $this->whereArea($query, $area);

        return $query;
    }

    /**
     * @return array{id: int, card: array<string, mixed>, schedule: array{flag: bool, open: string|null, close: string|null, tz: string}, circles: list<array{0: float, 1: float, 2: float|null}>, offline_minutes: int|null}
     */
    private static function entry(Store $store): array
    {
        $circles = [];
        foreach ($store->branches as $branch) {
            if ($branch->latitude === null || $branch->longitude === null) {
                continue;
            }
            $radius = $branch->getRawOriginal('radius_km');
            $circles[] = [(float) $branch->latitude, (float) $branch->longitude, $radius !== null ? (float) $radius : null];
        }

        $offline = $store->getAttribute('marketplace_offline_minutes');
        $card = $store->toArray();
        unset($card['marketplace_offline_minutes']);

        return [
            'id' => (int) $store->id,
            'card' => $card,
            'schedule' => StoreSchedule::of($store),
            'circles' => $circles,
            'offline_minutes' => $offline !== null ? (int) $offline : null,
        ];
    }

    /**
     * @param  Builder<Store>  $query
     */
    public function whereArea(Builder $query, Area $area): void
    {
        if ($area->isCell()) {
            $geometry = GeoGrid::make()->geometry($area->key);
            $query->whereHas('branches', function ($q) use ($geometry) {
                $q->whereNotNull('latitude')->whereNotNull('longitude')->whereRaw(
                    '(6371 * acos(least(1, greatest(-1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))) <= radius_km + ?',
                    [$geometry['lat'], $geometry['lng'], $geometry['lat'], $geometry['half_diagonal_km']],
                );
            });
        } elseif (str_starts_with($area->key, 'd:')) {
            $query->whereHas('branches', fn ($q) => $q->where('district_id', (int) substr($area->key, 2)));
        } elseif (str_starts_with($area->key, 'r:')) {
            $query->whereHas('branches', fn ($q) => $q->where('region_id', (int) substr($area->key, 2)));
        }
    }
}
