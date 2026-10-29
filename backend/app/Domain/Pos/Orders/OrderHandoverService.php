<?php

namespace App\Domain\Pos\Orders;

use App\Enums\OrderChannel;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * P6-24 (C4, C5): the two order changes the restaurant floor adds, run under the same lock, version, audit and
 * announcement as every PosOrderService change (mutate):
 *
 *  - transfer: an open table goes to another waiter. orders.transfer (manager and owner by default) or a manager's
 *    one-time approval token for it; the waiter rule is not asked (the manager decides who serves the table). The new
 *    waiter is a waiter of the order's branch; the old waiter and the new one are told on their own channels
 *    (OrderTransferredBroadcast) and the branch's screens refetch (order.items_changed, `waiter_changed`). Everything
 *    the order earns from now on follows the order's waiter (a personal tip, sales for the pay rules); what was
 *    already paid keeps the waiter the order had then (the audit row says who from whom, when and why);
 *  - setSeat: the guest number of a line (order_items.seat_no, null: nobody's, shared), for the guest split. The
 *    waiter rule applies as to any change of the order.
 *
 * It extends PosOrderService only to run inside its mutate(): nothing here changes how the order is opened, sent,
 * billed or closed.
 */
class OrderHandoverService extends PosOrderService
{
    /**
     * @throws PosOrderException 403 `approval_required`, 404 `waiter_not_found`, 422 `same_waiter`, `not_hall_order`
     */
    public function transfer(Order $order, int $version, User $actor, int $waiterId, ?string $reason, ?string $approvalToken = null): Order
    {
        $handover = null;
        $locked = $this->mutate($order, $version, $actor, function (Order $order) use ($actor, $waiterId, $reason, $approvalToken, &$handover) {
            if (!in_array($order->channel, [OrderChannel::POS_HALL, OrderChannel::POS_TAKEAWAY], true)) {
                throw new PosOrderException('Bu buyurtmani boshqa ofitsiantga berib bo\'lmaydi.', 422, 'not_hall_order');
            }
            $approvedBy = $this->authorizeOrApprove($actor, $order, Permission::ORDERS_TRANSFER, $approvalToken);

            $from = $order->waiter_id !== null ? (int) $order->waiter_id : null;
            if ($from === $waiterId) {
                throw new PosOrderException('Buyurtma allaqachon shu ofitsiantda.', 422, 'same_waiter');
            }

            $waiter = User::query()
                ->where('store_id', $order->store_id)
                ->where('role', UserRole::WAITER->value)
                ->whereNull('blocked_at')
                ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $order->branch_id))
                ->find($waiterId);
            if ($waiter === null) {
                throw new PosOrderException('Ofitsiant topilmadi: bu filialning faol ofitsiantini tanlang.', 404, 'waiter_not_found');
            }

            $reason = $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null;
            $order->forceFill(['waiter_id' => $waiter->id])->save();
            $this->audit->record('pos.order_transferred', $order, ['waiter_id' => $from], ['waiter_id' => (int) $waiter->id], array_filter([
                'from_waiter_id' => $from,
                'to_waiter_id' => (int) $waiter->id,
                'reason' => $reason,
                'approved_by' => $approvedBy,
            ], fn ($value) => $value !== null), ['actor' => $actor]);

            $handover = [$from, (int) $waiter->id];

            return ['waiter_changed', []];
        }, $approvalToken, false);

        if ($handover !== null) {
            event(new OrderTransferredBroadcast($locked, $handover[0], $handover[1]));
        }

        return $locked;
    }

    /**
     * @param  int|null  $seatNo  1-500, or null to take the line off every guest
     *
     * @throws PosOrderException 404 `item_not_found`, 422 `already_void`
     */
    public function setSeat(Order $order, int $version, User $actor, int $itemId, ?int $seatNo, ?string $approvalToken = null): Order
    {
        return $this->mutate($order, $version, $actor, function (Order $order) use ($actor, $itemId, $seatNo) {
            $item = OrderItem::where('order_id', $order->id)->whereKey($itemId)->lockForUpdate()->first();
            if ($item === null) {
                throw new PosOrderException('Taom topilmadi.', 404, 'item_not_found');
            }
            if ($item->isVoid()) {
                throw new PosOrderException("Bu taom allaqachon o'chirilgan.", 422, 'already_void');
            }
            if ($seatNo !== null && ($seatNo < 1 || $seatNo > 500)) {
                throw ValidationException::withMessages(['seat_no' => 'Mehmon raqami 1 dan 500 gacha bo\'lishi kerak.']);
            }

            $before = $item->seat_no;
            if ($before === $seatNo) {
                return ['seat_changed', []];
            }
            $item->forceFill(['seat_no' => $seatNo])->save();
            $this->audit->record('pos.line_seat_changed', $order, ['seat_no' => $before], ['seat_no' => $seatNo], ['order_item_id' => (int) $item->id], ['actor' => $actor]);

            return ['seat_changed', [(int) $item->id]];
        }, $approvalToken);
    }
}
