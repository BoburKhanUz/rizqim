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
 *    minutes (routes/console.php), which also alerts once about one still unconfirmed after an hour. A `payed` answer for
 *    another amount is state `mismatch`: never settled, never re-checked, a critical log for the team;
 *  - money Paylov took for an order that cannot take it (paid by another transaction, cancelled meanwhile: perform was
 *    answered +1 and payload.paylov.refused says why) is `paid` on the payment only, never on the order, and given back
 *    in full through payment/cancel (refund());
 *  - refund() is the full refund (subscribe/cancel-payment has no amount); applyCancel() records a cancellation however
 *    it came (our own payment/cancel, or the transaction.cancel webhook, which Paylov retries up to 3 times), once.
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

        if ($status === self::PAID_STATUS) {
            $tiyin = self::wholeTiyin($transaction['amount_in_tiyin'] ?? null);
            if ($tiyin === null || $tiyin !== Money::of($payment->amount)->toTiyin()) {
                return $this->mismatch($payment, $tiyin);
            }

            return $this->settle($payment);
        }

        if ($status !== null && isset(self::CLOSED_STATUSES[$status])) {
            return $this->close($payment, self::CLOSED_STATUSES[$status], $status);
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

    private function close(Payment $payment, PaymentStatus $to, string $paylovStatus): string
    {
        return DB::transaction(function () use ($payment, $to, $paylovStatus) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (!$locked || !self::isUnconfirmed($locked)) {
                return 'noop';
            }

            $this->write($locked, ['status' => $to, 'cancelled_at' => now()], [
                'state' => self::STATE_REJECTED,
                'paylov_status' => $paylovStatus,
                'closed_at' => now()->toIso8601String(),
            ]);

            return self::STATE_REJECTED;
        });
    }

    private function mismatch(Payment $payment, mixed $tiyin): string
    {
        $this->note($payment, [
            'state' => self::STATE_MISMATCH,
            'reported_tiyin' => is_scalar($tiyin) ? (string) $tiyin : null,
            'last_check_at' => now()->toIso8601String(),
        ]);
        Log::critical('Paylov reports another amount for a performed transaction: not settled, check it in the Paylov business panel', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'expected_tiyin' => Money::of($payment->amount)->toTiyin(),
            'reported_tiyin' => is_scalar($tiyin) ? $tiyin : null,
        ]);

        return self::STATE_MISMATCH;
    }

    /**
     * The full refund of a paid Paylov payment through payment/cancel (subscribe/cancel-payment). Returns refunded,
     * retry (Paylov did not answer, or answered a passing error: asked again later), manual (refused for good: flagged
     * for a manual refund) or noop.
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

        // Already cancelled at Paylov (its panel, or our earlier call whose answer was lost): read it back
        if ($result->errorCode === 'transaction_not_available_for_cancel') {
            $status = $this->api->transaction($account, $transactionId);
            $transaction = $status->ok ? $this->transactionOf($status, $transactionId) : null;
            if (is_array($transaction) && in_array(strtolower((string) ($transaction['status'] ?? '')), ['cancel', 'refunded'], true)) {
                $this->applyCancel($payment, 'merchant');

                return 'refunded';
            }
        }

        $attempts = (int) ($refund['attempts'] ?? 0) + 1;
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
                $this->write($locked, ['status' => PaymentStatus::CANCELLED, 'cancelled_at' => now()], ['state' => self::STATE_CANCELLED] + $cancel);

                return ['closed', null];
            }

            if ($locked->status !== PaymentStatus::PAID) {
                return ['noop', null];
            }

            $state = self::state($locked);
            $refund = is_array($state['refund'] ?? null) ? $state['refund'] : [];
            $this->write($locked, ['status' => PaymentStatus::REFUNDED, 'cancelled_at' => now()], $cancel + [
                'refund' => array_merge($refund, ['status' => self::REFUND_DONE, 'done_at' => now()->toIso8601String()]),
            ]);

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
            } elseif ($order->payment_status === PaymentStatus::PAID) {
                // Still in progress: the money went back, so the order is no longer paid (as Payme's CancelTransaction)
                $order->update(['payment_status' => PaymentStatus::PENDING->value]);
                Log::warning("Paylov cancelled the payment of order {$order->uuid} still in progress: it is unpaid again", [
                    'order_id' => $order->id,
                    'payment_id' => $locked->id,
                    'status' => $order->status->value,
                ]);
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
     */
    private function note(Payment $payment, array $state): void
    {
        DB::transaction(function () use ($payment, $state) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if ($locked) {
                $this->write($locked, [], $state);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $columns
     * @param  array<string, mixed>  $state  merged into payload.paylov
     */
    private function write(Payment $payment, array $columns, array $state): void
    {
        $payload = $payment->payload ?? [];
        $payload['paylov'] = array_merge(is_array($payload['paylov'] ?? null) ? $payload['paylov'] : [], $state);

        $payment->forceFill($columns + ['payload' => $payload])->save();
    }
}
