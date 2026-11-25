<?php

namespace App\Services\Finance;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderLedgerReversal;
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

    /** Why a booking reversed the parts refunded meanwhile (`execution.ledger_reversal_catch_up_reason`, review L2) */
    public const CATCH_UP_RETRY = 'retry';

    public const CATCH_UP_LATE_PAYMENT = 'late_payment';

    public const CATCH_UP_REPLAY = 'replay';

    /** `orders:retry-settlements --confirm-unbooked`: an unmarked order booked by hand (unbookedPaidOrders) */
    public const CATCH_UP_MANUAL = 'manual';

    /** Review H1: a paid, platform-collected, delivered order with no settlement and no marker is reported after this */
    public const UNBOOKED_AFTER_MINUTES = 15;

    /** Review (query cost): the check and the Today list look only at payments paid in the last this many days */
    public const UNBOOKED_WINDOW_DAYS = 30;

    /** bookAfterPayment sends the unmarked alert itself, once its outcome is known (recordFailure stays quiet) */
    private bool $quietUnmarkedAlert = false;

    /** The last failure book() recorded (bookAfterPayment names it when even the marker could not be written) */
    private ?Throwable $lastFailure = null;

    /**
     * Books a delivered order; callers hold its row lock inside their transaction. Returns whether it was booked; a
     * failure is rolled back to the booking's savepoint and recorded on the order instead of being thrown, unless even
     * the marker cannot be written because the caller's transaction is aborted (recordFailure): then it throws.
     *
     * Review L5: a booking that succeeds for an order marked by an earlier failure (the retry, or a late payment's
     * booking: MarkOrderAsPaid) clears the marker in the same savepoint, after everything the marker stood for
     * (the commission, the settlement, the parts refunded meanwhile, the bonus) is booked: a marker is never cleared
     * for a booking that did not commit with it.
     *
     * Review M1: the parts refunded meanwhile are reversed (reverseDeferredParts) only when there is a reason: the
     * order carried the marker (a booking failed, so parts approved since may have found nothing to take back), or
     * this booking booked the commission or the settlement for the first time. A booking of an order already booked
     * in full (a replayed PaymentPaid, a second retry) moves nothing, whatever its old requests or a manual
     * adjustment say. $catchUpReason is recorded on the requests a catch-up touched when the order carried no marker
     * (with the marker it is always `retry`). Review L1: the referral bonus is granted after the catch-up, so parts
     * that reached everything paid (a full reversal) leave no bonus rows at all.
     */
    public function book(Order $order, ?int $performedBy = null, string $catchUpReason = self::CATCH_UP_RETRY): bool
    {
        try {
            DB::transaction(function () use ($order, $performedBy, $catchUpReason) {
                $marked = $order->settlement_failed_at !== null;
                $booked = $this->ledger->bookCommissionAndSettlement($order, $performedBy);
                if ($marked || $booked['commission'] || $booked['settlement']) {
                    // Whatever this booking added (a deferred commission, a late settlement), the parts refunded since
                    // delivery take their share of it now; nothing outstanding moves nothing
                    $this->reverseDeferredParts($order, $performedBy, $marked ? self::CATCH_UP_RETRY : $catchUpReason);
                }
                // Review L1: after the parts (an order they refunded in full never earns it: the full reversal row)
                $this->ledger->grantReferralReward($order, $performedBy);
                if ($marked) {
                    $this->clearMarker($order);
                }
            });

            return true;
        } catch (Throwable $e) {
            $this->lastFailure = $e;
            $this->recordFailure($order, $e);

            return false;
        }
    }

    /**
     * Review H1: the settlement of a payment that lands only after delivery (MarkOrderAsPaid), in a savepoint of the
     * listener's transaction, with the payment's status. Returns whether it credited now. A failure is thrown, rolled
     * back to the savepoint: the listener catches it and books again through bookAfterPayment, which marks the order
     * if it fails again. When it credits now, the parts refunded since delivery (while the money was not booked)
     * take their share of it in the same savepoint (reverseDeferredParts; review M1: this is a booking that booked
     * something for the first time). $replay: the provider's repeated completion (PaymentPaid::$replay).
     */
    public function settleLatePayment(Order $order, bool $replay = false): bool
    {
        return DB::transaction(function () use ($order, $replay) {
            $settled = $this->ledger->settlePlatformOrder($order);
            if ($settled) {
                $this->reverseDeferredParts($order, null, $replay ? self::CATCH_UP_REPLAY : self::CATCH_UP_LATE_PAYMENT);
            }

            return $settled;
        });
    }

    /**
     * Review H1 (the reviewer's shape for MarkOrderAsPaid): a delivered order's payment landed; what delivery would
     * have booked with it (the settlement, if the listener's own attempt failed; the referral bonus the payment may
     * earn now) is booked here in a transaction of its own, under the order's row lock, so nothing it does can take
     * the payment back. A failure is marked on the order (settlement_failed_at, alerted) and
     * RetryFailedSettlementsJob books it later, exactly once: book() marks what fails inside the booking; anything
     * that escapes it, or a marker book() could not write, is marked here in a transaction of its own (the first one
     * rolls back whole). Should even that marker fail, this throws (logged critical, alerted once: here, after the
     * second attempt, never also from the rolled-back first one): the listener and the provider's handler see the
     * error, nothing of the booking is committed, and SettlementFailuresCheck reports the paid order without a
     * settlement (unbookedLatePayments) after UNBOOKED_AFTER_MINUTES. Returns whether it was booked. $catchUpReason:
     * see book().
     */
    public function bookAfterPayment(int $orderId, string $catchUpReason = self::CATCH_UP_LATE_PAYMENT): bool
    {
        // Review (one alert): a marker book() could not write is tried again below, so whether the order is left
        // unmarked is known only then; recordFailure leaves that alert to this method
        $quiet = $this->quietUnmarkedAlert;
        $this->quietUnmarkedAlert = true;

        try {
            return DB::transaction(function () use ($orderId, $catchUpReason) {
                $order = Order::whereKey($orderId)->lockForUpdate()->first();
                if (!$order || $order->status !== OrderStatus::DELIVERED) {
                    return false;
                }

                $this->lastFailure = null;
                if ($this->book($order, null, $catchUpReason)) {
                    return true;
                }
                if (!Order::whereKey($orderId)->whereNotNull('settlement_failed_at')->exists()) {
                    // book() could not write the marker: rolled back whole, marked below in a transaction of its own
                    throw new SettlementNotMarked($this->lastFailure ?? new \RuntimeException('the ledger booking failed'));
                }

                return false;
            });
        } catch (Throwable $e) {
            $error = $e instanceof SettlementNotMarked ? ($e->getPrevious() ?? $e) : $e;
            try {
                $marked = DB::transaction(function () use ($orderId, $error) {
                    $order = Order::whereKey($orderId)->lockForUpdate()->first();

                    // Nothing to mark for an order that is not delivered (any more)
                    return !$order || $order->status !== OrderStatus::DELIVERED || $this->recordFailure($order, $error);
                });
            } catch (Throwable $markerError) {
                // recordFailure threw (the transaction aborted, a deadlock): not marked either
                $marked = false;
            }
            if (!$marked) {
                Log::critical("Ledger booking after the late payment of order {$orderId} failed and could not be marked for a retry", ['order_id' => $orderId, 'error' => Str::limit($error->getMessage(), 480)]);
                $this->alert($this->unmarkedAlert($orderId, $error));

                throw new SettlementNotMarked($error);
            }

            return false;
        } finally {
            $this->quietUnmarkedAlert = $quiet;
        }
    }

    /**
     * Review H1: delivered, platform-collected, paid orders whose money was never credited to the store (no
     * settlement row, no debt payoff row) and that carry no marker: a late payment's settlement that failed and could
     * not even be marked (bookAfterPayment threw), or one lost before the fix. No time bound: `orders:retry-settlements`
     * books one of these (with --confirm-unbooked) whenever it was paid; the pre-deploy SQL in docs/OPERATIONS.md is
     * the same query.
     *
     * Review (double credit): finance may have credited such an order by hand already. A manual adjustment
     * (FinanceAdjustment, its ledger row) carries no order_id and names the order, if at all, only in free text
     * (`reason`, `notes`), so it cannot be matched reliably and is not excluded here; the command and the alerts say
     * to check the store's adjustments first.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Order>
     */
    public static function unbookedPaidOrders(): \Illuminate\Database\Eloquent\Builder
    {
        return Order::query()
            ->where('status', OrderStatus::DELIVERED->value)
            ->where('payment_owner', 'PLATFORM')
            ->where('payment_status', \App\Enums\PaymentStatus::PAID->value)
            ->whereNull('settlement_failed_at')
            ->where('total_price', '>', 0)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('store_wallet_transactions')
                ->whereColumn('store_wallet_transactions.order_id', 'orders.id')
                ->where('store_wallet_transactions.type', \App\Enums\StoreTransactionType::SETTLEMENT->value))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('store_transactions')
                ->whereColumn('store_transactions.order_id', 'orders.id')
                ->where('store_transactions.type', \App\Enums\StoreTransactionType::TOPUP->value));
    }

    /**
     * What SettlementFailuresCheck alerts on and UnbookedSettlementsSource lists: unbookedPaidOrders whose payment was
     * paid (payments.paid_at, written by Click, Payme and Paylov with the paid status) at least UNBOOKED_AFTER_MINUTES
     * and at most UNBOOKED_WINDOW_DAYS ago. Review (query cost): bounded by the payment's paid time, so neither runs
     * over every delivered order (payments_paid_at_index); older ones are left to the pre-deploy SQL and the command.
     * An order paid without a payment row is not reported here.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Order>
     */
    public static function unbookedLatePayments(): \Illuminate\Database\Eloquent\Builder
    {
        return self::unbookedPaidOrders()
            ->whereExists(fn ($query) => $query->select(DB::raw(1))->from('payments')
                ->whereColumn('payments.order_id', 'orders.id')
                ->where('payments.status', \App\Enums\PaymentStatus::PAID->value)
                ->where('payments.paid_at', '>=', now()->subDays(self::UNBOOKED_WINDOW_DAYS))
                ->where('payments.paid_at', '<=', now()->subMinutes(self::UNBOOKED_AFTER_MINUTES)));
    }

    /**
     * What `orders:retry-settlements` prints before it refuses an unbookedPaidOrders order without --confirm-unbooked:
     * the order, and the store's manual corrections since the order was placed that may have credited its money by
     * hand (finance adjustments that were not rejected; manual debt / wallet ledger rows without an order, e.g. the
     * legacy panel's top-ups). Read-only; none of them links to the order, so a person decides.
     *
     * @return list<string>
     */
    public function unbookedChecks(Order $order): array
    {
        $store = \App\Models\Store::withTrashed()->whereKey($order->store_id)->first(['id', 'name']);
        $paidAt = Payment::where('order_id', $order->id)->where('status', \App\Enums\PaymentStatus::PAID->value)->max('paid_at');
        $since = $order->created_at ?? now()->subDays(self::UNBOOKED_WINDOW_DAYS);
        $lines = [
            "Order #{$order->short_code} (id {$order->id}, {$order->uuid}), store {$order->store_id}" . ($store ? " ({$store->name})" : '')
                . ', total ' . Money::of((string) $order->total_price)->toString() . ', paid at ' . ($paidAt ?? 'unknown')
                . ', placed ' . $since->toDateTimeString(),
            'Check first that finance has not credited it by hand (an adjustment carries no order_id): the store\'s'
                . " finance adjustments (Moliya → Qo'lda tuzatish) and its debt / wallet ledgers since " . $since->toDateTimeString() . ':',
        ];

        $adjustments = \App\Models\FinanceAdjustment::where('store_id', $order->store_id)
            ->where('status', '!=', \App\Models\FinanceAdjustment::REJECTED)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at')->orderBy('id')->limit(20)
            ->get(['uuid', 'kind', 'amount', 'status', 'reason', 'created_at']);
        foreach ($adjustments as $adjustment) {
            $lines[] = "  adjustment {$adjustment->uuid} {$adjustment->kind} {$adjustment->amount} ({$adjustment->status}, {$adjustment->created_at}): "
                . Str::limit((string) $adjustment->reason, 120);
        }

        $manual = fn (\Illuminate\Database\Eloquent\Builder $rows) => $rows->where('store_id', $order->store_id)
            ->whereNull('order_id')->whereNull('reference_id')->where('created_at', '>=', $since)
            ->orderBy('created_at')->orderBy('id')->limit(20)
            ->get(['id', 'type', 'amount', 'notes', 'created_at']);
        foreach ([
            // Adjustment rows written by hand, and the legacy panel's manual top-ups (a member, nothing else linked)
            'debt' => $manual(\App\Models\StoreTransaction::query()->where(fn ($rows) => $rows
                ->where('type', \App\Enums\StoreTransactionType::ADJUSTMENT->value)
                ->orWhere(fn ($topup) => $topup->where('type', \App\Enums\StoreTransactionType::TOPUP->value)->whereNotNull('performed_by')
                    ->whereNull('payment_id')->whereNull('payout_request_id')->whereNull('subscription_invoice_id')))),
            'wallet' => $manual(\App\Models\StoreWalletTransaction::query()->where('type', \App\Enums\StoreTransactionType::ADJUSTMENT->value)),
        ] as $ledger => $rows) {
            foreach ($rows as $row) {
                $type = $row->getAttribute('type');
                $lines[] = "  {$ledger} ledger row {$row->getKey()} " . ($type instanceof \BackedEnum ? $type->value : (string) $type)
                    . ' ' . $row->getAttribute('amount') . ' (' . $row->getAttribute('created_at') . '): ' . Str::limit((string) $row->getAttribute('notes'), 120);
            }
        }

        if (count($lines) === 2) {
            $lines[] = '  none found';
        }

        return $lines;
    }

    /** The latest paid time of an order's paid payments (the age UnbookedSettlementsSource and the check show) */
    public static function paidAtSubquery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('payments')->selectRaw('max(payments.paid_at)')
            ->whereColumn('payments.order_id', 'orders.id')
            ->where('payments.status', \App\Enums\PaymentStatus::PAID->value);
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

            if (!$this->book($order, null, self::CATCH_UP_RETRY)) {
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
     * Callers run it only when something was booked for the first time or a failed booking is retried (review M1).
     *
     * Review M1, the legacy rule: a request approved before P4-23 (6ce13ffb, 28.10.2026) never touched the ledger and
     * says nothing about it; finance settled those parts by hand. Only a request that carries the key
     * `execution.ledger_reversal` (every approval of a part of a delivered order since P4-23 writes it, null or not)
     * or that a reversal row names (`order_refund_request_id`, or `components.catch_up_for_request`) takes part; an
     * older one is never reversed here. It still counts in the cumulative share of the parts after it, as it did
     * when those were approved (deliveryShareOf).
     *
     * Records what the request took back in all (`execution.ledger_reversal`) and, review L2, when and why the rest
     * was caught up (`ledger_reversal_caught_up_at`, `ledger_reversal_catch_up_reason`: retry, late_payment or
     * replay); `ledger_reversal_on_retry`, written before, is left as it is on older requests and no longer written.
     */
    protected function reverseDeferredParts(Order $order, ?int $performedBy, string $reason): void
    {
        if ($order->status !== OrderStatus::DELIVERED) {
            return;
        }

        $approved = RefundRequestService::approvedRequests([(int) $order->id])->get();
        $named = [];
        foreach (OrderLedgerReversal::where('order_id', $order->id)->get(['order_refund_request_id', 'components']) as $row) {
            foreach ([$row->order_refund_request_id, $row->components['catch_up_for_request'] ?? null] as $id) {
                if ($id !== null) {
                    $named[(int) $id] = true;
                }
            }
        }
        $parts = $approved->filter(fn (OrderRefundRequest $request) => ($request->execution['order_status_before'] ?? null) === OrderStatus::DELIVERED->value
            && (array_key_exists('ledger_reversal', (array) $request->execution) || isset($named[(int) $request->id])));
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
                'ledger_reversal_caught_up_at' => now()->toIso8601String(),
                'ledger_reversal_catch_up_reason' => $reason,
            ])])->save();
        }
    }

    /**
     * Saved in a savepoint of its own: if even the marker cannot be written (e.g. its columns are not migrated yet),
     * the delivery still commits and the critical log line and the alert remain. Review H1: not when the marker
     * fails because the caller's transaction is aborted (SQLSTATE 25P02) or on a deadlock / serialization failure:
     * nothing the caller does can commit any more, so the booking's failure is thrown, and the caller's transaction
     * rolls back whole and loudly (no after-commit event, the provider or the courier sees the error).
     * Returns whether the marker was written; when it was not (and nothing is thrown) the model keeps its old marker
     * attributes, a critical log line and an alert that no automatic retry follows remain (review H1).
     */
    protected function recordFailure(Order $order, Throwable $e): bool
    {
        $attempts = (int) $order->settlement_attempts + 1;
        $error = Str::limit(trim(get_class($e) . ': ' . $e->getMessage()), 480);
        $gaveUp = $attempts >= $this->maxAttempts();
        $context = ['order_id' => $order->id, 'attempts' => $attempts, 'error' => $error];
        $before = $order->only(['settlement_failed_at', 'settlement_error', 'settlement_attempts']);

        try {
            DB::transaction(fn () => $order->forceFill([
                'settlement_failed_at' => $order->settlement_failed_at ?? now(),
                'settlement_error' => $error,
                'settlement_attempts' => $attempts,
            ])->save());
        } catch (Throwable $markerError) {
            // The marker is not on the order: the model must not say it is (bookAfterPayment reads it back)
            $order->forceFill($before)->syncOriginalAttributes(array_keys($before));
            Log::critical("Could not mark order {$order->uuid} for a ledger booking retry: {$markerError->getMessage()}", $context);
            if (self::transactionAborted($markerError) || \App\Support\ConcurrencyFailure::is($markerError)) {
                throw $e;
            }
            if (!$this->quietUnmarkedAlert) {
                $this->alert($this->unmarkedAlert((int) $order->id, $e, $order->short_code));
            }

            return false;
        }

        $short = Str::limit($e->getMessage(), 300);

        if ($attempts === 1 && !$gaveUp) {
            Log::critical("Ledger booking of delivered order {$order->uuid} failed; it is retried every 10 minutes", $context);
            $this->alert("⚠️ Moliya: #{$order->short_code} buyurtmasi yetkazildi, lekin uning hisob-kitobi (komissiya, "
                . "do'konga o'tkazma, referal bonusi) yozilmadi. Har 10 daqiqada qayta urinib ko'riladi.\nXato: {$short}");
        } elseif ($gaveUp) {
            Log::critical("Ledger booking of delivered order {$order->uuid} given up after {$attempts} failed attempts", $context);
            $this->alert("🚨 Moliya: #{$order->short_code} buyurtmasining hisob-kitobi {$attempts} marta urinishdan keyin ham "
                . "yozilmadi, avtomatik urinishlar to'xtatildi. Sababini bartaraf etib: php artisan orders:retry-settlements {$order->id}\nXato: {$short}");
        } else {
            Log::error("Ledger booking retry {$attempts} of delivered order {$order->uuid} failed", $context);
        }

        return true;
    }

    /**
     * The order is left paid, uncredited and unmarked: no automatic retry. Review (double credit): finance may credit
     * it by hand meanwhile (an adjustment carries no order_id), so the store's adjustments are checked before the
     * command books it, and the command asks for --confirm-unbooked.
     */
    private function unmarkedAlert(int $orderId, Throwable $e, ?string $shortCode = null): string
    {
        if ($shortCode === null) {
            try {
                $shortCode = Order::whereKey($orderId)->value('short_code');
            } catch (Throwable) {
                // The alert goes out without it
            }
        }
        $label = $shortCode !== null ? "#{$shortCode}" : "#{$orderId}";

        return "🚨 Moliya: {$label} buyurtmasining hisob-kitobi yozilmadi va qayta urinish belgisi ham yozilmadi: avtomatik "
            . "qayta urinish bo'lmaydi. Monitoring uni 15 daqiqadan keyin ko'rsatadi. Sababini bartaraf eting, so'ng avval "
            . "do'konning moliya tuzatishlarini (Moliya → Qo'lda tuzatish, hamyon va qarz tuzatishlari) shu buyurtma vaqtidan "
            . "beri tekshiring: pul qo'lda yozilgan bo'lsa, buyruqni ishga tushirmang. Faqat shundan keyin: "
            . "php artisan orders:retry-settlements {$orderId} --confirm-unbooked\nXato: " . Str::limit($e->getMessage(), 300);
    }

    /** A superadmin alert that never turns a recorded failure into another one (the log line stays) */
    private function alert(string $text): void
    {
        try {
            $this->alerts->send($text);
        } catch (Throwable $e) {
            Log::error("Superadmin alert could not be sent: {$e->getMessage()}");
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
