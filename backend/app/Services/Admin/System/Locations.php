<?php

namespace App\Services\Admin\System;

use App\Models\Address;
use App\Models\Branch;
use App\Models\District;
use App\Models\Region;
use App\Models\StoreApplication;
use Illuminate\Support\Facades\DB;

/**
 * P4-31 Tizim → Viloyatlar va tumanlar: the regions and districts the Mini App's address form, the branches and the
 * store applications pick from (the old superadmin panel's Regions and Districts). Ordered by `sort_order`, then name.
 *
 * A region or district something points at is never deleted: branches (and through them the delivery zones), saved
 * customer addresses and store applications would lose it (their foreign keys only null it). Switch it off instead.
 * A region with districts is not deleted either: its districts go first.
 */
final class Locations
{
    public const DISTRICT = 'array{id: int, region_id: int, name: string, is_active: bool, sort_order: int, usage: array{branches: int, addresses: int}, deletable: bool}';

    public const REGION = 'array{id: int, name: string, is_active: bool, sort_order: int, districts_count: int, active_districts: int, usage: array{branches: int, addresses: int, applications: int}, deletable: bool, districts: list<' . self::DISTRICT . '>}';

    /**
     * Every region with its districts and what uses them (five grouped counts, no per-row queries).
     *
     * @return list<array<string, mixed>>
     */
    public function regions(): array
    {
        $regionBranches = $this->countBy(Branch::query(), 'region_id');
        $regionAddresses = $this->countBy(Address::query(), 'region_id');
        $regionApplications = $this->countBy(StoreApplication::query(), 'region_id');
        $districtBranches = $this->countBy(Branch::query(), 'district_id');
        $districtAddresses = $this->countBy(Address::query(), 'district_id');

        $districts = District::query()->orderBy('sort_order')->orderBy('name_uz')->orderBy('id')->get()->groupBy('region_id');

        return Region::query()->orderBy('sort_order')->orderBy('name_uz')->orderBy('id')->get()
            ->map(function (Region $region) use ($districts, $regionBranches, $regionAddresses, $regionApplications, $districtBranches, $districtAddresses) {
                $own = $districts->get($region->id, collect())
                    ->map(fn (District $district) => $this->district($district, $districtBranches[$district->id] ?? 0, $districtAddresses[$district->id] ?? 0))
                    ->values()->all();

                return $this->region($region, $own, [
                    'branches' => $regionBranches[$region->id] ?? 0,
                    'addresses' => $regionAddresses[$region->id] ?? 0,
                    'applications' => $regionApplications[$region->id] ?? 0,
                ]);
            })->values()->all();
    }

    /**
     * One region as in the list.
     *
     * @return array<string, mixed>
     */
    public function regionRow(Region $region): array
    {
        $districts = District::query()->where('region_id', $region->id)->orderBy('sort_order')->orderBy('name_uz')->orderBy('id')->get()
            ->map(fn (District $district) => $this->districtRow($district))
            ->values()->all();

        return $this->region($region, $districts, $this->regionUsage($region));
    }

    /**
     * @return array<string, mixed>
     */
    public function districtRow(District $district): array
    {
        $usage = $this->districtUsage($district);

        return $this->district($district, $usage['branches'], $usage['addresses']);
    }

    public function createRegion(string $name, bool $active): Region
    {
        $this->assertRegionNameFree($name, null);

        $region = new Region();
        $region->forceFill(['name_uz' => $name, 'is_active' => $active, 'sort_order' => (int) Region::query()->max('sort_order') + 1])->save();

        return $region;
    }

