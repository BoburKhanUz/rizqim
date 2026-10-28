<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Notifications\TelegramNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * P2-12: adding and editing store staff accounts, shared by the panel's employee screen (EmployeeResource) and the
 * partner web panel's API (StaffController): who sees whom, which roles one may give, and the login phone the form
 * writes (vouched for by the owner or manager who saved it: StaffPhoneLoginService, docs/AUTH.md).
 */
class StaffAccountService
{
    public function __construct(
        private readonly StaffPhoneLoginService $phones,
    ) {}

    /**
     * Roles $actor may give: the owner (and a super admin) any staff role, a manager those ranked below them. P2-33:
     * nobody else gives roles (a cashier the store let see the staff list included): none.
     *
     * @return list<UserRole>
     */
    public function assignableRoles(?User $actor): array
    {
        $role = $actor?->role;

        return match ($role) {
            UserRole::OWNER, UserRole::SUPER_ADMIN => UserRole::assignableStaffRoles(),
            UserRole::MANAGER => $this->rankedBelow(UserRole::MANAGER),
            default => [],
        };
    }

    /**
     * Roles whose people $actor sees in the staff list: the ones they may give; P2-33: other staff the store let see
     * the list (staff.view) see what their branch manager sees, the roles below a manager.
     *
     * @return list<UserRole>
     */
    public function visibleRoles(?User $actor): array
    {
        if ($actor === null || !$actor->role->isStoreStaff()) {
            return $this->assignableRoles($actor);
        }

        return in_array($actor->role, [UserRole::OWNER, UserRole::MANAGER], true)
            ? $this->assignableRoles($actor)
            : $this->rankedBelow(UserRole::MANAGER);
    }

    /**
     * @return list<UserRole>
     */
    private function rankedBelow(UserRole $role): array
    {
        return array_values(array_filter(
            UserRole::assignableStaffRoles(),
            fn (UserRole $candidate) => $candidate->rank() < $role->rank()
        ));
    }

    /**
     * Narrow a users query to the staff $actor sees in the store: visibleRoles, and for anyone tied to a branch
     * (P2-33: PermissionService::isBranchScoped, not only a manager) the people of their branch and the store-wide
     * ones (as the API's courier list).
     *
     * @template TQuery of Builder<User>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public function scopeVisible(Builder $query, ?User $actor, ?int $storeId, ?int $branchId): Builder
    {
        // A NULL store would become "store_id IS NULL" and list every customer and super admin
        if ($storeId === null) {
            return $query->whereRaw('1 = 0');
        }

        $roles = $this->visibleRoles($actor);
        if ($roles === []) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('store_id', $storeId)
            ->whereIn('role', array_map(fn (UserRole $role) => $role->value, $roles));

        // P2-33: whoever is tied to a branch sees their branch (and the store-wide staff), whatever the caller passed
        if ($actor !== null && app(PermissionService::class)->isBranchScoped($actor)) {
            $branchId = (int) $actor->branch_id;
        }
        if ($branchId !== null) {
            $query->where(fn ($query) => $query->where('branch_id', $branchId)->orWhereNull('branch_id'));
        }

        return $query;
    }

    /**
     * Whether $actor may edit $staff (the rule of the old store panel's employee form): the owner everyone of the store's staff, a
     * manager the staff below them of their own branch (a manager without a branch: the staff without one).
     */
    public function canEdit(User $actor, User $staff): bool
    {
        if ($actor->is($staff) || $staff->store_id === null || (int) $actor->store_id !== (int) $staff->store_id) {
            return false;
        }
        if (!in_array($staff->role, $this->assignableRoles($actor), true)) {
            return false;
        }
        if ($actor->role === UserRole::MANAGER) {
            // The same branch (a store-wide manager: the store-wide staff)
            $own = $actor->branch_id !== null ? (int) $actor->branch_id : null;
            $theirs = $staff->branch_id !== null ? (int) $staff->branch_id : null;

            return $own === $theirs;
        }

        return $actor->role === UserRole::OWNER || $actor->role === UserRole::SUPER_ADMIN;
    }

    /**
     * A new staff account of the store, as the panel's "Create": a random password (they sign in by Telegram or SMS),
     * an invite token for the Telegram link, the form's phone as their login phone; the invite goes to the creator's
     * Telegram when they have one.
     *
     * @param  array{name: string, role: string, branch_id?: int|null, phone?: string|null, courier_type?: string|null}  $data
     */
    public function create(int $storeId, array $data, ?User $actor, bool $sendInvite = true): User
    {
        $staff = DB::transaction(function () use ($storeId, $data) {
            $staff = User::create([
                'store_id' => $storeId,
                'name' => $data['name'],
                'role' => $data['role'],
                'branch_id' => $data['branch_id'] ?? null,
                'phone' => isset($data['phone']) && trim((string) $data['phone']) !== '' ? trim((string) $data['phone']) : null,
                'password' => Hash::make(Str::random(12)),
                'telegram_invite_token' => Str::uuid()->toString(),
            ] + (isset($data['courier_type']) ? ['courier_type' => $data['courier_type']] : []));

            $this->vouchLoginPhone($staff);

            return $staff;
        });

        if ($sendInvite && $actor?->telegram_id) {
            try {
                app(TelegramNotificationService::class)->sendEmployeeInvite($staff, $actor);
            } catch (\Throwable $e) {
                Log::warning('Employee invite could not be sent to Telegram.', ['user_id' => $staff->id, 'error' => $e->getMessage()]);
            }
        }

        return $staff;
    }

    /**
     * A new employee's phone is their login phone, on the authority of the owner or manager who saved it
     * (StaffPhoneLoginService::vouch, audited). A typed phone elsewhere (the person's own profile) stays unverified.
     */
    public function vouchLoginPhone(User $staff): void
    {
        if ($staff->phone !== null && $staff->phone_verified_at === null && $staff->role->isStoreStaff()) {
            $this->phones->vouch($staff, $staff->phone);
        }
    }

    /**
     * Save an edited employee. The phone is vouched for only when the form changed it (P1-34): saving the form for
     * anything else does not turn a number the employee typed into their own profile into a login phone. Changing or
     * clearing it is audited and ends their app sessions (StaffPhoneLoginService); the caller's validation has refused
     * replacing a phone they proved by SMS (StaffPhoneLoginService::refusalForStoreVouch).
     *
     * @param  array<string, mixed>  $data
     */
    public function save(User $staff, array $data): User
    {
        $phoneChanged = false;
        $phone = null;
        if (array_key_exists('phone', $data)) {
            $phone = is_string($data['phone']) && trim($data['phone']) !== '' ? trim($data['phone']) : null;
            $phoneChanged = $phone !== $staff->phone;
            unset($data['phone']);
        }

        DB::transaction(function () use ($staff, $data, $phone, $phoneChanged) {
            $staff->update($data);

            if (!$phoneChanged) {
                return;
            }
            if ($staff->role->isStoreStaff()) {
                $this->phones->vouch($staff, $phone);
            } else {
                $staff->update(['phone' => $phone]);
            }
        });

        return $staff;
    }
}
