<?php

namespace App\Jobs;

use App\Services\Finance\SubscriptionService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * P1-23: monthly subscription invoices (docs/BILLING.md). Scheduled every day at 00:15 Asia/Tashkent: on the 1st it
 * bills the month that just ended; on other days it only catches up a missed run and updates paid / past_due
 * statuses. Idempotent: running it again creates nothing. Not queued: it runs inside the scheduler.
 */
class BillSubscriptionsJob
{
    use Dispatchable;

    public function handle(SubscriptionService $subscriptions): void
    {
        $created = $subscriptions->run();

        if ($created > 0) {
            Log::info("Subscription billing: {$created} invoice(s) issued");
        }
    }
}
