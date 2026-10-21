<?php

namespace App\Domain\Pos\Setup;

use App\Models\Branch;
use App\Models\Device;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\User;

/**
 * The `halls` and `tables` of GET /pos/bootstrap (P3-12's models): the branch's open halls and zones and the places on
 * their floor maps (tables, rooms, tapchans). A seasonal zone that is switched off and its places stay out. Which
 * rooms are busy comes from the order endpoints, not from here.
 */
final class HallsBootstrapSection implements PosBootstrapContributor
{
    /**
     * @return array{halls: list<array<string, mixed>>, tables: list<array<string, mixed>>}
     */
    public function contribute(User $user, Branch $branch, ?Device $device): array
    {
        $halls = Hall::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $tables = DiningTable::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->whereIn('hall_id', $halls->pluck('id'))
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        return [
            'halls' => $halls->map(fn (Hall $hall) => [
                'id' => (int) $hall->id,
                'name' => (string) $hall->name,
                'sort_order' => (int) $hall->sort_order,
            ])->values()->all(),
            'tables' => $tables->map(fn (DiningTable $table) => [
                'id' => (int) $table->id,
                'hall_id' => (int) $table->hall_id,
                'name' => (string) $table->name,
                'kind' => $table->kind->value,
                'seats' => $table->seats !== null ? (int) $table->seats : null,
                'layout' => [
                    'x' => $table->layout_x,
                    'y' => $table->layout_y,
                    'w' => $table->layout_w,
                    'h' => $table->layout_h,
                ],
                'sort_order' => (int) $table->sort_order,
                'min_spend' => $table->min_spend,
                'min_spend_mode' => $table->min_spend_mode->value,
            ])->values()->all(),
        ];
    }
}
