<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Realtime\RevisionPostgresConnection;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Store;
use App\Models\User;
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

    public function test_a_single_write_outside_a_transaction_runs_under_the_role_and_a_read_does_not(): void
    {
        $who = DB::raw("current_user || '|' || current_setting('app.store_id', true)");
        $slug = 'rls-' . Str::random(8);
        $uri = $this->tenantRoute('single', function () use ($who, $slug) {
            User::whereKey($this->manager->id)->update(['name' => $who]); // Eloquent mass update
            DB::table('users')->where('id', $this->courier->id)->update(['name' => $who]); // query builder
            Category::create(['name' => $who, 'slug' => $slug]); // insert ... returning id
            DB::update('UPDATE stores SET name = ' . $who->getValue(DB::getQueryGrammar()) . ' WHERE id = ?', [$this->store->id]); // raw
            self::note('read');
        });

        $this->asOwner($uri);

        $a = "{$this->tenant}|{$this->store->id}";
        $this->assertSame($a, $this->manager->fresh()->name);
        $this->assertSame($a, $this->courier->fresh()->name);
        $this->assertSame($a, Category::where('slug', $slug)->value('name'));
        $this->assertSame($a, $this->store->fresh()->name);
        // Writes mode: a read outside a transaction stays with the application guard
        $this->assertSame(["read={$this->login}|"], self::$seen);
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
            $this->assertSame(0, $connection->update('UPDATE stores SET name = name WHERE id = -1'));
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
     * TENANT_RLS on, but the role cannot be taken (not created, or the login role is not its member): the request must
     * not fail. Its transactions run as the login role, as with TENANT_RLS off, and the log says so once.
     */
    public function test_a_role_that_cannot_be_taken_never_fails_the_request(): void
    {
        config(['database.tenant_rls.role' => 'rls_test_no_such_role']);
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
        Log::shouldHaveReceived('critical')->once()->withArgs(fn (string $message) => str_contains($message, 'tenant_rls'));

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
