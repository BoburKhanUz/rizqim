<?php

namespace App\Models\Concerns;

use App\Support\Tenancy\StoreScope;

/**
 * M4 stage 2 (docs/scale/M4_DESIGN.md 3.2): a model whose table has a NOT NULL `store_id`. Inside a tenant request
 * (TenantContext bound by the `tenant` route middleware) its queries read the bound store's rows only; elsewhere
 * (console, queue, webhooks, admin, marketplace) nothing changes. Reads only: `store_id` is neither filled nor checked
 * on create (tests/Feature/Tenancy/StoreScopeInventoryTest: every model that must use it).
 *
 * A legitimate read of another store's or a platform row inside a tenant request says so:
 * `->withoutGlobalScope(StoreScope::class)` with a comment on the same or the previous line saying why.
 */
trait BelongsToStore
{
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope(new StoreScope());
    }
}
