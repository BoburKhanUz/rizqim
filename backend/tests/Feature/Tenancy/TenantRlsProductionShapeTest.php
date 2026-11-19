<?php

namespace Tests\Feature\Tenancy;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\User;
use App\Support\Database\OwnerConnection;
use App\Support\Tenancy\TenantRls;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Feature\AccessFixtures;
use Tests\TestCase;

/**
 * M4 stage 3: a tenant request as production runs it, WITHOUT the suite's wrapping transaction: the request's
 * transactions are real ones (BEGIN at level 1, COMMIT), a single write outside them is wrapped in a real transaction of
 * its own, and what ends the role is COMMIT or ROLLBACK, not the class. Every note is
 * "<current_user>|<app.store_id>@<transaction level>". Also the retries of DB::transaction(..., 3) after an abort the
 * server itself raises (SQLSTATE 40P01).
 *
 * Nothing wraps this test, so its rows are committed: tearDown removes them (the audit lines as the schema owner, with
 * the append-only trigger off for that one transaction). Needs the tenant role and the owner connection.
 */
class TenantRlsProductionShapeTest extends TestCase
{
    use AccessFixtures;
    use RefreshDatabase;

    /** No wrapping transaction on any connection */
    protected $connectionsToTransact = [];

    /** @var list<string> */
    public static array $seen = [];

    private string $tenant;

    private string $login;

