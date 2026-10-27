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
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{status: string, checked_at: string, database: array{status: string, latency_ms?: float}, redis: array{status: string, latency_ms?: float}, queue: array{status: string, queues: list<array{name: string, size: int|null, oldest_age_seconds: int|null}>, failed_jobs: array{total: int, last_24h: int, latest_failed_at: string|null}}, scheduler: array{status: string, last_heartbeat_age_seconds: int|null, max_age_seconds: int}, reverb: array{status: string, latency_ms: float|null}, backup: array{status: string, last_backup_at: string|null, age_hours: int|null, max_age_hours: int}, app: array{version: string|null, commit: string|null, environment: string}, sentry: array{configured: bool, environment: string|null, project_url: string|null}, alerts: array{open: int, acknowledged: int, critical_unresolved: int}}}')]
    public function show(): JsonResponse
    {
        return $this->success($this->status->snapshot());
    }
}
