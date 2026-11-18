<?php

namespace App\Support\Tenancy;

/**
 * M4 stage 2 (docs/scale/M4_DESIGN.md 3.2): the store the current tenant request acts for. While a store is bound,
 * every model with `BelongsToStore` reads only that store's rows (StoreScope); unbound, nothing is filtered.
 *
 * Bound only by the `tenant` route middleware (BindTenant) for the length of the request, through runAs(), which puts
 * the previous value back when the request is done, also when it throws. So nothing outlives the request that bound it:
 * not the next request a test makes in the same process, not a long-lived worker. The container holds it as a scoped
 * instance (AppServiceProvider), which the queue worker (and Octane) forget between jobs and requests on top of that.
 * Console commands, the scheduler, queued jobs and webhooks never bind it.
 */
class TenantContext
{
    private ?int $storeId = null;

    public function storeId(): ?int
    {
        return $this->storeId;
    }

    public function bind(int $storeId): void
    {
        $this->storeId = $storeId;
    }

    public function forget(): void
    {
        $this->storeId = null;
    }

    /**
     * Run the callback with the store bound, then put back what was bound before (also when the callback throws).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function runAs(int $storeId, callable $callback): mixed
    {
        $previous = $this->storeId;
        $this->storeId = $storeId;

        try {
            return $callback();
        } finally {
            $this->storeId = $previous;
        }
    }
}
