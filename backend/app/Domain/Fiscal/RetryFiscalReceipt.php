<?php

namespace App\Domain\Fiscal;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * The next attempt at a pending receipt, queued for the time FiscalService set (next_attempt_at, with backoff). A job
 * that runs early, twice or after the receipt was issued does nothing (FiscalService::attempt claims the receipt).
 * Lost jobs are covered by the fiscal:retry-pending sweeper.
 */
class RetryFiscalReceipt implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $receiptId) {}

    public function handle(FiscalService $fiscal): void
    {
        $fiscal->attempt($this->receiptId);
    }
}