    /**
     * @param  array{name?: string, is_active?: bool}  $change
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public function updateRegion(Region $region, array $change): array
    {
        $before = ['name' => (string) $region->name_uz, 'is_active' => (bool) $region->is_active];
        $after = [
            'name' => array_key_exists('name', $change) ? $change['name'] : $before['name'],
            'is_active' => array_key_exists('is_active', $change) ? (bool) $change['is_active'] : $before['is_active'],
        ];
        if ($after['name'] !== $before['name']) {
            $this->assertRegionNameFree($after['name'], (int) $region->id);
        }
        if ($after !== $before) {
            $region->forceFill(['name_uz' => $after['name'], 'is_active' => $after['is_active']])->save();
        }

        return ['before' => $before, 'after' => $after];
    }

    public function deleteRegion(Region $region): void
    {
        $usage = $this->regionUsage($region);
        $districts = District::query()->where('region_id', $region->id)->count();
        if ($districts > 0) {
            throw new SystemAdminException("Viloyatda {$districts} ta tuman bor: avval ularni o'chiring yoki viloyatni o'chirib qo'ying (faol emas).", 'has_districts', 422, ['districts' => $districts]);
        }
        if (array_sum($usage) > 0) {
            throw new SystemAdminException("Viloyat ishlatilmoqda ({$this->usageText($usage)}): o'chirib bo'lmaydi, faol emas qilib qo'ying.", 'in_use', 422, ['usage' => $usage]);
        }

        $region->delete();
    }

    public function createDistrict(Region $region, string $name, bool $active): District
    {
        $this->assertDistrictNameFree((int) $region->id, $name, null);

        $district = new District();
        $district->forceFill([
            'region_id' => $region->id,
            'name_uz' => $name,
            'is_active' => $active,
            'sort_order' => (int) District::query()->where('region_id', $region->id)->max('sort_order') + 1,
        ])->save();

        return $district;
    }

    /**
     * @param  array{name?: string, is_active?: bool, region_id?: int}  $change
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public function updateDistrict(District $district, array $change): array
    {
        $before = ['name' => (string) $district->name_uz, 'is_active' => (bool) $district->is_active, 'region_id' => (int) $district->region_id];
        $after = [
            'name' => array_key_exists('name', $change) ? $change['name'] : $before['name'],
            'is_active' => array_key_exists('is_active', $change) ? (bool) $change['is_active'] : $before['is_active'],
            'region_id' => array_key_exists('region_id', $change) ? (int) $change['region_id'] : $before['region_id'],
        ];

        if ($after['region_id'] !== $before['region_id']) {
            // Branches and addresses keep their region next to the district: moving one they use would split them
            if (array_sum($this->districtUsage($district)) > 0) {
                throw new SystemAdminException("Tuman ishlatilmoqda: uni boshqa viloyatga ko'chirib bo'lmaydi.", 'in_use', 422, ['usage' => $this->districtUsage($district)]);
            }
            if (!Region::query()->whereKey($after['region_id'])->exists()) {
                throw new SystemAdminException('Viloyat topilmadi.', 'region_not_found');
            }
        }
        if ($after['name'] !== $before['name'] || $after['region_id'] !== $before['region_id']) {
            $this->assertDistrictNameFree($after['region_id'], $after['name'], (int) $district->id);
        }

        if ($after !== $before) {
            $attributes = ['name_uz' => $after['name'], 'is_active' => $after['is_active'], 'region_id' => $after['region_id']];
            if ($after['region_id'] !== $before['region_id']) {
                $attributes['sort_order'] = (int) District::query()->where('region_id', $after['region_id'])->max('sort_order') + 1;
            }
            $district->forceFill($attributes)->save();
        }

        return ['before' => $before, 'after' => $after];
    }

    public function deleteDistrict(District $district): void
    {
        $usage = $this->districtUsage($district);
        if (array_sum($usage) > 0) {
            throw new SystemAdminException("Tuman ishlatilmoqda ({$this->usageText($usage)}): o'chirib bo'lmaydi, faol emas qilib qo'ying.", 'in_use', 422, ['usage' => $usage]);
        }

        $district->delete();
    }

    /**
     * Puts the regions in the given order. `ids` must be every region exactly once.
     *
     * @param  list<int>  $ids
     * @return array{before: list<int>, after: list<int>}
     */
    public function orderRegions(array $ids): array
    {
        $before = Region::query()->orderBy('sort_order')->orderBy('name_uz')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        return ['before' => $before, 'after' => $this->applyOrder(Region::class, $before, $ids)];
    }

    /**
     * Puts one region's districts in the given order. `ids` must be every district of the region exactly once.
     *
     * @param  list<int>  $ids
     * @return array{before: list<int>, after: list<int>}
     */
    public function orderDistricts(Region $region, array $ids): array
    {
        $before = District::query()->where('region_id', $region->id)->orderBy('sort_order')->orderBy('name_uz')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        return ['before' => $before, 'after' => $this->applyOrder(District::class, $before, $ids)];
    }

