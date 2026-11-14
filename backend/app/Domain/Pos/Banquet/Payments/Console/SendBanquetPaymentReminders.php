<?php

namespace App\Domain\Pos\Banquet\Payments\Console;

use App\Domain\Pos\Banquet\Payments\BanquetReminders;
use Illuminate\Console\Command;

/**
 * P6-53, scheduled daily (PosBanquetPaymentsServiceProvider): Telegram reminders of banquet payments due soon or
 * overdue (BanquetReminders). Idempotent per row and day: safe to run again.
 */
class SendBanquetPaymentReminders extends Command
{
    protected $signature = 'pos:banquet-payment-reminders';

    protected $description = 'Remind the store owners of banquet payments due soon or overdue (once per row and day)';

    public function handle(BanquetReminders $reminders): int
    {
        $count = $reminders->run();
        $this->info("{$count} banquet payment reminder(s) sent");

        return self::SUCCESS;
    }
}
