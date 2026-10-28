<?php

namespace App\Services\Admin\Monitoring;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\MonitoringMarker;
use App\Support\HealthReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * P4-15: GET /admin/monitoring/health. The database, Redis, queue and scheduler verdicts are App\Support\HealthReport's
 * (the same report as GET /health, with its numbers), not a second set of checks; this adds what only the team sees:
 * every queue with its oldest waiting job, failed jobs, Reverb reachability, the last backup, app version and Sentry.
 */
class MonitoringStatus
{
    public function __construct(
        private readonly HealthReport $health,
        private readonly QueueInspector $queues,
        private readonly FailedJobs $failedJobs,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $report = $this->health->report();
        $checks = $report['checks'];

        $reverb = $this->reverb();
        $backup = $this->backup();
        $status = $report['status'];
        if ($status === 'ok' && (in_array($reverb['status'], ['fail'], true) || in_array($backup['status'], ['stale', 'missing'], true))) {
            $status = 'degraded';
        }

        $latest = $this->failedJobs->latestFailedAt();

        return [
            'status' => $status,
            'checked_at' => now()->toIso8601String(),
            'database' => $checks['database'],
            'redis' => $checks['redis'],
            'queue' => [
                'status' => $checks['queue']['status'],
                'queues' => $this->queues->snapshot(),
                'failed_jobs' => [
                    'total' => $this->failedJobs->count(),
                    'last_24h' => $this->failedJobs->count(now()->subDay()),
                    'latest_failed_at' => $latest?->toIso8601String(),
                ],
            ],
            'scheduler' => $checks['scheduler'] + ['max_age_seconds' => (int) config('health.scheduler_max_age')],
            'reverb' => $reverb,
            'backup' => $backup,
            'app' => [
                'version' => config('monitoring.version') ?: null,
                'commit' => config('monitoring.commit') ?: null,
                'environment' => (string) config('app.env'),
            ],
            'sentry' => [
                'configured' => filled(config('sentry.dsn')),
                'environment' => filled(config('sentry.dsn')) ? (string) config('sentry.environment') : null,
                'project_url' => config('monitoring.sentry_project_url') ?: null,
            ],
            'alerts' => [
                'open' => Alert::query()->where('state', AlertState::OPEN->value)->count(),
                'acknowledged' => Alert::query()->where('state', AlertState::ACKNOWLEDGED->value)->count(),
                'critical_unresolved' => Alert::query()->unresolved()->where('severity', 'critical')->count(),
            ],
        ];
    }

    /**
     * ok | fail | not_configured (real-time is switched off, BROADCAST_CONNECTION is not reverb). A plain request to
     * the server's /up route, no credentials; the result is kept for a few seconds because the tab polls.
     *
     * @return array{status: string, latency_ms: float|null}
     */
    public function reverb(): array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return ['status' => 'not_configured', 'latency_ms' => null];
        }

        return Cache::remember('monitoring:reverb', (int) config('monitoring.reverb.cache_seconds', 15), function () {
            $options = (array) config('reverb.apps.apps.0.options', []);
            $url = sprintf('%s://%s:%s/up', $options['scheme'] ?? 'http', $options['host'] ?? '127.0.0.1', $options['port'] ?? 8080);

            try {
                $started = microtime(true);
                $response = Http::timeout((int) config('monitoring.reverb.timeout', 2))->get($url);

                return [
                    'status' => $response->successful() ? 'ok' : 'fail',
                    'latency_ms' => round((microtime(true) - $started) * 1000, 1),
                ];
            } catch (Throwable $e) {
                Log::warning('Monitoring: Reverb is not reachable', ['exception' => $e::class]);

                return ['status' => 'fail', 'latency_ms' => null];
            }
        });
    }

    /**
     * ok | stale (older than the backup_stale rule's hours) | missing (nothing recorded).
     *
     * @return array{status: string, last_backup_at: string|null, age_hours: int|null, max_age_hours: int}
     */
    public function backup(): array
    {
        $maxHours = (int) (AlertRule::query()->where('kind', AlertKind::BACKUP_STALE->value)->value('threshold') ?? AlertKind::BACKUP_STALE->defaults()['threshold']);
        $marker = MonitoringMarker::query()->find(MonitoringMarker::BACKUP);

        if ($marker === null) {
            return ['status' => 'missing', 'last_backup_at' => null, 'age_hours' => null, 'max_age_hours' => $maxHours];
        }

        $recorded = Carbon::instance($marker->recorded_at);
        $hours = (int) floor($recorded->diffInHours(now(), true));

        return [
            'status' => $recorded->lt(now()->subHours($maxHours)) ? 'stale' : 'ok',
            'last_backup_at' => $recorded->toIso8601String(),
            'age_hours' => $hours,
            'max_age_hours' => $maxHours,
        ];
    }
}
