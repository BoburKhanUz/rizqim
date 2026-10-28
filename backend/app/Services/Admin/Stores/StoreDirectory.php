<?php

namespace App\Services\Admin\Stores;

use App\Enums\StoreHealthLevel;
use App\Models\District;
use App\Models\Region;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * P4-22: the Ovqatxonalar list and the store/region pickers. Filters:
 *  - q: name (any part), STIR, the store's phone or its owner's (the whole number: +998 and nine digits or the nine
 *    digits — a part of a phone would let a masked number be guessed), the uuid's start;
 *  - status (stores.status), region_id (any branch in the region), health (good | attention | churn_risk | none — not
 *    computed yet), plan (a tariff code or `none`), debt (has_debt | over_limit), next_contact (due: today or past).
 * Sort: newest (default), name, debt (largest first), health (churn risk first, then the lowest score).
 */
class StoreDirectory
{
    public const SORTS = ['newest', 'name', 'debt', 'health'];

    public function __construct(
        private readonly StorePlanReader $plans,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Store>
     */
    public function query(array $filters, bool $withHealth = true): Builder
    {
        $query = Store::query()->select('stores.*');

        if (filled($filters['q'] ?? null)) {
            $this->search($query, trim((string) $filters['q']));
        }
        if (filled($filters['status'] ?? null)) {
            $query->where('stores.status', $filters['status']);
        }
        if (filled($filters['region_id'] ?? null)) {
            $query->whereHas('branches', fn ($branches) => $branches->where('region_id', (int) $filters['region_id']));
        }
        if (filled($filters['plan'] ?? null)) {
            $this->plans->filterByPlan($query, (string) $filters['plan']);
        }
        if (($filters['debt'] ?? null) === 'has_debt') {
            $query->where('stores.debt_balance', '>', 0);
        } elseif (($filters['debt'] ?? null) === 'over_limit') {
            // Store::exceedsCreditLimit(): P4-21 counts the commission debt only (unpaid subscription invoices never block)
            $query->whereRaw('stores.debt_balance - stores.subscription_debt > abs(stores.credit_limit)');
        }
        if (($filters['next_contact'] ?? null) === 'due') {
            $query->whereIn('stores.id', DB::table('store_crm_profiles')->where('next_contact_on', '<=', now(\App\Services\Admin\Today\PlatformStats::TIMEZONE)->format('Y-m-d'))->select('store_id'));
        }
        if ($withHealth && filled($filters['health'] ?? null)) {
            $level = (string) $filters['health'];
            $level === 'none'
                ? $query->whereNotIn('stores.id', DB::table('store_health_scores')->select('store_id'))
                : $query->whereIn('stores.id', DB::table('store_health_scores')->where('level', $level)->select('store_id'));
        }

        return $query;
    }

    /**
     * @param  Builder<Store>  $query
     */
    public function sort(Builder $query, string $sort): void
    {
        match ($sort) {
            'name' => $query->orderBy('stores.name')->orderBy('stores.id'),
            'debt' => $query->orderByDesc('stores.debt_balance')->orderBy('stores.id'),
            'health' => $query
                ->leftJoin('store_health_scores as h', 'h.store_id', '=', 'stores.id')
                ->orderByRaw("case h.level when 'churn_risk' then 0 when 'attention' then 1 when 'good' then 2 else 3 end")
                ->orderBy('h.score')
                ->orderBy('stores.id'),
            default => $query->orderByDesc('stores.created_at')->orderByDesc('stores.id'),
        };
    }

    /**
     * Stores per health level under the same filters (all but health).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function healthCounts(array $filters): array
    {
        $rows = $this->query($filters, false)
            ->leftJoin('store_health_scores as hc', 'hc.store_id', '=', 'stores.id')
            ->reorder()
            ->groupBy('hc.level')
            ->select([DB::raw('hc.level as level'), DB::raw('count(*) as stores')])
            ->toBase()
            ->pluck('stores', 'level');

        $counts = ['all' => (int) $rows->sum()];
        foreach (StoreHealthLevel::values() as $level) {
            $counts[$level] = (int) ($rows[$level] ?? 0);
        }
        $counts['none'] = (int) ($rows[''] ?? 0);

        return $counts;
    }

    /**
     * Stores and regions for the pickers: up to 20 stores by name (or the given ids), every active region whose name
     * matches, and the active districts of `$districtsOf` (the convert form's branch district).
     *
     * @param  list<int>  $ids
     * @return array{stores: list<array{id: int, name: string, status: string, region: string|null}>, regions: list<array{id: int, name: string}>, districts: list<array{id: int, name: string}>}
     */
    public function lookup(?string $q, array $ids = [], ?int $districtsOf = null): array
    {
        $q = $q !== null ? trim($q) : '';
        $stores = Store::query()->with(['branches' => fn ($branches) => $branches->select(['id', 'store_id', 'region_id'])->orderBy('id'), 'branches.region:id,name_uz']);

        if ($ids !== []) {
            $stores->whereIn('id', $ids);
        } elseif ($q !== '') {
            $this->search($stores, $q);
        }

        $regions = Region::query()->where('is_active', true)
            ->when($q !== '' && $ids === [], fn ($query) => $query->where('name_uz', 'ilike', '%' . $this->escapeLike($q) . '%'))
            ->orderBy('name_uz')->get(['id', 'name_uz']);

        return [
            'stores' => $stores->orderBy('name')->orderBy('id')->limit(20)->get(['id', 'name', 'status'])
                ->map(fn (Store $store) => [
                    'id' => (int) $store->id,
                    'name' => (string) $store->name,
                    'status' => $store->status->value,
                    'region' => $store->branches->first()?->region?->name_uz,
                ])->values()->all(),
            'regions' => $regions->map(fn (Region $region) => ['id' => (int) $region->id, 'name' => (string) $region->name_uz])->values()->all(),
            'districts' => $districtsOf === null ? [] : District::query()->where('region_id', $districtsOf)->where('is_active', true)
                ->orderBy('name_uz')->get(['id', 'name_uz'])
                ->map(fn (District $district) => ['id' => (int) $district->id, 'name' => (string) $district->name_uz])->values()->all(),
        ];
    }

    /**
     * @param  Builder<Store>  $query
     */
    private function search(Builder $query, string $q): void
    {
        $digits = preg_replace('/\D+/', '', $q) ?? '';
        $phone = ApplicationPipeline::normalizePhone($q);
        $like = '%' . $this->escapeLike($q) . '%';

        $query->where(function (Builder $where) use ($q, $digits, $phone, $like) {
            $where->where('stores.name', 'ilike', $like);
            if (preg_match('/^\d{9}$/', $digits) && $digits === trim($q)) {
                $where->orWhere('stores.tin', $digits)->orWhere('stores.tax_number', $digits);
            }
            if ($phone !== null) {
                $where->orWhere('stores.phone', $phone)
                    ->orWhereIn('stores.id', DB::table('users')->where('phone', $phone)->whereIn('role', ['owner', 'manager'])->whereNotNull('store_id')->select('store_id'));
            }
            if (preg_match('/^[0-9a-f-]{8,36}$/i', $q)) {
                $where->orWhereRaw('stores.uuid::text like ?', [strtolower($q) . '%']);
            }
        });
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
