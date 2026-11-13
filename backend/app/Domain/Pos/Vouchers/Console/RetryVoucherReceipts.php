<?php

namespace App\Domain\Pos\Vouchers\Console;

use App\Domain\Pos\Vouchers\VoucherService;
use Illuminate\Console\Command;

/**
 * Review C12 (P6 wave 4), scheduled hourly (PosVouchersServiceProvider): certificates sold in the last
 * VoucherService::RECEIPT_RETRY_DAYS days whose ADVANCE receipt could not be asked for (`error`) or that the provider
 * refused (`failed`) are sent again under the same receipt key (never a second receipt); a pending one is then the
 * fiscal queue's (RetryFiscalReceipt, fiscal:retry-pending).
 */
class RetryVoucherReceipts extends Command
{
    protected $signature = 'pos:vouchers-retry-receipts {--limit=100 : at most this many certificates per run}';

    protected $description = "Send again the advance receipts of gift certificates that were not issued (safe to run any time)";

    public function handle(VoucherService $vouchers): int
    {
        $count = $vouchers->retryReceipts(max(1, (int) $this->option('limit')));
        $this->info("{$count} certificate receipt(s) sent again");

        return self::SUCCESS;
    }
}
