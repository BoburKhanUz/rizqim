<?php

namespace App\Http\Controllers\Api\V1\PosHall;

use App\Domain\Pos\Orders\PosOrderPresenter;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Domain\Pos\Waitlist\WaitlistPresenter;
use App\Domain\Pos\Waitlist\WaitlistService;
use App\Domain\Pos\Waitlist\WaitlistStatus;
use App\Enums\Permission;
use App\Models\PosWaitlistEntry;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Idempotency\IdempotencyService;
use App\Services\Order\StaffOrderScope;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * P6-24: the hostess' waitlist ("kutish ro'yxati", docs/API.md "Hostes: kutish ro'yxati va stol holati"). A terminal
 * PIN session or a normal staff sign-in with hostess.manage, on a branch with the hostess module; another branch's or
 * store's entry is a 404. Every write takes an Idempotency-Key (428 without) and the entry's `version` when the screen
 * has one (409 `stale_version`). The hostess sees the guest's number; the owner's view of it is masked.
 */
#[Group('POS: hostess', "Hostes: kutish ro'yxati, stol holati va stol broni (P6-24, P6-26).")]
#[HeaderParameter('Idempotency-Key', description: 'Required on every change: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
class PosWaitlistController extends PosHostessController
{
    public function __construct(
        StaffOrderScope $scope,
        PermissionService $permissions,
        IdempotencyService $idempotency,
        PosOrderPresenter $presenter,
        private readonly WaitlistService $waitlist,
        private readonly WaitlistPresenter $entries,
    ) {
        parent::__construct($scope, $permissions, $idempotency, $presenter);
    }

