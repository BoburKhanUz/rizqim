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

    // Wave M2: the services are resolved when an action needs them, not for every request (GET /pos/sync/status is
    // polled by every till: it built the whole sync engine graph for a status read)
    private function sync(): PosSyncService
    {
        return app(PosSyncService::class);
    }

    private function syncErrors(): PosSyncErrors
    {
        return app(PosSyncErrors::class);
    }

    private function scope(): StaffOrderScope
    {
        return app(StaffOrderScope::class);
    }

    private function permissions(): PermissionService
    {
        return app(PermissionService::class);
    }

    /**
     * Send the offline queue
     *
     * Up to 50 operations of this till in its `seq` order, each with a new `op_id` (UUID; the same one when sent again:
     * it is applied once), `type` (order.open, order.round, order.send, order.void, order.served, bill.create, bill.pay,
     * order.close), `occurred_at` (the device's clock), `staff_id` (who did it: signed in on this till before the
     * outage, with the permission) and `payload`. Each result: `status` applied | conflict (applied, but a manager
     * should look: oversold, payment on a cancelled order, amount mismatch, stale menu, a taken room, a device price the
     * dish never had — `offline_price_mismatch`, the server's price applied) | rejected (not applied: "Sinxronlash
     * xatolari"; `invalid_payload` for content the server cannot store), `reason`, `message` and `refs` (the server's
     * order uuid and version, local number, line ids, bill and payment uuids, flags). `sent_at` (the device's clock now)
     * gives the clock skew: more than 5 minutes is `clock_warning` (and corrects the operations' times, kept within the
     * last 48 hours). `stopped_at`: a temporary database failure only. `queue` (size, oldest_at) is what is still in the device's queue including
     * this batch: the shift does not close while it is not empty. Only a POS terminal's own token.
     * P6 local bridge (docs/POS_SPEC.md §5.5): the branch's hub also sends what it took over the LAN from the waiters'
     * phones and the KDS, each with `origin_device` (that terminal's uuid; the staff member signed in there); types
     * kitchen.bump, kitchen.recall, kitchen.assembled and bill.request too. A relay from a till that is not the hub, an
     * origin outside the branch or a money operation is rejected (relay_not_hub, origin_unknown, origin_wrong_branch,
     * origin_revoked, relay_not_allowed).
     * Wave M5 S3 (signed queue): when `GET /pos/bootstrap` says `offline.signing: true`, a till that holds a queue
     * signing key (the pairing answer's `sync_key`, or `POST /terminal/sync-key`) sends each operation as `{signed,
     * mac, prev, kid}` instead: `signed` is the operation above as JSON text, exactly the bytes written to the journal;
     * `mac` = HMAC-SHA256(key, prev || signed) and `prev` the mac of the operation before it (64 hex characters; the
     * first one under a key: HMAC-SHA256(key, "rizqim-sync/v1/" + the device uuid)); `kid` the key's number. The
     * server verifies the bytes, then reads the operation from them. The answer is the same; what the signature was
     * worth is kept with the operation (ok, missing, bad, chain_gap). A signature never refuses the batch, and the
     * unsigned shape is still taken.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{server_time: string, clock_skew_seconds: int|null, clock_warning: bool, stopped_at: int|null, results: list<' . PosSyncPresenter::RESULT . '>, device: ' . PosSyncPresenter::DEVICE . '}}')]
    #[HeaderParameter('Idempotency-Key', description: 'Optional: every operation is applied once by its op_id anyway; a key replays the whole answer.', type: 'string')]
    public function store(Request $request): JsonResponse
    {
        $device = $this->terminal($request);
        $signatures = $this->openSigned($request);
        $data = $request->validate([
            'sent_at' => 'nullable|date',
            'queue' => 'nullable|array',
            'queue.size' => 'nullable|integer|min:0|max:100000',
            'queue.oldest_at' => 'nullable|date',
            'operations' => 'present|array|max:' . PosSyncService::MAX_BATCH,
            // The operation's own fields: required in the unsigned shape; in the signed shape they are read from `signed`
            'operations.*.op_id' => 'required_without:operations.*.signed|uuid|distinct',
            'operations.*.seq' => 'required_without:operations.*.signed|integer|min:1|distinct',
            'operations.*.type' => ['required_without:operations.*.signed', 'string', Rule::in(array_map(fn (PosSyncOperationType $type) => $type->value, PosSyncOperationType::cases()))],
            'operations.*.occurred_at' => 'nullable|date',
            'operations.*.staff_id' => 'required_without:operations.*.signed|integer|min:1',
            'operations.*.payload' => 'present_with:operations.*.op_id|array',
            'operations.*.origin_device' => 'nullable|uuid',
            // The signed shape (wave M5 S3): the whole operation (op_id, seq, type, occurred_at, staff_id, payload, origin_device) as JSON text, exactly the bytes the till wrote to its journal. Fields beside it are ignored.
            'operations.*.signed' => 'nullable|string',
            // HMAC-SHA256(key, prev || signed), 64 hex characters.
            'operations.*.mac' => 'nullable|string',
            // The mac of the operation before it (the first under a key: HMAC-SHA256(key, "rizqim-sync/v1/" + device uuid)), 64 hex characters.
            'operations.*.prev' => 'nullable|string',
            // The number of the key it was signed with.
            'operations.*.kid' => 'nullable|integer',
        ]);

        $operations = [];
        foreach ($data['operations'] as $k => $operation) {
            // Signature fields beside an unsigned operation mean nothing
            unset($operation['signed'], $operation['mac'], $operation['prev'], $operation['kid']);
            $operations[] = $operation + (isset($signatures[$k]) ? ['signature' => $signatures[$k]] : []);
        }

        try {
            $outcome = $this->sync()->sync(
                $device,
                $operations,
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
            $this->sync()->report($device, [], ['size' => (int) $data['queue_size'], 'oldest_at' => $data['oldest_at'] ?? null], null);
        }

        $query = $this->syncErrors()->query((int) $device->store_id, null, $device);

        return $this->success([
            'server_time' => now()->toJSON(),
            // The guard's row: report() above saved onto this same instance, so no second read
            'device' => PosSyncPresenter::device($device),
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

        $page = $this->syncErrors()->query((int) $user->store_id, $branchId, $device, (bool) ($data['all'] ?? false))->paginate(50);

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

        $op = $this->syncErrors()->resolve($op, $user, (string) $data['resolution'], $data['note'] ?? null);

        return $this->success(PosSyncPresenter::operation($op->fresh() ?? $op), $data['resolution'] === 'resolved' ? 'Hal qilindi deb belgilandi.' : 'Yopildi.');
    }

    /**
     * Wave M5 S3 (docs/scale/M5_DESIGN.md §4): opens the signed operations of the batch. An element with `signed` (the
     * operation's JSON text as the till wrote it to its journal) is replaced in the request by the operation decoded
     * from that text, so the rules of store() check it like an unsigned one; what was written beside `signed` is dropped
     * (nobody signed it). Returned: what each such operation came with, by its place in the batch, for the service to
     * verify — the exact bytes received (`raw`), and `mac` / `prev` / `kid` as sent, whatever they hold: a signature
     * field of the wrong kind makes that operation badly signed, never the batch invalid.
     *
     * Text that is not a JSON object leaves an element without the operation's fields: 422, like any malformed
     * operation (the server cannot record what has no op_id and seq).
     *
     * @return array<int, array{raw: string, mac: mixed, prev: mixed, kid: mixed}>
     */
    private function openSigned(Request $request): array
    {
        $sent = $request->input('operations');
        if (!is_array($sent)) {
            return [];
        }
        // The bytes as they arrived: the request middleware trims the strings of the parsed input
        $body = $request->isJson() ? json_decode($request->getContent(), true) : null;

        $signatures = [];
        foreach ($sent as $k => $element) {
            if (!is_array($element) || !is_string($element['signed'] ?? null)) {
                continue;
            }
            $raw = is_string($body['operations'][$k]['signed'] ?? null) ? $body['operations'][$k]['signed'] : $element['signed'];
            $decoded = json_decode($raw, true, 64);
            $operation = is_array($decoded) && !array_is_list($decoded) ? self::asRequestInput($decoded) : [];

            // Without `signed`: the rules of store() then require the operation's fields of it, as of an unsigned one
            $sent[$k] = array_intersect_key($operation, array_flip(['op_id', 'seq', 'type', 'occurred_at', 'staff_id', 'payload', 'origin_device']));
            $signatures[$k] = ['raw' => $raw, 'mac' => $element['mac'] ?? null, 'prev' => $element['prev'] ?? null, 'kid' => $element['kid'] ?? null];
        }
        $request->merge(['operations' => $sent]);

        return $signatures;
    }

    /**
     * What the request middleware does to the old shape (TrimStrings, ConvertEmptyStringsToNull), done to an operation
     * decoded from signed text: both shapes reach the applier alike.
     */
    private static function asRequestInput(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::asRequestInput(...), $value);
        }
        if (is_string($value)) {
            $value = Str::trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
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
        if (!$user instanceof User || !$this->scope()->isStoreStaff($user)) {
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
        $acting = $this->permissions()->actingBranchId($user);
        if ($branchId === null) {
            return $acting;
        }
        $branch = \App\Models\Branch::where('store_id', $user->store_id)->find($branchId);
        if ($branch === null || !$this->scope()->allowsBranch($user, $permission, $branch)) {
            throw new HttpResponseException($this->error('Filial topilmadi.', [], 404));
        }

        return (int) $branch->id;
    }
}
