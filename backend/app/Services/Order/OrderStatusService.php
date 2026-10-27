<?php

namespace App\Services\Order;

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Events\Broadcast\OrderAssignedBroadcast;
use App\Events\Broadcast\OrderStatusChangedBroadcast;
use App\Events\OrderCancelled;
use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\OrderStatusHistory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Admin\Orders\RefundRequestService;
use App\Services\Finance\LedgerService;
use App\Services\Finance\SettlementRecoveryService;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class OrderStatusService
{
    public function __construct(
        protected LedgerService $ledger,
        protected SettlementRecoveryService $settlements
    ) {}

    /**
     * The single place an order's status changes, under a row lock on the order. Options:
     *  - courier_id: courier to (re)assign when the order goes to a courier (validated like assign-courier);
     *  - from: statuses the caller's action was offered in (e.g. a Telegram button); the order must still be in one;
     *  - notes: status history note;
     *  - refunded_by_provider: a refund the payment provider already made (nothing is flagged for a manual refund);
     *  - cancel_reason (CancelReason|string), required for canceled/rejected: staff pick any but payment_timeout,
     *    which is the system's (null actor) own, and no_show only for a pickup order already accepted
     *    (CancelReason::appliesTo); cancel_note: optional free text (max 500);
     *  - kitchen_recall: KitchenService::recall takes a ready order (not yet with a courier or handed over) back to
     *    preparing (P2-30); P2-33: that move and the next `ready` are quiet (result->quiet, OrderStatusUpdated::quiet):
     *    no customer alert, and callers send no staff alert (OrderAlertService::statusChanged);
     *  - customer: the actor cancels their own order (POST /orders/{uuid}/cancel): refused with 422 unless it is still
     *    new and unpaid (or already cancelled by them: a retried cancel is the no-op), and only for customer_request.
     * Requesting the current status is a no-op (changed = false), so retries never repeat side effects.
     * A null actor is the system and skips the permission checks.
     *
     * @throws OrderTransitionException
     */
    public function transition(Order $order, OrderStatus $to, ?User $actor, array $options = []): OrderTransitionResult
    {
        return DB::transaction(function () use ($order, $to, $actor, $options) {
            $order = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $from = $order->status;
            $previousCourierId = $order->courier_id !== null ? (int) $order->courier_id : null;
            $courierId = isset($options['courier_id']) ? (int) $options['courier_id'] : null;

            // Checked under the lock, so a store accepting (or a payment landing) a moment earlier wins with a 422. A
            // retry of this customer's own successful cancel (a lost response) falls through to the no-op below
            if (!empty($options['customer'])) {
                if (!$order->isCancellableByCustomer() && !$this->cancelledByCustomer($order, $actor)) {
                    throw OrderTransitionException::notCancellableByCustomer();
                }
                // Before OrderPolicy, which lets the customer cancel for customer_request only (a 403 otherwise)
                if ($this->requestedReason($options) !== CancelReason::CUSTOMER_REQUEST) {
                    throw OrderTransitionException::reasonNotAllowed();
                }
            }

            if ($from === $to && ($courierId === null || $courierId === (int) $order->courier_id)) {
                if ($actor && Gate::forUser($actor)->denies('view', $order)) {
                    throw OrderTransitionException::forbidden();
                }

                return new OrderTransitionResult($order, $from, false);
            }

            if ($actor && Gate::forUser($actor)->denies('changeStatus', [$order, $to, $this->requestedReason($options)])) {
                throw OrderTransitionException::forbidden();
            }

            if (isset($options['from']) && !in_array($from, $options['from'], true)) {
                throw OrderTransitionException::stale($from, $to);
            }

            // Same status with another courier: only a waiting assignment can be handed to someone else.
            // P2-30: ready -> preparing is not a move of the state machine: only the kitchen's recall of a line
            // (KitchenService::recall, option kitchen_recall) takes an order that became ready back to the kitchen
            $reassign = $from === $to;
            $recall = !empty($options['kitchen_recall']) && $from === OrderStatus::READY && $to === OrderStatus::PREPARING;
            if (($reassign && $to !== OrderStatus::COURIER_ASSIGNED) || (!$reassign && !$recall && !$from->canTransitionTo($to, $order->type))) {
                throw OrderTransitionException::illegal($from, $to);
            }

            $cancellation = $to->isCancellation() ? $this->cancellation($order, $from, $actor, $options) : [];

            // P2-33: the kitchen correcting itself is not news: a line recalled from a ready order (ready -> preparing),
            // and the order becoming ready again after that (it has been ready before: only a recall leaves `ready`
            // for the kitchen). The customer and the couriers already heard "ready" once; staff screens still get
            // the real-time events
            $quiet = $recall || ($to === OrderStatus::READY
                && OrderStatusHistory::where('order_id', $order->id)->where('status', OrderStatus::READY->value)->exists());

            $courier = $this->resolveCourier($order, $to, $actor, $courierId);

            $order->update(array_filter([
                'status' => $to->value,
                'courier_id' => $courier?->id,
            ]) + $cancellation);

            if ($courier) {
                $this->recordAssignment($order, $courier, $actor);
            }

            // P2-11: a ready order is ready in the kitchen too, whoever marked it (kitchen display, panel, Telegram).
            // P2-33: so is an order that left the kitchen (on its way, handed over): its lines are closed, so the board's
            // items_ready stays right and no ticket keeps waiting for food that is gone
            if (in_array($to, [OrderStatus::READY, OrderStatus::ON_THE_WAY, OrderStatus::DELIVERY, OrderStatus::DELIVERED], true)) {
                // P3-12: a POS line sent to the kitchen too; a POS line never sent (held) and a voided one stay as they are
                $order->items()->whereIn('kitchen_status', \App\Enums\KitchenItemStatus::cookingValues())
                    ->where(fn ($query) => $query->whereNotNull('sent_at')->orWhereNull('fire_mode'))
                    ->update(['kitchen_status' => \App\Enums\KitchenItemStatus::READY->value, 'ready_at' => now()]);
            }

            $notes = array_filter([$options['notes'] ?? null, $courier ? "Kuryer: {$courier->name}" : null]);
            if ($cancellation) {
                $notes[] = 'Sabab: ' . CancelReason::from($cancellation['cancel_reason'])->label()
                    . ($cancellation['cancel_note'] !== null ? " ({$cancellation['cancel_note']})" : '');
            }
            $manualRefund = false;

            if ($to->isCancellation()) {
                $this->restoreStock($order);
                $this->refundKeshbek($order);

                if ($refundNote = $this->flagManualRefund($order, $to)) {
                    $notes[] = $refundNote;
                    $manualRefund = true;
                }
            }

            if ($to === OrderStatus::REFUNDED) {
                // The food was delivered, so no stock comes back; the store's ledger first, then the customer's keshbek
                if ($ledgerNote = $this->reverseDelivery($order, $actor)) {
                    $notes[] = $ledgerNote;
                }
                $this->refundKeshbek($order);

                if (empty($options['refunded_by_provider']) && ($refundNote = $this->flagManualRefund($order, $to))) {
                    $notes[] = $refundNote;
                    $manualRefund = true;
                }
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $actor?->id,
                'from_status' => $from->value,
                'status' => $to->value,
                'notes' => $notes ? implode(' | ', $notes) : null,
            ]);

            $this->audit($order, $from, $to, $actor, $cancellation, $courier, $options);

            if ($to === OrderStatus::DELIVERED) {
                // In a savepoint: a failed booking is marked on the order, alerted and retried, never fails the delivery
                $this->settlements->book($order, $actor?->id);
            }

            // Both events dispatch after commit (ShouldDispatchAfterCommit)
            event($to->isCancellation() ? new OrderCancelled($order) : new OrderStatusUpdated($order, $quiet));
            // Real-time hints for the Mini App (Reverb; queued, after commit, ids and status only)
            event(new OrderStatusChangedBroadcast($order, $previousCourierId));
            if ($courier) {
                event(new OrderAssignedBroadcast($order));
            }

            return new OrderTransitionResult($order, $from, true, $courier, $manualRefund, $quiet);
        });
    }

    /**
     * The audit log (P1-12): every cancellation/rejection with its reason, every refund, and status changes made by
     * staff. A customer's or the system's other moves (payment landing, courier app) are in the status history only.
     *
     * @param  array{cancel_reason?: string, cancel_note?: ?string, cancelled_by?: ?int}  $cancellation
     */
    protected function audit(Order $order, OrderStatus $from, OrderStatus $to, ?User $actor, array $cancellation, ?User $courier, array $options): void
    {
        $byStaff = ($actor?->role?->isStoreStaff() ?? false) || $actor?->role === UserRole::SUPER_ADMIN;

        $action = match (true) {
            $to === OrderStatus::CANCELED => 'order.cancelled',
            $to === OrderStatus::REJECTED => 'order.rejected',
            $to === OrderStatus::REFUNDED => 'order.refunded',
            $byStaff => 'order.status_changed',
            default => null,
        };

        if ($action === null) {
            return;
        }

        app(\App\Services\Audit\AuditLogger::class)->record(
            $action,
            $order,
            ['status' => $from->value],
            array_filter(['status' => $to->value, 'courier_id' => $courier?->id], fn ($value) => $value !== null),
            array_filter([
                'short_code' => $order->short_code,
                'reason' => $cancellation['cancel_reason'] ?? null,
                'note' => $cancellation['cancel_note'] ?? ($to === OrderStatus::REFUNDED ? ($options['notes'] ?? null) : null),
                'refunded_by_provider' => $to === OrderStatus::REFUNDED ? !empty($options['refunded_by_provider']) : null,
                'total_price' => in_array($to, [OrderStatus::CANCELED, OrderStatus::REJECTED, OrderStatus::REFUNDED], true) ? $order->total_price : null,
                'payment_method' => $order->payment_method,
            ], fn ($value) => $value !== null),
            ['actor' => $actor],
        );
    }

    /**
     * The one way to refund a delivered order (super_admin; a null actor is the system, e.g. a refund Payme made).
     * Calling it again is a no-op.
     *
     * @throws OrderTransitionException
     */
    public function refund(Order $order, ?User $actor, string $reason, bool $refundedByProvider = false): OrderTransitionResult
    {
        if (trim($reason) === '') {
            throw new OrderTransitionException("Qaytarish sababini kiriting.", 422, 'reason');
        }

        return $this->transition($order, OrderStatus::REFUNDED, $actor, [
            'notes' => trim($reason),
            'refunded_by_provider' => $refundedByProvider,
        ]);
    }

    /**
     * Staff an order of this store may be handed to: couriers, managers and owners (who stand in for couriers);
     * an assigner tied to a branch (a branch manager) only picks from their branch, store-wide staff or the owner. Invited couriers and managers
     * count only once they joined; owners can always act from the admin panel.
     *
     * @return Builder<User>
     */
    public function assignableStaff(int $storeId, ?User $assigner): Builder
    {
        // P2-33: the branch the assigner acts in (their own, or a PIN session's terminal branch)
        $acting = $assigner !== null ? app(\App\Services\Auth\PermissionService::class)->actingBranchId($assigner) : null;

        return User::query()
            ->where('store_id', $storeId)
            ->whereIn('role', [UserRole::COURIER->value, UserRole::MANAGER->value, UserRole::OWNER->value])
            ->where(function (Builder $query) {
                $query->where('role', UserRole::OWNER->value)
                    ->orWhere(fn (Builder $query) => $this->joined($query));
            })
            // P2-30: anyone tied to a branch who may assign (a manager by default; any role the store grants it to);
            // P2-33: and a PIN session on a branch terminal (the terminal's branch)
            ->when($acting !== null, function (Builder $query) use ($acting) {
                $query->where(function (Builder $query) use ($acting) {
                    $query->where('branch_id', $acting)
                        ->orWhereNull('branch_id')
                        ->orWhere('role', UserRole::OWNER->value);
                });
            });
    }

    /**
     * The store's courier with the fewest orders in hand, preferring the order's branch, then store-wide couriers,
     * then other branches; ties go to the lowest id. The owner stands in when the store has no courier.
     */
    public function leastLoadedCourier(Order $order): ?User
    {
        $inHand = array_map(fn (OrderStatus $status) => $status->value, OrderStatus::withCourier());

        $courier = User::query()
            ->where('store_id', $order->store_id)
            ->where('role', UserRole::COURIER->value)
            ->where(fn (Builder $query) => $this->joined($query))
            ->addSelect(['active_orders' => Order::query()
                ->selectRaw('count(*)')
                ->whereColumn('orders.courier_id', 'users.id')
                ->whereIn('orders.status', $inHand)])
            ->orderByRaw('CASE WHEN users.branch_id = ? THEN 0 WHEN users.branch_id IS NULL THEN 1 ELSE 2 END', [$order->branch_id])
            ->orderBy('active_orders')
            ->orderBy('users.id')
            ->first();

        return $courier ?? User::where('store_id', $order->store_id)
            ->where('role', UserRole::OWNER->value)
            ->orderBy('id')
            ->first();
    }

    /**
     * Staff who can see and move the order: they claimed their Telegram invite (no pending token, a real Telegram
     * account rather than a 'temp_…' placeholder), or they signed in to a Rizqim app (P1-36: an app-only courier,
     * the restaurant's own or the Rizqim fleet, has a live personal device and no Telegram). An unclaimed invite with
     * neither could never see the order. How they hear about it is NotificationRouter's business (Telegram when they
     * have it, otherwise push).
     */
    protected function joined(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where(fn (Builder $query) => $query->whereNull('telegram_invite_token')
                ->whereNotNull('telegram_id')
                ->where('telegram_id', 'not like', 'temp\\_%'))
            ->orWhereExists(fn ($devices) => $devices->selectRaw('1')->from('devices')
                ->whereColumn('devices.user_id', 'users.id')
                ->whereNull('devices.revoked_at')));
    }

    /**
     * The cancel_reason option as a CancelReason, null when missing or unknown.
     */
    protected function requestedReason(array $options): ?CancelReason
    {
        $reason = $options['cancel_reason'] ?? null;

        return $reason instanceof CancelReason ? $reason : CancelReason::tryFrom((string) $reason);
    }

    /**
     * Whether the order is already cancelled by this customer themselves (customer_request, cancelled_by them).
     */
    protected function cancelledByCustomer(Order $order, ?User $actor): bool
    {
        return $actor !== null
            && $order->status === OrderStatus::CANCELED
            && $order->cancel_reason === CancelReason::CUSTOMER_REQUEST
            && (int) $order->cancelled_by === (int) $actor->id;
    }

    /**
     * The cancel_reason/cancel_note/cancelled_by columns of a cancellation, after checking the reason may be given.
     *
     * @return array{cancel_reason: string, cancel_note: ?string, cancelled_by: ?int}
     */
    protected function cancellation(Order $order, OrderStatus $from, ?User $actor, array $options): array
    {
        $reason = $this->requestedReason($options);

        if (!$reason) {
            throw OrderTransitionException::reasonRequired();
        }

        $allowed = match (true) {
            !empty($options['customer']) => $reason === CancelReason::CUSTOMER_REQUEST,
            $actor !== null => $reason !== CancelReason::PAYMENT_TIMEOUT,
            default => true,
        };
        if (!$allowed) {
            throw OrderTransitionException::reasonNotAllowed();
        }
        if (!$reason->appliesTo($order->order_type, $from)) {
            throw OrderTransitionException::noShowNotApplicable();
        }

        $note = trim((string) ($options['cancel_note'] ?? ''));

        return [
            'cancel_reason' => $reason->value,
            'cancel_note' => $note === '' ? null : mb_substr($note, 0, 500),
            'cancelled_by' => $actor?->id,
        ];
    }

    protected function resolveCourier(Order $order, OrderStatus $to, ?User $actor, ?int $courierId): ?User
    {
        if ($courierId !== null) {
            if (!$to->isWithCourier()) {
                throw OrderTransitionException::courierNotApplicable();
            }

            if ($actor && Gate::forUser($actor)->denies('assignCourier', $order)) {
                throw OrderTransitionException::forbidden();
            }

            return $this->assignableStaff((int) $order->store_id, $actor)->whereKey($courierId)->first()
                ?? throw OrderTransitionException::invalidCourier();
        }

        if (!$to->isWithCourier() || $order->courier_id) {
            return null;
        }

        return $this->leastLoadedCourier($order) ?? throw OrderTransitionException::noCourier();
    }

    protected function recordAssignment(Order $order, User $courier, ?User $actor): void
    {
        OrderAssignment::where('order_id', $order->id)
            ->where('is_current', true)
            ->update(['is_current' => false, 'unassigned_at' => now()]);

        if (!$order->branch_id) {
            return; // assigned_branch_id is required
        }

        OrderAssignment::create([
            'order_id' => $order->id,
            'courier_id' => $courier->id,
            'assigned_by' => $actor->id ?? $courier->id,
            'assigned_branch_id' => $order->branch_id,
            'is_current' => true,
            'assigned_at' => now(),
        ]);
    }

    protected function restoreStock(Order $order): void
    {
        // P3-12: a voided POS line already gave its portion back, or was cooked and thrown away (PosOrderService::void)
        $items = $order->items()->where('kitchen_status', '!=', \App\Enums\KitchenItemStatus::VOID->value)
            ->get(['product_variant_id', 'quantity', 'batch_id', 'batch_quantity']);

        // Decimal quantities (P1-21: 0.5 kg) are added exactly
        $quantities = [];
        foreach ($items->whereNotNull('product_variant_id') as $item) {
            $variantId = (int) $item->product_variant_id;
            $quantities[$variantId] = ($quantities[$variantId] ?? Quantity::zero())->plus($item->quantity);
        }
        ksort($quantities);

        // Variant id order, as CreateOrderAction locks them, so a cancel and a checkout cannot deadlock
        foreach ($quantities as $variantId => $quantity) {
            if ($quantity->isPositive()) {
                ProductVariant::withTrashed()->whereKey($variantId)->increment('stock', $quantity->toString()); // @phpstan-ignore argument.type
            }
        }

        // Then the batches the lines were taken from (batch id order, as checkout locks them)
        $batches = [];
        foreach ($items->whereNotNull('batch_id') as $item) {
            $batchId = (int) $item->batch_id;
            $batches[$batchId] = ($batches[$batchId] ?? Quantity::zero())->plus($item->batch_quantity ?? $item->quantity);
        }
        if ($batches !== []) {
            app(\App\Services\Catalog\BatchService::class)->restore($batches);
        }
    }

    /**
     * Gives the keshbek still on the order back to the customer's balance: each paid keshbek payment minus what
     * approved refund requests already returned from it (the Boshqaruv markazi's partial refunds, as
     * RefundRequestService::balanceOf counts them), nothing when nothing is left. An old order without a payment
     * row (no request can have refunded any of it) gives back its total.
     */
    protected function refundKeshbek(Order $order): void
    {
        if ($order->payment_method !== 'keshbek' || $order->payment_status !== PaymentStatus::PAID) {
            return;
        }

        $payments = $order->payments()
            ->where('provider', 'keshbek')
            ->where('status', PaymentStatus::PAID->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($payments->isEmpty()) {
            $this->creditBalance($order, Money::of($order->total_price));
        } else {
            $returned = RefundRequestService::balanceOf($payments, RefundRequestService::approvedRequests([(int) $order->id])->get())['allocated'];
            foreach ($payments as $payment) {
                $before = Money::min(Money::of($payment->amount), $returned[(int) $payment->id] ?? Money::zero());
                $left = Money::of($payment->amount)->minus($before);
                $this->creditBalance($order, $left);

                $payment->update([
                    'status' => PaymentStatus::REFUNDED,
                    'cancelled_at' => now(),
                    'payload' => array_merge($payment->payload ?? [], array_filter([
                        'refunded_to_balance' => $left->toFloat(),
                        'refunded_to_balance_by_requests' => $before->isPositive() ? $before->toFloat() : null,
                        'refunded_at' => now()->toIso8601String(),
                    ], fn ($value) => $value !== null)),
                ]);
            }
        }

        $order->update(['payment_status' => PaymentStatus::REFUNDED->value]);
    }

    private function creditBalance(Order $order, Money $amount): void
    {
        if ($amount->isPositive()) {
            // A numeric string keeps the amount exact; Laravel accepts it although the stub says float|int
            User::whereKey($order->user_id)->increment('balance', $amount->toString()); // @phpstan-ignore argument.type
        }
    }

    /**
     * Money already taken by Click/Payme (or collected cash) cannot be returned from here: the payment stays
     * paid and is flagged for a manual refund. Returns the history note, or null when nothing is owed.
     */
    protected function flagManualRefund(Order $order, OrderStatus $to): ?string
    {
        if ($order->payment_status !== PaymentStatus::PAID) {
            return null;
        }

        $payments = $order->payments()->where('status', PaymentStatus::PAID->value)->lockForUpdate()->get();
        foreach ($payments as $payment) {
            $payment->update(['payload' => array_merge($payment->payload ?? [], [
                'manual_refund_required' => true,
                'manual_refund_reason' => "order_{$to->value}",
                'manual_refund_flagged_at' => now()->toIso8601String(),
            ])]);
        }

        $paid = Money::sum(...$payments->pluck('amount')->all());
        $amount = $paid->isZero() ? Money::of($order->total_price) : $paid;

        Log::warning("Manual refund required for {$to->value} order {$order->uuid}", [
            'order_id' => $order->id,
            'payment_method' => $order->payment_method,
            'amount' => $amount->toString(),
        ]);

        return "To'lov avtomatik qaytarilmadi: " . number_format($amount->toFloat(), 0, '.', ' ')
            . " UZS ({$order->payment_method}) mijozga qo'lda qaytarilishi kerak.";
    }

    /**
     * Undoes what delivery booked to the store (LedgerService::reverseDeliveredOrder); returns the history note.
     */
    protected function reverseDelivery(Order $order, ?User $actor): ?string
    {
        $reversed = $this->ledger->reverseDeliveredOrder($order, $actor?->id);
        $uzs = fn (Money $amount) => number_format($amount->toFloat(), 0, '.', ' ') . ' UZS';

        $parts = array_filter([
            $reversed['commission']->isPositive() ? "komissiya {$uzs($reversed['commission'])} qaytarildi" : null,
            $reversed['settlement']->isPositive() ? "do'konga o'tkazilgan {$uzs($reversed['settlement'])} qaytarib olindi" : null,
            $reversed['added_to_debt']->isPositive() ? "shundan {$uzs($reversed['added_to_debt'])} hamyonda yo'qligi uchun qarzga yozildi" : null,
        ]);

        return $parts ? 'Moliya: ' . implode(', ', $parts) . '.' : null;
    }
}