    private int $auditFrom = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $role = TenantRlsPolicyTest::tenantRole();
        if ($role === null || !OwnerConnection::prepare()) {
            $this->markTestSkipped('Needs the tenant role and the owner connection (split roles, scripts/db-roles.sh): runs in the CI job tests-app-role.');
        }
        $this->tenant = $role;
        $this->login = (string) DB::scalar('SELECT session_user');
        $this->assertSame(0, DB::transactionLevel(), 'no wrapping transaction');
        config(['database.tenant_rls.enabled' => true, 'app.debug' => false]);
        self::$seen = [];
        $this->auditFrom = (int) DB::scalar('SELECT coalesce(max(id), 0) FROM audit_logs');
        $this->setUpLifecycleStore();
    }

    protected function tearDown(): void
    {
        if (isset($this->store)) {
            DB::rollBack(0);
            $userIds = User::where('store_id', $this->store->id)->pluck('id')->push($this->customer->id)->all();
            PersonalAccessToken::whereIn('tokenable_id', $userIds)->delete();
            Order::withTrashed()->where('store_id', $this->store->id)->forceDelete();
            $categoryId = $this->product->category_id;
            $this->store->forceDelete();
            User::whereIn('id', $userIds)->forceDelete();
            Category::whereKey($categoryId)->forceDelete();
            $owner = DB::connection(OwnerConnection::NAME);
            $owner->transaction(function () use ($owner) {
                $owner->statement('ALTER TABLE audit_logs DISABLE TRIGGER audit_logs_append_only');
                $owner->table('audit_logs')->where('id', '>', $this->auditFrom)->delete();
                $owner->statement('ALTER TABLE audit_logs ENABLE TRIGGER audit_logs_append_only');
            });
            DB::disconnect(OwnerConnection::NAME);
        }

        parent::tearDown();
    }

    public static function note(string $what): void
    {
        $row = DB::selectOne("SELECT current_user AS role, coalesce(current_setting('app.store_id', true), '') AS store");
        self::$seen[] = "{$what}={$row->role}|{$row->store}@" . DB::transactionLevel();
    }

    private function request(string $name, callable $action): void
    {
        Route::middleware(['api', 'auth:sanctum', 'tenant'])->post("api/test-rls-shape/{$name}", function () use ($action) {
            $action();

            return response()->json(['ok' => true]);
        });
        $this->bearerJson('POST', "/api/test-rls-shape/{$name}", $this->owner->createToken('t')->plainTextToken)->assertOk();
    }

    public function test_a_tenant_request_on_real_transactions(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->request('flow', function () use ($order) {
            self::note('before');
            DB::transaction(function () use ($order) {
                self::note('inside');
                try {
                    DB::transaction(fn () => DB::select('SELECT 1 / 0'));
                } catch (QueryException) {
                }
                self::note('after-failed-nested');
                DB::afterCommit(function () use ($order) {
                    self::note('after-commit');
                    // A policy table, outside every transaction: wrapped in one of its own, as the tenant role
                    Order::whereKey($order->id)->update(['notes' => DB::raw("'ac ' || current_user")]);
                    self::note('after-commit-write');
                });
                AuditLog::create(['action' => 'zz.platform']); // no store: written as the login role, then back
                self::note('after-null-audit');
                Order::whereKey($order->id)->lockForUpdate()->first()->update(['cancel_note' => 'in-tx']);
            });
            self::note('after');

            Order::whereKey($order->id)->update(['promo_note' => DB::raw("current_user || '|' || current_setting('app.store_id', true)")]);
            $order->refresh()->update(['total_price' => 77000]);
            self::note('end');
        });

        $t = "{$this->tenant}|{$this->store->id}";
        // After the first COMMIT the setting reads '' (never the store), the role is the login role again
        $this->assertSame([
            "before={$this->login}|@0", "inside={$t}@1", "after-failed-nested={$t}@1", "after-null-audit={$t}@1",
            "after-commit={$this->login}|@0", "after-commit-write={$this->login}|@0", "after={$this->login}|@0", "end={$this->login}|@0",
        ], self::$seen);
        $order->refresh();
        $this->assertSame(["ac {$this->tenant}", 'in-tx', $t, '77000.00'], [$order->notes, $order->cancel_note, $order->promo_note, $order->total_price]);
        $this->assertSame(1, AuditLog::where('action', 'zz.platform')->whereNull('store_id')->where('id', '>', $this->auditFrom)->count());
        $this->assertSame("{$this->login}|@0", self::probe());
    }

    /**
     * An abort raised by the server (a real SQLSTATE 40P01, the transaction is aborted on the server): every attempt of
     * DB::transaction(..., 3) starts a new transaction and takes role and store again; also when the abort happens in
     * a nested transaction (Laravel hands it to the outer one, which retries).
     */
    public function test_every_retry_after_a_server_raised_abort_takes_the_role_again(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);
        $abort = "DO \$\$ BEGIN RAISE EXCEPTION 'deadlock detected' USING ERRCODE = '40P01'; END \$\$";

        $this->request('retries', function () use ($order, $abort) {
            $attempt = 0;
            DB::transaction(function () use ($order, $abort, &$attempt) {
                self::note('top-' . ++$attempt);
                if ($attempt < 3) {
                    DB::statement($abort);
                }
                Order::whereKey($order->id)->update(['notes' => DB::raw("'top ' || current_user")]);
            }, 3);
            self::note('after-top');

            $attempt = 0;
            DB::transaction(function () use ($order, $abort, &$attempt) {
                self::note('nested-' . ++$attempt);
                DB::transaction(function () use ($abort, $attempt) {
                    if ($attempt < 3) {
                        DB::statement($abort);
                    }
                });
                Order::whereKey($order->id)->update(['cancel_note' => DB::raw("'nested ' || current_user")]);
            }, 3);
            self::note('after-nested');
        });

        $t = "{$this->tenant}|{$this->store->id}";
        $this->assertSame([
            "top-1={$t}@1", "top-2={$t}@1", "top-3={$t}@1", "after-top={$this->login}|@0",
            "nested-1={$t}@1", "nested-2={$t}@1", "nested-3={$t}@1", "after-nested={$this->login}|@0",
        ], self::$seen);
        $order->refresh();
        $this->assertSame(["top {$this->tenant}", "nested {$this->tenant}"], [$order->notes, $order->cancel_note]);
    }

    private static function probe(): string
    {
        $row = DB::selectOne("SELECT current_user AS role, coalesce(current_setting('app.store_id', true), '') AS store");

        return "{$row->role}|{$row->store}@" . DB::transactionLevel();
    }
}
