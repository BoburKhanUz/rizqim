<?php

namespace App\Services\Finance;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderRefundRequest;
use App\Models\Payment;
use App\Services\Admin\Orders\RefundRequestService;
use App\Services\Notifications\SuperadminAlertService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Delivery's ledger booking (LedgerService::processOrderDelivered: commission, platform settlement, referral reward)
 * is never silently lost. It runs in a savepoint of the delivery's transaction, so a failure (e.g. a schema mismatch
 * during a deploy, a deadlock or a lock timeout) is rolled back to that savepoint: it books nothing at all, never half,
 * and the delivery still commits; the order then carries a marker (settlement_failed_at, settlement_error,
 * settlement_attempts), the failure is logged as critical and superadmins are alerted. RetryFailedSettlementsJob books
 * such orders again every 10 minutes (a booking that succeeds clears the marker in the same savepoint), until
 * config('orders.settlement_retry_max_attempts') bookings failed: then it gives up with a louder alert.
 *
 * Review H1: a deadlock or serialization failure is rolled back to the savepoint as well (RevisionPostgresConnection::
 * handleTransactionException; Laravel alone left the caller's whole transaction aborted, and the delivery's "commit"
 * then silently lost the delivered status, the marker and everything else). Should the caller's transaction be
 * aborted all the same, so that the marker cannot be written, book() throws: the caller's transaction rolls back
 * whole and loudly instead of committing nothing while reporting success.
 *
 * Review W2: a part of the order refunded (an approved refund request) while the booking had failed found nothing on
 * the ledger to take back; the booking that succeeds later reverses those parts in the same savepoint
 * (reverseDeferredParts: cumulative, each request once, as if each had been approved after the booking).
 * A part approved while the booking stood only in part (the late payment's settlement booked by MarkOrderAsPaid, the
 * commission still failing) reversed only that part and recorded its reversal row; the booking that succeeds later
 * reverses the rest of its share (the outstanding reversal per part of the booking: its share of what is booked now
 * less what the order's reversal rows took back), so every approved share is applied to every part exactly once.
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
     * failure is rolled back to the booking's savepoint and recorded on the order instead of being thrown, unless even
     * the marker cannot be written because the caller's transaction is aborted (recordFailure): then it throws.
     *
     * Review L5: a booking that succeeds for an order marked by an earlier failure (the retry, or a late payment's
     * booking: MarkOrderAsPaid) clears the marker in the same savepoint, after everything the marker stood for
     * (processOrderDelivered: commission, settlement, bonus; the parts refunded meanwhile) is booked: a marker is never
     * cleared for a booking that did not commit with it.
     */
    public function book(Order $order, ?int $performedBy = null): bool
    {
        try {
            DB::transaction(function () use ($order, $performedBy) {
                $this->ledger->processOrderDelivered($order, $performedBy);
                // Whatever this booking added (a deferred commission, a late settlement), the parts refunded since
                // delivery take their share of it now; nothing outstanding moves nothing
                $this->reverseDeferredParts($order, $performedBy);
                if ($order->settlement_failed_at !== null) {
                    $this->clearMarker($order);
                }
            });

            return true;
        } catch (Throwable $e) {
            $this->recordFailure($order, $e);

            return false;
        }
    }

    /**
     * Review H1 (the reviewer's shape for MarkOrderAsPaid): a delivered order's payment landed and its settlement
     * committed; what delivery would have booked with it (the referral bonus the payment may earn now) is booked here
     * in a transaction of its own, under the order's row lock, so nothing it does can take the payment or the
     * settlement back. A failure is marked on the order (settlement_failed_at, alerted) and RetryFailedSettlementsJob
     * grants it later, exactly once: book() marks what fails inside the booking; anything that escapes it (the
     * transaction then rolls back whole) is marked here, in a transaction of its own. Returns whether it was booked.
     */
    public function bookAfterPayment(int $orderId): bool
    {
        try {
            return DB::transaction(function () use ($orderId) {
                $order = Order::whereKey($orderId)->lockForUpdate()->first();
                if (!$order || $order->status !== OrderStatus::DELIVERED) {
                    return false;
                }

                return $this->book($order);
            });
        } catch (Throwable $e) {
            DB::transaction(function () use ($orderId, $e) {
                $order = Order::whereKey($orderId)->lockForUpdate()->first();
                if ($order && $order->status === OrderStatus::DELIVERED) {
                    $this->recordFailure($order, $e);
                }
            });

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

            // book() cleared the marker with the booking
            Log::info("Ledger booking of delivered order {$order->uuid} succeeded on retry");
            $this->alerts->send("✅ Moliya: #{$order->short_code} buyurtmasining hisob-kitobi qayta urinishda yozildi.");

            return true;
        });
    }

    /**
     * Every part of a delivered order approved after its delivery takes back, of each part of the booking (commission,
     * settlement, debt payoff), what is still outstanding of its share, in the order the parts were approved: its
     * cumulative share (pro-rata of everything given back up to it of what was paid at delivery,
     * RefundRequestService::deliveryShareOf) of what is booked now, less what the order's reversal rows already took
     * back (LedgerService::reverseOutstandingShare). A part approved while nothing was booked takes all of its share; a
     * part approved while only some of the booking stood (the late payment's settlement booked, the commission still
     * failing) reversed that some then and takes the rest of its share now; a part reversed in full takes nothing.
     * Records what the request took back in all (`execution.ledger_reversal`, `ledger_reversal_on_retry`).
     */
    protected function reverseDeferredParts(Order $order, ?int $performedBy): void
    {
        if ($order->status !== OrderStatus::DELIVERED) {
            return;
        }

        $approved = RefundRequestService::approvedRequests([(int) $order->id])->get();
        $parts = $approved->filter(fn (OrderRefundRequest $request) => ($request->execution['order_status_before'] ?? null) === OrderStatus::DELIVERED->value);
        if ($parts->isEmpty()) {
            return;
        }

        $payments = Payment::where('order_id', $order->id)->orderBy('id')->get(['id', 'provider', 'amount', 'status', 'payload', 'cancelled_at']);
        foreach ($parts as $request) {
            // Security review F1: the share of what was paid at delivery (payments refunded before it take no part)
            $upTo = RefundRequestService::deliveryShareOf($order, $payments, $approved->filter(fn (OrderRefundRequest $other) => (int) $other->id <= (int) $request->id));
            $taken = $this->ledger->reverseOutstandingShare($order, (int) $request->id, $upTo['refunded'], $upTo['paid'], $performedBy);
            if (collect($taken)->every(fn (Money $value) => $value->isZero())) {
                continue;
            }

            // What the request took back in all: at its approval (if anything was booked then) and now
            $before = (array) ($request->execution['ledger_reversal'] ?? []);
            $total = [];
            foreach ($taken as $key => $value) {
                $total[$key] = $value->plus((string) ($before[$key] ?? '0'))->toString();
            }
            $request->forceFill(['execution' => array_merge($request->execution ?? [], [
                'ledger_reversal' => $total,
                'ledger_reversal_on_retry' => true,
            ])])->save();
        }
    }

    /**
     * Saved in a savepoint of its own: if even the marker cannot be written (e.g. its columns are not migrated yet),
     * the delivery still commits and the critical log line and the alert remain. Review H1: not when the marker
     * fails because the caller's transaction is aborted (SQLSTATE 25P02) or on a deadlock / serialization failure:
     * nothing the caller does can commit any more, so the booking's failure is thrown, and the caller's transaction
     * rolls back whole and loudly (no after-commit event, the provider or the courier sees the error).
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
            if (self::transactionAborted($markerError) || \App\Support\ConcurrencyFailure::is($markerError)) {
                throw $e;
            }
        }

        $short = Str::limit($e->getMessage(), 300);

        if ($attempts === 1 && !$gaveUp) {
            Log::critical("Ledger booking of delivered order {$order->uuid} failed; it is retried every 10 minutes", $context);
            $this->alerts->send("⚠️ Moliya: #{$order->short_code} buyurtmasi yetkazildi, lekin uning hisob-kitobi (komissiya, "
                . "do'konga o'tkazma, referal bonusi) yozilmadi. Har 10 daqiqada qayta urinib ko'riladi.\nXato: {$short}");
        } elseif ($gaveUp) {
            Log::critical("Ledger booking of delivered order {$order->uuid} given up after {$attempts} failed attempts", $context);
            $this->alerts->send("🚨 Moliya: #{$order->short_code} buyurtmasining hisob-kitobi {$attempts} marta urinishdan keyin ham "
                . "yozilmadi, avtomatik urinishlar to'xtatildi. Sababini bartaraf etib: php artisan orders:retry-settlements {$order->id}\nXato: {$short}");
        } else {
            Log::error("Ledger booking retry {$attempts} of delivered order {$order->uuid} failed", $context);
        }
    }

    /** SQLSTATE 25P02: the transaction is aborted, only a rollback is possible */
    private static function transactionAborted(Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ((string) $current->getCode() === '25P02') {
                return true;
            }
        }

        return false;
    }

    protected function clearMarker(Order $order): void
    {
        $order->forceFill(['settlement_failed_at' => null, 'settlement_error' => null, 'settlement_attempts' => 0])->save();
    }
}
