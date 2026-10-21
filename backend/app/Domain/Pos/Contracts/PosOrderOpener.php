<?php

namespace App\Domain\Pos\Contracts;

use App\Models\Order;

/**
 * Opens a POS order (P3-12). Used directly by the cashier and waiter endpoints and by a booking's "Keldi" (P3-17),
 * which passes the booking and its pre-ordered lines; those lines are added on hold (fire_mode = hold) with their
 * due time, so the kitchen sees them when they are due, not when the guests arrive.
 */
interface PosOrderOpener
{
    /**
     * @param  'pos_hall'|'pos_takeaway'  $channel
     * @param  list<array{product_variant_id: int, quantity: string, modifiers?: list<array{modifier_id: int, quantity?: int}>, note?: string|null, due_at?: string|null}>  $heldItems
     */
    public function open(
        int $branchId,
        string $channel,
        ?int $tableId,
        int $guestsCount,
        ?int $waiterId,
        ?int $deviceId,
        int $openedByUserId,
        ?int $bookingId = null,
        array $heldItems = [],
    ): Order;
}
