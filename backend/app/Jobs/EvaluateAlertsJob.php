<?php

namespace App\Jobs;

use App\Services\Admin\Monitoring\AlertEvaluator;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * P4-15: runs the alert rules (AlertEvaluator) every minute (App\Providers\AdminMonitoringServiceProvider).
 *
 * Deliberately NOT queued: the scheduler runs it in its own process, so a queue that is backed up or has no worker (the
 * very things a rule watches) cannot delay or swallow the check. A run that fails is not repeated: the next minute is
 * the retry.
 */
class EvaluateAlertsJob
{
    use Dispatchable;

    public function handle(AlertEvaluator $evaluator): void
    {
        $evaluator->run();
    }

    public function displayName(): string
    {
        return 'monitoring-evaluate-alerts';
    }
}
