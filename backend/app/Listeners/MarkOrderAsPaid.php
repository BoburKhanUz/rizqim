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
use Illuminate\Support\Facades\Log;
use Throwable;

class MarkOrderAsPaid
{
    public function __construct(
        protected OrderStatusService $orderStatus,
        protected LedgerService $ledger,
        protected \App\Services\Finance\SettlementRecoveryService $settlements,
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

        $bookLate = false;
        try {
            $order = DB::transaction(function () use ($payment, $event, &$bookLate) {
                $order = Order::whereKey($payment->order_id)->lockForUpdate()->first();

                if (!$order) {
                    return null;
                }

                // 1. Update Order billing status
                if ($order->payment_status !== PaymentStatus::PAID) {
                    $order->update(['payment_status' => PaymentStatus::PAID->value]);
                }

                // Paid only after delivery: delivery skipped the settlement, so credit the store now (idempotent), and it
                // commits with the payment status before anything else is tried
                if ($order->status === OrderStatus::DELIVERED) {
                    $bookLate = true;
                    try {
                        // Review H1: in a savepoint of its own. A failure (a lock timeout, a deadlock, any error) is
                        // rolled back to it and never takes the payment status with it; bookAfterPayment below books
                        // the settlement again and marks the order (settlement_failed_at) if it fails there too
                        $this->settlements->settleLatePayment($order, $event->replay);
                    } catch (Throwable $e) {
                        Log::warning("Settlement of the late payment of order {$order->uuid} failed, booked again: {$e->getMessage()}", [
                            'order_id' => $order->id,
                            'payment_id' => $payment->id,
                        ]);
                    }

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
        } catch (Throwable $e) {
            if (!$bookLate) {
                throw $e;
            }
            // The late payment's transaction failed whole (e.g. its commit): the booking below tries everything again
            Log::warning("Late payment of order {$payment->order_id} could not be settled in its transaction, booked again: {$e->getMessage()}");
            $order = null;
        }

        if ($bookLate) {
            // Owner decision 3: the payment is verified only now, so the referral bonus it may earn is granted now
            // (once). Review finding 3 and review H1: in a transaction of its own, after the payment and the settlement
            // committed, so the bonus never undoes either. The whole delivery booking again (commission, settlement:
            // idempotent, already done unless the attempt above failed; the referral bonus): a failure (a deadlock, a
            // lock timeout) is rolled back, the order is marked (settlement_failed_at, alerted) and
            // RetryFailedSettlementsJob books it again later, exactly once. A provider does not replay a payment it got
            // an answer for, so nothing else would retry it. Should not even the marker be written, this throws
            // (SettlementNotMarked): never logged-and-forgotten here, and SettlementFailuresCheck finds the order
            $this->settlements->bookAfterPayment(
                (int) $payment->order_id,
                $event->replay ? \App\Services\Finance\SettlementRecoveryService::CATCH_UP_REPLAY : \App\Services\Finance\SettlementRecoveryService::CATCH_UP_LATE_PAYMENT,
            );

            return;
        }

        if (!$order) {
            return;
        }

        $this->notify($order);
    }

    private function notify(Order $order): void
    {
        // 3. Dispatch admin / kitchen telegram notifications
        try {
            $telegramService = app(\App\Services\Notifications\TelegramNotificationService::class);
            $telegramService->sendOrderCreated($order); // Refresh notification card with PAID badge!
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to trigger Telegram notification from MarkOrderAsPaid listener: " . $e->getMessage());
        }
    }
}