    /**
     * @param  class-string<Region|District>  $model
     * @param  list<int>  $current
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function applyOrder(string $model, array $current, array $ids): array
    {
        $sortedCurrent = $current;
        $sortedIds = $ids;
        sort($sortedCurrent);
        sort($sortedIds);
        if ($sortedCurrent !== $sortedIds) {
            throw new SystemAdminException("Tartib to'liq ro'yxat bo'lishi kerak: har biri bir marta.", 'order_mismatch');
        }

        if ($ids !== $current) {
            DB::transaction(function () use ($model, $ids) {
                foreach ($ids as $position => $id) {
                    $model::query()->whereKey($id)->update(['sort_order' => $position + 1]);
                }
            });
        }

        return $ids;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TModel>  $query
     * @return array<int, int>
     */
    private function countBy(\Illuminate\Database\Eloquent\Builder $query, string $column): array
    {
        return $query->whereNotNull($column)->groupBy($column)->selectRaw("{$column} as ref, count(*) as total")->pluck('total', 'ref')
            ->mapWithKeys(fn ($total, $ref) => [(int) $ref => (int) $total])->all();
    }

    /**
     * @return array{branches: int, addresses: int, applications: int}
     */
    private function regionUsage(Region $region): array
    {
        return [
            'branches' => Branch::query()->where('region_id', $region->id)->count(),
            'addresses' => Address::query()->where('region_id', $region->id)->count(),
            'applications' => StoreApplication::query()->where('region_id', $region->id)->count(),
        ];
    }

    /**
     * @return array{branches: int, addresses: int}
     */
    private function districtUsage(District $district): array
    {
        return [
            'branches' => Branch::query()->where('district_id', $district->id)->count(),
            'addresses' => Address::query()->where('district_id', $district->id)->count(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $districts
     * @param  array{branches: int, addresses: int, applications: int}  $usage
     * @return array<string, mixed>
     */
    private function region(Region $region, array $districts, array $usage): array
    {
        return [
            'id' => (int) $region->id,
            'name' => (string) $region->name_uz,
            'is_active' => (bool) $region->is_active,
            'sort_order' => (int) $region->getAttribute('sort_order'),
            'districts_count' => count($districts),
            'active_districts' => count(array_filter($districts, fn (array $district) => $district['is_active'])),
            'usage' => $usage,
            'deletable' => $districts === [] && array_sum($usage) === 0,
            'districts' => $districts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function district(District $district, int $branches, int $addresses): array
    {
        return [
            'id' => (int) $district->id,
            'region_id' => (int) $district->region_id,
            'name' => (string) $district->name_uz,
            'is_active' => (bool) $district->is_active,
            'sort_order' => (int) $district->getAttribute('sort_order'),
            'usage' => ['branches' => $branches, 'addresses' => $addresses],
            'deletable' => $branches + $addresses === 0,
        ];
    }

    /**
     * @param  array<string, int>  $usage
     */
    private function usageText(array $usage): string
    {
        $words = ['branches' => 'filial', 'addresses' => 'manzil', 'applications' => 'ariza'];
        $parts = [];
        foreach ($usage as $key => $count) {
            if ($count > 0) {
                $parts[] = "{$count} ta {$words[$key]}";
            }
        }

        return implode(', ', $parts);
    }

    private function assertRegionNameFree(string $name, ?int $except): void
    {
        $taken = Region::query()->whereRaw('lower(name_uz) = ?', [mb_strtolower($name)])->when($except !== null, fn ($query) => $query->whereKeyNot($except))->exists();
        if ($taken) {
            throw new SystemAdminException('Bunday nomli viloyat bor.', 'name_taken', 422, ['field' => 'name']);
        }
    }

    private function assertDistrictNameFree(int $regionId, string $name, ?int $except): void
    {
        $taken = District::query()->where('region_id', $regionId)->whereRaw('lower(name_uz) = ?', [mb_strtolower($name)])
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except))->exists();
        if ($taken) {
            throw new SystemAdminException('Bu viloyatda bunday nomli tuman bor.', 'name_taken', 422, ['field' => 'name']);
        }
    }
}
