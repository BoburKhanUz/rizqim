<?php

namespace App\Domain\Pos\QrPayments;

use Illuminate\Console\Command;

/**
 * P6-41: every minute, the QRs whose time ran out (and that no provider is paying) become `expired` (qr.expired on
 * the branch channel). Reading a QR expires it too, so a missed run only delays the hint.
 */
class ExpireQrPaymentsCommand extends Command
{
    protected $signature = 'pos:expire-qr-payments';

    protected $description = 'Expire POS QR payment requests whose time ran out (P6-41)';

    public function handle(QrPaymentService $service): int
    {
        $count = $service->expireDue();
        if ($count > 0) {
            $this->info("Expired {$count} QR payment request(s).");
        }

        return self::SUCCESS;
    }
}
