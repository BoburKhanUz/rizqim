<?php

namespace App\Services\Finance;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Notifications\SuperadminAlertService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Delivery's ledger booking (LedgerService::processOrderDelivered: commission, platform settlement, referral reward)
 * is never silently lost. It runs in a savepoint of the delivery's transaction, so a failure (e.g. a schema mismatch
 * during a deploy) books nothing at all, never half, and the delivery still commits; the order then carries a marker
 * (settlement_failed_at, settlement_error, settlement_attempts), the failure is logged as critical and superadmins are
 * alerted. RetryFailedSettlementsJob books such orders again every 10 minutes, clearing the marker once it succeeds,
 * until config('orders.settlement_retry_max_attempts') bookings failed: then it gives up with a louder alert.
 */
class SettlementRecoveryService
{
    public function __construct(
        protected LedgerService $ledger,
        protected SuperadminAlertService $alerts
    ) {}

    public function maxAttempts(): int
    {
        return max(1, (int) config('orders.settlement_retry_max_attempts', 12));
    }

    /**
     * Books a delivered order; callers hold its row lock inside their transaction. Returns whether it was booked; a
     * failure is recorded on the order instead of being thrown.
     */
    public function book(Order $order, ?int $performedBy = null): bool
    {
        try {
            DB::transaction(fn () => $this->ledger->processOrderDelivered($order, $performedBy));

            return true;
        } catch (Throwable $e) {
            $this->recordFailure($order, $e);

            return false;
        }
    }

    /**
     * Books again an order whose booking failed (RetryFailedSettlementsJob). Returns whether it is booked now; an
     * order booked meanwhile or no longer delivered is left alone.
     */
    public function retry(int $orderId): bool
    {
        return DB::transaction(function () use ($orderId) {
            // The lock every transition takes: a refund cannot run while the booking is retried
            $order = Order::whereKey($orderId)->lockForUpdate()->first();

            if (!$order || $order->settlement_failed_at === null) {
                return false;
            }

            if ($order->status !== OrderStatus::DELIVERED) {
                // Refunded since: the refund reversed what delivery booked, which was nothing, so nothing is owed now
                Log::warning("Ledger booking of order {$order->uuid} dropped: the order is {$order->status->value} now");
                $this->clearMarker($order);

                return false;
            }

            if (!$this->book($order)) {
                return false;
            }

            $this->clearMarker($order);
            Log::info("Ledger booking of delivered order {$order->uuid} succeeded on retry");
            $this->alerts->send("✅ Moliya: #{$order->short_code} buyurtmasining hisob-kitobi qayta urinishda yozildi.");

            return true;
        });
    }

    /**
     * Saved in a savepoint of its own: if even the marker cannot be written (e.g. its columns are not migrated yet),
     * the delivery still commits and the critical log line and the alert remain.
     */
    protected function recordFailure(Order $order, Throwable $e): void
    {
        $attempts = (int) $order->settlement_attempts + 1;
        $error = Str::limit(trim(get_class($e) . ': ' . $e->getMessage()), 480);
        $gaveUp = $attempts >= $this->maxAttempts();
        $context = ['order_id' => $order->id, 'attempts' => $attempts, 'error' => $error];

        try {
            DB::transaction(fn () => $order->forceFill([
                'settlement_failed_at' => $order->settlement_failed_at ?? now(),
                'settlement_error' => $error,
                'settlement_attempts' => $attempts,
            ])->save());
        } catch (Throwable $markerError) {
            Log::critical("Could not mark order {$order->uuid} for a ledger booking retry: {$markerError->getMessage()}", $context);
        }

        $short = Str::limit($e->getMessage(), 300);

        if ($attempts === 1 && !$gaveUp) {
            Log::critical("Ledger booking of delivered order {$order->uuid} failed; it is retried every 10 minutes", $context);
            $this->alerts->send("⚠️ Moliya: #{$order->short_code} buyurtmasi yetkazildi, lekin uning hisob-kitobi (komissiya, "
                . "do'konga o'tkazma, referal bonusi) yozilmadi. Har 10 daqiqada qayta urinib ko'riladi.\nXato: {$short}");
        } elseif ($gaveUp) {
            Log::critical("Ledger booking of delivered order {$order->uuid} given up after {$attempts} failed attempts", $context);
            $this->alerts->send("🚨 Moliya: #{$order->short_code} buyurtmasining hisob-kitobi {$attempts} marta urinishdan keyin ham "
                . "yozilmadi, avtomatik urinishlar to'xtatildi. Sababini bartaraf etib, qo'lda yozing.\nXato: {$short}");
        } else {
            Log::error("Ledger booking retry {$attempts} of delivered order {$order->uuid} failed", $context);
        }
    }

    protected function clearMarker(Order $order): void
    {
        $order->forceFill(['settlement_failed_at' => null, 'settlement_error' => null, 'settlement_attempts' => 0])->save();
    }
}
