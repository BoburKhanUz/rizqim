<?php

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;
use App\Services\Order\StaffOrderScope;
use App\Support\RowId;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Real-time order channels (Laravel Reverb)
|--------------------------------------------------------------------------
|
| Every channel is private: the Mini App signs a subscription with POST /api/broadcasting/auth (Bearer Sanctum token,
| bootstrap/app.php). The scoping is OrderPolicy's: an owner sees their whole store, a manager their store or only
| their branch, a courier the orders handed to them, a customer their own. Staff without a store are refused
| everywhere (EnsureStaffHasStore). The events (app/Events/Broadcast) carry no personal data, only the order's ids
| and status: clients refetch the order through the API, which applies OrderPolicy again.
|
*/

$guards = ['guards' => ['sanctum']];

$staffWithStore = static fn (User $user, UserRole ...$roles): bool => $user->store_id !== null
    && in_array($user->role, $roles, true);

// The owner of the store and its store-wide managers (a branch manager listens on their branch channel)
Broadcast::channel('store.{storeId}', function (User $user, string $storeId) use ($staffWithStore) {
    if (!ctype_digit($storeId) || !$staffWithStore($user, UserRole::OWNER, UserRole::MANAGER)) {
        return false;
    }

    return (int) $user->store_id === (int) $storeId
        && ($user->role === UserRole::OWNER || $user->branch_id === null);
}, $guards);

// The branch's managers and the owner of the store it belongs to
Broadcast::channel('branch.{branchId}', function (User $user, string $branchId) use ($staffWithStore) {
    if (!ctype_digit($branchId) || !$staffWithStore($user, UserRole::OWNER, UserRole::MANAGER)) {
        return false;
    }

    $storeId = Branch::whereKey((int) $branchId)->value('store_id');
    if ($storeId === null || (int) $storeId !== (int) $user->store_id) {
        return false;
    }

    return $user->role === UserRole::OWNER || (int) $user->branch_id === (int) $branchId;
}, $guards);

// The courier themselves (an owner or manager standing in as the courier of an order listens on their own one too)
Broadcast::channel('courier.{userId}', function (User $user, string $userId) use ($staffWithStore) {
    return ctype_digit($userId)
        && (int) $user->id === (int) $userId
        && $staffWithStore($user, UserRole::COURIER, UserRole::MANAGER, UserRole::OWNER);
}, $guards);

// The customer: their own orders only
Broadcast::channel('user.{userId}', function (User $user, string $userId) {
    return ctype_digit($userId) && (int) $user->id === (int) $userId;
}, $guards);

/*
| P2-11: everyone working in a branch (docs/API.md §8): staff who may see its orders (orders.view there: the owner,
| store-wide staff, the branch's own manager, cashier, waiter, cook; a staff PIN session only for its terminal's
| branch) and the branch's paired POS/KDS terminals, which sign the subscription with their own terminal token (the
| `device` guard; a terminal paired without a branch serves every branch of its store). Couriers and customers keep
| their own channels.
*/
Broadcast::channel('pos-branch.{branchId}', function (User|Device $actor, string $branchId) {
    $id = RowId::parse($branchId);
    $branch = $id !== null ? Branch::find($id) : null;
    if ($branch === null) {
        return false;
    }

    if ($actor instanceof Device) {
        return $actor->user_id === null
            && $actor->isTerminal()
            && !$actor->isRevoked()
            && (int) $actor->store_id === (int) $branch->store_id
            && ($actor->branch_id === null || (int) $actor->branch_id === (int) $branch->id);
    }

    return app(StaffOrderScope::class)->allowsBranch($actor, Permission::ORDERS_VIEW, $branch);
}, ['guards' => ['sanctum', 'device']]);

/*
| P4-11: the RIZQIM team's live channel (Boshqaruv markazi): new orders, problems and alerts (stage B), for team
| members with admin.today.view. Only the admin session token signs it (the `admin` guard); a store or customer token
| never does.
*/
Broadcast::channel(\App\Events\Broadcast\PlatformOpsBroadcast::CHANNEL, function (User $member) {
    return $member->isActivePlatformStaff()
        && app(\App\Services\Admin\AdminPermissionService::class)->has($member, \App\Enums\AdminPermission::TODAY_VIEW);
}, ['guards' => ['admin']]);
