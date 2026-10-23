<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Domain\Pos\Sync\PosSyncErrors;
use App\Domain\Pos\Sync\PosSyncException;
use App\Domain\Pos\Sync\PosSyncOperationType;
use App\Domain\Pos\Sync\PosSyncPresenter;
use App\Domain\Pos\Sync\PosSyncService;
use App\Enums\DeviceType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\PosSyncOperation;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Order\StaffOrderScope;
use App\Traits\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * P3-31: the offline limited mode (docs/POS_SPEC.md §5, docs/API.md "Internetsiz rejim"). The till sends its offline
 * queue with its own terminal token (the staff PIN session may have expired during the outage; each operation names
 * the staff member who did it); managers see and settle what did not go through ("Sinxronlash xatolari") from the
 * till's PIN session or the web panel.
 */
#[Group('POS: sync', "Internetsiz cheklangan rejim: kassaning offlayn navbati, sinxronlash holati va xatolari (P3-31).")]
class PosSyncController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PosSyncService $sync,
        private readonly PosSyncErrors $errors,
        private readonly StaffOrderScope $scope,
        private readonly PermissionService $permissions,
    ) {}

    /**
     * Send the offline queue
     *
     * Up to 50 operations of this till in its `seq` order, each with a new `op_id` (UUID; the same one when sent again:
     * it is applied once), `type` (order.open, order.round, order.send, order.void, order.served, bill.create, bill.pay,
     * order.close), `occurred_at` (the device's clock), `staff_id` (who did it: signed in on this till before the
     * outage, with the permission) and `payload`. Each result: `status` applied | conflict (applied, but a manager
     * should look: oversold, payment on a cancelled order, amount mismatch, stale menu, a taken room) | rejected (not
     * applied: "Sinxronlash xatolari"), `reason`, `message` and `refs` (the server's order uuid and version, local
     * number, line ids, bill and payment uuids, flags). `sent_at` (the device's clock now) gives the clock skew: more
     * than 5 minutes is `clock_warning`. `queue` (size, oldest_at) is what is still in the device's queue including
     * this batch: the shift does not close while it is not empty. Only a POS terminal's own token.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{server_time: string, clock_skew_seconds: int|null, clock_warning: bool, stopped_at: int|null, results: list<' . PosSyncPresenter::RESULT . '>, device: ' . PosSyncPresenter::DEVICE . '}}')]
    #[HeaderParameter('Idempotency-Key', description: 'Optional: every operation is applied once by its op_id anyway; a key replays the whole answer.', type: 'string')]
    public function store(Request $request): JsonResponse
    {
        $device = $this->terminal($request);
        $data = $request->validate([
            'sent_at' => 'nullable|date',
            'queue' => 'nullable|array',
            'queue.size' => 'nullable|integer|min:0|max:100000',
            'queue.oldest_at' => 'nullable|date',
            'operations' => 'present|array|max:' . PosSyncService::MAX_BATCH,
            'operations.*.op_id' => 'required|uuid|distinct',
            'operations.*.seq' => 'required|integer|min:1|distinct',
            'operations.*.type' => ['required', 'string', Rule::in(array_map(fn (PosSyncOperationType $type) => $type->value, PosSyncOperationType::cases()))],
            'operations.*.occurred_at' => 'nullable|date',
            'operations.*.staff_id' => 'required|integer|min:1',
            'operations.*.payload' => 'present|array',
        ]);

        try {
            $outcome = $this->sync->sync(
                $device,
                array_values($data['operations']),
                isset($data['sent_at']) ? Carbon::parse((string) $data['sent_at']) : null,
                $data['queue'] ?? null,
            );
        } catch (PosSyncException $e) {
            return $this->error($e->getMessage(), ['reason' => $e->reason] + $e->context, 409);
        }

        $duplicates = array_flip($outcome['duplicates']);

        return $this->success([
            'server_time' => now()->toJSON(),
            'clock_skew_seconds' => $outcome['clock_skew_seconds'],
            'clock_warning' => PosSyncService::clockWarning($outcome['clock_skew_seconds']),
            'stopped_at' => $outcome['stopped_at'],
            'results' => array_map(fn (PosSyncOperation $op) => PosSyncPresenter::result($op, isset($duplicates[$op->op_id])), $outcome['results']),
            'device' => PosSyncPresenter::device($device->refresh()),
        ], 'Sinxronlandi.');
    }

    /**
     * Sync status of this till
     *
     * What the server holds for this till: the last `seq` it received, what the till last said about its queue, the
     * clock skew, and its operations that need a manager (rejected or conflicting, not resolved yet; newest first, at
     * most 100). `queue_size` / `oldest_at` (optional) report the queue now (0 after it drained). Only a POS terminal's
     * own token.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{server_time: string, device: ' . PosSyncPresenter::DEVICE . ', errors: list<' . PosSyncPresenter::OPERATION . '>, errors_count: int}}')]
    #[QueryParameter('queue_size', description: 'Operations still in the device queue now.', type: 'int')]
    #[QueryParameter('oldest_at', description: 'When the oldest of them happened (device clock).', type: 'string')]
    public function status(Request $request): JsonResponse
    {
        $device = $this->terminal($request);
        $data = $request->validate([
            'queue_size' => 'nullable|integer|min:0|max:100000',
            'oldest_at' => 'nullable|date',
        ]);
        if (isset($data['queue_size'])) {
            $this->sync->report($device, [], ['size' => (int) $data['queue_size'], 'oldest_at' => $data['oldest_at'] ?? null], null);
        }

        $query = $this->errors->query((int) $device->store_id, null, $device);

        return $this->success([
            'server_time' => now()->toJSON(),
            'device' => PosSyncPresenter::device($device->refresh()),
            'errors' => $query->clone()->limit(100)->get()->map(fn (PosSyncOperation $op) => PosSyncPresenter::operation($op))->values()->all(),
            'errors_count' => $query->count(),
        ]);
    }

    /**
     * Sync errors ("Sinxronlash xatolari")
     *
     * Offline operations of the caller's branch (or `branch_id`) that a manager should look at: rejected (not applied)
     * and conflicting (applied with a difference), not resolved yet; `all=1` resolved ones too; `device` (uuid) one
     * till. Newest first, 50 per page. Needs orders.view.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{errors: list<' . PosSyncPresenter::OPERATION . '>, meta: array{current_page: int, last_page: int, per_page: int, total: int}}}')]
    #[QueryParameter('branch_id', type: 'int')]
    #[QueryParameter('device', description: 'A till uuid.', type: 'string')]
    #[QueryParameter('all', description: 'Resolved and dismissed ones too.', type: 'bool')]
    #[QueryParameter('page', type: 'int')]
    public function errors(Request $request): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1',
            'device' => 'nullable|uuid',
            'all' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
        ]);
        $branchId = $this->branchId($user, isset($data['branch_id']) ? (int) $data['branch_id'] : null, Permission::ORDERS_VIEW);
        $device = isset($data['device']) ? Device::where('store_id', $user->store_id)->where('uuid', $data['device'])->first() : null;
        if (isset($data['device']) && $device === null) {
            return $this->error('Kassa topilmadi.', [], 404);
        }

        $page = $this->errors->query((int) $user->store_id, $branchId, $device, (bool) ($data['all'] ?? false))->paginate(50);

        return $this->success([
            'errors' => collect($page->items())->map(fn (PosSyncOperation $op) => PosSyncPresenter::operation($op))->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Resolve or dismiss a sync error
     *
     * The manager settled it by hand (`resolution: resolved`, e.g. entered the round again, refunded the guest) or
     * there is nothing to do (`dismissed`), with an optional `note`. A resolved rejected operation no longer keeps its
     * till's shift from closing. Repeating it changes nothing. Needs orders.cancel (owner, manager).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PosSyncPresenter::OPERATION . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Optional: resolving twice changes nothing anyway.', type: 'string')]
    public function resolve(Request $request, string $opId): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate([
            'resolution' => 'required|string|in:resolved,dismissed',
            'note' => 'nullable|string|max:500',
        ]);

        $op = Str::isUuid($opId) ? PosSyncOperation::where('op_id', strtolower($opId))->where('store_id', $user->store_id)->first() : null;
        if ($op === null || $op->status === 'applied' || ($op->branch_id !== null && $this->branchId($user, (int) $op->branch_id, Permission::ORDERS_CANCEL) !== (int) $op->branch_id)) {
            return $this->error('Sinxronlash xatosi topilmadi.', [], 404);
        }

        $op = $this->errors->resolve($op, $user, (string) $data['resolution'], $data['note'] ?? null);

        return $this->success(PosSyncPresenter::operation($op->fresh() ?? $op), $data['resolution'] === 'resolved' ? 'Hal qilindi deb belgilandi.' : 'Yopildi.');
    }

    private function terminal(Request $request): Device
    {
        $device = $request->user('device');
        if (!$device instanceof Device || $device->type !== DeviceType::POS || $device->store_id === null) {
            throw new HttpResponseException($this->error("Faqat kassa terminali o'z tokeni bilan sinxronlaydi.", ['reason' => 'terminal_required'], 403));
        }

        return $device;
    }

    private function staff(Request $request): User
    {
        $user = $request->user();
        if (!$user instanceof User || !$this->scope->isStoreStaff($user)) {
            throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
        }

        return $user;
    }

    /**
     * The branch the caller looks at: the one asked for (holding $permission there) or the one they act in (null: the
     * whole store, an owner or store-wide staff).
     */
    private function branchId(User $user, ?int $branchId, Permission $permission): ?int
    {
        $acting = $this->permissions->actingBranchId($user);
        if ($branchId === null) {
            return $acting;
        }
        $branch = \App\Models\Branch::where('store_id', $user->store_id)->find($branchId);
        if ($branch === null || !$this->scope->allowsBranch($user, $permission, $branch)) {
            throw new HttpResponseException($this->error('Filial topilmadi.', [], 404));
        }

        return (int) $branch->id;
    }
}
