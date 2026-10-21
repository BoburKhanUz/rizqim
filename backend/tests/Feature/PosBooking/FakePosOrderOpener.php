<?php

namespace Tests\Feature\PosBooking;

use App\Domain\Pos\Contracts\PosOrderOpener;
use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Order;

/**
 * PosOrderOpener for the P3-17 tests (P3-12 owns the real one): records the call and opens a bare pos_hall order.
 */
class FakePosOrderOpener implements PosOrderOpener
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];

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
    ): Order {
        $this->calls[] = compact('branchId', 'channel', 'tableId', 'guestsCount', 'waiterId', 'deviceId', 'openedByUserId', 'bookingId', 'heldItems');

        return Order::create([
            'store_id' => Branch::findOrFail($branchId)->store_id,
            'branch_id' => $branchId,
            'user_id' => $openedByUserId,
            'status' => OrderStatus::ACCEPTED,
            'order_type' => 'dine_in',
            'payment_method' => 'cash',
            'total_price' => 0,
            'commission_amount' => 0,
            'channel' => $channel,
            'guests_count' => $guestsCount,
            'booking_id' => $bookingId,
        ]);
    }
}
