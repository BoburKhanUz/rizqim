<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Finance\SettlementRecoveryService;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Books again the delivered orders whose ledger booking failed (SettlementRecoveryService), every 10 minutes, until
 * each succeeds or reaches config('orders.settlement_retry_max_attempts'). Not queued: it runs inside the scheduler,
 * needing no queue worker.
 */
class RetryFailedSettlementsJob
{
    use Dispatchable;

    public function handle(SettlementRecoveryService $settlements): void
    {
        Order::query()
            ->whereNotNull('settlement_failed_at')
            ->where('settlement_attempts', '<', $settlements->maxAttempts())
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $orderId) use ($settlements) {
                try {
                    $settlements->retry($orderId);
                } catch (Throwable $e) {
                    // e.g. the lock timed out: the next run tries again; one order never holds up the others
                    Log::error("Ledger booking retry of order {$orderId} could not run: {$e->getMessage()}");
                }
            });
    }
}
