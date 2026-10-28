<?php

namespace App\Services\Admin\System;

use App\Features\Features;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

/**
 * P4-31 Tizim → Funksiyalar: the feature flags of App\Features\Features (Laravel Pennant, per store), as the old
 * superadmin page "Feature flaglar" switched them: on or off for one store, or the store back to the defaults (its
 * stored values forgotten). The flags themselves and their defaults live in code.
 *
 * Reads look at Pennant's `features` table directly, so listing never stores a value (Pennant remembers the default
 * the first time a store is checked; such a row is shown as `stored`, the same as a switch).
 */
final class FeatureFlagAdmin
{
    public const FLAG = 'array{name: string, label: string, description: string, default: bool, stores_on: int, stores_off: int, stores_total: int}';

    public const STORE_ROW = 'array{store: array{id: int, name: string, status: string|null}, flags: array<string, array{active: bool, stored: bool}>}';

    /**
     * @return list<array<string, mixed>>
     */
    public function flags(): array
    {
        $total = Store::query()->count();
        $prefix = $this->scopePrefix();
        $stored = DB::table('features')->whereIn('name', Features::names())->where('scope', 'like', $prefix . '%')
            ->groupBy('name', 'value')->selectRaw('name, value, count(*) as total')->get();

        $rows = [];
        $defaults = Features::defaults();
        foreach (Features::FLAGS as $name => $definition) {
            $default = $defaults[$name] ?? false;
            $on = (int) $stored->where('name', $name)->filter(fn ($row) => $this->decode($row->value))->sum('total');
            $off = (int) $stored->where('name', $name)->reject(fn ($row) => $this->decode($row->value))->sum('total');
            $unstored = max(0, $total - $on - $off);
            $rows[] = [
                'name' => $name,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'default' => $default,
                'stores_on' => min($total, $on + ($default ? $unstored : 0)),
                'stores_off' => min($total, $off + ($default ? 0 : $unstored)),
                'stores_total' => $total,
            ];
        }

        return $rows;
    }

    public function exists(string $flag): bool
    {
        return array_key_exists($flag, Features::FLAGS);
    }

    /**
     * Stores (by name, `q`) with every flag's value for them.
     *
     * @return LengthAwarePaginator<int, Store>
     */
    public function storePage(?string $q, int $perPage): LengthAwarePaginator
    {
        return Store::query()
            ->when(filled($q), fn ($query) => $query->where('name', 'ilike', '%' . str_replace(['%', '_'], ['\%', '\_'], (string) $q) . '%'))
            ->orderBy('name')->orderBy('id')
            ->paginate($perPage, ['id', 'name', 'status']);
    }

    /**
     * @param  iterable<Store>  $stores
     * @return list<array<string, mixed>>
     */
    public function storeRows(iterable $stores): array
    {
        $stores = collect($stores);
        $scopes = $stores->mapWithKeys(fn (Store $store) => [(int) $store->id => Feature::serializeScope($store)]);
        $stored = DB::table('features')->whereIn('name', Features::names())->whereIn('scope', $scopes->values()->all())->get(['name', 'scope', 'value']);

        return $stores->map(function (Store $store) use ($scopes, $stored) {
            $flags = [];
            foreach (Features::FLAGS as $name => $definition) {
                $row = $stored->first(fn ($row) => $row->name === $name && $row->scope === $scopes[(int) $store->id]);
                $flags[$name] = ['active' => $row !== null ? $this->decode($row->value) : $definition['default'], 'stored' => $row !== null];
            }

            return [
                'store' => ['id' => (int) $store->id, 'name' => (string) $store->name, 'status' => $store->status->value],
                'flags' => $flags,
            ];
        })->values()->all();
    }

    /**
     * Switches one flag for one store. Answers its value before.
     */
    public function set(Store $store, string $flag, bool $active): bool
    {
        $before = $this->valueFor($store, $flag);
        $active ? Feature::for($store)->activate($flag) : Feature::for($store)->deactivate($flag);

        return $before;
    }

    /**
     * Forgets every stored flag value of the store (it follows the defaults again). Answers the flags that had one.
     *
     * @return list<string>
     */
    public function reset(Store $store): array
    {
        $had = DB::table('features')->whereIn('name', Features::names())->where('scope', Feature::serializeScope($store))->pluck('name')->map(fn ($name) => (string) $name)->sort()->values()->all();
        if ($had !== []) {
            Feature::for($store)->forget(Features::names());
        }

        return $had;
    }

    public function valueFor(Store $store, string $flag): bool
    {
        $value = DB::table('features')->where('name', $flag)->where('scope', Feature::serializeScope($store))->value('value');

        return $value !== null ? $this->decode($value) : (bool) (Features::FLAGS[$flag]['default'] ?? false);
    }

    private function decode(mixed $value): bool
    {
        return json_decode((string) $value, true) === true;
    }

    private function scopePrefix(): string
    {
        // "App\Models\Store|12" without the id (backslashes are LIKE's escape character in PostgreSQL)
        return str_replace('\\', '\\\\', Store::class) . '|';
    }
}
