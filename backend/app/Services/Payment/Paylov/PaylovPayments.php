<?php

namespace App\Services\Payment\Paylov;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentPaid;
use App\Jobs\RefundPaylovFiscalReceipt;
use App\Jobs\RefundPaylovPayment;
use App\Jobs\RegisterPaylovFiscalReceipt;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Order\OrderStatusService;
use App\Services\Payment\Fiscal\OnlineFiscalReceiptBuilder;
use App\Services\Payment\Fiscal\OnlineFiscalRecorder;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Wave P: what happens to a Paylov payment after transaction.perform. Paylov's callbacks carry Basic Auth only, no
 * signature (developer.paylov.uz/merchants/paylov-payment-gateway), so a perform is never settled on its own word:
 *
 *  - perform records the payment `pending` with payload.paylov.state `unconfirmed` and answers Paylov at once;
 *  - confirm() reads the transaction back (getTransactions, subscribe/payment/transaction-status). `payed` for exactly
 *    the recorded amount settles it, exactly once under the order and payment locks: the payment `paid`, the order paid,
 *    PaymentPaid after the commit, the fiscal receipt queued. `failed` / `cancel` / `refunded` close it (state
 *    `rejected`). Anything else (no answer, an error, an empty list, created / transfer / pending / waiting) leaves it
 *    `unconfirmed` for the next check: ConfirmPaylovPayment's retries, then `payments:paylov-reconcile` every five
 *    minutes (routes/console.php), which also alerts once about one still unconfirmed after an hour. The transaction must
 *    be this order's: getTransactions' `account.order_id` is compared with the order's uuid (accountMatches): another
 *    order's transaction closes the payment (`rejected`, rejected_reason transaction_of_another_order, never settled and
 *    never refunded from here: the money is that other order's); one listed without its account is not settled on the
 *    amount alone and stays `unconfirmed`. A `payed` answer for another amount is state `mismatch` (review 1 note: never
 *    left pending with money in it): never the order's payment, given back in full like a refused one (refused
 *    `amount_mismatch`, payment/cancel) and a critical log for the team;
 *  - money Paylov took for an order that cannot take it (paid by another transaction, cancelled meanwhile: perform was
 *    answered +1 and payload.paylov.refused says why) is `paid` on the payment only, never on the order, and given back
 *    in full through payment/cancel (refund());
 *  - refund() is the full refund (subscribe/cancel-payment has no amount). Before a refund is ever flagged for a manual
 *    one, Paylov is asked for the transaction's status (review 1: our earlier payment/cancel may have gone through with
 *    its answer lost, and API cancels send no webhook); a status that cannot be read is asked again, never flagged;
 *  - the transaction.cancel webhook carries Basic Auth only, which a store on its own Paylov account knows: it is applied
 *    only once getTransactions reports the transaction cancelled (verifyCancel, review 2). Until Paylov answers it is
 *    `cancel_reported_at` and the scheduled re-check asks again; a cancel Paylov does not confirm is logged and ignored.
 *    applyCancel() records a cancellation however it came (our own payment/cancel, or a verified webhook), once.
 *
 * An order with a payment still unconfirmed is never expired as unpaid (CancelUnpaidOnlineOrdersJob).
 */
class PaylovPayments
{
    public const STATE_UNCONFIRMED = 'unconfirmed';
    public const STATE_CONFIRMED = 'confirmed';
    public const STATE_REJECTED = 'rejected';
    public const STATE_MISMATCH = 'mismatch';
    public const STATE_CANCELLED = 'cancelled';

    /** getTransactions statuses (subscribe/payment/transaction-status, "Statuses") */
    public const PAID_STATUS = 'payed';
    public const WAITING_STATUSES = ['created', 'transfer', 'pending', 'waiting'];
    public const CLOSED_STATUSES = ['failed' => PaymentStatus::FAILED, 'cancel' => PaymentStatus::CANCELLED, 'refunded' => PaymentStatus::CANCELLED];

    /** The getTransactions statuses of a transaction whose money went back (a cancel or a refund) */
    public const CANCELLED_STATUSES = ['cancel', 'refunded'];

    /** A cancel webhook Paylov has not confirmed for this long is alerted once (a critical log); still asked after that */
    public const CANCEL_STALE_MINUTES = 60;

