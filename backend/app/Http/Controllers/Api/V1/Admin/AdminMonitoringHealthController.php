<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\Monitoring\MonitoringStatus;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;

/**
 * P4-15: Monitoring → Holat (admin.monitoring.view): the system's health at a glance.
 */
#[Group('Admin')]
class AdminMonitoringHealthController extends AdminController
{
    public function __construct(
        private readonly MonitoringStatus $status,
    ) {}

    /**
     * System health
     *
     * `status` is `ok`, `degraded` (the app works, something needs a look: a stale scheduler, a long queue, Reverb
     * down, an old backup) or `fail` (database or Redis). `database`, `redis`, `queue` (the `/health` report's numbers
     * plus every queue with its size and the age of its oldest waiting job, and failed job counts) and `scheduler` come
     * from the same report as `GET /health`. `reverb`: `ok`, `fail` or `not_configured` (real-time is switched off).
     * `backup`: `ok`, `stale` (older than the backup rule's hours) or `missing` (the backup service never reported).
     * `app` (version, commit), `sentry` (`configured`, link to the project from the config; no Sentry API calls) and
     * `alerts` (open, acknowledged, critical unresolved counts).
     *
     * M3, the server: `postgres` (`ok`, `warning` or `unknown`: connections against max_connections, the longest open
     * transaction, sessions waiting on a lock, the WAL archiver). `pitr`, the continuous backup: `off` (not switched
     * on), `missing` (the backup service never reported), `stale` (it stopped reporting), `fail`, `warning` (no working
     * off-site copy) or `ok`, with the last backups, the last WAL sent and `offsite` (`on`, `off`, `no_cipher`,
     * `partial`, `bad_endpoint`). `drill`, the last restore drill: `off`, `never`, `failed`, `stale`, `ok` or `unknown`,
     * with its restore time and data loss window in seconds. `partitions`: the journals' months (`ok`, `warning`,
     * `fail`, `unknown`) with the problems in plain words. `disk` (`ok`, `warning`, `fail`, `stale`, `missing`) and
     * `redis.memory` (null when Redis is not used or not readable; `max_bytes` null = no cap). `queue.worker`: the
     * age of the queue worker's last heartbeat.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{status: string, checked_at: string, database: array{status: string, latency_ms?: float}, redis: array{status: string, latency_ms?: float, memory: array{used_bytes: int, max_bytes: int|null, percent: float|null, policy: string|null}|null}, queue: array{status: string, queues: list<array{name: string, size: int|null, oldest_age_seconds: int|null}>, worker: array{last_heartbeat_age_seconds: int|null, max_age_seconds: int}, failed_jobs: array{total: int, last_24h: int, latest_failed_at: string|null}}, scheduler: array{status: string, last_heartbeat_age_seconds: int|null, max_age_seconds: int}, reverb: array{status: string, latency_ms: float|null}, backup: array{status: string, last_backup_at: string|null, age_hours: int|null, max_age_hours: int}, postgres: array{status: string, connections: array{used: int, max: int, percent: float}|null, longest_transaction_seconds: int|null, idle_transaction_seconds: int|null, lock_waits: int|null, archiver: array{archive_mode: string, last_archived_wal: string|null, last_archived_at: string|null, last_failed_at: string|null, failing: bool, ready_wal: int|null}|null}, pitr: array{status: string, enabled: bool, marker_at: string|null, marker_age_seconds: int|null, message: string|null, last_backup_at: string|null, last_backup_type: string|null, last_backup_age_seconds: int|null, last_full_at: string|null, last_wal_archived_at: string|null, wal_archived_age_seconds: int|null, archive_failing: bool|null, ready_wal: int|null, offsite: string|null, offsite_note: string|null, max_backup_age_hours: int, max_marker_age_minutes: int}, drill: array{status: string, last_at: string|null, last_status: string|null, restore_seconds: int|null, data_loss_seconds: int|null, reached_time: string|null, backup_label: string|null, error: string|null, last_ok_at: string|null, max_age_days: int}, partitions: array{status: string, months_ahead: int|null, min_months_ahead: int, default_rows: int, archive_failed: int, archive_stale: int, problems: list<array{table: string|null, code: string, message: string}>, tables: list<array{table: string, partitioned: bool, future_months: int, default_rows: int, oldest_month: string|null, newest_month: string|null}>}, disk: array{status: string, recorded_at: string|null, age_seconds: int|null, used_percent: float|null, avail_bytes: int|null, total_bytes: int|null, pg_wal_bytes: int|null, spool_bytes: int|null, db_bytes: int|null, repo_used_percent: float|null, repo_avail_bytes: int|null, warn_percent: int, critical_percent: int}, app: array{version: string|null, commit: string|null, environment: string}, sentry: array{configured: bool, environment: string|null, project_url: string|null}, alerts: array{open: int, acknowledged: int, critical_unresolved: int}}}')]
    public function show(): JsonResponse
    {
        return $this->success($this->status->snapshot());
    }
}
