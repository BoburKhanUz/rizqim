<?php

namespace App\Domain\Pos\QrPayments;

use Illuminate\Console\Command;

/**
 * P6-41: every minute, the QRs whose time ran out (and that no provider is paying) become `expired` (qr.expired on
 * the branch channel). Reading a QR expires it too, so a missed run only delays the hint. Review P6W4 C2: the same run
 * asks the providers about QR refunds left `unknown` (QrRefunds::reconcile) and records the ones they reversed. Wave P
 * (review 11): and asks the UzQR operator about the UzQRs that ended here but that it has not settled
 * (QrPaymentService::recheckEndedUzQr): a payment that still went through is taken or kept for reconciliation, never
 * lost. One run is bounded (qr_payments.uzqr.recheck.run_seconds; the asks use the short poll timeouts): it stays
 * well inside the schedule's withoutOverlapping lock, and what it did not reach goes first next minute.
 */
class ExpireQrPaymentsCommand extends Command
{
    protected $signature = 'pos:expire-qr-payments';

    protected $description = 'Expire POS QR payment requests whose time ran out and resolve unanswered QR refunds (P6-41)';

    public function handle(QrPaymentService $service, QrRefunds $refunds): int
    {
        $until = microtime(true) + (int) config('qr_payments.uzqr.recheck.run_seconds', 40);
        $count = $service->expireDue($until);
        if ($count > 0) {
            $this->info("Expired {$count} QR payment request(s).");
        }
        $resolved = $refunds->reconcile($until);
        if ($resolved > 0) {
            $this->info("Resolved {$resolved} QR refund attempt(s).");
        }
        $late = $service->recheckEndedUzQr(null, $until);
        if ($late > 0) {
            $this->info("Settled {$late} late UzQR payment(s).");
        }

        return self::SUCCESS;
    }
}
