<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AuditLog;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P4-11: Tizim → Harakatlar jurnali (admin.audit.view): the whole platform's audit log, the team's actions and every
 * store's own entries. Read-only, like the log itself (append-only). Secrets never reach it and phone numbers in it
 * are masked (AuditRedactor).
 */
#[Group('Admin')]
class AdminAuditLogController extends AdminController
{
    /**
     * Audit log (whole platform)
     *
     * Newest first, cursor pages (`cursor` from `next_cursor`, `per_page` 1–100, default 50). Filters: `actor_id`,
     * `action` (a prefix: `admin.team` or `admin.team.*`; an exact action matches itself), `store_id`,
     * `subject_type` (Order, User, Store...) + `subject_id`, `from` / `to` (dates, inclusive), `q` (text in the action
     * and its details, at least 2 characters).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<array{id: int, action: string, actor: array{id: int, name: string, role: string, platform_role: string|null}|null, store: array{id: int, name: string}|null, branch_id: int|null, device: array{uuid: string, name: string|null, type: string}|null, subject_type: string|null, subject_id: int|null, before: array<string, mixed>|null, after: array<string, mixed>|null, meta: array<string, mixed>|null, reason: string|null, ip: string|null, user_agent: string|null, created_at: string}>, next_cursor: string|null, prev_cursor: string|null, per_page: int}}')]
    public function index(Request $request): JsonResponse
    {
        $id = 'nullable|integer|min:1|max:999999999999999999';
        $request->validate([
            'actor_id' => $id,
            'action' => 'nullable|string|max:64',
            'store_id' => $id,
            'subject_type' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z]+$/'],
            'subject_id' => $id,
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'q' => 'nullable|string|min:2|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
            'cursor' => 'nullable|string|max:500',
        ], UzValidation::messages());

        $logs = AuditLog::query()
            ->with(['user:id,name,role,platform_role', 'store:id,name', 'device:id,uuid,name,type'])
            ->when($request->filled('actor_id'), fn (Builder $query) => $query->where('user_id', $request->integer('actor_id')))
            ->when($request->filled('action'), function (Builder $query) use ($request) {
                $prefix = (string) $request->input('action');
                $prefix = str_ends_with($prefix, '.*') ? substr($prefix, 0, -1) : $prefix;
                $query->where('action', 'like', str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $prefix) . '%');
            })
            ->when($request->filled('store_id'), fn (Builder $query) => $query->where('store_id', $request->integer('store_id')))
            ->when($request->filled('subject_type'), fn (Builder $query) => $query->where('model_type', 'App\\Models\\' . ucfirst((string) $request->input('subject_type'))))
            ->when($request->filled('subject_id'), fn (Builder $query) => $query->where('model_id', $request->integer('subject_id')))
            ->when($request->filled('from'), fn (Builder $query) => $query->where('created_at', '>=', $request->date('from')?->startOfDay()))
            ->when($request->filled('to'), fn (Builder $query) => $query->where('created_at', '<=', $request->date('to')?->endOfDay()))
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower((string) $request->input('q'))) . '%';
                $query->where(fn (Builder $q) => $q
                    ->whereRaw('lower(action) like ?', [$like])
                    ->orWhereRaw('lower(cast(payload_json as text)) like ?', [$like])
                    ->orWhereRaw('lower(cast("before" as text)) like ?', [$like])
                    ->orWhereRaw('lower(cast("after" as text)) like ?', [$like]));
            })
            ->orderByDesc('id')
            ->cursorPaginate($request->integer('per_page', 50))
            ->withQueryString();

        return $this->success([
            'data' => collect($logs->items())->map(fn (AuditLog $log) => $this->present($log))->all(),
            'next_cursor' => $logs->nextCursor()?->encode(),
            'prev_cursor' => $logs->previousCursor()?->encode(),
            'per_page' => $logs->perPage(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AuditLog $log): array
    {
        $meta = $log->payload_json;

        return [
            'id' => (int) $log->id,
            'action' => $log->action,
            'actor' => $log->user ? [
                'id' => (int) $log->user->id,
                'name' => (string) $log->user->name,
                'role' => $log->user->role->value,
                'platform_role' => $log->user->platform_role?->value,
            ] : null,
            'store' => $log->store ? ['id' => (int) $log->store->id, 'name' => (string) $log->store->name] : null,
            'branch_id' => $log->branch_id !== null ? (int) $log->branch_id : null,
            'device' => $log->device ? ['uuid' => $log->device->uuid, 'name' => $log->device->name, 'type' => $log->device->type->value] : null,
            'subject_type' => $log->subjectName(),
            'subject_id' => $log->model_id !== null ? (int) $log->model_id : null,
            'before' => $log->before,
            'after' => $log->after,
            'meta' => $meta,
            'reason' => is_array($meta) && is_string($meta['reason'] ?? null) ? $meta['reason'] : null,
            'ip' => $log->ip,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at->toIso8601String(),
        ];
    }
}
