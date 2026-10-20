<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use App\Models\AuditLog;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /owner/audit-logs: the store's harakatlar jurnali (audit.view, owners by default), newest first, with filters.
 * Only the caller's own store's entries exist here.
 */
#[Group('Owner: audit log', "Do'kon harakatlar jurnali (ega). Maxfiy ma'lumotlar va telefon raqamlari yashirilgan.")]
class AuditLogController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'nullable|string|max:64',
            'user_id' => 'nullable|integer',
            'device_uuid' => 'nullable|uuid',
            'branch_id' => 'nullable|integer',
            'subject_type' => 'nullable|string|in:Order,User,Device,Product,ProductVariant,Store,Branch,DevicePairingCode',
            'subject_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        /** @var User $user */
        $user = $request->user();

        $logs = AuditLog::query()
            ->where('store_id', $user->store_id)
            ->with(['user:id,name,role', 'device:id,uuid,name,type'])
            ->when($request->filled('action'), fn ($query) => str_ends_with((string) $request->input('action'), '.*')
                ? $query->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], substr((string) $request->input('action'), 0, -1)) . '%')
                : $query->where('action', $request->input('action')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('device_uuid'), fn ($query) => $query->whereHas('device', fn ($q) => $q->where('uuid', $request->input('device_uuid'))))
            ->when($request->filled('branch_id'), fn ($query) => $query->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('subject_type'), fn ($query) => $query->where('model_type', 'App\\Models\\' . $request->input('subject_type')))
            ->when($request->filled('subject_id'), fn ($query) => $query->where('model_id', $request->integer('subject_id')))
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 50));

        $logs->getCollection()->transform(fn (AuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'actor' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name, 'role' => $log->user->role->value] : null,
            'device' => $log->device ? ['uuid' => $log->device->uuid, 'name' => $log->device->name, 'type' => $log->device->type->value] : null,
            'subject_type' => $log->subjectName(),
            'subject_id' => $log->model_id,
            'branch_id' => $log->branch_id,
            'before' => $log->before,
            'after' => $log->after,
            'meta' => $log->payload_json,
            // Only staff and terminal addresses are stored; shown to the owner (and superadmin) only
            'ip' => in_array($user->role, [UserRole::OWNER, UserRole::SUPER_ADMIN], true) ? $log->ip : null,
            'created_at' => $log->created_at->toIso8601String(),
        ]);

        return $this->success($logs);
    }
}
