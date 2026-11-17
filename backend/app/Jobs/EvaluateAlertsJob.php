<?php

namespace App\Jobs;

use App\Services\Admin\Monitoring\AlertEvaluator;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * P4-15: runs the alert rules (AlertEvaluator) every minute (App\Providers\AdminMonitoringServiceProvider).
 *
 * Deliberately NOT queued: the scheduler runs it in its own process, so a queue that is backed up or has no worker (the
 * very things a rule watches) cannot delay or swallow the check. A run that fails is not repeated: the next minute is
 * the retry.
 *
 * M3: after a run that finished, the dead-man switch HEALTHCHECKS_PING_URL (healthchecks.io or alike) is pinged; when
 * the pings stop (server, scheduler or database down) that outside service tells the owner. Unset: no call.
 */
class EvaluateAlertsJob
{
    use Dispatchable;

    public function handle(AlertEvaluator $evaluator): void
    {
        $evaluator->run();
        $this->ping();
    }

    public function displayName(): string
    {
        return 'monitoring-evaluate-alerts';
    }

    private function ping(): void
    {
        $url = trim((string) config('monitoring.healthchecks_ping_url'));
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return;
        }

        try {
            Http::timeout((int) config('monitoring.healthchecks_timeout', 3))->connectTimeout(2)->get($url);
        } catch (Throwable $e) {
            // The outside service alerts on missing pings; a failed ping is no reason to fail the evaluation
            Log::warning('Monitoring: dead-man ping failed', ['exception' => $e::class]);
        }
    }
}
