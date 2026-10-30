<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use App\Services\Auth\StaffAccountService;
use App\Services\Auth\StaffPhoneLoginService;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * P2-12: the staff list, adding staff and editing their name, branch and login phone from the partner web panel, as
 * the panel's employee screen does (StaffAccountService): staff.view; the owner manages every staff member, a manager
 * those ranked below them in their own branch. A login phone written here is vouched for by whoever saved it
 * (StaffPhoneLoginService, docs/AUTH.md). Roles of existing staff: PUT /owner/staff/{id}/role; PINs: /owner/staff/{id}/pin.
 */
#[Group('Owner: staff', "Xodimlar ro'yxati, xodim qo'shish, ismi, filiali va kirish telefoni (P2-12).")]
class StaffController extends ManagementController
{
    private const STAFF = 'array{id: int, name: string, role: string, role_label: string, branch: array{id: int, name: string}|null, courier_type: string|null, phone: string|null, login_phone_enabled: bool, login_phone_proof: \'sms\'|\'vouched\'|null, has_pin: bool, staff_code: string|null, telegram_linked: bool, invite_pending: bool, can_edit: bool, created_at: string|null}';

    public function __construct(
        \App\Services\Auth\PermissionService $permissions,
        private readonly StaffAccountService $staff,
    ) {
        parent::__construct($permissions);
    }

    /**
     * Staff list
     *
     * The staff the caller manages, newest first: for the owner every manager, cashier, waiter, cook and courier;
     * for a branch manager the staff below them of their branch and the store-wide ones. `login_phone_enabled`: the
     * phone signs in by SMS code; `login_phone_proof`: `sms` (the member proved it: it cannot be replaced or cleared
     * here) or `vouched` (written by an owner, manager or super admin), null without a login phone.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . self::STAFF . '>}')]
    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $branchId = $this->permissions->isBranchScoped($actor) ? (int) $actor->branch_id : null;

        $list = $this->staff->scopeVisible(User::query(), $actor, $this->storeId($request), $branchId)
            ->with('branch:id,name')
            ->orderByDesc('id')
            ->get();

        return $this->success($list->map(fn (User $user) => $this->present($user, $actor))->values());
    }

    /**
     * Add a staff member
     *
     * `role`: one the caller may give (a manager: cashier, waiter, cook, hostess, courier); `branch_id`: a branch of the store
     * (a branch manager: their own). `phone` (+998XXXXXXXXX, optional) becomes their login phone for the partner panel
     * and the staff apps; one staff account per phone in a store. The response carries the Telegram invite link.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{staff: ' . self::STAFF . ', invite_link: string}}')]
    public function store(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $storeId = $this->storeId($request);
        if ($this->staff->assignableRoles($actor) === [] || !in_array($actor->role, [UserRole::OWNER, UserRole::MANAGER], true)) {
            throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
        }

        // The request's shape (literal rules, so the OpenAPI document is the same however it is generated), then the
        // checks that depend on the caller and their store
        $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|string|in:manager,cashier,waiter,cook,hostess,courier',
            'branch_id' => 'required|integer|min:1|max:999999999999999999',
            'phone' => 'nullable|string|regex:/^\+998\d{9}$/',
        ], $this->messages(), $this->attributes());
        $data = $this->scoped($request, [
            'name' => ['required'],
            'role' => ['required', Rule::in(array_map(fn (UserRole $role) => $role->value, $this->staff->assignableRoles($actor)))],
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('store_id', $storeId)],
            'phone' => $this->phoneRules($storeId, null),
        ]);

        $this->branchAllowed($actor, (int) $data['branch_id']);

        $user = $this->staff->create($storeId, $data, $actor);

        return $this->success([
            'staff' => $this->present($user->load('branch:id,name'), $actor),
            'invite_link' => $this->inviteLink($user),
        ], "Xodim qo'shildi.", 201);
    }

    /**
     * Edit a staff member
     *
     * `name`, `branch_id`, `phone` (any subset). A new phone becomes their login phone and ends their app sessions
     * signed in with the old one; a phone they proved by SMS themselves cannot be replaced or cleared here (422).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::STAFF . '}')]
    public function update(Request $request, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $storeId = $this->storeId($request);
        $user = User::where('store_id', $storeId)->whereIn('role', array_map(fn (UserRole $role) => $role->value, UserRole::assignableStaffRoles()))->find($id);
        if (!$user instanceof User) {
            throw new HttpResponseException($this->error('Xodim topilmadi.', [], 404));
        }
        if (!$this->staff->canEdit($actor, $user)) {
            throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'branch_id' => 'sometimes|required|integer|min:1|max:999999999999999999',
            'phone' => 'sometimes|nullable|string|regex:/^\+998\d{9}$/',
        ], $this->messages(), $this->attributes());
        $data = $this->scoped($request, [
            'name' => ['sometimes'],
            'branch_id' => ['sometimes', Rule::exists('branches', 'id')->where('store_id', $storeId)],
            'phone' => ['sometimes', ...$this->phoneRules($storeId, $user)],
        ]);

        if (array_key_exists('branch_id', $data)) {
            $this->branchAllowed($actor, (int) $data['branch_id']);
        }

        $this->staff->save($user, $data);

        return $this->success($this->present($user->refresh()->load('branch:id,name'), $actor), 'Xodim saqlandi.');
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        if (!$user instanceof User) {
            throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
        }

        return $user;
    }

    /**
     * A branch manager places staff in their own branch only.
     */
    private function branchAllowed(User $actor, int $branchId): void
    {
        if ($this->permissions->isBranchScoped($actor) && (int) $actor->branch_id !== $branchId) {
            throw ValidationException::withMessages(['branch_id' => "Xodimni faqat o'z filialingizga qo'sha olasiz."]);
        }
    }

