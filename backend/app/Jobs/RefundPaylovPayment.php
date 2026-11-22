<?php

namespace App\Jobs;

use App\Services\Payment\Paylov\PaylovPayments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;

/**
 * Wave P: the full refund of a paid Paylov payment through payment/cancel (PaylovPayments::refund), queued once the
 * cancellation or refund that asked for it is committed. A passing failure is asked again with backoff, then by the
 * scheduled `payments:paylov-reconcile`; a refusal for good flags the payment for a manual refund.
 */
class RefundPaylovPayment implements ShouldQueueAfterCommit
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
        return [30, 120, 600];
    }

    public function handle(PaylovPayments $payments): void
    {
        $outcome = $payments->refund($this->paymentId);

        if ($outcome === 'retry' && $this->job && !$this->job instanceof SyncJob && $this->attempts() < $this->tries) {
            $this->release($this->backoff()[$this->attempts() - 1] ?? 600);
        }
    }
}
