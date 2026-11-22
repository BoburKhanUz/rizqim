<?php

namespace App\Jobs;

use App\Services\Payment\Paylov\PaylovPayments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;

/**
 * Wave P: reads a performed Paylov transaction back (getTransactions) and settles it (PaylovPayments::confirm), on the
 * queue right after the perform was answered. While it stays unconfirmed it is asked again with backoff; after that
 * the scheduled `payments:paylov-reconcile` keeps checking it every five minutes, so nothing is ever dropped.
 */
class ConfirmPaylovPayment implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 4;

    public int $timeout = 60;

    public function __construct(public int $paymentId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [20, 60, 180];
    }

    public function handle(PaylovPayments $payments): void
    {
        $outcome = $payments->confirm($this->paymentId);

        // The sync driver (tests, local) cannot wait: the scheduled re-check picks it up
        if ($outcome === PaylovPayments::STATE_UNCONFIRMED && $this->job && !$this->job instanceof SyncJob && $this->attempts() < $this->tries) {
            $this->release($this->backoff()[$this->attempts() - 1] ?? 180);
        }
    }
}
