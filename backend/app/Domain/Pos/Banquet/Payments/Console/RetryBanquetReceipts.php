<?php

namespace App\Domain\Pos\Banquet\Payments\Console;

use App\Domain\Pos\Banquet\Payments\BanquetReceiptRetry;
use Illuminate\Console\Command;

/**
 * Review C5 (P6 wave 5), scheduled hourly (PosBanquetPaymentsServiceProvider): banquet advance, refund and retained
 * receipts of the last BanquetReceiptRetry::RETRY_DAYS days that were not issued are sent again under the same key
 * (never a second receipt). Safe to run any time.
 */
class RetryBanquetReceipts extends Command
{
    protected $signature = 'pos:banquet-retry-receipts {--limit=100 : at most this many entries per run}';

    protected $description = 'Send again the banquet advance, refund and retained receipts that were not issued (safe to run any time)';

    public function handle(BanquetReceiptRetry $retry): int
    {
        $count = $retry->run(max(1, (int) $this->option('limit')));
        $this->info("{$count} banquet receipt(s) sent again");

        return self::SUCCESS;
    }
}
