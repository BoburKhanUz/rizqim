<?php

namespace App\Jobs;

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

/**
 * Cancels online orders nobody paid for within config('orders.unpaid_online_ttl_minutes'), so they stop holding
 * stock and promo code use. Not queued: it runs inside the scheduler, needing no queue worker.
 */
class CancelUnpaidOnlineOrdersJob
{
    use Dispatchable;

    public function handle(OrderStatusService $orderStatus): void
    {
        $ttl = (int) config('orders.unpaid_online_ttl_minutes', 30);
        if ($ttl < 1) {
            return;
        }

        Order::query()
            ->where('status', OrderStatus::NEW->value)
            ->where('payment_status', '!=', PaymentStatus::PAID->value)
            ->whereIn('payment_method', (array) config('orders.online_payment_methods', []))
            ->where('created_at', '<=', now()->subMinutes($ttl))
            ->chunkById(100, function ($orders) use ($orderStatus, $ttl) {
                foreach ($orders as $order) {
                    $this->expire($order, $orderStatus, $ttl);
                }
            });
    }

    protected function expire(Order $order, OrderStatusService $orderStatus, int $ttl): void
    {
        try {
            DB::transaction(function () use ($order, $orderStatus, $ttl) {
                // Re-checked under the order lock the payment webhooks also take: a payment either landed first
                // (the order is skipped) or finds the order cancelled and is refused
                $locked = Order::whereKey($order->id)->lockForUpdate()->first();
                if (!$locked || $locked->status !== OrderStatus::NEW || $locked->payment_status === PaymentStatus::PAID) {
                    return;
                }

                // The customer's alert (Telegram and in-app feed) goes out with the OrderCancelled event
                $orderStatus->transition($locked, OrderStatus::CANCELED, null, [
                    'from' => [OrderStatus::NEW],
                    'cancel_reason' => CancelReason::PAYMENT_TIMEOUT,
                    'notes' => "To'lov {$ttl} daqiqa ichida amalga oshirilmadi, buyurtma avtomatik bekor qilindi.",
                ]);
            });
        } catch (OrderTransitionException) {
            // Moved on meanwhile (e.g. accepted): nothing to expire
        }
    }
}
