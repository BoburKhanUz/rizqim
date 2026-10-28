<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AdminPermission;
use App\Enums\StoreApplicationStage;
use App\Enums\StoreFormat;
use App\Http\Requests\Admin\AdminRules;
use App\Models\StoreApplication;
use App\Models\User;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\AdminSessionService;
use App\Services\Admin\Stores\ApplicationPipeline;
use App\Services\Admin\Stores\StoreAdminException;
use App\Services\Admin\Stores\StorePresenter;
use App\Services\Admin\Today\PlatformStats;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-22: Ovqatxonalar → Ulash jarayoni: store applications and their pipeline stages. Reads need admin.stores.view;
 * edits, stage moves and checklist ticks admin.stores.manage (audited); converting to a store and rejecting also a
 * step-up and a `reason`, and so does the move to `faol` (it activates the store).
 */
#[Group('Admin')]
class AdminStoreApplicationController extends AdminController
{
    private const ROW = 'array{id: int, uuid: string, name: string, format: string, format_label: string, region: array{id: int, name: string}|null, city: string|null, owner_name: string, source: string, stage: string, stage_label: string, stage_changed_at: string|null, assignee: array{id: int, name: string}|null, due_on: string|null, overdue: bool, store: array{id: int, name: string, status: string}|null, created_at: string|null}';

    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    public function __construct(
        private readonly ApplicationPipeline $pipeline,
        private readonly StorePresenter $presenter,
        private readonly AdminPermissionService $permissions,
        private readonly AdminSessionService $sessions,
    ) {}

