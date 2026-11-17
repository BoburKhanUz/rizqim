<?php

namespace App\Services\Admin\Monitoring;

use App\Jobs\QueueHeartbeatJob;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\MonitoringMarker;
use App\Support\HealthReport;
use App\Support\Partitions\PartitionManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * P4-15: GET /admin/monitoring/health. The database, Redis, queue and scheduler verdicts are App\Support\HealthReport's
 * (the same report as GET /health, with its numbers), not a second set of checks; this adds what only the team sees:
 * every queue with its oldest waiting job, failed jobs, Reverb reachability, the last backup, app version and Sentry.
 *
 * M3: and the server itself: `postgres` (connections, longest transaction, lock waits, WAL archiver), `pitr` (the
 * continuous backup: last backups, last WAL sent, the off-site copy), `drill` (the last restore drill), `partitions`
 * (the journals' months), `disk` and `redis.memory`. Thresholds are the alert rules' own, so the page and the alerts
 * agree. Something that could not be measured says so (`unknown`, `missing`, `stale`), never `ok`.
 */
class MonitoringStatus
{
    /** @var \Illuminate\Support\Collection<string, AlertRule>|null */
    private ?\Illuminate\Support\Collection $rules = null;

    public function __construct(
        private readonly HealthReport $health,
        private readonly QueueInspector $queues,
        private readonly FailedJobs $failedJobs,
        private readonly PostgresStats $postgres,
        private readonly BackupState $backups,
        private readonly RedisStats $redis,
        private readonly PartitionManager $partitionManager,
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
        $archiver = $this->postgres->archiver();
        $postgres = $this->postgresState($archiver);
        $pitr = $this->pitr($archiver);
        $drill = $this->drill();
        $partitions = $this->partitions();
        $disk = $this->disk();
        $memory = $this->redis->memory();
        $worker = QueueHeartbeatJob::last();

        $status = $report['status'];
        $needsLook = in_array($reverb['status'], ['fail'], true)
            || in_array($backup['status'], ['stale', 'missing'], true)
            || in_array($pitr['status'], ['fail', 'stale', 'missing'], true)
            || $drill['status'] === 'failed'
            || in_array($disk['status'], ['warning', 'fail', 'stale'], true)
            || $partitions['status'] === 'fail'
            || $postgres['status'] === 'warning'
            || ($memory !== null && $memory['percent'] !== null && $memory['percent'] >= $this->threshold(AlertKind::REDIS_MEMORY));
        if ($status === 'ok' && $needsLook) {
            $status = 'degraded';
        }

        $latest = $this->failedJobs->latestFailedAt();

        return [
            'status' => $status,
            'checked_at' => now()->toIso8601String(),
            'database' => $checks['database'],
            'redis' => $checks['redis'] + ['memory' => $memory],
            'queue' => [
                'status' => $checks['queue']['status'],
                'queues' => $this->queues->snapshot(),
                'worker' => [
                    'last_heartbeat_age_seconds' => $worker['age_seconds'] ?? null,
                    'max_age_seconds' => $this->threshold(AlertKind::QUEUE_WORKER_STALE),
                ],
                'failed_jobs' => [
                    'total' => $this->failedJobs->count(),
                    'last_24h' => $this->failedJobs->count(now()->subDay()),
                    'latest_failed_at' => $latest?->toIso8601String(),
                ],
            ],
            'scheduler' => $checks['scheduler'] + ['max_age_seconds' => (int) config('health.scheduler_max_age')],
            'reverb' => $reverb,
            'backup' => $backup,
            'postgres' => $postgres,
            'pitr' => $pitr,
            'drill' => $drill,
            'partitions' => $partitions,
            'disk' => $disk,
            'app' => [
                'version' => config('monitoring.version') ?: null,
                'commit' => config('monitoring.commit') ?: null,
                'environment' => (string) config('app.env'),
            ],
            // Who would be told: without these nobody hears of an alert, or of the server being down
            'notifications' => [
                'telegram_configured' => app(AlertNotifier::class)->configured(),
                'dead_man_configured' => trim((string) config('monitoring.healthchecks_ping_url')) !== '',
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

    /**
     * @param  array<string, mixed>|null  $archiver
     * @return array{status: string, connections: array{used: int, max: int, percent: float}|null, longest_transaction_seconds: int|null, idle_transaction_seconds: int|null, lock_waits: int|null, archiver: array{archive_mode: string, last_archived_wal: string|null, last_archived_at: string|null, last_failed_at: string|null, failing: bool, ready_wal: int|null}|null}
     */
    public function postgresState(?array $archiver): array
    {
        $connections = $this->postgres->connections();
        $transactions = $this->postgres->longTransactions();
        $locks = $this->postgres->lockWaits();

        $longest = $transactions === null ? null : (int) max([0, ...array_column($transactions['active'], 'seconds')]);
        $idle = $transactions === null ? null : (int) max([0, ...array_column($transactions['idle'], 'seconds')]);
        $lockLimit = $this->threshold(AlertKind::DB_LOCK_WAITS, 'threshold_secondary');
        $waiting = $locks === null ? null : count(array_filter($locks, fn ($lock) => $lock['seconds'] >= $lockLimit));

        $status = 'ok';
        if ($connections === null || $transactions === null || $locks === null) {
            $status = 'unknown';
        } elseif ($connections['percent'] >= $this->threshold(AlertKind::DB_CONNECTIONS)
            || $longest >= $this->threshold(AlertKind::DB_LONG_TRANSACTION)
            || $idle >= $this->threshold(AlertKind::DB_LONG_TRANSACTION, 'threshold_secondary')
            || $waiting >= $this->threshold(AlertKind::DB_LOCK_WAITS)) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'connections' => $connections === null ? null : ['used' => $connections['used'], 'max' => $connections['max'], 'percent' => $connections['percent']],
            'longest_transaction_seconds' => $longest,
            'idle_transaction_seconds' => $idle,
            'lock_waits' => $waiting,
            'archiver' => $archiver === null ? null : [
                'archive_mode' => $archiver['archive_mode'],
                'last_archived_wal' => $archiver['last_archived_wal'],
                'last_archived_at' => $archiver['last_archived_at'],
                'last_failed_at' => $archiver['last_failed_at'],
                'failing' => $archiver['failing'],
                'ready_wal' => $archiver['ready_wal'],
            ],
        ];
    }

    /**
     * off (PITR not switched on) | missing (on, the backup service never reported) | stale (it stopped reporting) |
     * fail (an error, archiving fails, or the last backup is too old) | warning (no working off-site copy) | ok.
     *
     * @param  array<string, mixed>|null  $archiver
     * @return array{status: string, enabled: bool, marker_at: string|null, marker_age_seconds: int|null, message: string|null, last_backup_at: string|null, last_backup_type: string|null, last_backup_age_seconds: int|null, last_full_at: string|null, last_wal_archived_at: string|null, wal_archived_age_seconds: int|null, archive_failing: bool|null, archive_failing_seconds: int|null, ready_wal: int|null, offsite: string|null, offsite_note: string|null, max_backup_age_hours: int, max_marker_age_minutes: int}
     */
    public function pitr(?array $archiver): array
    {
        $window = $this->threshold(AlertKind::PITR_BACKUP_STALE, 'window_minutes') ?: BackupState::MARKER_MAX_AGE_MINUTES;
        $maxHours = $this->threshold(AlertKind::PITR_BACKUP_STALE);
        $marker = $this->backups->marker(MonitoringMarker::PITR);
        $enabled = $this->backups->pitrEnabled($marker);
        $meta = $marker !== null ? (array) $marker->meta : [];
        $age = $this->backups->markerAgeSeconds($marker);

        $last = BackupState::parseTime($meta['last_backup_at'] ?? null);
        $full = BackupState::parseTime($meta['last_full_at'] ?? null);
        // The live archiver first (this very minute); the marker's copy when it cannot be read
        $archivedAt = $archiver['last_archived_at'] ?? ($meta['archived_at'] ?? null);
        $failing = $archiver !== null ? $archiver['failing'] : (isset($meta['archive_failing']) ? (bool) $meta['archive_failing'] : null);
        // How long it has been failing (WalFailureClock), not "now - last success": an idle server archives nothing
        $failingFor = $archiver !== null && $enabled ? WalFailureClock::failingSeconds($archiver) : null;
        $failLimit = 60 * $this->threshold(AlertKind::WAL_ARCHIVE, 'threshold_secondary');
        $ready = $archiver['ready_wal'] ?? (isset($meta['ready_wal']) ? (int) $meta['ready_wal'] : null);
        $offsite = isset($meta['repo2']) ? (string) $meta['repo2'] : null;

        $status = match (true) {
            !$enabled => 'off',
            $marker === null => 'missing',
            $age > $window * 60 => 'stale',
            ($meta['enabled'] ?? null) === false,
            ($meta['status'] ?? 'ok') !== 'ok',
            $failingFor !== null && $failingFor >= $failLimit,
            $last === null || $last->lt(now()->subHours($maxHours)) => 'fail',
            $offsite !== 'on' => 'warning',
            default => 'ok',
        };

        return [
            'status' => $status,
            'enabled' => $enabled,
            'marker_at' => $marker?->recorded_at->toIso8601String(),
            'marker_age_seconds' => $age,
            'message' => isset($meta['message']) && ($meta['status'] ?? 'ok') !== 'ok' ? mb_substr((string) $meta['message'], 0, 300) : null,
            'last_backup_at' => $last?->toIso8601String(),
            'last_backup_type' => isset($meta['last_backup_type']) ? (string) $meta['last_backup_type'] : null,
            'last_backup_age_seconds' => BackupState::ageSeconds($last),
            'last_full_at' => $full?->toIso8601String(),
            'last_wal_archived_at' => is_string($archivedAt) ? BackupState::parseTime($archivedAt)?->toIso8601String() : null,
            'wal_archived_age_seconds' => BackupState::ageSeconds(is_string($archivedAt) ? BackupState::parseTime($archivedAt) : null),
            'archive_failing' => $failing,
            'archive_failing_seconds' => $failingFor,
            'ready_wal' => $ready,
            'offsite' => $offsite,
            'offsite_note' => isset($meta['repo2_note']) ? mb_substr((string) $meta['repo2_note'], 0, 200) : null,
            'max_backup_age_hours' => $maxHours,
            'max_marker_age_minutes' => $window,
        ];
    }

    /**
     * off (PITR off and no drill ever) | never (on, no drill yet) | failed (the newest drill failed) | stale (the last
     * good one is older than the rule's days) | ok | unknown (the table cannot be read).
     *
     * @return array{status: string, last_at: string|null, last_status: string|null, restore_seconds: int|null, data_loss_seconds: int|null, reached_time: string|null, backup_label: string|null, error: string|null, last_ok_at: string|null, max_age_days: int, trigger: string|null, app_check: string|null, last_app_check_ok_at: string|null, app_check_max_age_days: int}
     */
    public function drill(): array
    {
        $maxDays = $this->threshold(AlertKind::RESTORE_DRILL_STALE);
        $drills = $this->backups->drills();
        $appDays = max(1, (int) config('monitoring.drill_app_check_days', 100));
        $empty = ['last_at' => null, 'last_status' => null, 'restore_seconds' => null, 'data_loss_seconds' => null, 'reached_time' => null, 'backup_label' => null, 'error' => null, 'last_ok_at' => null, 'max_age_days' => $maxDays, 'trigger' => null, 'app_check' => null, 'last_app_check_ok_at' => null, 'app_check_max_age_days' => $appDays];
        if ($drills === null) {
            return ['status' => 'unknown'] + $empty;
        }

        $last = $drills['last'];
        $ok = $drills['last_ok'];
        $okAt = $ok !== null ? Carbon::parse($ok->started_at, 'UTC') : null;
        $status = match (true) {
            $last === null => $this->backups->pitrEnabled() ? 'never' : 'off',
            $last->status === 'failed' => 'failed',
            $okAt === null || $okAt->lt(now()->subDays($maxDays)) => 'stale',
            default => 'ok',
        };

        return [
            'status' => $status,
            'last_at' => $last !== null ? Carbon::parse($last->started_at, 'UTC')->toIso8601String() : null,
            'last_status' => $last->status ?? null,
            'restore_seconds' => isset($last->restore_seconds) ? (int) $last->restore_seconds : null,
            'data_loss_seconds' => isset($last->data_loss_seconds) ? (int) $last->data_loss_seconds : null,
            'reached_time' => isset($last->reached_time) ? Carbon::parse($last->reached_time, 'UTC')->toIso8601String() : null,
            'backup_label' => $last->backup_label ?? null,
            'error' => isset($last->error) ? mb_substr((string) $last->error, 0, 300) : null,
            'last_ok_at' => $okAt?->toIso8601String(),
            'max_age_days' => $maxDays,
            // auto (the monthly drill of the backup service) | manual (scripts/restore-drill.sh) | null (older rows)
            'trigger' => isset(BackupState::checks($last)['trigger']) ? (string) BackupState::checks($last)['trigger'] : null,
            // ok | failed | skipped: the app check (db:restore-smoke) of the last drill; skipped = passed, not fully verified
            'app_check' => isset(BackupState::checks($last)['app_smoke']) ? (string) BackupState::checks($last)['app_smoke'] : null,
            'last_app_check_ok_at' => $drills['last_app_ok'] !== null ? Carbon::parse($drills['last_app_ok']->started_at, 'UTC')->toIso8601String() : null,
            'app_check_max_age_days' => $appDays,
        ];
    }

    /**
     * ok | warning (rows in DEFAULT, an archive problem, a conversion to finish) | fail (fewer future months than the
     * rule wants) | unknown. Reused for a minute: the tab polls.
     *
     * @return array{status: string, months_ahead: int|null, min_months_ahead: int, default_rows: int, archive_failed: int, archive_stale: int, problems: list<array{table: string|null, code: string, message: string}>, tables: list<array{table: string, partitioned: bool, future_months: int, default_rows: int, oldest_month: string|null, newest_month: string|null}>}
     */
    public function partitions(): array
    {
        $min = $this->threshold(AlertKind::PARTITIONS);
        $status = Cache::remember('monitoring:partitions', 60, fn () => PostgresStats::bounded(fn () => $this->partitionManager->status(), 'partitions'));
        if (!is_array($status)) {
            Cache::forget('monitoring:partitions');

            return ['status' => 'unknown', 'months_ahead' => null, 'min_months_ahead' => $min, 'default_rows' => 0, 'archive_failed' => 0, 'archive_stale' => 0, 'problems' => [], 'tables' => []];
        }

        $tables = array_map(fn (array $t) => [
            'table' => (string) $t['table'],
            'partitioned' => (bool) $t['partitioned'],
            'future_months' => (int) $t['future_months'],
            'default_rows' => (int) $t['default_rows'],
            'oldest_month' => $t['oldest_month'],
            'newest_month' => $t['newest_month'],
        ], $status['tables']);
        $partitioned = array_filter($status['tables'], fn ($t) => $t['partitioned']);
        $ahead = $partitioned === [] ? null : (int) min(array_map(fn ($t) => $t['current_month_attached'] ? (int) $t['future_months'] : -1, $partitioned));
        $defaultRows = (int) array_sum(array_column($tables, 'default_rows'));
        $problems = array_map(fn ($p) => ['table' => $p['table'], 'code' => (string) $p['code'], 'message' => (string) $p['message']], $status['problems']);

        return [
            'status' => $ahead !== null && $ahead < $min ? 'fail' : ($problems !== [] ? 'warning' : 'ok'),
            'months_ahead' => $ahead === null ? null : max(0, $ahead),
            'min_months_ahead' => $min,
            'default_rows' => $defaultRows,
            'archive_failed' => (int) ($status['archives']['failed'] ?? 0),
            'archive_stale' => (int) ($status['archives']['stale_detached'] ?? 0),
            'problems' => $problems,
            'tables' => $tables,
        ];
    }

    /**
     * ok | warning | fail (the disk_usage rule's percentages) | stale (the backup service stopped reporting) | missing
     * (it never reported: a backup service not rebuilt for M3).
     *
     * @return array{status: string, recorded_at: string|null, age_seconds: int|null, used_percent: float|null, avail_bytes: int|null, total_bytes: int|null, pg_wal_bytes: int|null, spool_bytes: int|null, db_bytes: int|null, repo_used_percent: float|null, repo_avail_bytes: int|null, warn_percent: int, critical_percent: int}
     */
    public function disk(): array
    {
        $warn = $this->threshold(AlertKind::DISK_USAGE);
        $critical = $this->threshold(AlertKind::DISK_USAGE, 'threshold_secondary');
        $window = $this->threshold(AlertKind::DISK_USAGE, 'window_minutes') ?: BackupState::MARKER_MAX_AGE_MINUTES;
        $marker = $this->backups->marker(MonitoringMarker::DISK);
        $meta = $marker !== null ? (array) $marker->meta : [];
        $age = $this->backups->markerAgeSeconds($marker);
        $num = fn (string $key) => isset($meta[$key]) && is_numeric($meta[$key]) ? $meta[$key] : null;
        $used = $num('used_percent') !== null ? (float) $num('used_percent') : null;
        $repo = $num('repo_used_percent') !== null ? (float) $num('repo_used_percent') : null;
        $highest = max($used ?? 0, $repo ?? 0);

        $status = match (true) {
            $marker === null => 'missing',
            $age > $window * 60 => 'stale',
            $highest >= $critical => 'fail',
            $highest >= $warn => 'warning',
            default => 'ok',
        };

        return [
            'status' => $status,
            'recorded_at' => $marker?->recorded_at->toIso8601String(),
            'age_seconds' => $age,
            'used_percent' => $used,
            'avail_bytes' => $num('fs_avail_bytes') !== null ? (int) $num('fs_avail_bytes') : null,
            'total_bytes' => $num('fs_total_bytes') !== null ? (int) $num('fs_total_bytes') : null,
            'pg_wal_bytes' => $num('pg_wal_bytes') !== null ? (int) $num('pg_wal_bytes') : null,
            'spool_bytes' => $num('spool_bytes') !== null ? (int) $num('spool_bytes') : null,
            'db_bytes' => $num('db_bytes') !== null ? (int) $num('db_bytes') : null,
            'repo_used_percent' => $repo,
            'repo_avail_bytes' => $num('repo_avail_bytes') !== null ? (int) $num('repo_avail_bytes') : null,
            'warn_percent' => $warn,
            'critical_percent' => $critical,
        ];
    }

    /**
     * A rule's current value (the default when the row is missing or the field unused).
     */
    private function threshold(AlertKind $kind, string $field = 'threshold'): int
    {
        $this->rules ??= AlertRule::query()->get()->keyBy('kind');
        $value = $this->rules->get($kind->value)?->getAttribute($field);

        return (int) ($value ?? $kind->defaults()[$field] ?? 0);
    }
}
