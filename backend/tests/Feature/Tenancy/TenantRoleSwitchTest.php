<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Realtime\RevisionPostgresConnection;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\AlertRule;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\Checks\PartitionsCheck;
use App\Support\Database\OwnerConnection;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantRls;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PDOException;
use RuntimeException;
use Tests\Feature\AccessFixtures;
use Tests\TestCase;

/**
 * M4 stage 3 (docs/scale/M4_DESIGN.md 4.3): the role switch of RevisionPostgresConnection. Inside a tenant request's
 * transaction the session is the tenant role with the request's store in `app.store_id`; before and after it, in
 * after-commit callbacks, in queued jobs, in TenantRls::asApp(), in unbound requests, with TENANT_RLS off and on the
 * owner connection it is the login role.
 *
 * The suite wraps every test in a transaction, so here the application's transactions are savepoints and the role is
 * put back by hand when they end; the same code runs in production at level 1, where COMMIT ends it. The tests on the
 * connection `rls_level0` (a second, unwrapped session: real BEGIN and COMMIT, nothing written) show that path.
 */
class TenantRoleSwitchTest extends TestCase
{
    use AccessFixtures;
    use RefreshDatabase;

    /** @var list<string> what the probes saw, in order */
    public static array $seen = [];

    private string $tenant;

    private string $login;

    private Store $storeB;

    private User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();
        $role = TenantRlsPolicyTest::tenantRole();
        if ($role === null) {
            $this->markTestSkipped('Needs the tenant role (split roles, scripts/db-roles.sh): runs in the CI job tests-app-role.');
        }
        $this->tenant = $role;
        $this->login = (string) DB::scalar('SELECT session_user');
        config(['database.tenant_rls.enabled' => true]);
        self::$seen = [];

