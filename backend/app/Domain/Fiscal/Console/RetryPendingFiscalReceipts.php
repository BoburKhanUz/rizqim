<?php

namespace App\Domain\Fiscal\Console;

use App\Domain\Fiscal\FiscalService;
use Illuminate\Console\Command;

/**
 * Scheduled every minute (FiscalServiceProvider): pending receipts whose next attempt is due, whether or not their
 * queued RetryFiscalReceipt job still exists, get an attempt; those out of attempts become failed.
 */
class RetryPendingFiscalReceipts extends Command
{
    protected $signature = 'fiscal:retry-pending {--limit=100 : at most this many receipts per run}';

    protected $description = 'Send again the fiscal receipts still waiting for the provider (safe to run any time)';

    public function handle(FiscalService $fiscal): int
    {
        $count = $fiscal->sweep(max(1, (int) $this->option('limit')));
        $this->info("{$count} receipt(s) attempted");

        return self::SUCCESS;
    }
}
