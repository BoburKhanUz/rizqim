<?php

namespace App\Services\Payment\Paylov;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Wave P: the scheduled re-check (`payments:paylov-reconcile`, every five minutes): every Paylov payment still
 * `unconfirmed` is read back from Paylov again (PaylovPayments::confirm), and every full refund Paylov has not done yet
 * is asked again (PaylovPayments::refund). An unconfirmed payment older than PaylovPayments::STALE_MINUTES is alerted
 * once (a critical log the monitoring picks up) and still checked after that: a payment is never dropped. Review 2: a
 * paid payment whose cancel webhook Paylov has not confirmed yet (`cancel_reported_at`) is asked about again
 * (PaylovPayments::verifyCancel).
 *
 * Runs outside any tenant request (console), so the payments of every store are read: no store scope applies here.
 */
class PaylovReconciler
{
    public function __construct(private readonly PaylovPayments $payments) {}

    /**
     * @return array{checked: int, refunds: int, cancels: int}
     */
    public function run(): array
    {
        $checked = 0;
        Payment::query()
            ->where('provider', 'paylov')
            ->where('status', PaymentStatus::PENDING->value)
            ->whereNotNull('provider_transaction_id')
            ->orderBy('id')
            ->chunkById(100, function ($payments) use (&$checked) {
                foreach ($payments as $payment) {
                    if (!PaylovPayments::isUnconfirmed($payment)) {
                        continue;
                    }
                    $checked++;
                    if ($this->payments->confirm((int) $payment->id) === PaylovPayments::STATE_UNCONFIRMED) {
                        $this->alertIfStale($payment);
                    }
                }
            });

        $refunds = 0;
        $cancels = 0;
        Payment::query()
            ->where('provider', 'paylov')
            ->where('status', PaymentStatus::PAID->value)
            // A refund being asked for touches the row; one untouched for a week is not waiting for anything here
            ->where('updated_at', '>=', now()->subDays(7))
            ->orderBy('id')
            ->chunkById(100, function ($payments) use (&$refunds, &$cancels) {
                foreach ($payments as $payment) {
                    $state = PaylovPayments::state($payment);
                    $refund = $state['refund'] ?? null;
                    if (is_array($refund) && in_array($refund['status'] ?? null, [PaylovPayments::REFUND_REQUESTED, PaylovPayments::REFUND_RETRYING], true)) {
                        $refunds++;
                        $this->payments->refund((int) $payment->id);
                    }
                    // verifyCancel reads the row again: a refund just done above makes it a noop
                    if (!empty($state['cancel_reported_at'])) {
                        $cancels++;
                        $this->payments->verifyCancel($payment);
                    }
                }
            });

        return ['checked' => $checked, 'refunds' => $refunds, 'cancels' => $cancels];
    }

    /**
     * Review 1 note: the alert's mark is written under the payment's lock (PaylovPayments::noteOnce), never by saving a
     * payload read before (which would erase what a webhook or a refund wrote meanwhile), and only once.
     */
    private function alertIfStale(Payment $payment): void
    {
        if ($payment->created_at === null || $payment->created_at->gt(now()->subMinutes(PaylovPayments::STALE_MINUTES))
            || !$this->payments->noteOnce((int) $payment->id, 'stale_alerted_at')) {
            return;
        }

        $fresh = Payment::find($payment->id);
        Log::critical('Paylov payment unconfirmed for over an hour: check the transaction in the Paylov business panel', [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'amount' => (string) $payment->amount,
            'last_check' => $fresh ? (PaylovPayments::state($fresh)['last_check'] ?? null) : null,
        ]);
    }
}