    /** payload.paylov.refund.status */
    public const REFUND_REQUESTED = 'requested';
    public const REFUND_RETRYING = 'retrying';
    public const REFUND_DONE = 'done';
    public const REFUND_FAILED = 'failed';

    /** After this many failed tries a refund is left to the team (a manual refund in Paylov's business panel). */
    public const REFUND_MAX_ATTEMPTS = 12;

    /** An unconfirmed payment older than this is alerted once (a critical log); it is still checked after that. */
    public const STALE_MINUTES = 60;

    public function __construct(private readonly PaylovApi $api) {}

    /**
     * Whether $payment is a Paylov transaction still waiting for its status read.
     */
    public static function isUnconfirmed(Payment $payment): bool
    {
        return $payment->provider === 'paylov'
            && $payment->status === PaymentStatus::PENDING
            && $payment->provider_transaction_id !== null
            && (self::state($payment)['state'] ?? null) === self::STATE_UNCONFIRMED;
    }

    /**
     * @return array<string, mixed>
     */
    public static function state(Payment $payment): array
    {
        $state = $payment->payload['paylov'] ?? null;

        return is_array($state) ? $state : [];
    }

    /**
     * Whether an order may take a payment now (the webhooks' rule): not paid yet, not cancelled, rejected or refunded.
     */
    public static function isPayable(Order $order): bool
    {
        return $order->payment_status !== PaymentStatus::PAID
            && !in_array($order->status, [OrderStatus::CANCELED, OrderStatus::REJECTED, OrderStatus::REFUNDED], true);
    }

    /**
     * Reads the transaction back from Paylov and settles, closes or keeps the payment. Returns the outcome: paid,
     * refund (taken for an order that could not take it: given back), rejected, mismatch, unconfirmed or noop.
     */
    public function confirm(int $paymentId): string
    {
        $payment = Payment::with('order.store')->find($paymentId);
        if (!$payment || !self::isUnconfirmed($payment)) {
            return 'noop';
        }

        $transactionId = (string) $payment->provider_transaction_id;
        $result = $this->api->transaction(PaylovAccount::forOrder($payment->order), $transactionId);
        $transaction = $result->ok ? $this->transactionOf($result, $transactionId) : null;
        $status = is_array($transaction) ? strtolower((string) ($transaction['status'] ?? '')) : null;
        $ours = is_array($transaction) ? self::accountMatches($transaction, $payment) : null;

        if ($ours === false) {
            return $this->foreign($payment, $status);
        }

        if ($status === self::PAID_STATUS && $ours === true) {
            $tiyin = self::wholeTiyin($transaction['amount_in_tiyin'] ?? null);
            if ($tiyin === null || $tiyin !== Money::of($payment->amount)->toTiyin()) {
                return $this->mismatch($payment, $transaction['amount_in_tiyin'] ?? null);
            }

            return $this->settle($payment);
        }

        if ($status !== null && isset(self::CLOSED_STATUSES[$status])) {
            return $this->close($payment, self::CLOSED_STATUSES[$status], $status);
        }

        if ($status === self::PAID_STATUS) {
            // Listed `payed` without the account it was paid for: never settled on the amount alone
            $status = 'account_missing';
        }

        // Not decided yet: no answer, an error, Paylov does not list it (yet), or it is still being processed
        $this->note($payment, [
            'checks' => (int) (self::state($payment)['checks'] ?? 0) + 1,
            'last_check_at' => now()->toIso8601String(),
            'last_check' => $status ?? ($result->ok ? 'not_listed' : (string) $result->errorCode),
        ]);
        Log::warning('Paylov payment still unconfirmed', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'paylov_status' => $status,
            'error' => $result->ok ? null : $result->errorCode,
        ]);

