<?php

namespace App\Jobs;

use App\Services\Finance\Billing\DunningService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * P4-21: the daily unpaid-invoice routine (docs/BILLING.md, "Eslatmalar va cheklov"): reminders 1, 3 and 7 days after
 * the due date, the lock a week after it. Scheduled at config('billing.dunning.run_at') Asia/Tashkent
 * (BillingServiceProvider). Idempotent: running it again sends nothing twice. Not queued: it runs in the scheduler.
 */
class SendBillingRemindersJob
{
    use Dispatchable;

    public function handle(DunningService $dunning): void
    {
        $result = $dunning->run();

        if ($result['reminders'] > 0 || $result['locked'] > 0) {
            Log::info("Billing dunning: {$result['reminders']} reminder(s), {$result['locked']} lock(s)");
        }
    }
}
