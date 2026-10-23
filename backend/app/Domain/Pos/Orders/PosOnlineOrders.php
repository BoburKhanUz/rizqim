<?php

namespace App\Domain\Pos\Orders;

use App\Domain\Pos\Contracts\ManagerApprovals;
use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PermissionService;
use App\Services\Notifications\OrderAlertService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use App\Services\Order\OrderTransitionResult;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * P3-26: the online orders (Telegram, mobile, aggregator) waiting at the till: accept or reject a `new` one from the
 * POS (POST /pos/orders/{uuid}/accept|reject). The same state machine as the staff board (OrderStatusService), with the
 * POS rules on top: an optional `version` the till last saw (409 when stale), and a rejection by orders.cancel or a
 * manager's one-time approval token for it (a cashier runs orders but does not cancel them). The customer and staff
 * hear it as from the board (OrderAlertService, after commit).
 */
class PosOnlineOrders
{
    public function __construct(
        protected OrderStatusService $orderStatus,
        protected PermissionService $permissions,
        protected OrderAlertService $alerts,
        protected AuditLogger $audit,
    ) {}

    /**
     * new → accepted (orders.manage, the route's). Accepted already: unchanged.
     */
    public function accept(Order $order, User $actor, ?int $version): Order
    {
        return DB::transaction(function () use ($order, $actor, $version) {
            $locked = $this->lock($order, $version);
            if ($locked->status === OrderStatus::ACCEPTED) {
                return $locked;
            }
            $this->assertNew($locked);

            $result = $this->transition($locked, OrderStatus::ACCEPTED, $actor, [
                'notes' => "Kassa qabul qildi ({$actor->name})",
            ]);
            if ($result->changed && !$result->quiet) {
                DB::afterCommit(fn () => $this->alerts->statusChanged($result->order, OrderStatus::ACCEPTED->value));
            }

            return $result->order;
        });
    }

    /**
     * new → rejected with $note (and the customer-facing $reason): orders.cancel or a manager's approval token for it.
     * Rejected already: unchanged.
     */
    public function reject(Order $order, User $actor, string $note, CancelReason $reason, ?string $approvalToken, ?int $version): Order
    {
        $note = mb_substr(trim($note), 0, 255);

        return DB::transaction(function () use ($order, $actor, $note, $reason, $approvalToken, $version) {
            $locked = $this->lock($order, $version);
            if ($locked->status === OrderStatus::REJECTED) {
                return $locked;
            }
            $this->assertNew($locked);

            $approvedBy = $this->authorizeOrApprove($actor, $locked, $approvalToken);

            // The system's move (null actor): who may reject was checked above, an approval token included
            $result = $this->transition($locked, OrderStatus::REJECTED, null, [
                'cancel_reason' => $reason,
                'cancel_note' => $note,
                'notes' => "Kassa rad etdi ({$actor->name})",
            ]);
            $rejected = $result->order;
            $rejected->forceFill(['cancelled_by' => $actor->id])->save();
            OrderStatusHistory::where('order_id', $rejected->id)->where('status', OrderStatus::REJECTED->value)->whereNull('user_id')->update(['user_id' => $actor->id]);

            $this->audit->record('pos.online_order_rejected', $rejected, [], [], array_filter([
                'short_code' => $rejected->short_code,
                'reason' => $reason->value,
                'note' => $note,
                'approved_by' => $approvedBy,
            ], fn ($value) => $value !== null), ['actor' => $actor]);

            if ($result->changed) {
                DB::afterCommit(fn () => $this->alerts->statusChanged($rejected, OrderStatus::REJECTED->value));
            }

            return $rejected;
        });
    }

    private function lock(Order $order, ?int $version): Order
    {
        $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
        if ($locked->channel->isPos()) {
            throw new PosOrderException("Bu kassa (POS) buyurtmasi: onlayn buyurtma emas.", 422, 'not_online_order');
        }
        if ($version !== null && (int) $locked->version !== $version) {
            throw PosOrderException::versionConflict((int) $locked->version);
        }

        return $locked;
    }

    private function assertNew(Order $order): void
    {
        if ($order->status !== OrderStatus::NEW) {
            throw new PosOrderException(
                "Bu buyurtma endi yangi emas ({$order->status->label()}): uni qabul qilib yoki rad etib bo'lmaydi.",
                422,
                'order_not_new',
                ['status' => $order->status->value],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function transition(Order $order, OrderStatus $to, ?User $actor, array $options): OrderTransitionResult
    {
        try {
            return $this->orderStatus->transition($order, $to, $actor, $options);
        } catch (OrderTransitionException $e) {
            throw new PosOrderException($e->getMessage(), $e->status, $e->reason);
        }
    }

    /**
     * The approving manager's id when $actor lacks orders.cancel and gave a valid one-time token; null when the actor
     * holds it. 403 `approval_required` / `approval_*` otherwise.
     */
    private function authorizeOrApprove(User $actor, Order $order, ?string $token): ?int
    {
        if ($this->permissions->allows($actor, Permission::ORDERS_CANCEL, $order)) {
            return null;
        }

        if ($token === null || trim($token) === '' || !app()->bound(ManagerApprovals::class)) {
            throw new PosOrderException("Buyurtmani rad etish uchun menejer tasdig'i kerak.", 403, 'approval_required', ['permission' => Permission::ORDERS_CANCEL->value]);
        }

        try {
            return app(ManagerApprovals::class)->consume(trim($token), Permission::ORDERS_CANCEL->value, $this->permissions->terminalOf($actor)?->id);
        } catch (QueryException $e) {
            throw $e;
        } catch (Throwable $e) {
            $reason = property_exists($e, 'reason') && is_string($e->reason) && str_starts_with($e->reason, 'approval_') ? $e->reason : 'approval_invalid';
            $message = $reason !== 'approval_invalid' ? $e->getMessage() : "Menejer tasdig'i noto'g'ri, eskirgan yoki boshqa amal uchun.";

            throw new PosOrderException($message, 403, $reason, ['permission' => Permission::ORDERS_CANCEL->value]);
        }
    }
}