    /**
     * The waitlist
     *
     * The branch's entries of a business day (default today's), in the order the guests came, each with its place in
     * the queue (`position`, waiting entries only), how long it has waited and the wait the hostess quoted; `summary`
     * counts them by state. Filter by `status`. Needs hostess.manage; the hostess module (403 `module_disabled`).
     */
    #[QueryParameter('branch_id', description: "The branch; defaults to the caller's or the terminal's branch.", type: 'int')]
    #[QueryParameter('date', description: 'Business day, Y-m-d (default: today).', type: 'string')]
    #[QueryParameter('status', description: 'One state: waiting, seated, canceled or no_show.', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{branch_id: int, date: string, entries: list<' . WaitlistPresenter::ENTRY . '>, summary: ' . WaitlistPresenter::SUMMARY . '}}')]
    public function index(Request $request): JsonResponse
    {
        $user = $this->staff($request);
        $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'status' => ['nullable', 'string', Rule::in(WaitlistStatus::values())],
        ]);
        $branch = $this->branchFor($request, $user, Permission::HOSTESS_MANAGE);
        $this->requireModule($branch, PosModule::HOSTESS);

        $all = $this->waitlist->query($branch, $request->query('date'))->get();
        $status = $request->filled('status') ? WaitlistStatus::from($request->string('status')->toString()) : null;
        $rows = $this->entries->entries($all);
        if ($status !== null) {
            $rows = array_values(array_filter($rows, fn (array $row) => $row['status'] === $status->value));
        }

        return $this->success([
            'branch_id' => (int) $branch->id,
            'date' => (string) ($request->query('date') ?? BusinessDay::of($branch)),
            'entries' => $rows,
            'summary' => $this->entries->summary($all),
        ]);
    }

    /**
     * Write a guest on the waitlist
     *
     * `guest_name`, `party_size` (1-200), optional `guest_phone` (stored as +998XXXXXXXXX), `quoted_wait_minutes`
     * (0-600: what the hostess told the guest) and `note`. Announced as `waitlist.changed` on the branch's channel.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . WaitlistPresenter::ENTRY . '}')]
    public function store(Request $request): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate([
            'guest_name' => 'required|string|min:1|max:120',
            'guest_phone' => 'nullable|string|max:32',
            'party_size' => 'required|integer|min:1|max:' . WaitlistService::MAX_PARTY,
            'quoted_wait_minutes' => 'nullable|integer|min:0|max:' . WaitlistService::MAX_WAIT_MINUTES,
            'note' => 'nullable|string|max:500',
        ]);
        $branch = $this->branchFor($request, $user, Permission::HOSTESS_MANAGE);
        $this->requireModule($branch, PosModule::HOSTESS);

        return $this->atomically(
            $request,
            fn () => $this->waitlist->create($branch, $data, $user),
            fn (PosWaitlistEntry $entry) => $this->success($this->present($entry), "Mehmon kutish ro'yxatiga yozildi.", 201),
        );
    }

    /**
     * Change an entry
     *
     * Only the fields sent change (name, phone, party size, quoted wait, note) and only while the entry is waiting
     * (409 `entry_closed`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . WaitlistPresenter::ENTRY . '}')]
    public function update(Request $request, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate([
            'guest_name' => 'sometimes|required|string|min:1|max:120',
            'guest_phone' => 'sometimes|nullable|string|max:32',
            'party_size' => 'sometimes|required|integer|min:1|max:' . WaitlistService::MAX_PARTY,
            'quoted_wait_minutes' => 'sometimes|nullable|integer|min:0|max:' . WaitlistService::MAX_WAIT_MINUTES,
            'note' => 'sometimes|nullable|string|max:500',
            'version' => 'nullable|integer|min:1',
        ]);
        $entry = $this->entry($user, $uuid);

        return $this->atomically(
            $request,
            fn () => $this->waitlist->update($entry, $data, $user, isset($data['version']) ? (int) $data['version'] : null),
            fn (PosWaitlistEntry $entry) => $this->success($this->present($entry), "Yozuv o'zgartirildi."),
        );
    }

    /**
     * Call the guest ("stol tayyor")
     *
     * Stamps `notified_at` on a waiting entry (calling again moves it). Nothing is sent to the guest: the hostess calls
     * them with the number on the entry.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . WaitlistPresenter::ENTRY . '}')]
    public function notify(Request $request, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate(['version' => 'nullable|integer|min:1']);
        $entry = $this->entry($user, $uuid);

        return $this->atomically(
            $request,
            fn () => $this->waitlist->notify($entry, $user, isset($data['version']) ? (int) $data['version'] : null),
            fn (PosWaitlistEntry $entry) => $this->success($this->present($entry), 'Mehmon chaqirildi.'),
        );
    }

    /**
     * Seat the party
     *
     * Gives the waiting party `dining_table_id` (an active place of the branch; 422 `table_not_found`). With
     * `open_order: true` the party's hall order is opened there (`party_size` guests; optional `waiter_id`, else the
     * opener if a waiter) and linked to the entry (`order_uuid`); the order needs orders.manage. 409 `entry_closed`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . WaitlistPresenter::ENTRY . '}')]
    public function seat(Request $request, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate([
            'dining_table_id' => 'required|integer|min:1',
            'open_order' => 'nullable|boolean',
            'waiter_id' => 'nullable|integer|min:1',
            'version' => 'nullable|integer|min:1',
        ]);
        $entry = $this->entry($user, $uuid);
        $open = (bool) ($data['open_order'] ?? false);
        if ($open && !$this->permissions->allows($user, Permission::ORDERS_MANAGE, $entry->branch)) {
            throw new HttpResponseException($this->error('Buyurtma ochish uchun ruxsat yo\'q.', ['permission' => Permission::ORDERS_MANAGE->value], 403));
        }

        return $this->atomically(
            $request,
            fn () => $this->waitlist->seat($entry, (int) $data['dining_table_id'], $user, $open, isset($data['waiter_id']) ? (int) $data['waiter_id'] : null, $this->permissions->terminalOf($user)?->id, isset($data['version']) ? (int) $data['version'] : null),
            fn (PosWaitlistEntry $entry) => $this->success($this->present($entry), 'Mehmon joylashtirildi.'),
        );
    }

    /**
     * The guest left
     *
     * Closes a waiting entry as `canceled`; optional `reason`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . WaitlistPresenter::ENTRY . '}')]
    public function cancel(Request $request, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate(['reason' => 'nullable|string|max:255', 'version' => 'nullable|integer|min:1']);
        $entry = $this->entry($user, $uuid);

        return $this->atomically(
            $request,
            fn () => $this->waitlist->cancel($entry, $data['reason'] ?? null, $user, isset($data['version']) ? (int) $data['version'] : null),
            fn (PosWaitlistEntry $entry) => $this->success($this->present($entry), 'Yozuv bekor qilindi.'),
        );
    }

    /**
     * The guest never came
     *
     * Closes a waiting entry as `no_show` (usually after calling them).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . WaitlistPresenter::ENTRY . '}')]
    public function noShow(Request $request, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate(['version' => 'nullable|integer|min:1']);
        $entry = $this->entry($user, $uuid);

        return $this->atomically(
            $request,
            fn () => $this->waitlist->noShow($entry, $user, isset($data['version']) ? (int) $data['version'] : null),
            fn (PosWaitlistEntry $entry) => $this->success($this->present($entry), '"Kelmadi" deb belgilandi.'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PosWaitlistEntry $entry): array
    {
        $position = null;
        if ($entry->status->isOpen()) {
            $position = 1 + PosWaitlistEntry::query()
                ->where('branch_id', $entry->branch_id)->where('business_date', $entry->business_date->format('Y-m-d'))
                ->where('status', WaitlistStatus::WAITING->value)
                ->where(fn ($query) => $query->where('created_at', '<', $entry->created_at)->orWhere(fn ($same) => $same->where('created_at', $entry->created_at)->where('id', '<', $entry->id)))
                ->count();
        }

        return $this->entries->entry($entry, $position);
    }

    private function entry(User $user, string $uuid): PosWaitlistEntry
    {
        $entry = Str::isUuid($uuid) ? PosWaitlistEntry::with('branch')->where('uuid', $uuid)->first() : null;
        if (!$entry instanceof PosWaitlistEntry || $entry->branch === null || !$this->scope->allowsBranch($user, Permission::HOSTESS_MANAGE, $entry->branch)) {
            throw new HttpResponseException($this->error('Yozuv topilmadi yoki sizning filialingizga tegishli emas.', [], 404));
        }
        $this->requireModule($entry->branch, PosModule::HOSTESS);

        return $entry;
    }
}