    /**
     * Applications (pipeline)
     *
     * Filters: `stage` (a stage, `open` — every stage but active and rejected — or `overdue`: open and past the due
     * date), `assignee_id` (or `me`), `source`, `q` (name, owner, the whole phone); pages (`page`, `per_page` 1–200,
     * default 50), the overdue first, then by due date. `stage_counts`: per stage under the same filters (but stage),
     * plus `overdue`. `stages`: the pipeline with labels.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::ROW . '>, current_page: int, last_page: int, per_page: int, total: int, stage_counts: array<string, int>, stages: list<array{key: string, label: string}>, formats: list<array{key: string, label: string}>}}')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'stage' => ['nullable', Rule::in([...StoreApplicationStage::values(), 'open', 'overdue'])],
            'assignee_id' => ['nullable', 'regex:/^(me|\d{1,18})$/'],
            'source' => ['nullable', Rule::in(StoreApplication::SOURCES)],
            'q' => 'nullable|string|min:2|max:100',
            'per_page' => 'nullable|integer|min:1|max:200',
            'page' => 'nullable|integer|min:1',
        ], UzValidation::messages());
        $member = $this->member($request);
        $today = PlatformStats::dayStart(PlatformStats::now())->format('Y-m-d');
        $open = fn (Builder $query) => $query->whereNotIn('stage', [StoreApplicationStage::ACTIVE->value, StoreApplicationStage::REJECTED->value]);

        $base = StoreApplication::query();
        if (filled($filters['assignee_id'] ?? null)) {
            $base->where('assignee_id', $filters['assignee_id'] === 'me' ? $member->id : (int) $filters['assignee_id']);
        }
        if (filled($filters['source'] ?? null)) {
            $base->where('source', $filters['source']);
        }
        if (filled($filters['q'] ?? null)) {
            $q = trim((string) $filters['q']);
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $phone = ApplicationPipeline::normalizePhone($q);
            $base->where(fn (Builder $where) => $where->where('name', 'ilike', $like)->orWhere('owner_name', 'ilike', $like)
                ->when($phone !== null, fn (Builder $byPhone) => $byPhone->orWhere('phone', $phone)));
        }

        $counts = (clone $base)->groupBy('stage')->selectRaw('stage, count(*) as applications')->toBase()->pluck('applications', 'stage');
        $stageCounts = [];
        foreach (StoreApplicationStage::values() as $stage) {
            $stageCounts[$stage] = (int) ($counts[$stage] ?? 0);
        }
        $stageCounts['overdue'] = $open(clone $base)->where('due_on', '<', $today)->count();

        $query = clone $base;
        $stage = $filters['stage'] ?? null;
        if ($stage === 'open') {
            $open($query);
        } elseif ($stage === 'overdue') {
            $open($query)->where('due_on', '<', $today);
        } elseif ($stage !== null) {
            $query->where('stage', $stage);
        }

        $page = $query
            ->orderByRaw("case when stage not in ('active', 'rejected') and due_on < ? then 0 else 1 end", [$today])
            ->orderByRaw('due_on asc nulls last')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 50) ?: 50)
            ->withQueryString();

        return $this->success([
            'data' => array_map(fn (StoreApplication $application) => $this->presenter->applicationRow($application), $page->items()),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'stage_counts' => $stageCounts,
            'stages' => array_map(fn (StoreApplicationStage $stage) => ['key' => $stage->value, 'label' => $stage->label()], StoreApplicationStage::cases()),
            'formats' => array_map(fn (StoreFormat $format) => ['key' => $format->value, 'label' => $format->label()], StoreFormat::cases()),
        ]);
    }

    /**
     * Application card
     *
     * The row fields and `phone` (masked unless admin.pii.view, reveals audited), `tin`, `comment`, the rejection,
     * `checklist` (every stage: items with `done`, `ticked` by hand, `detected` from the store), `crm` (notes, tasks),
     * `audit` and `actions`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $application = $this->find($uuid);
        if ($application === null) {
            return $this->notFound();
        }

        return $this->success($this->presenter->applicationCard($application, $this->member($request)));
    }

    /**
     * Add an application (typed in by the team)
     *
     * The public form's fields (`name`, `format`, `region_id`, `city`, `owner_name`, `phone`, `tin`, `comment`) and
     * optionally `assignee_id` and `due_on`. Source `admin`. 201 with the card.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            ...self::fieldRules(true),
            'assignee_id' => ['nullable', 'integer', $this->memberRule()],
            'due_on' => 'nullable|date_format:Y-m-d',
        ], UzValidation::messages());

        $application = $this->pipeline->create($data, 'admin', $this->member($request));
        AdminAudit::for($request)->subject($application)->changes([], ['stage' => $application->stage->value, 'name' => $application->name]);

        return $this->success($this->presenter->applicationCard($application, $this->member($request)), "Ariza qo'shildi.", 201);
    }

    /**
     * Edit an application
     *
     * Any of the fields, `assignee_id` (a team member with admin.stores.manage, or null), `due_on` (the current stage's
     * deadline). Answers the card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    public function update(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            ...self::fieldRules(false),
            'assignee_id' => ['sometimes', 'nullable', 'integer', $this->memberRule()],
            'due_on' => 'sometimes|nullable|date_format:Y-m-d',
        ], UzValidation::messages());

        $application = $this->find($uuid);
        if ($application === null) {
            return $this->notFound();
        }

        $change = $this->pipeline->update($application, $data);
        $audit = AdminAudit::for($request)->subject($application)->store($application->store_id)->changes($change['before'], $change['after']);
        if ($change['before'] === $change['after']) {
            $audit->skip();
        }

        return $this->success($this->presenter->applicationCard($application->refresh(), $this->member($request)), 'Ariza saqlandi.');
    }

    /**
     * Move to another stage
     *
     * `stage` (not `rejected`: use reject), `note` (optional), `due_on` (optional; default today + the stage's days).
     * Forward needs the required checklist items of every stage passed (422 `checklist_incomplete` with `stage` and
     * `missing`); `menu` and later need the store (422 `store_required`); `active` activates the store and needs a
     * step-up (403 `reauth_required`) and a `reason`. Backward is free (not from `active`, 422 `already_active`); a
     * rejected application reopens at `application` or `review` (422 `reopen_only`). Answers the card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(422, 'Not allowed (checklist_incomplete, store_required, already_active, reopen_only, use_reject)', type: self::ERROR)]
    public function stage(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'stage' => ['required', Rule::enum(StoreApplicationStage::class)],
            'note' => 'nullable|string|max:500',
            'due_on' => 'nullable|date_format:Y-m-d',
            'reason' => AdminRules::reason($request->input('stage') === StoreApplicationStage::ACTIVE->value),
        ], UzValidation::messages());

        $application = $this->find($uuid);
        if ($application === null) {
            return $this->notFound();
        }

        $to = StoreApplicationStage::from($data['stage']);
        $member = $this->member($request);
        if ($to === StoreApplicationStage::ACTIVE && !$this->recentlyConfirmed($member)) {
            return $this->refuse('Bu amal uchun 2FA kodini qayta kiriting.', 'reauth_required', 403);
        }

        $from = $application->stage;
        try {
            $application = $this->pipeline->move($application, $to, $member, $data['note'] ?? $data['reason'] ?? null, $data['due_on'] ?? null);
        } catch (StoreAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        AdminAudit::for($request)->subject($application)->store($application->store_id)
            ->changes(['stage' => $from->value], ['stage' => $application->stage->value])
            ->meta(array_filter(['note' => isset($data['note']) ? mb_substr($data['note'], 0, 500) : null]));

        return $this->success($this->presenter->applicationCard($application, $member), "Ariza «{$application->stage->label()}» bosqichiga o'tdi.");
    }

    /**
     * Tick a checklist item
     *
     * `item` (a key of any stage's checklist), `done` (true / false). Detected items are done whatever is ticked.
     * Answers the card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    public function checklist(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'item' => 'required|string|max:64',
            'done' => 'required|boolean',
        ], UzValidation::messages());

        $application = $this->find($uuid);
        if ($application === null) {
            return $this->notFound();
        }

        try {
            $application = $this->pipeline->tick($application, $data['item'], (bool) $data['done'], $this->member($request));
        } catch (StoreAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        AdminAudit::for($request)->subject($application)->store($application->store_id)->meta(['item' => $data['item'], 'done' => (bool) $data['done']]);

        return $this->success($this->presenter->applicationCard($application, $this->member($request)), "Ro'yxat saqlandi.");
    }

    /**
     * Create the store from the application
     *
     * The store, its owner (a vouched SMS login phone and a Telegram invite) and the main branch, through the same
     * path as every other store creation (StoreProvisioner). Store `pending` and hidden from the marketplace until the application
     * reaches `faol`. Fields: `branch_district_id`, `branch_address`, `branch_lat`, `branch_lng` (required);
     * `branch_region_id` (default: the application's region), `branch_radius` (5), `branch_phone`,
     * `branch_open_time`/`branch_close_time` (HH:MM), `name`, `owner_name`, `owner_phone` (+998XXXXXXXXX; default: the
     * application's), `phone`, `tin`, `legal_name`, `commission_rate` (a flat rate; none: the channel rates of Billing v2),
     * `payment_account_type`; `reason`. Step-up.
     * 409 `already_converted`, 409 `owner_phone_taken` (another staff account has it), 422 `owner_phone_invalid`.
     * Answers the card and `invite_link` (only here).
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{application: array<string, mixed>, store_id: int, invite_link: string}}')]
    #[ApiDocResponse(409, 'Already converted or the phone is taken (already_converted, owner_phone_taken)', type: self::ERROR)]
    public function convert(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'tin' => 'nullable|digits:9',
            'legal_name' => 'nullable|string|max:255',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'payment_account_type' => ['nullable', Rule::enum(\App\Enums\PaymentAccountType::class)],
            'owner_name' => 'nullable|string|max:255',
            'owner_phone' => 'nullable|string|max:20',
            'branch_region_id' => 'nullable|integer|exists:regions,id',
            'branch_district_id' => 'required|integer|exists:districts,id',
            'branch_address' => 'required|string|max:255',
            'branch_phone' => 'nullable|string|max:20',
            'branch_lat' => 'required|numeric|min:-90|max:90',
            'branch_lng' => 'required|numeric|min:-180|max:180',
            'branch_radius' => 'nullable|numeric|min:0.5|max:100',
            'branch_open_time' => 'nullable|date_format:H:i',
            'branch_close_time' => 'nullable|date_format:H:i',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        $application = $this->find($uuid);
        if ($application === null) {
            return $this->notFound();
        }
        if (!isset($data['branch_region_id']) && $application->region_id === null) {
            return $this->refuse('Filial viloyatini tanlang.', 'region_required', 422);
        }
        if (isset($data['owner_phone'])) {
            $data['owner_phone'] = ApplicationPipeline::normalizePhone($data['owner_phone']) ?? $data['owner_phone'];
        }
        if (isset($data['phone'])) {
            $data['phone'] = ApplicationPipeline::normalizePhone($data['phone']) ?? $data['phone'];
        }

        try {
            $result = $this->pipeline->convert($application, $data);
        } catch (StoreAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        AdminAudit::for($request)->subject($result['application'])->store((int) $result['store']->id)
            ->changes(['store_id' => null], ['store_id' => (int) $result['store']->id])
            ->meta(['store_name' => $result['store']->name]);

        return $this->success([
            'application' => $this->presenter->applicationCard($result['application'], $this->member($request)),
            'store_id' => (int) $result['store']->id,
            'invite_link' => $result['invite_link'],
        ], 'Ovqatxona yaratildi. Telegram havolani egasiga yuboring.', 201);
    }

    /**
     * Reject an application
     *
     * `reason` (the team sees it on the card). A converted application's store becomes `rejected` and hidden. Step-up.
     * 409 `already_rejected`, 422 `already_active`. Answers the card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    public function reject(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());

        $application = $this->find($uuid);
        if ($application === null) {
            return $this->notFound();
        }

        $from = $application->stage;
        try {
            $application = $this->pipeline->reject($application, $this->member($request), $data['reason']);
        } catch (StoreAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        AdminAudit::for($request)->subject($application)->store($application->store_id)
            ->changes(['stage' => $from->value], ['stage' => $application->stage->value]);

        return $this->success($this->presenter->applicationCard($application, $this->member($request)), 'Ariza rad etildi.');
    }

    /**
     * The fields of an application (the public form's, which StoreApplicationController shares).
     *
     * @return array<string, mixed>
     */
    public static function fieldRules(bool $required): array
    {
        $need = $required ? 'required' : 'sometimes';

        return [
            'name' => "{$need}|string|min:2|max:120",
            'format' => [$need, Rule::enum(StoreFormat::class)],
            'region_id' => ['nullable', 'integer', Rule::exists('regions', 'id')->where('is_active', true)],
            'city' => 'nullable|string|max:120',
            'owner_name' => "{$need}|string|min:2|max:120",
            'phone' => [$need, 'string', 'max:30', function (string $attribute, mixed $value, \Closure $fail) {
                if (!is_string($value) || ApplicationPipeline::normalizePhone($value) === null) {
                    $fail("Telefon raqami +998 XX XXX XX XX ko'rinishida bo'lsin.");
                }
            }],
            'tin' => 'nullable|digits:9',
            'comment' => 'nullable|string|max:1000',
        ];
    }

    private function memberRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if ($value === null) {
                return;
            }
            $member = User::query()->whereKey((int) $value)->whereNotNull('platform_role')->whereNull('platform_disabled_at')->first();
            if ($member === null || !$this->permissions->has($member, AdminPermission::STORES_MANAGE)) {
                $fail("Mas'ul — ovqatxonalarni boshqarish ruxsati bor jamoa a'zosi bo'lsin.");
            }
        };
    }

    private function recentlyConfirmed(User $member): bool
    {
        $token = $this->sessions->current($member);

        return $token !== null && $this->sessions->reauthenticatedUntil($token) !== null;
    }

    private function find(string $uuid): ?StoreApplication
    {
        return StoreApplication::query()->where('uuid', $uuid)->first();
    }

    private function notFound(): JsonResponse
    {
        return $this->refuse('Ariza topilmadi.', 'not_found', 404);
    }
}
