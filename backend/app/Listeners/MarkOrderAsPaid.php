<?php

namespace App\Listeners;

use App\Events\PaymentPaid;
use App\Models\OrderStatusHistory;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;

class MarkOrderAsPaid
{
    /**
     * Handle the event.
     */
    public function handle(PaymentPaid $event): void
    {
        $payment = $event->payment;
        $order = $payment->order;

        if (!$order) {
            return;
        }

        // 1. Update Order billing status
        $order->update([
            'payment_status' => PaymentStatus::PAID->value,
            'status' => OrderStatus::ACCEPTED->value, // Transition automatically to Accepted!
        ]);

        // 2. Register status transition history log
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'status' => OrderStatus::ACCEPTED->value,
            'note' => "Payment verified via Webhook ({$payment->provider}). Order automatically Accepted.",
        ]);

        // 3. Dispatch admin / kitchen telegram notifications
        try {
            $telegramService = app(\App\Services\Notifications\TelegramNotificationService::class);
            $telegramService->sendOrderCreated($order); // Refresh notification card with PAID badge!
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to trigger Telegram notification from MarkOrderAsPaid listener: " . $e->getMessage());
        }
    }
}