        return self::STATE_UNCONFIRMED;
    }

    /**
     * The `payed` transaction: the order's payment exactly once, or (refused at perform, or the order can no longer take
     * it) money to give back.
     */
    private function settle(Payment $payment): string
    {
        [$outcome, $settled] = DB::transaction(function () use ($payment) {
            // Every write about this order's money is serialised on the order row, then the payment's
            $order = $payment->order_id ? Order::whereKey($payment->order_id)->lockForUpdate()->first() : null;
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (!$locked || !self::isUnconfirmed($locked)) {
                return ['noop', null];
            }

            $refused = self::state($locked)['refused'] ?? null;
            if ($refused === null && $order !== null && !self::isPayable($order)) {
                $refused = $order->payment_status === PaymentStatus::PAID ? 'order_already_paid' : 'order_not_payable';
            }

            $this->write($locked, [
                'status' => PaymentStatus::PAID,
                'paid_at' => now(),
            ], ['state' => self::STATE_CONFIRMED, 'confirmed_at' => now()->toIso8601String(), 'refused' => $refused]
                + ($refused !== null ? ['refund' => ['status' => self::REFUND_REQUESTED, 'reason' => $refused, 'requested_at' => now()->toIso8601String()]] : []));

            if ($refused !== null || $order === null) {
                Log::warning('Paylov took money for an order that cannot take it: refunding it in full', [
                    'payment_id' => $locked->id,
                    'order_id' => $locked->order_id,
                    'reason' => $refused,
                ]);

                return ['refund', $locked];
            }

            $order->update(['payment_status' => PaymentStatus::PAID->value]);

            return ['paid', $locked];
        });

        if ($outcome === 'paid') {
            $this->firePaid($settled);
            $this->queueFiscalReceipt($settled);
        } elseif ($outcome === 'refund') {
            RefundPaylovPayment::dispatch((int) $settled->id);
        }

        return $outcome;
    }

    private function close(Payment $payment, PaymentStatus $to, string $paylovStatus, ?string $reason = null): string
    {
        return DB::transaction(function () use ($payment, $to, $paylovStatus, $reason) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (!$locked || !self::isUnconfirmed($locked)) {
                return 'noop';
            }

            $this->write($locked, ['status' => $to, 'cancelled_at' => now()], [
                'state' => self::STATE_REJECTED,
                'paylov_status' => $paylovStatus,
                'closed_at' => now()->toIso8601String(),
            ] + ($reason !== null ? ['rejected_reason' => $reason] : []), ['cancel_reported_at']);

            return self::STATE_REJECTED;
        });
    }

    /**
     * Review 1 (L): Paylov lists the transaction for another order (account.order_id): the perform that named this order
     * was wrong. Closed and alerted, never settled, never refunded from here (the money is the other order's).
     */
    private function foreign(Payment $payment, ?string $paylovStatus): string
    {
        Log::critical('Paylov lists the performed transaction for another order: not settled, check it in the Paylov business panel', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'paylov_status' => $paylovStatus !== null ? mb_substr($paylovStatus, 0, 32) : null,
        ]);

        return $this->close($payment, PaymentStatus::FAILED, (string) $paylovStatus, 'transaction_of_another_order');
    }

    /**
     * Whether a getTransactions entry is this payment's order (account.order_id is the uuid the checkout link carries,
     * PaymentProviderSwitch::paylovUrl); null when Paylov lists it without an account. The transaction-status page shows
     * `account` in its answer but not whether a link payment always has it: treating a missing one as "cannot tell" (never
     * settled, checked again) is our guess, kept here.
     *
     * @param  array<string, mixed>  $transaction
     */
    public static function accountMatches(array $transaction, Payment $payment): ?bool
    {
        $reference = is_array($transaction['account'] ?? null) ? ($transaction['account']['order_id'] ?? null) : null;
        if (!is_scalar($reference) || trim((string) $reference) === '') {
            return null;
        }
        $uuid = $payment->order?->uuid;
        if ($uuid === null) {
            return null;
        }

        return strcasecmp(trim((string) $reference), (string) $uuid) === 0;
    }

    /**
     * Review 1 note: Paylov took another amount than the order's for this order's transaction. Never the order's payment
     * and never left pending with the money in it: recorded as money to give back (paid on the payment only, refused
     * `amount_mismatch`) and refunded in full through payment/cancel (a whole-transaction refund, whatever amount Paylov
     * took), with a critical log for the team. A refund Paylov refuses for good is flagged for a manual one as any other.
     */
    private function mismatch(Payment $payment, mixed $tiyin): string
    {
        $reported = is_scalar($tiyin) ? mb_substr((string) $tiyin, 0, 32) : null;

        $refund = DB::transaction(function () use ($payment, $reported) {
            // The order row first, as every other write about this order's money
            if ($payment->order_id) {
                Order::whereKey($payment->order_id)->lockForUpdate()->first();
            }
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (!$locked || !self::isUnconfirmed($locked)) {
                return null;
            }

            $this->write($locked, ['status' => PaymentStatus::PAID, 'paid_at' => now()], [
                'state' => self::STATE_MISMATCH,
                'reported_tiyin' => $reported,
                'last_check_at' => now()->toIso8601String(),
                'refused' => 'amount_mismatch',
                'refund' => ['status' => self::REFUND_REQUESTED, 'reason' => 'amount_mismatch', 'requested_at' => now()->toIso8601String()],
            ]);

            return $locked;
        });

        if ($refund === null) {
            return 'noop';
        }

        Log::critical('Paylov reports another amount for a performed transaction: not settled, given back in full through payment/cancel', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'expected_tiyin' => Money::of($payment->amount)->toTiyin(),
            'reported_tiyin' => $reported,
        ]);
        RefundPaylovPayment::dispatch((int) $refund->id);

        return self::STATE_MISMATCH;
    }

    /**
     * The full refund of a paid Paylov payment through payment/cancel (subscribe/cancel-payment). Returns refunded,
     * retry (Paylov did not answer, or answered a passing error, or its status cannot be read: asked again later), manual
     * (refused for good and Paylov's status says the money is still there: flagged for a manual refund) or noop.
     */
    public function refund(int $paymentId): string
    {
        $payment = Payment::with('order')->find($paymentId);
        $refund = $payment ? (self::state($payment)['refund'] ?? null) : null;
        if (!$payment || $payment->provider !== 'paylov' || $payment->status !== PaymentStatus::PAID
            || !is_array($refund) || !in_array($refund['status'] ?? null, [self::REFUND_REQUESTED, self::REFUND_RETRYING], true)) {
            return 'noop';
        }

        $transactionId = (string) $payment->provider_transaction_id;
        $account = PaylovAccount::forOrder($payment->order);
        $result = $this->api->cancel($account, $transactionId);

        if ($result->ok && strtolower((string) ($result->result['status'] ?? '')) === 'cancelled') {
            $this->applyCancel($payment, 'merchant');

            return 'refunded';
        }

        $attempts = (int) ($refund['attempts'] ?? 0) + 1;
        $lastTry = !$result->transient || $attempts >= self::REFUND_MAX_ATTEMPTS;

        // Review 1: before a refund is ever flagged for a manual one, Paylov is asked whether it is cancelled already (its
        // panel, or our earlier payment/cancel whose answer was lost: API cancels send no webhook). A status that cannot
        // be read is asked again later, never flagged: a manual refund on top of Paylov's would give the money back twice
        if ($result->errorCode === 'transaction_not_available_for_cancel' || $lastTry) {
            $status = $this->api->transaction($account, $transactionId);
            $transaction = $status->ok ? $this->transactionOf($status, $transactionId) : null;
            $paylovStatus = is_array($transaction) ? strtolower((string) ($transaction['status'] ?? '')) : null;
            if ($paylovStatus !== null && in_array($paylovStatus, self::CANCELLED_STATUSES, true) && self::accountMatches($transaction, $payment) !== false) {
                $this->applyCancel($payment, 'merchant');

                return 'refunded';
            }

            if ($paylovStatus === null) {
                $this->note($payment, ['refund' => array_merge($refund, [
                    'status' => self::REFUND_RETRYING,
                    'attempts' => $attempts,
                    'last_error' => $result->errorCode,
                    'last_status_error' => $status->ok ? 'not_listed' : $status->errorCode,
                    'last_attempt_at' => now()->toIso8601String(),
                ])]);
                if ($attempts >= self::REFUND_MAX_ATTEMPTS && $this->noteOnce((int) $payment->id, 'refund_unverified_alerted_at')) {
                    Log::critical('Paylov refund not confirmed and its status cannot be read: still asked again, check it in the Paylov business panel', [
                        'payment_id' => $payment->id,
                        'order_id' => $payment->order_id,
                        'amount' => Money::of($payment->amount)->toString(),
                        'error' => $result->errorCode,
                        'attempts' => $attempts,
                    ]);
                }
                Log::warning('Paylov refund not done yet and its status unreadable, asked again later', ['payment_id' => $payment->id, 'error' => $result->errorCode, 'attempts' => $attempts]);

                return 'retry';
            }
        }

        if ($result->transient && $attempts < self::REFUND_MAX_ATTEMPTS) {
            $this->note($payment, ['refund' => array_merge($refund, [
                'status' => self::REFUND_RETRYING,
                'attempts' => $attempts,
                'last_error' => $result->errorCode,
                'last_attempt_at' => now()->toIso8601String(),
            ])]);
            Log::warning('Paylov refund not done yet, asked again later', ['payment_id' => $payment->id, 'error' => $result->errorCode, 'attempts' => $attempts]);

            return 'retry';
        }

        DB::transaction(function () use ($payment, $refund, $attempts, $result) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (!$locked || $locked->status !== PaymentStatus::PAID) {
                return;
            }
            $payload = $locked->payload ?? [];
            $payload['paylov']['refund'] = array_merge($refund, [
                'status' => self::REFUND_FAILED,
                'attempts' => $attempts,
                'last_error' => $result->errorCode,
                'failed_at' => now()->toIso8601String(),
            ]);
            $payload['manual_refund_required'] = true;
            $payload['manual_refund_reason'] = 'paylov_cancel_failed';
            $payload['manual_refund_flagged_at'] = now()->toIso8601String();
            $locked->forceFill(['payload' => $payload])->save();
        });
        Log::error('Paylov refund failed: flagged for a manual refund in the Paylov business panel', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'amount' => Money::of($payment->amount)->toString(),
            'error' => $result->errorCode,
        ]);

        return 'manual';
    }

    /**
     * Review 2 (H1): the transaction.cancel webhook, applied only once Paylov itself reports the transaction cancelled.
     * Its Basic Auth is no proof: a store on its own Paylov account knows its callback login and password, and an
     * unverified cancel would refund a delivered order (its commission and settlement reversed) while the money stays
     * in the store's account. The status is read with the account the order was paid into. Returns closed / refunded
     * (applied), noop (applied before: a retry), not_cancelled (Paylov says otherwise: logged, ignored) or unverified
     * (Paylov did not answer: `cancel_reported_at` is kept and the scheduled re-check asks again).
     */
    public function verifyCancel(Payment $payment, ?int $cancelTime = null): string
    {
        $payment = Payment::with('order')->find($payment->id);
        if (!$payment || !in_array($payment->status, [PaymentStatus::PENDING, PaymentStatus::PAID], true)) {
            return 'noop';
        }

        $transactionId = (string) $payment->provider_transaction_id;
        $result = $this->api->transaction(PaylovAccount::forOrder($payment->order), $transactionId);
        $transaction = $result->ok ? $this->transactionOf($result, $transactionId) : null;
        $status = is_array($transaction) ? strtolower((string) ($transaction['status'] ?? '')) : null;

        if ($status === null) {
            $reported = self::state($payment)['cancel_reported_at'] ?? null;
            if (!is_string($reported)) {
                $this->note($payment, ['cancel_reported_at' => now()->toIso8601String(), 'cancel_reported_time' => $cancelTime]);
            } elseif (now()->subMinutes(self::CANCEL_STALE_MINUTES)->gt(\Illuminate\Support\Carbon::parse($reported))
                && $this->noteOnce((int) $payment->id, 'cancel_alerted_at')) {
                Log::critical('Paylov cancel webhook still not confirmed by Paylov after an hour: check the transaction in the Paylov business panel', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                ]);
            }
            Log::warning('Paylov cancel webhook not applied yet: Paylov did not confirm it, asked again later', [
                'payment_id' => $payment->id,
                'error' => $result->ok ? 'not_listed' : $result->errorCode,
            ]);

            return 'unverified';
        }

        if (in_array($status, self::CANCELLED_STATUSES, true) && self::accountMatches($transaction, $payment) !== false) {
            $time = $cancelTime ?? (is_numeric(self::state($payment)['cancel_reported_time'] ?? null) ? (int) self::state($payment)['cancel_reported_time'] : null);

            return $this->applyCancel($payment, 'webhook', $time);
        }

        // Paylov says the money is still there (or the transaction is another order's): a forged or mistaken cancel
        $this->note($payment, [], ['cancel_reported_at', 'cancel_reported_time']);
        Log::warning('Paylov cancel webhook not confirmed by Paylov: ignored', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'paylov_status' => mb_substr($status, 0, 32),
        ]);

        return 'not_cancelled';
    }

    /**
     * Sets payload.paylov.$key to now under the payment's lock unless it is set already; whether it was set now (an
     * alert sent once). Review 1 note: never a save of a payload read before, which would erase another writer's change.
     */
    public function noteOnce(int $paymentId, string $key): bool
    {
        return DB::transaction(function () use ($paymentId, $key) {
            $locked = Payment::whereKey($paymentId)->lockForUpdate()->first();
            if (!$locked || !empty(self::state($locked)[$key])) {
                return false;
            }
            $this->write($locked, [], [$key => now()->toIso8601String()]);

            return true;
        });
    }

    /**
     * Records that Paylov cancelled the transaction (by us, from its business panel, or by its staff), exactly once.
     * A pending (unconfirmed) payment is closed; a paid one is refunded, and its order follows as with Payme's
     * CancelTransaction: a delivered order is refunded (its commission and settlement reversed), a cancelled or refunded
     * one is marked refunded, one still in progress is no longer paid. Returns closed, refunded or noop.
     *
     * @param  'merchant'|'webhook'  $by
     */
    public function applyCancel(Payment $payment, string $by, ?int $cancelTime = null): string
    {
        [$outcome, $refunded] = DB::transaction(function () use ($payment, $by, $cancelTime) {
            $order = $payment->order_id ? Order::whereKey($payment->order_id)->lockForUpdate()->first() : null;
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (!$locked) {
                return ['noop', null];
            }

            $cancel = [
                'cancelled_by' => $by,
                'cancel_time' => $cancelTime,
                'cancel_recorded_at' => now()->toIso8601String(),
            ];

            if ($locked->status === PaymentStatus::PENDING) {
                $this->write($locked, ['status' => PaymentStatus::CANCELLED, 'cancelled_at' => now()], ['state' => self::STATE_CANCELLED] + $cancel, ['cancel_reported_at']);

                return ['closed', null];
            }

            if ($locked->status !== PaymentStatus::PAID) {
                return ['noop', null];
            }

            $state = self::state($locked);
            $refund = is_array($state['refund'] ?? null) ? $state['refund'] : [];
            $this->write($locked, ['status' => PaymentStatus::REFUNDED, 'cancelled_at' => now()], $cancel + [
                'refund' => array_merge($refund, ['status' => self::REFUND_DONE, 'done_at' => now()->toIso8601String()]),
            ], ['cancel_reported_at']);

            // Money taken for an order that could not take it never was the order's
            if (($state['refused'] ?? null) !== null || $order === null) {
                return ['refunded', $locked];
            }

            if ($order->status === OrderStatus::DELIVERED) {
                $order->update(['payment_status' => PaymentStatus::REFUNDED->value]);
                app(OrderStatusService::class)->refund($order, null, "Paylov to'lovni mijozga qaytardi.", true);
                Log::warning("Paylov refund after delivery for order {$order->uuid}: order refunded", [
                    'order_id' => $order->id,
                    'payment_id' => $locked->id,
                    'payment_owner' => $order->payment_owner,
                    'amount' => Money::of($locked->amount)->toString(),
                ]);
            } elseif (in_array($order->status, [OrderStatus::REFUNDED, OrderStatus::CANCELED, OrderStatus::REJECTED], true)) {
                $order->update(['payment_status' => PaymentStatus::REFUNDED->value]);
            } else {
                // Security review F1: given back while the order is still in progress, no part of what delivery books
                // the commission on (RefundRequestService::deliveryShareOf)
                $locked->forceFill(['payload' => array_merge($locked->payload ?? [], ['refunded_before_delivery' => true])])->save();
                if ($order->payment_status === PaymentStatus::PAID) {
                    // Still in progress: the money went back, so the order is no longer paid (as Payme's CancelTransaction)
                    $order->update(['payment_status' => PaymentStatus::PENDING->value]);
                    Log::warning("Paylov cancelled the payment of order {$order->uuid} still in progress: it is unpaid again", [
                        'order_id' => $order->id,
                        'payment_id' => $locked->id,
                        'status' => $order->status->value,
                    ]);
                }
            }

            return ['refunded', $locked];
        });

        if ($outcome === 'refunded') {
            $this->queueFiscalRefund($refunded);
        }

        return $outcome;
    }

    /**
     * PaymentPaid after the commit; a listener failure is logged (ids only) and never changes the answer to Paylov. A
     * replay re-fires it only for an order still paid (never revives a refunded or reverted one), as Payme's.
     */
    public function firePaid(Payment $payment, bool $replay = false): void
    {
        if (!$payment->order_id || (self::state($payment)['refused'] ?? null) !== null) {
            return;
        }

        try {
            if ($replay && Order::whereKey($payment->order_id)->first(['id', 'payment_status'])?->payment_status !== PaymentStatus::PAID) {
                return;
            }

            event(new PaymentPaid($payment));
        } catch (\Throwable $e) {
            Log::error('Paylov PaymentPaid listener failed after the payment was committed: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'replay' => $replay,
            ]);
        }
    }

    private function queueFiscalReceipt(Payment $payment): void
    {
        try {
            if (app(OnlineFiscalReceiptBuilder::class)->isEnabled($payment->order?->store)) {
                RegisterPaylovFiscalReceipt::dispatch((int) $payment->id);
            }
        } catch (\Throwable $e) {
            Log::error('Paylov fiscal receipt could not be queued: ' . $e->getMessage(), ['payment_id' => $payment->id]);
        }
    }

    private function queueFiscalRefund(Payment $payment): void
    {
        $fiscal = $payment->payload['fiscal'] ?? null;
        if (!is_array($fiscal) || ($fiscal['provider'] ?? null) !== 'paylov' || ($fiscal['status'] ?? null) !== OnlineFiscalRecorder::STATUS_RECORDED) {
            return;
        }

        try {
            RefundPaylovFiscalReceipt::dispatch((int) $payment->id);
        } catch (\Throwable $e) {
            Log::error('Paylov refund receipt could not be queued: ' . $e->getMessage(), ['payment_id' => $payment->id]);
        }
    }

    /**
     * An exact whole number of tiyin (an int, or a float / numeric string with no fraction), else null: never rounded.
     */
    public static function wholeTiyin(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if ((is_float($value) || is_string($value)) && is_numeric($value)) {
            try {
                $decimal = \Brick\Math\BigDecimal::of(is_float($value) ? sprintf('%.6F', $value) : trim($value));

                return $decimal->hasNonZeroFractionalPart() ? null : $decimal->toBigInteger()->toInt();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * The transaction with this id in a getTransactions answer, or null.
     *
     * @return array<string, mixed>|null
     */
    private function transactionOf(PaylovResult $result, string $transactionId): ?array
    {
        $transactions = $result->result['transactions'] ?? null;
        if (!is_array($transactions)) {
            return null;
        }

        foreach ($transactions as $transaction) {
            if (is_array($transaction) && strcasecmp((string) ($transaction['id'] ?? ''), $transactionId) === 0) {
                return $transaction;
            }
        }

        return null;
    }

    /**
     * Merges $state into payload.paylov (no other column), under the payment's lock.
     *
     * @param  array<string, mixed>  $state
     * @param  list<string>  $forget  keys of payload.paylov to remove
     */
    private function note(Payment $payment, array $state, array $forget = []): void
    {
        DB::transaction(function () use ($payment, $state, $forget) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if ($locked) {
                $this->write($locked, [], $state, $forget);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $columns
     * @param  array<string, mixed>  $state  merged into payload.paylov
     * @param  list<string>  $forget  keys of payload.paylov to remove
     */
    private function write(Payment $payment, array $columns, array $state, array $forget = []): void
    {
        $payload = $payment->payload ?? [];
        $payload['paylov'] = array_diff_key(
            array_merge(is_array($payload['paylov'] ?? null) ? $payload['paylov'] : [], $state),
            array_flip($forget),
        );

        $payment->forceFill($columns + ['payload' => $payload])->save();
    }
}