        $this->setUpLifecycleStore();
        $this->storeB = $this->makeStore('Boshqa');
        $this->ownerB = $this->makeStaff(UserRole::OWNER, null, $this->storeB);
    }

    protected function tearDown(): void
    {
        DB::purge('rls_level0');
        parent::tearDown();
    }

    /** "<role>|<app.store_id>" of the session right now */
    public static function probe(?ConnectionInterface $connection = null): string
    {
        $row = ($connection ?? DB::connection())->selectOne("SELECT current_user AS role, coalesce(current_setting('app.store_id', true), '') AS store");

        return "{$row->role}|{$row->store}";
    }

    public static function note(string $what): void
    {
        self::$seen[] = "{$what}=" . self::probe();
    }

    private function tenantRoute(string $name, callable $action): string
    {
        Route::middleware(['api', 'auth:sanctum', 'tenant'])->post("api/test-rls/{$name}", function () use ($action) {
            $action();

            return response()->json(['ok' => true]);
        });

        return "/api/test-rls/{$name}";
    }

    private function asOwner(string $uri, ?User $owner = null): void
    {
        $this->bearerJson('POST', $uri, ($owner ?? $this->owner)->createToken('t')->plainTextToken)->assertOk();
    }

    private function finding(string $subject): ?AlertFinding
    {
        return collect(app(PartitionsCheck::class)->findings((new AlertRule)->forceFill(['kind' => 'partitions', 'threshold' => 2])))
            ->keyBy('subjectKey')->get($subject);
    }

    private function fakeDeadlock(): PDOException
    {
        return new PDOException('SQLSTATE[40P01]: Deadlock detected: 7 ERROR:  deadlock detected');
    }

    public function test_a_tenant_transaction_runs_as_the_tenant_role_with_its_store_and_ends_with_it(): void
    {
        $uri = $this->tenantRoute('transaction', function () {
            self::note('before');
            DB::transaction(function () {
                self::note('inside');
                DB::transaction(fn () => self::note('nested'));
                self::note('after-nested');
                DB::afterCommit(fn () => self::note('after-commit'));
            });
            self::note('after');
        });
        $before = RevisionPostgresConnection::$tenantTransactions;

        $this->asOwner($uri);
        $this->asOwner($uri, $this->ownerB);

        $a = "{$this->tenant}|{$this->store->id}";
        $b = "{$this->tenant}|{$this->storeB->id}";
        $this->assertSame([
            "before={$this->login}|", "inside={$a}", "nested={$a}", "after-nested={$a}", "after-commit={$this->login}|", "after={$this->login}|",
            // The next request, of another store, in the same session: its own store, nothing left of the first
            "before={$this->login}|", "inside={$b}", "nested={$b}", "after-nested={$b}", "after-commit={$this->login}|", "after={$this->login}|",
        ], self::$seen);
        $this->assertSame("{$this->login}|", self::probe());
        $this->assertSame($before + 2, RevisionPostgresConnection::$tenantTransactions, 'one switch per outermost transaction, none for the nested ones');
    }

    public function test_a_single_write_of_a_table_under_the_policy_outside_a_transaction_runs_under_the_role(): void
    {
        $who = "current_user || '|' || coalesce(current_setting('app.store_id', true), '')";
        $order = $this->makeOrder(OrderStatus::NEW);
        $second = $this->makeOrder(OrderStatus::NEW);
        $third = $this->makeOrder(OrderStatus::NEW);
        $uri = $this->tenantRoute('single', function () use ($who, $order, $second, $third) {
            Order::whereKey($order->id)->update(['notes' => DB::raw($who)]); // Eloquent mass update
            DB::table('orders')->where('id', $second->id)->update(['notes' => DB::raw($who)]); // query builder
            DB::update("UPDATE orders SET notes = {$who} WHERE id = ?", [$third->id]); // raw
            AuditLog::create(['store_id' => $this->store->id, 'action' => DB::raw($who)]); // insert ... returning id
            // Not under the policy: as before stage 3 (no transaction of its own, the login role)
            User::whereKey($this->manager->id)->update(['name' => DB::raw($who)]);
            self::note('read');
        });
        $before = RevisionPostgresConnection::$tenantTransactions;

        $this->asOwner($uri);

        $a = "{$this->tenant}|{$this->store->id}";
        $this->assertSame([$a, $a, $a], [$order->fresh()->notes, $second->fresh()->notes, $third->fresh()->notes]);
        $this->assertSame(1, AuditLog::where('store_id', $this->store->id)->where('action', $a)->count());
        $this->assertSame("{$this->login}|", $this->manager->fresh()->name);
        // Writes mode: a read outside a transaction stays with the application guard
        $this->assertSame(["read={$this->login}|"], self::$seen);
        $this->assertSame($before + 4, RevisionPostgresConnection::$tenantTransactions);
        $this->assertSame("{$this->login}|", self::probe());
    }

    public function test_exceptions_rollbacks_and_hand_made_transactions_end_the_role(): void
    {
        $uri = $this->tenantRoute('ends', function () {
            try {
                DB::transaction(function () {
                    self::note('throwing');
                    throw new RuntimeException('no');
                });
            } catch (RuntimeException) {
            }
            self::note('after-exception');

            DB::beginTransaction();
            self::note('by-hand');
            DB::rollBack();
            self::note('after-rollback');

            // DB::transaction nested in a transaction opened by hand: one switch, kept until the outer commit
            DB::beginTransaction();
            DB::transaction(fn () => self::note('nested-in-by-hand'));
            self::note('after-nested-commit');
            DB::commit();
            self::note('after-commit');

            // A failed statement in a nested transaction, caught: the outer one goes on as the tenant role
            DB::transaction(function () {
                try {
                    DB::transaction(fn () => DB::select('SELECT 1 / 0'));
                } catch (QueryException) {
                }
                self::note('after-failed-nested');
            });
            self::note('end');
        });

        $this->asOwner($uri);

        $a = "{$this->tenant}|{$this->store->id}";
        $this->assertSame([
            "throwing={$a}", "after-exception={$this->login}|",
            "by-hand={$a}", "after-rollback={$this->login}|",
            "nested-in-by-hand={$a}", "after-nested-commit={$a}", "after-commit={$this->login}|",
            "after-failed-nested={$a}", "end={$this->login}|",
        ], self::$seen);
        $this->assertSame("{$this->login}|", self::probe());
    }

    /**
     * Laravel answers a deadlock inside a nested transaction by lowering its level only (DeadlockException, no rollback
     * to the savepoint). In the suite the tenant transaction is such a nested one: the role must not stay behind.
     */
    public function test_a_deadlock_in_a_tenant_transaction_inside_another_transaction_does_not_leave_the_role_behind(): void
    {
        $uri = $this->tenantRoute('deadlock', function () {
            try {
                DB::transaction(function () {
                    self::note('inside');
                    throw $this->fakeDeadlock();
                }, 3);
            } catch (DeadlockException) {
                self::note('after-deadlock');
            }
            DB::transaction(fn () => self::note('next'));
            self::note('end');
        });

        $this->asOwner($uri);

        $a = "{$this->tenant}|{$this->store->id}";
        $this->assertSame(["inside={$a}", "after-deadlock={$this->login}|", "next={$a}", "end={$this->login}|"], self::$seen);
    }

    public function test_queued_jobs_run_as_the_login_role_inside_a_tenant_transaction(): void
    {
        $uri = $this->tenantRoute('jobs', function () {
            DB::transaction(function () {
                RoleProbeJob::dispatch('job');
                self::note('after-job');
                try {
                    RoleProbeJob::dispatch('throws', true);
                } catch (RuntimeException) {
                }
                self::note('after-throw');
            });
            self::note('end');
        });

        $this->asOwner($uri);

        $a = "{$this->tenant}|{$this->store->id}";
        // A worker would run the job in its own session as the login role; the setting is left alone, that role
        // does not read it
        $job = "{$this->login}|{$this->store->id}";
        $this->assertSame([
            "job={$job}", "job-transaction={$job}", 'job-bound=NULL', "after-job={$a}",
            "throws={$job}", "throws-transaction={$job}", 'throws-bound=NULL', "after-throw={$a}",
            "end={$this->login}|",
        ], self::$seen);
    }

    public function test_as_app_reaches_other_stores_for_exactly_its_callback(): void
    {
        $this->makeOrder(OrderStatus::NEW);
        $this->makeOrder(OrderStatus::NEW, ['store_id' => $this->storeB->id, 'branch_id' => $this->makeBranch($this->storeB, 'B filial')->id]);
        $orders = fn () => DB::table('orders')->count();

        $uri = $this->tenantRoute('as-app', function () use ($orders) {
            DB::transaction(function () use ($orders) {
                self::$seen[] = 'tenant sees ' . $orders();
                self::$seen[] = 'asApp sees ' . TenantRls::asApp(function () use ($orders) {
                    self::note('in-as-app');
                    TenantRls::asApp(fn () => self::note('nested-as-app'));
                    self::note('after-nested-as-app');
                    // A transaction of its own inside the section stays the login role
                    DB::transaction(fn () => self::note('transaction-in-as-app'));

                    return $orders();
                });
                self::note('after-as-app');
                self::$seen[] = 'tenant sees ' . $orders();

                try {
                    TenantRls::asApp(fn () => throw new RuntimeException('no'));
                } catch (RuntimeException) {
                }
                self::note('after-exception-in-as-app');

                // A statement fails inside the section, in a nested transaction that is rolled back
                try {
                    DB::transaction(fn () => TenantRls::asApp(fn () => DB::select('SELECT 1 / 0')));
                } catch (QueryException) {
                }
                self::note('after-failed-statement-in-as-app');
            });

            // Outside a tenant transaction: a transaction opened inside the section does not take the tenant role
            TenantRls::asApp(fn () => DB::transaction(fn () => self::note('as-app-outside')));
            DB::transaction(fn () => self::note('next-transaction'));
        });

        $this->asOwner($uri);

        $a = "{$this->tenant}|{$this->store->id}";
        $app = "{$this->login}|{$this->store->id}";
        $this->assertSame([
            'tenant sees 1',
            "in-as-app={$app}", "nested-as-app={$app}", "after-nested-as-app={$app}", "transaction-in-as-app={$app}",
            'asApp sees 2',
            "after-as-app={$a}", 'tenant sees 1',
            "after-exception-in-as-app={$a}",
            "after-failed-statement-in-as-app={$a}",
            "as-app-outside={$this->login}|", "next-transaction={$a}",
        ], self::$seen);
        $this->assertSame("{$this->login}|", self::probe());
    }

    public function test_unbound_requests_and_tenant_rls_off_never_switch(): void
    {
        Route::middleware(['api', 'auth:sanctum'])->post('api/test-rls/unbound', function () {
            DB::transaction(fn () => self::note('unbound'));
            User::whereKey(request()->user()->id)->update(['name' => DB::raw('current_user')]);

            return response()->json(['ok' => true]);
        });
        $uri = $this->tenantRoute('off', fn () => DB::transaction(fn () => self::note('off')));
        $before = RevisionPostgresConnection::$tenantTransactions;

        $this->asOwner('/api/test-rls/unbound');
        config(['database.tenant_rls.enabled' => false]);
        $this->asOwner($uri);

        $this->assertSame(["unbound={$this->login}|", "off={$this->login}|"], self::$seen);
        $this->assertSame($this->login, $this->owner->fresh()->name);
        $this->assertSame($before, RevisionPostgresConnection::$tenantTransactions);
    }

    public function test_the_owner_connection_never_switches(): void
    {
        if (!OwnerConnection::prepare()) {
            $this->markTestSkipped('Needs the owner connection (DB_OWNER_USERNAME): runs in the CI job tests-app-role.');
        }
        $owner = DB::connection(OwnerConnection::NAME);

        try {
            $seen = app(TenantContext::class)->runAs($this->store->id, fn () => $owner->transaction(fn () => self::probe($owner)));
        } finally {
            DB::disconnect(OwnerConnection::NAME);
        }

        $this->assertSame(OwnerConnection::username() . '|', $seen);
    }

    /**
     * A real transaction (level 1: BEGIN ... COMMIT), as in production: role and store come with every attempt of
     * DB::transaction(..., n) and end at COMMIT without a statement of ours.
     */
    public function test_on_a_real_transaction_every_retry_takes_the_role_again_and_commit_ends_it(): void
    {
        config(['database.connections.rls_level0' => config('database.connections.pgsql')]);
        $connection = DB::connection('rls_level0');
        $this->assertInstanceOf(RevisionPostgresConnection::class, $connection);
        $this->assertSame(0, $connection->transactionLevel());
        $a = "{$this->tenant}|{$this->store->id}";
        $before = RevisionPostgresConnection::$tenantTransactions;

        app(TenantContext::class)->runAs($this->store->id, function () use ($connection, $a, $before) {
            $attempts = [];
            $connection->transaction(function () use ($connection, &$attempts) {
                $attempts[] = self::probe($connection);
                if (count($attempts) < 3) {
                    throw $this->fakeDeadlock();
                }
            }, 3);
            $this->assertSame([$a, $a, $a], $attempts, 'each attempt is a new transaction and takes the role again');
            $this->assertSame($before + 3, RevisionPostgresConnection::$tenantTransactions);
            // COMMIT ended both (the setting reads '' from now on in this session, never the store)
            $this->assertSame("{$this->login}|", self::probe($connection));

            // A single write outside a transaction: wrapped in one (nothing matches, nothing is written)
            $this->assertSame(0, $connection->update('UPDATE orders SET notes = notes WHERE id = -1'));
            $this->assertSame($before + 4, RevisionPostgresConnection::$tenantTransactions);
            $this->assertSame(0, $connection->transactionLevel());
            $this->assertSame("{$this->login}|", self::probe($connection));

            // By hand, and a reconnect in the middle of a request (a new session: nothing is carried over)
            $connection->beginTransaction();
            $this->assertSame($a, self::probe($connection));
            $connection->reconnect();
            $this->assertSame(0, $connection->transactionLevel());
            $this->assertSame("{$this->login}|", self::probe($connection));
            $this->assertSame($a, $connection->transaction(fn () => self::probe($connection)));
            $connection->beginTransaction();
            $connection->rollBack();
            $this->assertSame("{$this->login}|", self::probe($connection));
        });

        // Unbound: the same connection, no switch
        $this->assertSame("{$this->login}|", $connection->transaction(fn () => self::probe($connection)));
    }

    /**
     * The audit journal through its model, inside a tenant transaction: the store's own line passes the policy; a line
     * for no store (a platform-level one) is written as the login role, so the action it records does not fail; a line
     * for ANOTHER store is what the policy is there to stop, whoever asks: refused.
     */
    public function test_an_audit_line_for_another_store_is_refused_and_one_for_no_store_is_written(): void
    {
        config(['app.debug' => false]);
        $uri = $this->tenantRoute('audit', fn () => DB::transaction(function () {
            AuditLog::create(['store_id' => $this->store->id, 'action' => 'rls.own']);
            AuditLog::create(['action' => 'rls.platform']);
            self::note('after-platform-line');
        }));
        $foreign = $this->tenantRoute('audit-foreign', fn () => DB::transaction(function () {
            AuditLog::create(['store_id' => $this->store->id, 'action' => 'rls.own-before-foreign']);
            AuditLog::create(['store_id' => $this->storeB->id, 'action' => 'rls.foreign']);
        }));

        $this->asOwner($uri);
        $this->bearerJson('POST', $foreign, $this->owner->createToken('t')->plainTextToken)->assertStatus(500);
        // The same one statement outside a transaction
        $single = $this->tenantRoute('audit-foreign-single', fn () => AuditLog::create(['store_id' => $this->storeB->id, 'action' => 'rls.foreign']));
        $this->bearerJson('POST', $single, $this->owner->createToken('t')->plainTextToken)->assertStatus(500);

        $this->assertSame(["after-platform-line={$this->tenant}|{$this->store->id}"], self::$seen);
        $this->assertSame(['rls.own' => $this->store->id, 'rls.platform' => null], AuditLog::where('action', 'like', 'rls.%')->orderBy('id')->pluck('store_id', 'action')->all());
        $this->assertSame(2, Cache::get(TenantRls::REFUSED)['count']);
        $this->assertSame('audit_logs', Cache::get(TenantRls::REFUSED)['table']);
    }

    /**
     * A write PostgreSQL refuses under the policy (here a forged row for another store, by raw SQL: the application
     * guard is out of the way): the client gets the usual server error and nothing else, the log and the monitoring
     * marker say which table, route and store, without any row data.
     */
    public function test_a_refused_write_is_a_server_error_for_the_client_and_loud_for_the_operator(): void
    {
        config(['app.debug' => false]);
        Log::spy();
        $uri = $this->tenantRoute('refused', fn () => DB::transaction(
            fn () => DB::table('audit_logs')->insert(['store_id' => $this->storeB->id, 'action' => 'forged', 'created_at' => now()]),
        ));

        $this->bearerJson('POST', $uri, $this->owner->createToken('t')->plainTextToken)
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Server Error']);

        $expected = ['table' => 'audit_logs', 'store_id' => $this->store->id, 'route' => 'POST api/test-rls/refused'];
        Log::shouldHaveReceived('critical')->once()->withArgs(
            fn (string $message, array $context) => str_contains($message, 'tenant_rls: PostgreSQL refused a write') && $context === $expected,
        );
        $marker = Cache::get(TenantRls::REFUSED);
        $this->assertSame($expected + ['count' => 1], array_diff_key($marker, ['at' => true]));
        // The owner's alert (the monitoring check `partitions`, critical), with the way to switch it off
        $finding = $this->finding('tenant_rls_refused');
        $this->assertSame(['critical', $this->store->id], [$finding->severity, $finding->storeId]);
        $this->assertStringContainsString("1 marta (oxirgisi: jadval audit_logs, yo'l POST api/test-rls/refused, do'kon {$this->store->id})", $finding->title);
        $this->assertStringContainsString('TENANT_RLS=0', $finding->title);
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'forged')->count());
        $this->assertSame("{$this->login}|", self::probe());

        // The same single statement outside a transaction is wrapped in one and refused the same way
        $single = $this->tenantRoute('refused-single', fn () => DB::table('audit_logs')->insert(['store_id' => $this->storeB->id, 'action' => 'forged', 'created_at' => now()]));
        $this->bearerJson('POST', $single, $this->owner->createToken('t')->plainTextToken)->assertStatus(500);
        $this->assertSame(2, Cache::get(TenantRls::REFUSED)['count']);
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'forged')->count());
    }

    /**
     * TENANT_RLS on, but the role cannot be taken (not created, or the login role is not its member): the request must
     * not fail. Its transactions run as the login role, as with TENANT_RLS off, and the log says so once.
     */
    public function test_a_role_that_cannot_be_taken_never_fails_the_request_and_never_goes_unseen(): void
    {
        $this->assertNull($this->finding('tenant_rls_unavailable'), 'the roles of db-roles: nothing to report');

        config(['database.tenant_rls.role' => 'rls_test_no_such_role']);
        // Before any request met it: the monitoring check (every minute, in the scheduler) asks the database itself,
        // so the alert is open for as long as the state lasts, also in an hour without a single sale
        $standing = $this->finding('tenant_rls_unavailable');
        $this->assertSame('critical', $standing?->severity);
        $this->assertStringContainsString("Qator himoyasi (RLS) yoqilgan, lekin ishlamayapti: TENANT_RLS=1, lekin bazada rls_test_no_such_role roli yo'q", $standing->title);
        $this->assertSame("{$this->login}|", self::probe(), 'asking left no role behind');

        Log::spy();
        $slug = 'rls-' . Str::random(8);
        $uri = $this->tenantRoute('missing-role', function () use ($slug) {
            DB::transaction(function () use ($slug) {
                self::note('first');
                Category::create(['name' => 'Yozildi', 'slug' => $slug]);
            });
            DB::transaction(fn () => self::note('second'));
        });

        $this->asOwner($uri);

        $this->assertSame(["first={$this->login}|", "second={$this->login}|"], self::$seen);
        $this->assertTrue(Category::where('slug', $slug)->exists(), 'the transaction was started again and its work is kept');
        // The request that met it says so at once (laravel.log, Sentry), once per connection
        Log::shouldHaveReceived('critical')->once()->withArgs(fn (string $message, array $context) => str_contains($message, 'WITHOUT row-level security')
            && array_diff_key($context, ['error' => true]) === ['role' => 'rls_test_no_such_role', 'store_id' => $this->store->id, 'route' => 'POST api/test-rls/missing-role']);
        $this->assertNotNull($this->finding('tenant_rls_unavailable'), 'still open after the request');
        $this->assertNull($this->finding('tenant_rls_refused'), 'nothing was refused');

        // The same on a real transaction (ROLLBACK, BEGIN again)
        config(['database.connections.rls_level0' => config('database.connections.pgsql')]);
        $connection = DB::connection('rls_level0');
        $seen = app(TenantContext::class)->runAs($this->store->id, fn () => $connection->transaction(fn () => self::probe($connection) . '@' . $connection->transactionLevel()));
        $this->assertSame("{$this->login}|@1", $seen);
        $this->assertSame(0, $connection->transactionLevel());
    }
}

class RoleProbeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public string $name, public bool $throws = false) {}

    public function handle(): void
    {
        TenantRoleSwitchTest::note($this->name);
        DB::transaction(fn () => TenantRoleSwitchTest::note("{$this->name}-transaction"));
        TenantRoleSwitchTest::$seen[] = "{$this->name}-bound=" . var_export(app(TenantContext::class)->storeId(), true);
        if ($this->throws) {
            throw new RuntimeException('job failed');
        }
    }
}
