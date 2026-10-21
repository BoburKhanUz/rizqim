<?php

namespace App\Domain\Pos\Booking;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The branch's rooms and tables as bookings see them (dining_tables and halls belong to P3-12; read as rows so the
 * booking code does not depend on that task's models).
 */
class DiningTables
{
    /**
     * Live (not deleted) tables of the branch in floor order, keyed by id; only `$ids` when given.
     *
     * @param  list<int>|null  $ids
     * @return Collection<int, object{id: int, name: string, kind: string, seats: int|null, hall_id: int, hall_name: string|null, is_active: bool, min_spend: string|null}>
     */
    public function ofBranch(int $branchId, ?array $ids = null): Collection
    {
        /** @var Collection<int, object{id: int, name: string, kind: string, seats: int|null, hall_id: int, hall_name: string|null, is_active: bool, min_spend: string|null}> $rows */
        $rows = DB::table('dining_tables')
            ->leftJoin('halls', 'halls.id', '=', 'dining_tables.hall_id')
            ->where('dining_tables.branch_id', $branchId)
            ->whereNull('dining_tables.deleted_at')
            ->when($ids !== null, fn ($query) => $query->whereIn('dining_tables.id', $ids === [] ? [0] : $ids))
            ->orderByRaw('COALESCE(halls.sort_order, 0)')
            ->orderBy('dining_tables.hall_id')
            ->orderBy('dining_tables.sort_order')
            ->orderBy('dining_tables.id')
            ->get([
                'dining_tables.id', 'dining_tables.name', 'dining_tables.kind', 'dining_tables.seats', 'dining_tables.hall_id',
                'halls.name as hall_name', 'dining_tables.is_active', 'dining_tables.min_spend',
            ])
            ->map(function (object $row) {
                $row->id = (int) $row->id;
                $row->hall_id = (int) $row->hall_id;
                $row->seats = $row->seats !== null ? (int) $row->seats : null;
                $row->is_active = (bool) $row->is_active;

                return $row;
            })
            ->keyBy('id');

        return $rows;
    }
}
