<?php

namespace Tests\Feature\Tenancy;

use App\Support\Database\OwnerConnection;
use App\Support\Tenancy\TenantRls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\AsSchemaOwner;
use Tests\TestCase;

/**
 * M4 stage 3: the migration 2026_11_28_100000_enable_tenant_row_level_security itself: down() and up() again, the
 * refusal when the application's role would be bound by the policies, and waiting for a table a reader holds.
 */
class TenantRlsMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_28_100000_enable_tenant_row_level_security.php';

    private function state(): array
    {
        return [
            (int) DB::scalar("SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = current_schema() AND c.relrowsecurity"),
            (int) DB::scalar("SELECT count(*) FROM pg_policy WHERE polname = 'tenant'"),
            (bool) DB::scalar("SELECT prosecdef FROM pg_proc WHERE oid = to_regprocedure('payments_parent_store(bigint, jsonb)')"),
            (string) DB::scalar("SELECT coalesce(array_to_string(proconfig, ','), '') FROM pg_proc WHERE oid = to_regprocedure('payments_parent_store(bigint, jsonb)')"),
        ];
    }

    #[AsSchemaOwner]
    public function test_down_takes_everything_back_and_up_puts_it_again(): void
    {
        $migration = require database_path(self::MIGRATION);
        $n = count(TenantRls::TABLES);
        $this->assertSame([$n, $n, true, 'search_path=pg_catalog, pg_temp'], $this->state());

        $migration->down();
        $this->assertSame([0, 0, false, ''], $this->state());

        $migration->up();
        $migration->up(); // and again: nothing to do, no error
        $this->assertSame([$n, $n, true, 'search_path=pg_catalog, pg_temp'], $this->state());
    }

    /**
     * Roles split but the application's role without BYPASSRLS (scripts/db-roles.sh of this stage not run): with the
     * policies on, every queue worker, webhook and report would see no row of these tables. Refused before anything
     * is changed. Shown with the tenant role named as the application's.
     */
    #[AsSchemaOwner]
    public function test_up_refuses_when_the_applications_role_would_be_bound_by_the_policies(): void
    {
        $role = TenantRls::role();
        if (!OwnerConnection::configured() || !DB::scalar('SELECT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = ? AND NOT rolbypassrls AND NOT rolsuper)', [$role])) {
            $this->markTestSkipped('Needs the tenant role (split roles, scripts/db-roles.sh): runs in the CI job tests-app-role.');
        }
        $migration = require database_path(self::MIGRATION);
        $migration->down();
        config(['database.connections.pgsql.username' => $role]);

        try {
            $migration->up();
            $this->fail('the policies were put on although the application role is bound by them');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("ilova roli {$role} da BYPASSRLS yo'q", $e->getMessage());
        }
        $this->assertSame([0, 0], array_slice($this->state(), 0, 2), 'nothing was changed');
    }

    /**
     * A reader holds a table (in production: the nightly pg_dump, ACCESS SHARE on every table for its whole run). The
     * migration's lock wait is short on purpose (a till must never queue behind it), so it gives up, pauses and tries
     * again until the reader is gone, instead of failing the deploy at once. Real sessions and committed DDL: the
     * holder is a second connection that keeps its lock for 3 seconds on its own (libpq, asynchronously).
     */
    public function test_up_waits_for_a_table_a_reader_holds_and_then_completes(): void
    {
        if (!OwnerConnection::prepare() || !function_exists('pg_send_query')) {
            $this->markTestSkipped('Needs the owner connection (DB_OWNER_USERNAME) and ext-pgsql: runs in the CI job tests-app-role.');
        }
        $config = config('database.connections.' . OwnerConnection::NAME);
        $owner = DB::connection(OwnerConnection::NAME);
        $holder = pg_connect(sprintf("host=%s port=%s dbname=%s user=%s password='%s'", $config['host'], $config['port'], $config['database'], $config['username'], addslashes((string) $config['password'])), PGSQL_CONNECT_FORCE_NEW);
        $this->assertNotFalse($holder);
        $held = fn (): bool => (bool) $owner->scalar("SELECT EXISTS (SELECT 1 FROM pg_locks WHERE relation = 'vouchers'::regclass AND mode = 'AccessShareLock' AND granted AND pid <> pg_backend_pid())");

        try {
            $owner->unprepared('DROP POLICY tenant ON vouchers; ALTER TABLE vouchers DISABLE ROW LEVEL SECURITY'); // not done yet
            pg_send_query($holder, 'BEGIN; LOCK TABLE vouchers IN ACCESS SHARE MODE; SELECT pg_sleep(3); COMMIT');
            for ($i = 0; $i < 100 && !$held(); $i++) {
                usleep(20_000);
            }
            $this->assertTrue($held(), 'the reader holds the table');

            $started = microtime(true);
            OwnerConnection::run(fn () => (require database_path(self::MIGRATION))->up());

            $this->assertFalse($held(), 'the migration got through only after the reader had gone');
            $this->assertGreaterThan(0.5, microtime(true) - $started);
            $this->assertTrue((bool) $owner->scalar("SELECT relrowsecurity AND EXISTS (SELECT 1 FROM pg_policy p WHERE p.polrelid = c.oid AND p.polname = 'tenant') FROM pg_class c WHERE c.oid = 'vouchers'::regclass"));
        } finally {
            pg_close($holder);
            OwnerConnection::run(fn () => (require database_path(self::MIGRATION))->up());
            DB::disconnect(OwnerConnection::NAME);
        }
    }
}
