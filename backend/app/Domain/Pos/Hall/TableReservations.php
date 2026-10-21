<?php

namespace App\Domain\Pos\Hall;

use Carbon\CarbonInterface;

/**
 * The bookings about to arrive at a branch's tables, for the floor map's "reserved soon" state (R1). Bookings are
 * P3-17's; BookingTableReservations reads them, and P3-17 may bind its own implementation instead.
 */
interface TableReservations
{
    /**
     * Bookings still expected (not arrived, not cancelled) that start between `$from` and `$until`, per table id: the
     * earliest one of each table.
     *
     * @param  list<int>  $tableIds
     * @return array<int, array{booking_uuid: string, starts_at: string, guests: int|null}>
     */
    public function upcoming(int $branchId, array $tableIds, CarbonInterface $from, CarbonInterface $until): array;
}
