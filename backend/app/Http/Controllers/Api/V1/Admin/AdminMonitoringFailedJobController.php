<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Monitoring\FailedJobs;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P4-15: Monitoring → Yiqilgan vazifalar. Reading needs admin.monitoring.view, retrying admin.monitoring.manage,
 * deleting admin.monitoring.manage with a step-up and a reason. Payloads and exception texts are redacted
 * (App\Services\Admin\Monitoring\FailedJobs): secrets, tokens and phone numbers never reach the answer or the audit.
 */
#[Group('Admin')]
class AdminMonitoringFailedJobController extends AdminController
{
    public function __construct(
        private readonly FailedJobs $failedJobs,
    ) {}

    /**
     * Failed jobs
     *
     * Newest first, cursor pages (`cursor` from `next_cursor`, `per_page` 1–100, default 25). Each row: the job's short
     * name and class, queue, the exception's first line (redacted) and when it failed. Filters: `queue`, `q` (part of
     * the job class name), `from` / `to` (dates, inclusive). `total` counts all failed jobs.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<array{id: int, uuid: string, job: string, job_class: string, queue: string, connection: string, exception_line: string, failed_at: string|null}>, next_cursor: string|null, prev_cursor: string|null, per_page: int, total: int}}')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'queue' => 'nullable|string|max:100',
            'q' => 'nullable|string|min:2|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
            'cursor' => 'nullable|string|max:500',
        ], UzValidation::messages());

        $page = $this->failedJobs->listQuery($filters)
            ->cursorPaginate($request->integer('per_page', 25))
            ->withQueryString();

        return $this->success([
            'data' => collect($page->items())->map(fn (object $row) => $this->failedJobs->presentRow($row))->all(),
            'next_cursor' => $page->nextCursor()?->encode(),
            'prev_cursor' => $page->previousCursor()?->encode(),
            'per_page' => $page->perPage(),
            'total' => $this->failedJobs->count(),
        ]);
    }

    /**
     * One failed job
     *
     * The full exception text with the stack trace and the job's payload as plain data, both redacted: keys that hold
     * secrets (tokens, passwords, codes, keys, card data) show `[redacted]`, tokens and phone numbers inside text are
     * masked. The job is read without running any of its code.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int, uuid: string, job: string, job_class: string, queue: string, connection: string, exception_line: string, failed_at: string|null, attempts: int|null, max_tries: int|null, exception: string, payload: array<string, mixed>}}')]
    #[ApiDocResponse(404, 'No such failed job', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function show(string $uuid): JsonResponse
    {
        $job = $this->failedJobs->show($uuid);

        return $job === null ? $this->notFound() : $this->success($job);
    }

    /**
     * Retry a failed job
     *
     * Puts the job back on its queue with its attempts reset and removes it from the list (like
     * `php artisan queue:retry`). Optional `reason`. 404 if it is no longer there (retried or deleted meanwhile).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{uuid: string, job: string, queue: string}}')]
    #[ApiDocResponse(404, 'No such failed job', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function retry(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason(false)], UzValidation::messages());

        $job = $this->failedJobs->summary($uuid);
        if ($job === null || !$this->failedJobs->retry($uuid)) {
            return $this->notFound();
        }

        AdminAudit::for($request)->meta($this->auditMeta($job));

        return $this->success(['uuid' => $uuid, 'job' => $job['job'], 'queue' => $job['queue']], 'Vazifa navbatga qaytarildi.');
    }

    /**
     * Delete a failed job
     *
     * Removes it for good. Needs step-up and a `reason`; the audit entry keeps the job name, queue and uuid, not the
     * payload.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{uuid: string, job: string, queue: string}}')]
    #[ApiDocResponse(404, 'No such failed job', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    #[ApiDocResponse(403, 'Step-up needed (reauth_required) or no permission', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());

        $job = $this->failedJobs->summary($uuid);
        if ($job === null || !$this->failedJobs->delete($uuid)) {
            return $this->notFound();
        }

        AdminAudit::for($request)->meta($this->auditMeta($job));

        return $this->success(['uuid' => $uuid, 'job' => $job['job'], 'queue' => $job['queue']], 'Vazifa o\'chirildi.');
    }

    /**
     * @param  array{uuid: string, job_class: string, queue: string, failed_at: string|null, exception_line: string}  $job
     * @return array<string, mixed>
     */
    private function auditMeta(array $job): array
    {
        return [
            'failed_job_uuid' => $job['uuid'],
            'job' => $job['job_class'],
            'queue' => $job['queue'],
            'failed_at' => $job['failed_at'],
            // The first line of the exception only (already redacted)
            'exception' => $job['exception_line'],
        ];
    }

    private function notFound(): JsonResponse
    {
        return $this->refuse('Yiqilgan vazifa topilmadi (allaqachon qayta urinilgan yoki o\'chirilgan bo\'lishi mumkin).', 'not_found', 404);
    }
}
