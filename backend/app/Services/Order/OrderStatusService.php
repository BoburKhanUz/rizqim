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
     *  - two_person_check: owner decision 4 — what two-person check a refund of a DELIVERED order paid online into
     *    the store's own provider account (StoreOwnRefund::isStoreOwned; not cash) went through (`refund_request:<id>`, `store_refund_review`). Without it a person cannot
     *    refund such an order (422 `two_person_check`): RIZQIM's commission on it goes back only after one member
     *    asked and another confirmed. The system (null actor) refunds such an order only for a refund the provider
     *    confirmed (refunded_by_provider), after a refund RIZQIM itself had asked for;
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

            // Owner decision 4: RIZQIM's commission on a delivered order paid into the store's own provider account goes
            // back only after a two-person check (a refund request asked by one member and approved by another, or a
            // held cabinet refund proposed and confirmed: StoreRefundReviewDecision). Cash orders are not concerned
            if ($to === OrderStatus::REFUNDED && $from === OrderStatus::DELIVERED && \App\Services\Payment\StoreOwnRefund::isStoreOwned($order)
                && !($actor === null && !empty($options['refunded_by_provider']))
                && !$this->twoPersonCheckHolds($order, $actor, $options['two_person_check'] ?? null)) {
                throw new OrderTransitionException(
                    "Ovqatxona o'z hisobiga olgan buyurtmani bitta xodim qaytara olmaydi: komissiya faqat ikki xodim tekshiruvidan keyin qaytadi. "
                    . "Boshqaruv markazida pulni qaytarish so'rovini yarating — uni boshqa xodim tasdiqlaydi.",
                    422,
                    'two_person_check',
                );
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
            // P6-13: a counter order paid first is closed while the kitchen still cooks its queue number: its lines stay
            $tracker = app()->bound(\App\Domain\Pos\Contracts\PickupTracker::class) ? app(\App\Domain\Pos\Contracts\PickupTracker::class) : null;
            $keepsKitchen = $to === OrderStatus::DELIVERED && $tracker !== null && $tracker->keepsKitchen($order);
            if (!$keepsKitchen && in_array($to, [OrderStatus::READY, OrderStatus::ON_THE_WAY, OrderStatus::DELIVERY, OrderStatus::DELIVERED], true)) {
                // P3-12: a POS line sent to the kitchen too; a POS line never sent (held) and a voided one stay as they are
                // P6-31: a combo's part lines with it
                \App\Models\OrderItem::withComboParts()->where('order_id', $order->id)->whereIn('kitchen_status', \App\Enums\KitchenItemStatus::cookingValues())
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

                if ($providerNote = $this->refundThroughProvider($order, $to)) {
                    $notes[] = $providerNote;
                }
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

                if (empty($options['refunded_by_provider']) && ($providerNote = $this->refundThroughProvider($order, $to))) {
                    $notes[] = $providerNote;
                }
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

            // P6-13: the pickup queue (an online pickup order's number and state, a cancelled number leaving the board)
            $tracker?->statusChanged($order, $from, $to);

            if ($to === OrderStatus::DELIVERED) {
                // In a savepoint: a failed booking (a deadlock too, review H1) is rolled back to it, marked on the order,
                // alerted and retried, never fails the delivery. Only if the marker cannot be written either (the
                // transaction is aborted) does book() throw: the delivery then rolls back whole, no event is sent
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
                // Owner decision 4: the two-person check a store-owned delivered order's refund went through
                'two_person_check' => $to === OrderStatus::REFUNDED ? ($options['two_person_check'] ?? null) : null,
                'total_price' => in_array($to, [OrderStatus::CANCELED, OrderStatus::REJECTED, OrderStatus::REFUNDED], true) ? $order->total_price : null,
                'payment_method' => $order->payment_method,
            ], fn ($value) => $value !== null),
            ['actor' => $actor],
        );
    }

    /**
     * Owner decision 4: whether the two-person check a refund of a delivered store-owned order names really took place,
     * read under the order's lock: `refund_request:<id>` — a pending request for this order asked by someone other than
     * $actor (RefundRequestService::approve refunds before it marks the request approved); `store_refund_review` — a
     * pending cabinet refund of this order proposed by someone other than $actor (StoreRefundReviewDecision). Anything
     * else, or the asker/proposer acting alone, is no check.
     */
    protected function twoPersonCheckHolds(Order $order, ?User $actor, mixed $check): bool
    {
        if ($actor === null || !is_string($check)) {
            return false;
        }

        if (preg_match('/^refund_request:(\d+)$/', $check, $match) === 1) {
            $request = \App\Models\OrderRefundRequest::query()->whereKey((int) $match[1])->where('order_id', $order->id)
                ->where('status', \App\Models\OrderRefundRequest::PENDING)
                ->first(['id', 'requested_by']);
            // Review finding 7: an asker whose account was deleted (requested_by NULL) is no "different person"
            if ($request !== null && $request->requested_by === null) {
                throw self::requesterMissing();
            }

            return $request !== null && (int) $request->requested_by !== (int) $actor->id;
        }

        if ($check === 'store_refund_review') {
            return \App\Models\Payment::query()->where('order_id', $order->id)->get(['id', 'payload'])
                ->contains(function (\App\Models\Payment $payment) use ($actor) {
                    $review = \App\Services\Payment\StoreRefundReview::of($payment);

                    return ($review['status'] ?? null) === \App\Services\Payment\StoreRefundReview::PENDING
                        && is_numeric($review['proposed_by'] ?? null) && (int) $review['proposed_by'] !== (int) $actor->id;
                });
        }

        return false;
    }

    /**
     * Review finding 7: a refund request whose asker's account was deleted cannot be the first person of a two-person
     * check; the admin makes a new request (and another member approves it).
     */
    public static function requesterMissing(): OrderTransitionException
    {
        return new OrderTransitionException(
            "Bu so'rovni yaratgan xodimning akkaunti o'chirilgan: ikki kishilik tekshiruv bajarilmaydi. "
            . "So'rovni rad eting va yangi qaytarish so'rovini yarating — uni boshqa xodim tasdiqlaydi.",
            422,
            'requester_missing',
        );
    }

    /**
     * The one way to refund a delivered order (super_admin; a null actor is the system, e.g. a refund Payme made).
     * Calling it again is a no-op. Owner decision 4: an order paid into the store's own account needs $twoPersonCheck
     * (the refund request or store refund review it went through) when a person refunds it.
     *
     * @throws OrderTransitionException
     */
    public function refund(Order $order, ?User $actor, string $reason, bool $refundedByProvider = false, ?string $twoPersonCheck = null): OrderTransitionResult
    {
        if (trim($reason) === '') {
            throw new OrderTransitionException("Qaytarish sababini kiriting.", 422, 'reason');
        }

        return $this->transition($order, OrderStatus::REFUNDED, $actor, array_filter([
            'notes' => trim($reason),
            'refunded_by_provider' => $refundedByProvider,
            // Owner decision 4: required for a delivered order paid into the store's own account (see transition)
            'two_person_check' => $twoPersonCheck,
        ], fn ($value) => $value !== null));
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
            // P4-31: a blocked account cannot take or work an order
            ->whereNull('blocked_at')
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
            ->whereNull('blocked_at')
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
            ->whereNull('blocked_at')
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
     * RefundRequestService::balanceOf counts them), nothing when nothing is left. An old order without any keshbek
     * payment row (no request can have refunded any of it) gives back its total, with the record its debit never had
     * (refundUnrecordedKeshbek). Review L4: only without any row, whatever its status: an order whose keshbek row
     * exists but is no longer paid (given back already, refunded or cancelled) gets nothing back again.
     */
    protected function refundKeshbek(Order $order): void
    {
        if ($order->payment_method !== 'keshbek' || $order->payment_status !== PaymentStatus::PAID) {
            return;
        }

        $rows = $order->payments()
            ->where('provider', 'keshbek')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $payments = $rows->filter(fn (\App\Models\Payment $payment) => $payment->status === PaymentStatus::PAID)->values();

        if ($rows->isEmpty()) {
            $this->refundUnrecordedKeshbek($order);
        } elseif ($payments->isNotEmpty()) {
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

    /**
     * Owner decision B: a legacy keshbek order whose debit left no keshbek payment row (the code of the time debited
     * the balance and wrote nothing) gets its total back, and the row it never had, in the same transaction: provider
     * keshbek, the total, refunded, the give-back in the payload as for any cancellation (refunded_to_balance,
     * refunded_at) and `backfilled_without_debit_record` with `debited_at` (the order's placing).
     * KeshbekLiabilityReport counts the row as spent at debited_at and as returned at refunded_at, so the debit and the
     * give-back are both recorded and the reconciliation's difference (−total while the order stood) is 0 again;
     * KeshbekHistory shows both. The payment is written refunded, never paid: nothing else can take it for money still
     * on the order. Callers hold the order's lock (refund: the order is then refunded, so this runs once).
     */
    private function refundUnrecordedKeshbek(Order $order): void
    {
        $total = Money::of($order->total_price);
        if (!$total->isPositive()) {
            return;
        }
        $this->creditBalance($order, $total);

        \App\Models\Payment::create([
            'order_id' => $order->id,
            'store_id' => $order->store_id,
            'merchant_scope' => \App\Models\Payment::merchantScopeOf($order),
            'provider' => 'keshbek',
            'amount' => $total->toString(),
            'status' => PaymentStatus::REFUNDED,
            'cancelled_at' => now(),
            'payload' => [
                'refunded_to_balance' => $total->toFloat(),
                'refunded_at' => now()->toIso8601String(),
                'backfilled_without_debit_record' => true,
                'debited_at' => ($order->created_at ?? now())->toIso8601String(),
            ],
        ]);
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
     * Wave P: a Paylov payment is given back in full through Paylov's payment/cancel instead (RefundPaylovPayment, after
     * the commit; PaylovPayments::refund flags it for a manual refund only if Paylov refuses it for good).
     */
    protected function flagManualRefund(Order $order, OrderStatus $to): ?string
    {
        if ($order->payment_status !== PaymentStatus::PAID) {
            return null;
        }

        $payments = $order->payments()->where('status', PaymentStatus::PAID->value)->lockForUpdate()->get();
        // Wave P: Paylov payments are refunded through Paylov (refundThroughProvider), never flagged here
        $paylov = $payments->filter(fn ($payment) => self::refundsThroughPaylov($payment));
        $payments = $payments->reject(fn ($payment) => self::refundsThroughPaylov($payment))->values();
        if ($payments->isEmpty() && $paylov->isNotEmpty()) {
            return null;
        }

        return $this->flagPaymentsForManualRefund($order, $to, $payments);
    }

    protected static function refundsThroughPaylov(\App\Models\Payment $payment): bool
    {
        return $payment->provider === 'paylov' && $payment->provider_transaction_id !== null;
    }

    /**
     * Wave P: the full refund of the order's paid Paylov payments through Paylov's payment/cancel, queued for after the
     * commit (RefundPaylovPayment). Returns the history note, or null when there is none.
     */
    protected function refundThroughProvider(Order $order, OrderStatus $to): ?string
    {
        if ($order->payment_status !== PaymentStatus::PAID) {
            return null;
        }

        $payments = $order->payments()->where('status', PaymentStatus::PAID->value)->where('provider', 'paylov')->whereNotNull('provider_transaction_id')->lockForUpdate()->get();
        if ($payments->isEmpty()) {
            return null;
        }

        foreach ($payments as $payment) {
            $payload = $payment->payload ?? [];
            $refund = $payload['paylov']['refund'] ?? null;
            if (!is_array($refund) || !in_array($refund['status'] ?? null, [\App\Services\Payment\Paylov\PaylovPayments::REFUND_REQUESTED, \App\Services\Payment\Paylov\PaylovPayments::REFUND_RETRYING], true)) {
                $payload['paylov']['refund'] = [
                    'status' => \App\Services\Payment\Paylov\PaylovPayments::REFUND_REQUESTED,
                    'reason' => "order_{$to->value}",
                    'requested_at' => now()->toIso8601String(),
                ];
                $payment->update(['payload' => $payload]);
            }
            \App\Jobs\RefundPaylovPayment::dispatch((int) $payment->id);
        }

        $amount = Money::sum(...$payments->pluck('amount')->all());

        return "To'lov Paylov orqali mijozga avtomatik qaytarilmoqda: " . number_format($amount->toFloat(), 0, '.', ' ') . ' UZS.';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Payment>  $payments  paid payments, locked
     */
    protected function flagPaymentsForManualRefund(Order $order, OrderStatus $to, \Illuminate\Support\Collection $payments): string
    {
        foreach ($payments as $payment) {
            $payment->update(['payload' => array_merge($payment->payload ?? [], [
                'manual_refund_required' => true,
                'manual_refund_reason' => "order_{$to->value}",
                'manual_refund_flagged_at' => now()->toIso8601String(),
            ])]);
        }

        $paid = Money::sum(...$payments->pluck('amount')->all());
        $amount = $paid->isZero() ? Money::of($order->total_price) : $paid;
        // Owner decision 4: money in the store's own account goes back from there, never from RIZQIM
        $stores = $payments->contains(fn (\App\Models\Payment $payment) => \App\Services\Payment\StoreOwnRefund::isStores($order, $payment));

        Log::warning("Manual refund required for {$to->value} order {$order->uuid}", [
            'order_id' => $order->id,
            'payment_method' => $order->payment_method,
            'amount' => $amount->toString(),
            'refund_by' => $stores ? \App\Services\Payment\StoreOwnRefund::REFUND_BY : null,
        ]);

        return "To'lov avtomatik qaytarilmadi: " . number_format($amount->toFloat(), 0, '.', ' ')
            . " UZS ({$order->payment_method}) mijozga qo'lda qaytarilishi kerak."
            . ($stores ? ' ' . \App\Services\Payment\StoreOwnRefund::LABEL . " (pul ovqatxonaning o'z to'lov hisobida, RIZQIM'da emas)." : '');
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
