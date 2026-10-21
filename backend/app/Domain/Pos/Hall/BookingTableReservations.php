<?php

namespace App\Domain\Pos\Hall;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TableReservations from P3-17's tables (bookings and booking_tables: a banquet holds several rooms), read as rows so
 * this code does not depend on that task's models. Before those tables exist: no reservations.
 */
class BookingTableReservations implements TableReservations
{
    /** Booking statuses still expected to arrive. */
    public const EXPECTED = ['requested', 'confirmed'];

    public function upcoming(int $branchId, array $tableIds, CarbonInterface $from, CarbonInterface $until): array
    {
        if ($tableIds === [] || !Schema::hasTable('bookings')) {
            return [];
        }

        $hasLinks = Schema::hasTable('booking_tables');
        $rows = DB::table('bookings')
            ->where('bookings.branch_id', $branchId)
            ->whereIn('bookings.status', self::EXPECTED)
            ->where('bookings.starts_at', '>=', $from)
            ->where('bookings.starts_at', '<=', $until)
            ->when($hasLinks, fn ($query) => $query->leftJoin('booking_tables', 'booking_tables.booking_id', '=', 'bookings.id'))
            ->where(fn ($query) => $query->whereIn('bookings.dining_table_id', $tableIds)
                ->when($hasLinks, fn ($query) => $query->orWhereIn('booking_tables.dining_table_id', $tableIds)))
            ->orderBy('bookings.starts_at')
            ->get(array_merge(
                ['bookings.uuid', 'bookings.starts_at', 'bookings.guests_planned', 'bookings.dining_table_id'],
                $hasLinks ? ['booking_tables.dining_table_id as linked_table_id'] : [],
            ));

        $result = [];
        foreach ($rows as $row) {
            foreach ([$row->dining_table_id ?? null, $row->linked_table_id ?? null] as $tableId) {
                if ($tableId === null || !in_array((int) $tableId, $tableIds, true) || isset($result[(int) $tableId])) {
                    continue;
                }

                $result[(int) $tableId] = [
                    'booking_uuid' => (string) $row->uuid,
                    'starts_at' => \Illuminate\Support\Carbon::parse((string) $row->starts_at)->toJSON(),
                    'guests' => $row->guests_planned !== null ? (int) $row->guests_planned : null,
                ];
            }
        }

        return $result;
    }
}
