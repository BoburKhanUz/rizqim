<?php

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
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