    /**
     * The rules that depend on the caller and their store, run after the request's shape is valid.
     *
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, mixed>
     */
    private function scoped(Request $request, array $rules): array
    {
        return \Illuminate\Support\Facades\Validator::make($request->only(array_keys($rules)), $rules, $this->messages(), $this->attributes())->validate();
    }

    /**
     * @return list<mixed>
     */
    private function phoneRules(int $storeId, ?User $staff): array
    {
        return [
            'nullable',
            // One staff account per phone in a store (a person in two stores is fine)
            Rule::unique('users', 'phone')->where('store_id', $storeId)->ignore($staff?->id),
            // Implicit: an emptied phone the employee proved by SMS is refused too (as the panel's employee form)
            new class($staff, $storeId) implements \Illuminate\Contracts\Validation\ValidationRule {
                public bool $implicit = true;

                public function __construct(private ?User $staff, private int $storeId) {}

                public function validate(string $attribute, mixed $value, \Closure $fail): void
                {
                    $refusal = app(StaffPhoneLoginService::class)->refusalForStoreVouch($this->staff, is_string($value) ? $value : null, $this->storeId);
                    if ($refusal !== null) {
                        $fail($refusal);
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return UzValidation::messages([
            'phone.regex' => "Raqam +998XXXXXXXXX ko'rinishida bo'lsin.",
            'phone.unique' => "Bu raqam do'koningizning boshqa xodimiga biriktirilgan.",
            'role.in' => "Bu rolni bera olmaysiz.",
            'branch_id.exists' => 'Filial topilmadi.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return ['name' => 'Ismi', 'role' => 'Roli', 'branch_id' => 'Filial', 'phone' => 'Telefon'];
    }

    /**
     * A courier's type; a row just created carries the column's default (own) only after a reload.
     */
    private function courierType(User $user): string
    {
        $type = $user->getAttribute('courier_type');

        return $type instanceof \App\Enums\CourierType ? $type->value : (is_string($type) ? $type : \App\Enums\CourierType::OWN->value);
    }

    private function inviteLink(User $user): string
    {
        $bot = $user->store?->telegram_bot_username ?: config('services.telegram.bot_username');

        return "https://t.me/{$bot}?start=invite_{$user->telegram_invite_token}";
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user, User $actor): array
    {
        $branch = $user->branch;
        $telegramId = (string) ($user->telegram_id ?? '');

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'role' => $user->role->value,
            'role_label' => $user->role->labelUz(),
            'branch' => $branch instanceof Branch ? ['id' => (int) $branch->id, 'name' => (string) $branch->name] : null,
            'courier_type' => $user->role === UserRole::COURIER ? $this->courierType($user) : null,
            'phone' => $user->phone,
            'login_phone_enabled' => StaffPhoneLoginService::loginPhone($user) !== null,
            // P2-30: how the login phone was proven: `sms` (the member typed the code: the form cannot replace or clear
            // it) or `vouched` (an owner, manager or super admin wrote it); null without a login phone
            'login_phone_proof' => StaffPhoneLoginService::loginPhone($user) !== null && in_array($user->phone_verified_via, ['sms', 'vouched'], true) ? $user->phone_verified_via : null,
            'has_pin' => $user->hasPin(),
            'staff_code' => $user->staff_code,
            'telegram_linked' => $telegramId !== '' && !str_starts_with($telegramId, 'temp_'),
            'invite_pending' => $user->telegram_invite_token !== null,
            'can_edit' => $this->staff->canEdit($actor, $user),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
