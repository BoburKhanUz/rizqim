<?php

namespace Tests\Unit\Tenancy;

use App\Models\Order;
use App\Models\PosShift;
use App\Models\Store;
use App\Support\Tenancy\StoreScope;
use App\Support\Tenancy\TenantContext;
use RuntimeException;
use Tests\TestCase;

/**
 * M4 stage 2: the tenant context (bound, unbound, nested runAs, exception safety, the worker's reset) and the store
 * scope's SQL (a predicate while bound, none while unbound, evaluated when the query runs). No database needed.
 */
class TenantContextTest extends TestCase
{
    private function tenant(): TenantContext
    {
        return app(TenantContext::class);
    }

    public function test_unbound_by_default_and_bind_forget(): void
    {
        $this->assertNull($this->tenant()->storeId());
        $this->tenant()->bind(7);
        $this->assertSame(7, $this->tenant()->storeId());
        $this->assertSame($this->tenant(), app(TenantContext::class), 'one instance per request');
        $this->tenant()->forget();
        $this->assertNull($this->tenant()->storeId());
    }

    public function test_run_as_nests_and_restores(): void
    {
        $seen = $this->tenant()->runAs(1, function () {
            $inner = $this->tenant()->runAs(2, fn () => $this->tenant()->storeId());

            return [$inner, $this->tenant()->storeId()];
        });

        $this->assertSame([2, 1], $seen);
        $this->assertNull($this->tenant()->storeId());
    }

    public function test_run_as_restores_after_an_exception(): void
    {
        $this->tenant()->bind(3);
        try {
            $this->tenant()->runAs(4, function () {
                $this->tenant()->runAs(5, fn () => throw new RuntimeException('till error'));
            });
            $this->fail('the exception must pass through');
        } catch (RuntimeException $e) {
            $this->assertSame('till error', $e->getMessage());
        }

        $this->assertSame(3, $this->tenant()->storeId());
    }

    public function test_a_long_lived_worker_forgets_it_between_jobs(): void
    {
        // The queue worker (and Octane) call forgetScopedInstances() before each job (request)
        $this->tenant()->bind(9);
        $this->app->forgetScopedInstances();

        $this->assertNull($this->tenant()->storeId());
    }

    public function test_the_scope_adds_one_predicate_only_while_bound(): void
    {
        $this->assertStringNotContainsString('store_id', Order::query()->whereKey(5)->toSql());

        $this->tenant()->runAs(11, function () {
            $query = Order::query()->whereKey(5);
            $this->assertSame('select * from "orders" where "orders"."id" = ? and "orders"."store_id" = ? and "orders"."deleted_at" is null', $query->toSql());
            $this->assertSame([5, 11], $query->getBindings());

            // A query without the scope on purpose
            $this->assertStringNotContainsString('store_id', Order::query()->withoutGlobalScope(StoreScope::class)->whereKey(5)->toSql());
            // Models without a direct NOT NULL store_id are not scoped
            $this->assertStringNotContainsString('store_id', Store::query()->whereKey(5)->toSql());
        });

        $this->assertStringNotContainsString('store_id', Order::query()->whereKey(5)->toSql());
    }

    public function test_the_store_bound_when_the_query_runs_counts(): void
    {
        $built = PosShift::query()->where('status', 'open');

        $bound = $this->tenant()->runAs(12, fn () => (clone $built)->toBase()->getBindings());

        $this->assertSame(['open', 12], $bound);
        $this->assertSame(['open'], (clone $built)->toBase()->getBindings());
    }

    public function test_joins_get_the_qualified_column(): void
    {
        $sql = $this->tenant()->runAs(13, fn () => Order::query()->join('branches', 'branches.id', '=', 'orders.branch_id')->toSql());

        $this->assertStringContainsString('"orders"."store_id" = ?', $sql);
    }
}
