<?php

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Events\OrderCancelled;
use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\OrderStatusHistory;
use App\Models\OrderStatusLog;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Finance\LedgerService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class OrderStatusService
{
    public function __construct(
        protected LedgerService $ledger
    ) {}

    /**
     * The single place an order's status changes, under a row lock on the order. Options:
     *  - courier_id: courier to (re)assign when the order goes to a courier (validated like assign-courier);
     *  - from: statuses the caller's action was offered in (e.g. a Telegram button); the order must still be in one;
     *  - notes: status history note;
     *  - refunded_by_provider: a refund the payment provider already made (nothing is flagged for a manual refund).
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
            $courierId = isset($options['courier_id']) ? (int) $options['courier_id'] : null;

            if ($from === $to && ($courierId === null || $courierId === (int) $order->courier_id)) {
                if ($actor && Gate::forUser($actor)->denies('view', $order)) {
                    throw OrderTransitionException::forbidden();
                }

                return new OrderTransitionResult($order, $from, false);
            }

            if ($actor && Gate::forUser($actor)->denies('changeStatus', [$order, $to])) {
                throw OrderTransitionException::forbidden();
            }

            if (isset($options['from']) && !in_array($from, $options['from'], true)) {
                throw OrderTransitionException::stale($from, $to);
            }

            // Same status with another courier: only a waiting assignment can be handed to someone else
            $reassign = $from === $to;
            if (($reassign && $to !== OrderStatus::COURIER_ASSIGNED) || (!$reassign && !$from->canTransitionTo($to, $order->type))) {
                throw OrderTransitionException::illegal($from, $to);
            }

            $courier = $this->resolveCourier($order, $to, $actor, $courierId);

            $order->update(array_filter([
                'status' => $to->value,
                'courier_id' => $courier?->id,
            ]));

            if ($courier) {
                $this->recordAssignment($order, $courier, $actor);
            }

            $notes = array_filter([$options['notes'] ?? null, $courier ? "Kuryer: {$courier->name}" : null]);
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
                'status' => $to->value,
                'notes' => $notes ? implode(' | ', $notes) : null,
            ]);

            OrderStatusLog::create([
                'order_id' => $order->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'user_id' => $actor?->id,
            ]);

            if ($to === OrderStatus::DELIVERED) {
                $this->settle($order, $actor);
            }

            // Both events dispatch after commit (ShouldDispatchAfterCommit)
            event($to->isCancellation() ? new OrderCancelled($order) : new OrderStatusUpdated($order));

            return new OrderTransitionResult($order, $from, true, $courier, $manualRefund);
        });
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
     * a branch manager only picks from their branch, store-wide staff or the owner. Invited couriers and managers
     * count only once they joined; owners can always act from the admin panel.
     */
    public function assignableStaff(int $storeId, ?User $assigner): Builder
    {
        return User::query()
            ->where('store_id', $storeId)
            ->whereIn('role', [UserRole::COURIER->value, UserRole::MANAGER->value, UserRole::OWNER->value])
            ->where(function (Builder $query) {
                $query->where('role', UserRole::OWNER->value)
                    ->orWhere(fn (Builder $query) => $this->joined($query));
            })
            ->when($assigner?->role === UserRole::MANAGER && $assigner->branch_id, function (Builder $query) use ($assigner) {
                $query->where(function (Builder $query) use ($assigner) {
                    $query->where('branch_id', $assigner->branch_id)
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
     * Staff who claimed their invite: an unclaimed one still has a pending token and no real Telegram account
     * ('temp_…' placeholder or none), so it could never see or move the order.
     */
    protected function joined(Builder $query): Builder
    {
        return $query->whereNull('telegram_invite_token')
            ->whereNotNull('telegram_id')
            ->where('telegram_id', 'not like', 'temp\\_%');
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
            'assigned_by' => $actor?->id ?? $courier->id,
            'assigned_branch_id' => $order->branch_id,
            'is_current' => true,
            'assigned_at' => now(),
        ]);
    }

    protected function restoreStock(Order $order): void
    {
        $quantities = $order->items()
            ->whereNotNull('product_variant_id')
            ->get(['product_variant_id', 'quantity'])
            ->groupBy('product_variant_id')
            ->map(fn ($items) => (int) $items->sum('quantity'))
            ->sortKeys();

        // Variant id order, as CreateOrderAction locks them, so a cancel and a checkout cannot deadlock
        foreach ($quantities as $variantId => $quantity) {
            if ($quantity > 0) {
                ProductVariant::withTrashed()->whereKey($variantId)->increment('stock', $quantity);
            }
        }
    }

    protected function refundKeshbek(Order $order): void
    {
        if ($order->payment_method !== 'keshbek' || $order->payment_status !== PaymentStatus::PAID) {
            return;
        }

        $payment = $order->payments()
            ->where('provider', 'keshbek')
            ->where('status', PaymentStatus::PAID->value)
            ->lockForUpdate()
            ->first();
        $amount = Money::of($payment?->amount ?? $order->total_price);

        if ($amount->isPositive()) {
            User::whereKey($order->user_id)->increment('balance', $amount->toString());
        }

        $payment?->update([
            'status' => PaymentStatus::REFUNDED,
            'cancelled_at' => now(),
            'payload' => array_merge($payment->payload ?? [], [
                'refunded_to_balance' => $amount->toFloat(),
                'refunded_at' => now()->toIso8601String(),
            ]),
        ]);

        $order->update(['payment_status' => PaymentStatus::REFUNDED->value]);
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

    protected function settle(Order $order, ?User $actor): void
    {
        try {
            $this->ledger->processOrderDelivered($order, $actor?->id);
        } catch (\Throwable $e) {
            Log::error("Failed to process financials for order {$order->uuid}: " . $e->getMessage());
        }
    }
}
