<?php

namespace App\Listeners;

use App\Events\PaymentPaid;
use App\Models\Order;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Services\Finance\LedgerService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use Illuminate\Support\Facades\DB;

class MarkOrderAsPaid
{
    public function __construct(
        protected OrderStatusService $orderStatus,
        protected LedgerService $ledger
    ) {}

    /**
     * Handle the event.
     */
    public function handle(PaymentPaid $event): void
    {
        $payment = $event->payment;

        if (!$payment->order_id) {
            return;
        }

        $order = DB::transaction(function () use ($payment) {
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->first();

            if (!$order) {
                return null;
            }

            // 1. Update Order billing status
            if ($order->payment_status !== PaymentStatus::PAID) {
                $order->update(['payment_status' => PaymentStatus::PAID->value]);
            }

            // Paid only after delivery: delivery skipped the settlement, so credit the store now (idempotent).
            if ($order->status === OrderStatus::DELIVERED) {
                $this->ledger->settlePlatformOrder($order);

                return null;
            }

            // 2. Only a still-new order is auto-accepted, so a repeated event never regresses or revives an order.
            try {
                $result = $this->orderStatus->transition($order, OrderStatus::ACCEPTED, null, [
                    'from' => [OrderStatus::NEW],
                    'notes' => "Payment verified via Webhook ({$payment->provider}). Order automatically Accepted.",
                ]);
            } catch (OrderTransitionException) {
                return null;
            }

            return $result->changed ? $result->order : null;
        });

        if (!$order) {
            return;
        }

        // 3. Dispatch admin / kitchen telegram notifications
        try {
            $telegramService = app(\App\Services\Notifications\TelegramNotificationService::class);
            $telegramService->sendOrderCreated($order); // Refresh notification card with PAID badge!
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to trigger Telegram notification from MarkOrderAsPaid listener: " . $e->getMessage());
        }
    }
}
