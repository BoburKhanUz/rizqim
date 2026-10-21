<?php

namespace App\Services\Order;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Http\Middleware\RejectStaffSession;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Which orders a store staff member works with on the staff order screens (P2-11: partner web board, Mini App staff
 * screens, kitchen display): their store's orders, narrowed to their branch when they are tied to one (the owner and
 * staff without a branch: every branch). A staff PIN session on a terminal paired to a branch is narrowed to that
 * terminal's branch as well, so a store-wide cook signed in on branch A's kitchen display sees branch A only.
 *
 * The permission itself (orders.view, orders.manage, kitchen.manage...) comes from PermissionService; couriers and
 * customers hold none of these and keep GET /orders.
 */
class StaffOrderScope
{
    /** @var array<int, Device|false> terminal of a PIN session token, by token id */
    private array $terminals = [];

    public function __construct(
        protected PermissionService $permissions,
    ) {}

    /**
     * The paired POS/KDS terminal this request's staff PIN session is bound to; null for any other sign-in.
     */
    public function terminalOf(User $user): ?Device
    {
        /** @var mixed $token a TransientToken for a session sign-in (panel, tests) */
        $token = $user->currentAccessToken();
        if (!$token instanceof PersonalAccessToken || !RejectStaffSession::isStaffSession($token)) {
            return null;
        }

        $deviceId = $token->getAttribute('device_id');
        if ($deviceId === null) {
            return null;
        }

        $key = (int) $token->getKey();
        if (!array_key_exists($key, $this->terminals)) {
            $device = Device::whereKey((int) $deviceId)->whereNull('revoked_at')->first();
            $this->terminals[$key] = $device !== null && $device->isTerminal() ? $device : false;
        }

        return $this->terminals[$key] ?: null;
    }

    /**
     * The branches the user works in: null for every branch of the store, else exactly one branch id.
     *
     * @return list<int>|null
     */
    public function branchIds(User $user): ?array
    {
        if ($this->permissions->isBranchScoped($user)) {
            return [(int) $user->branch_id];
        }

        $terminalBranch = $this->terminalOf($user)?->branch_id;

        return $terminalBranch !== null ? [(int) $terminalBranch] : null;
    }

    /**
     * Whether the user holds the permission for this order: their store, their branch, and the branch of the terminal
     * their PIN session is on. A super admin is not store staff here (the panels are theirs).
     */
    public function allows(User $user, Permission $permission, Order $order): bool
    {
        if (!$this->isStoreStaff($user) || !$this->permissions->allows($user, $permission, $order)) {
            return false;
        }

        $branchIds = $this->branchIds($user);

        return $branchIds === null || ($order->branch_id !== null && in_array((int) $order->branch_id, $branchIds, true));
    }

    /**
     * The same for a branch (channel subscriptions, the branch filter of the lists).
     */
    public function allowsBranch(User $user, Permission $permission, Branch $branch): bool
    {
        if (!$this->isStoreStaff($user) || !$this->permissions->allows($user, $permission, $branch)) {
            return false;
        }

        $branchIds = $this->branchIds($user);

        return $branchIds === null || in_array((int) $branch->id, $branchIds, true);
    }

    /**
     * The orders of the user's store and branches (no permission check: the routes do it).
     *
     * @return Builder<Order>
     */
    public function query(User $user): Builder
    {
        $branchIds = $this->branchIds($user);

        return Order::query()
            ->where('orders.store_id', (int) $user->store_id)
            ->when($branchIds !== null, fn (Builder $query) => $query->whereIn('orders.branch_id', $branchIds));
    }

    public function isStoreStaff(User $user): bool
    {
        return $user->store_id !== null
            && in_array($user->role, UserRole::storeRoles(), true)
            && $user->role !== UserRole::COURIER;
    }
}
