<?php

namespace Tests\Feature\Tenancy;

use App\Enums\OrderStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Third review L1: the payments triggers read the payment's order as the tables' owner (row-level security must not
 * hide it), but a callable SECURITY DEFINER helper (payments_merchant_scope(bigint), payments_parent_store(bigint,
 * jsonb)) let any role with EXECUTE (PUBLIC by default; scripts/db-roles.sh grants EXECUTE on every function to the
 * application and tenant roles) read any order's store across tenants. The lookups now live in the trigger functions
 * themselves (SECURITY DEFINER, fixed search_path), which PostgreSQL only ever calls as triggers.
 */
class PaymentTriggerHelpersTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    public function test_no_security_definer_function_of_the_schema_can_be_called_directly(): void
    {
        $callable = collect(DB::select(
            "SELECT p.oid::regprocedure::text AS f FROM pg_proc p
              WHERE p.pronamespace = current_schema()::regnamespace AND p.prosecdef AND p.prorettype <> 'trigger'::regtype
              ORDER BY 1"
        ))->pluck('f')->all();

        $this->assertSame([], $callable);
        $this->assertSame(
            ['payments_set_merchant_scope|search_path=pg_catalog, pg_temp', 'payments_store_id|search_path=pg_catalog, pg_temp'],
            collect(DB::select(
                "SELECT p.proname || '|' || array_to_string(p.proconfig, ',') AS f FROM pg_proc p
                  WHERE p.pronamespace = current_schema()::regnamespace AND p.prosecdef ORDER BY 1"
            ))->pluck('f')->all(),
        );
    }

    /**
     * The reviewer's probe as the tenant role with store A bound: no helper answers another store's order, and the
     * triggers still set every payment's store and merchant account from its order.
     */
    public function test_as_the_tenant_role_no_helper_reveals_another_stores_order_and_the_triggers_still_work(): void
    {
        $role = TenantRlsPolicyTest::tenantRole();
        if ($role === null) {
            $this->markTestSkipped('Needs the tenant role (split roles, scripts/db-roles.sh): runs in the CI job tests-app-role.');
        }
        $this->setUpLifecycleStore();
        $own = $this->makeOrder(OrderStatus::NEW, ['payment_owner' => 'STORE']);
        $other = $this->makeStore('Boshqa');
        $foreign = $this->makeOrder(OrderStatus::NEW, ['store_id' => $other->id, 'branch_id' => $this->makeBranch($other, 'B filial')->id, 'payment_owner' => 'STORE']);

        DB::beginTransaction();
        try {
            DB::unprepared("SET LOCAL ROLE \"{$role}\"; SELECT set_config('app.store_id', '{$this->store->id}', true)");
            foreach (["payments_merchant_scope({$foreign->id})", "payments_parent_store({$foreign->id}, NULL)"] as $call) {
                DB::beginTransaction();
                try {
                    $answer = DB::scalar("SELECT {$call}");
                    $this->fail("{$call} answered " . var_export($answer, true) . ' to the tenant role');
                } catch (QueryException $e) {
                    $this->assertSame('42883', $e->getCode(), $e->getMessage()); // no such function
                } finally {
                    DB::rollBack();
                }
            }

            // A payment of the tenant's own order, written without store or account: both come from the order
            $id = DB::table('payments')->insertGetId(['uuid' => (string) Str::uuid(), 'order_id' => $own->id, 'provider' => 'payme',
                'provider_transaction_id' => 'tenant-1', 'amount' => '1000.00', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            $this->assertSame([(int) $this->store->id, 'store:' . $this->store->id],
                [(int) DB::table('payments')->where('id', $id)->value('store_id'), DB::table('payments')->where('id', $id)->value('merchant_scope')]);

            // A payment "of my store" for another store's order still takes that order's store, and the policy refuses it
            DB::beginTransaction();
            try {
                DB::table('payments')->insert(['uuid' => (string) Str::uuid(), 'order_id' => $foreign->id, 'store_id' => $this->store->id,
                    'provider' => 'cash', 'amount' => '1000.00', 'status' => 'paid', 'created_at' => now(), 'updated_at' => now()]);
                $this->fail("a payment for another store's order was accepted under the tenant's own store");
            } catch (QueryException $e) {
                $this->assertSame('42501', $e->getCode());
            } finally {
                DB::rollBack();
            }
        } finally {
            DB::rollBack();
        }
    }

    /**
     * The migration that moved the store lookup into the trigger (2026_12_09_100200) rolls back to the helper as
     * 2026_11_28_100000 left it and runs again; the trigger sets the order's store at every step.
     */
    #[\Tests\Concerns\AsSchemaOwner]
    public function test_the_lookup_migration_rolls_back_and_runs_again(): void
    {
        $this->setUpLifecycleStore();
        $order = $this->makeOrder(OrderStatus::NEW);
        $migration = require database_path('migrations/2026_12_09_100200_inline_payments_parent_store_lookup.php');
        $state = fn () => [
            DB::scalar("SELECT to_regprocedure('payments_parent_store(bigint, jsonb)') IS NOT NULL"),
            (bool) DB::scalar("SELECT prosecdef FROM pg_proc WHERE oid = to_regprocedure('payments_store_id()')"),
        ];
        $storeOfNewPayment = fn () => (int) DB::table('payments')->where('id', DB::table('payments')->insertGetId(['uuid' => (string) Str::uuid(),
            'order_id' => $order->id, 'provider' => 'cash', 'amount' => '1000.00', 'status' => 'paid', 'created_at' => now(), 'updated_at' => now()]))->value('store_id');

        $this->assertSame([false, true], $state());
        $this->assertSame((int) $this->store->id, $storeOfNewPayment());

        $migration->down();
        $this->assertSame([true, false], $state());
        $this->assertTrue((bool) DB::scalar("SELECT prosecdef FROM pg_proc WHERE oid = to_regprocedure('payments_parent_store(bigint, jsonb)')"));
        $this->assertSame((int) $this->store->id, $storeOfNewPayment());

        $migration->up();
        $migration->up();
        $this->assertSame([false, true], $state());
        $this->assertSame((int) $this->store->id, $storeOfNewPayment());
    }
}
