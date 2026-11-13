<?php

namespace App\Domain\Pos\QrPayments;

use Illuminate\Console\Command;

/**
 * P6-41: every minute, the QRs whose time ran out (and that no provider is paying) become `expired` (qr.expired on
 * the branch channel). Reading a QR expires it too, so a missed run only delays the hint. Review P6W4 C2: the same run
 * asks the providers about QR refunds left `unknown` (QrRefunds::reconcile) and records the ones they reversed.
 */
class ExpireQrPaymentsCommand extends Command
{
    protected $signature = 'pos:expire-qr-payments';

    protected $description = 'Expire POS QR payment requests whose time ran out and resolve unanswered QR refunds (P6-41)';

    public function handle(QrPaymentService $service, QrRefunds $refunds): int
    {
        $count = $service->expireDue();
        if ($count > 0) {
            $this->info("Expired {$count} QR payment request(s).");
        }
        $resolved = $refunds->reconcile();
        if ($resolved > 0) {
            $this->info("Resolved {$resolved} QR refund attempt(s).");
        }

        return self::SUCCESS;
    }
}
