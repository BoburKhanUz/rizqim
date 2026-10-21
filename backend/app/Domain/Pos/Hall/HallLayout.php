<?php

namespace App\Domain\Pos\Hall;

use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\ServiceFeeSetting;
use Illuminate\Support\Collection;

/**
 * Halls and their places as the web panel (and anyone else, e.g. the POS bootstrap) shows them (P3-12).
 */
class HallLayout
{
    /**
     * @param  Collection<int, Hall>  $halls
     * @return list<array<string, mixed>>
     */
    public function halls(Collection $halls): array
    {
        $ids = $halls->pluck('id')->all();
        $tables = DiningTable::whereIn('hall_id', $ids)->orderBy('sort_order')->orderBy('id')->get()->groupBy('hall_id');
        $fees = ServiceFeeSetting::whereIn('hall_id', $ids)->get()->keyBy('hall_id');

        return $halls->values()->map(function (Hall $hall) use ($tables, $fees) {
            /** @var ServiceFeeSetting|null $fee */
            $fee = $fees->get($hall->id);

            return [
                'id' => (int) $hall->id,
                'branch_id' => (int) $hall->branch_id,
                'name' => $hall->name,
                'sort_order' => $hall->sort_order,
                'is_active' => $hall->is_active,
                'service_fee' => $fee !== null ? [
                    'service_charge_percent' => (string) $fee->service_charge_percent,
                    'place_fee_amount' => (string) $fee->place_fee_amount,
                    'place_fee_mode' => $fee->place_fee_mode->value,
                ] : null,
                'tables' => $tables->get($hall->id, collect())->map(fn (DiningTable $table) => $this->table($table))->values()->all(),
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function table(DiningTable $table): array
    {
        return [
            'id' => (int) $table->id,
            'branch_id' => (int) $table->branch_id,
            'hall_id' => (int) $table->hall_id,
            'name' => $table->name,
            'kind' => $table->kind->value,
            'seats' => $table->seats,
            'layout' => ['x' => $table->layout_x, 'y' => $table->layout_y, 'w' => $table->layout_w, 'h' => $table->layout_h],
            'sort_order' => $table->sort_order,
            'is_active' => $table->is_active,
            'min_spend' => $table->min_spend,
            'min_spend_mode' => $table->min_spend_mode->value,
        ];
    }
}
