<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AdminPermission;
use App\Models\Store;
use App\Models\StoreApplication;
use App\Models\StoreTask;
use App\Models\User;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\Stores\StoreCrm;
use App\Services\Admin\Stores\StorePresenter;
use App\Services\Admin\Today\PlatformStats;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-22: CRM-lite (Ovqatxonalar): notes and tasks about a store or an application, a store's next contact date, and
 * the task list across stores. Reads admin.stores.view, writes admin.stores.manage (audited, no step-up: nothing
 * here changes the store).
 */
#[Group('Admin')]
class AdminStoreCrmController extends AdminController
{
    private const TASK = 'array{id: int, title: string, due_on: string, state: string, assignee: array{id: int, name: string}|null, created_by: array{id: int, name: string}|null, store: array{id: int, name: string}|null, application: array{uuid: string, name: string}|null, done_at: string|null, created_at: string|null}';

    public function __construct(
        private readonly StoreCrm $crm,
        private readonly StorePresenter $presenter,
        private readonly AdminPermissionService $permissions,
    ) {}

    /**
     * Tasks
     *
     * Open tasks by due date (`state`: overdue, today, upcoming), across stores and applications. `assignee_id` (or
     * `me`), `due` (`today`: due today or earlier), `done=1` for the last 50 done instead. Up to 200.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{tasks: list<' . self::TASK . '>, counts: array{overdue: int, today: int, upcoming: int}}}')]
    public function tasks(Request $request): JsonResponse
    {
        $data = $request->validate([
            'assignee_id' => ['nullable', 'regex:/^(me|\d{1,18})$/'],
            'due' => ['nullable', Rule::in(['today'])],
            'done' => 'nullable|boolean',
        ], UzValidation::messages());
        $today = PlatformStats::dayStart(PlatformStats::now())->format('Y-m-d');

        $base = StoreTask::query();
        if (filled($data['assignee_id'] ?? null)) {
            $base->where('assignee_id', $data['assignee_id'] === 'me' ? $this->member($request)->id : (int) $data['assignee_id']);
        }
        $open = (clone $base)->whereNull('done_at');

        $query = ($data['done'] ?? false)
            ? (clone $base)->whereNotNull('done_at')->orderByDesc('done_at')->limit(50)
            : (clone $open)->when(($data['due'] ?? null) === 'today', fn ($q) => $q->where('due_on', '<=', $today))->orderBy('due_on')->orderBy('id')->limit(200);

        return $this->success([
            'tasks' => $query->get()->map(fn (StoreTask $task) => $this->presenter->task($task))->values()->all(),
            'counts' => [
                'overdue' => (clone $open)->where('due_on', '<', $today)->count(),
                'today' => (clone $open)->where('due_on', $today)->count(),
                'upcoming' => (clone $open)->where('due_on', '>', $today)->count(),
            ],
        ]);
    }

    /**
     * Add a note to a store
     *
     * `body` (1–2000 characters). 201 with the note.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{id: int, body: string, author: array{id: int, name: string}|null, store_id: int|null, application_id: int|null, created_at: string|null}}')]
    public function storeNote(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['body' => 'required|string|min:1|max:2000'], UzValidation::messages());
        $store = Store::query()->find($id);
        if ($store === null) {
            return $this->refuse('Ovqatxona topilmadi.', 'not_found', 404);
        }

        $note = $this->crm->addNote($store, $this->member($request), $data['body']);
        AdminAudit::for($request)->subject($store)->store((int) $store->id)->meta(['note_id' => (int) $note->id]);

        return $this->success($this->presenter->note($note), "Izoh qo'shildi.", 201);
    }

    /**
     * Add a note to an application
     *
     * `body` (1–2000 characters). 201 with the note.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{id: int, body: string, author: array{id: int, name: string}|null, store_id: int|null, application_id: int|null, created_at: string|null}}')]
    public function applicationNote(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['body' => 'required|string|min:1|max:2000'], UzValidation::messages());
        $application = StoreApplication::query()->where('uuid', $uuid)->first();
        if ($application === null) {
            return $this->refuse('Ariza topilmadi.', 'not_found', 404);
        }

        $note = $this->crm->addNote($application, $this->member($request), $data['body']);
        AdminAudit::for($request)->subject($application)->store($application->store_id)->meta(['note_id' => (int) $note->id]);

        return $this->success($this->presenter->note($note), "Izoh qo'shildi.", 201);
    }

    /**
     * Add a task
     *
     * `title`, `due_on` (Y-m-d, today or later), `assignee_id` (a member with admin.stores.view; default: me) and the
     * store (`store_id`) or the application (`application_uuid`). 201 with the task.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::TASK . '}')]
    public function storeTask(Request $request): JsonResponse
    {
        $today = PlatformStats::dayStart(PlatformStats::now())->format('Y-m-d');
        $data = $request->validate([
            'title' => 'required|string|min:2|max:255',
            'due_on' => "required|date_format:Y-m-d|after_or_equal:{$today}",
            'assignee_id' => ['nullable', 'integer', $this->memberRule()],
            'store_id' => 'required_without:application_uuid|nullable|integer|exists:stores,id',
            'application_uuid' => 'required_without:store_id|nullable|uuid|exists:store_applications,uuid',
        ], UzValidation::messages());

        $about = isset($data['store_id'])
            ? Store::query()->findOrFail((int) $data['store_id'])
            : StoreApplication::query()->where('uuid', $data['application_uuid'])->firstOrFail();

        $task = $this->crm->addTask($about, $this->member($request), $data['title'], $data['due_on'], isset($data['assignee_id']) ? (int) $data['assignee_id'] : null);
        AdminAudit::for($request)->subject($task)->store($task->store_id)->changes([], ['title' => $task->title, 'due_on' => $data['due_on'], 'assignee_id' => $task->assignee_id]);

        return $this->success($this->presenter->task($task), "Vazifa qo'shildi.", 201);
    }

    /**
     * Change a task
     *
     * Any of `title`, `due_on`, `assignee_id`, `done` (true: done now by me; false: open again). Answers the task.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::TASK . '}')]
    public function updateTask(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|min:2|max:255',
            'due_on' => 'sometimes|date_format:Y-m-d',
            'assignee_id' => ['sometimes', 'nullable', 'integer', $this->memberRule()],
            'done' => 'sometimes|boolean',
        ], UzValidation::messages());

        $task = StoreTask::query()->find($id);
        if ($task === null) {
            return $this->refuse('Vazifa topilmadi.', 'not_found', 404);
        }

        $change = $this->crm->updateTask($task, $data, $this->member($request));
        $audit = AdminAudit::for($request)->subject($task)->store($task->store_id)->changes($change['before'], $change['after']);
        if ($change['before'] === $change['after']) {
            $audit->skip();
        }

        return $this->success($this->presenter->task($task), 'Vazifa saqlandi.');
    }

    /**
     * Set the next contact date
     *
     * `next_contact_on` (Y-m-d, or null to clear). From that day the store shows in "Diqqat talab qiladi" until a new
     * date is set.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{next_contact_on: string|null}}')]
    public function nextContact(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['next_contact_on' => 'present|nullable|date_format:Y-m-d'], UzValidation::messages());
        $store = Store::query()->find($id);
        if ($store === null) {
            return $this->refuse('Ovqatxona topilmadi.', 'not_found', 404);
        }

        $change = $this->crm->setNextContact($store, $data['next_contact_on'], $this->member($request));
        $audit = AdminAudit::for($request)->subject($store)->store((int) $store->id)
            ->changes(['next_contact_on' => $change['before']], ['next_contact_on' => $change['after']]);
        if ($change['before'] === $change['after']) {
            $audit->skip();
        }

        return $this->success(['next_contact_on' => $change['after']], 'Keyingi aloqa sanasi saqlandi.');
    }

    private function memberRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if ($value === null) {
                return;
            }
            $member = User::query()->whereKey((int) $value)->whereNotNull('platform_role')->whereNull('platform_disabled_at')->first();
            if ($member === null || !$this->permissions->has($member, AdminPermission::STORES_VIEW)) {
                $fail("Mas'ul — ovqatxonalarni ko'rish ruxsati bor jamoa a'zosi bo'lsin.");
            }
        };
    }
}
