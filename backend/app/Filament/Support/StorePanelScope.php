<?php

namespace App\Filament\Support;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Store;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the store panel shows its user: the current tenant store's records, and for a manager tied to a branch only
 * that branch's orders (the scope OrderPolicy and the API's order lists use).
 *
 * Widgets and pages build their queries from here rather than counting on Filament's tenancy scope, which only
 * covers models that have a resource in the panel, is only registered once the panel has booted, and never reaches
 * DB::table() queries.
 */
class StorePanelScope
{
    /**
     * The panel's tenant store, as a Store (Filament hands the tenant out as a plain Model); null outside a tenant.
     */
    public static function store(): ?Store
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Store ? $tenant : null;
    }

    /**
     * The tenant store (a super admin has no store of their own), else the signed-in staff member's store.
     */
    public static function storeId(): ?int
    {
        $storeId = Filament::getTenant()?->getKey() ?? auth()->user()?->store_id;

        return $storeId !== null ? (int) $storeId : null;
    }

    /**
     * The branch a branch manager works in; null for owners, super admins and store-wide managers.
     */
    public static function branchId(): ?int
    {
        $user = auth()->user();

        return $user?->role === UserRole::MANAGER && $user->branch_id ? (int) $user->branch_id : null;
    }

    /**
     * Narrow an orders query (Eloquent or DB::table) to the panel user's orders. Without a store it matches nothing:
     * a NULL store would become "store_id IS NULL".
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function scopeOrders($query, string $table = 'orders')
    {
        $storeId = static::storeId();

        if ($storeId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where("{$table}.store_id", $storeId)
            ->when(static::branchId(), fn ($query, int $branchId) => $query->where("{$table}.branch_id", $branchId));
    }

    /**
     * @return Builder<Order>
     */
    public static function orders(): Builder
    {
        return static::scopeOrders(Order::query());
    }
}
