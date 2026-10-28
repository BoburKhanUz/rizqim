<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Pos\Sync\PosSyncErrors as SyncResolver;
use App\Domain\Pos\Sync\PosSyncOperationType;
use App\Domain\Pos\Sync\PosSyncStatus;
use App\Http\Requests\Admin\AdminRules;
use App\Models\PosSyncOperation;
use App\Models\Store;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Pos\PosPage;
use App\Services\Admin\Pos\PosSyncErrors;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-24 Oflayn sinxron xatolari: the offline operations of every till that were rejected or applied with a conflict
 * (admin.devices.view). Resolving or dismissing one: admin.stores.manage, a step-up and a `reason`, through the same
 * service as the store's own "Sinxronlash xatolari" screen.
 */
#[Group('Admin')]
class AdminPosSyncErrorController extends AdminController
{
    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    public function __construct(
        private readonly PosSyncErrors $errors,
        private readonly SyncResolver $resolver,
    ) {}

    /**
     * Offline sync errors (all stores)
     *
     * Newest first, pages (`page`, `per_page` 1-100, default 30). Filters: `state` (open: nobody resolved it, the
     * default; resolved; all), `status` (rejected: not applied; conflict: applied with a difference), `type` (the
     * operation, e.g. bill.pay), `store_id`, `device` (uuid), `from` / `to` (dates in Asia/Tashkent). Rows carry no
     * payload (the card has it). `counts`: open rejected / conflicting operations.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . PosSyncErrors::ROW . '>, ' . PosPage::SHAPE . ', counts: array{rejected: int, conflict: int, open: int}}}')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'state' => ['nullable', Rule::in(['open', 'resolved', 'all'])],
            'status' => ['nullable', Rule::in([PosSyncStatus::REJECTED->value, PosSyncStatus::CONFLICT->value])],
            'type' => ['nullable', Rule::enum(PosSyncOperationType::class)],
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'device' => 'nullable|uuid',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ], UzValidation::messages());

        $page = $this->errors->withRelations($this->errors->query($filters))->orderByDesc('id')->paginate(PosPage::perPage($filters['per_page'] ?? null));
        $names = Store::withTrashed()->whereIn('id', collect($page->items())->pluck('store_id')->unique()->all())->pluck('name', 'id');

        return $this->success(PosPage::of(
            $page,
            collect($page->items())->map(fn (PosSyncOperation $op) => $this->errors->row($op, $names[$op->store_id] ?? null))->all(),
            ['counts' => $this->errors->counts()],
        ));
    }

    /**
     * An offline sync error
     *
     * The row, the payload the till sent (keys that look like a phone, card, PIN or secret are replaced by ***) and what
     * the server made of it (`refs`). By the operation's id.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(404, 'No such error (not_found)', type: self::ERROR)]
    public function show(string $opId): JsonResponse
    {
        $op = $this->find($opId);

        return $op === null ? $this->notFound() : $this->success($this->errors->detail($op, $this->storeName($op)));
    }

    /**
     * Resolve or dismiss an error
     *
     * `resolution`: `resolved` (somebody did what was needed by hand) or `dismissed` (nothing to do); `reason` is kept
     * as the resolution note. A resolved rejected operation no longer keeps its till's shift from closing. Resolving
     * one again changes nothing. Answers the card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    public function resolve(Request $request, string $opId): JsonResponse
    {
        $data = $request->validate([
            'resolution' => ['required', Rule::in(['resolved', 'dismissed'])],
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());
        $op = $this->find($opId);
        if ($op === null) {
            return $this->notFound();
        }

        $already = $op->resolved_at !== null;
        $op = $this->resolver->resolve($op, $this->member($request), (string) $data['resolution'], (string) $data['reason']);
        $op->refresh();

        $audit = AdminAudit::for($request)->subject($op)->store((int) $op->store_id)
            ->changes(['resolution' => null], ['resolution' => $op->resolution])
            ->meta(['op_id' => $op->op_id, 'type' => $op->type, 'status' => $op->status]);
        if ($already) {
            $audit->skip();
        }

        return $this->success(
            $this->errors->detail($this->find($opId) ?? $op, $this->storeName($op)),
            $data['resolution'] === 'resolved' ? 'Hal qilindi deb belgilandi.' : 'Yopildi.',
        );
    }

    private function find(string $opId): ?PosSyncOperation
    {
        if (!\Illuminate\Support\Str::isUuid($opId)) {
            return null;
        }

        return $this->errors->withRelations(PosSyncOperation::query()->where('op_id', strtolower($opId))->where('status', '!=', PosSyncStatus::APPLIED->value))->first();
    }

    private function storeName(PosSyncOperation $op): ?string
    {
        return Store::withTrashed()->whereKey($op->store_id)->value('name');
    }

    private function notFound(): JsonResponse
    {
        return $this->refuse('Sinxronlash xatosi topilmadi.', 'not_found', 404);
    }
}
