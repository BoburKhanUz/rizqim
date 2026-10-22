<?php

namespace App\Domain\Pos\Billing;

use App\Domain\Pos\Shifts\OrderStamp;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Order\OrderStatusService;

/**
 * Closes a POS order once it is settled (POS_SPEC 2.9, 3): every live line and charge is on a bill, every bill that is
 * not void is paid (its receipt issued). The order then becomes `delivered` (through OrderStatusService, so its
 * history, kitchen lines and real-time events follow), `payment_status = paid`, `closed_at` is set and its table is
 * free (a table is busy while it has an open order). Nothing happens to an order that is not settled yet.
 */
final class OrderCloser
{
    public function __construct(
        private readonly OrderStatusService $statuses,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Call with the order row locked (inside the caller's transaction). Returns whether it closed now.
     */
    public function closeIfSettled(Order $order, ?User $actor): bool
    {
        if (!$this->isSettled($order)) {
            return false;
        }

        $order->forceFill([
            'payment_status' => PaymentStatus::PAID->value,
            'closed_at' => now(),
        ]);
        self::bumpVersion($order);
        $order->save();

        // The kitchen's own moves come first when nothing was sent yet (accepted -> preparing), then the hand-over
        if ($order->status === OrderStatus::ACCEPTED) {
            $this->statuses->transition($order, OrderStatus::PREPARING, null, ['notes' => 'POS: hisob yopildi']);
        }
        $this->statuses->transition($order->refresh(), OrderStatus::DELIVERED, null, ['notes' => "POS: to'landi va yopildi"]);

        $bills = OrderBill::where('order_id', $order->id)->where('status', BillStatus::PAID->value)->get(['uuid', 'total']);
        $this->audit->record('pos.order_closed', $order, [], [], [
            'short_code' => $order->short_code,
            'bills' => $bills->pluck('uuid')->all(),
            'total' => $order->total_price,
        ], ['actor' => $actor]);

        return true;
    }

    /**
     * Whether everything of the order is on paid bills (and there is at least one).
     */
    public function isSettled(Order $order): bool
    {
        if ($order->closed_at !== null || !in_array($order->channel, OrderStamp::CHANNELS, true)) {
            return false;
        }
        if (!in_array($order->status, [OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::READY], true)) {
            return false;
        }

        $bills = OrderBill::where('order_id', $order->id)->where('status', '!=', BillStatus::VOID->value)->get(['status']);
        if ($bills->isEmpty() || $bills->contains(fn (OrderBill $bill) => $bill->status !== BillStatus::PAID)) {
            return false;
        }

        return BillableOrder::of($order)->isEmpty();
    }

    /**
     * POS order mutations move the optimistic lock (orders.version, P3-12) so a terminal holding the old one gets 409.
     */
    public static function bumpVersion(Order $order): void
    {
        $order->forceFill(['version' => (int) $order->getAttribute('version') + 1]);
    }
}
