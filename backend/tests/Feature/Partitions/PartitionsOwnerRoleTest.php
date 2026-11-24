<?php

namespace Tests\Feature\Partitions;

use App\Models\AlertRule;
use App\Models\AuditLog;
use App\Services\Admin\Monitoring\Checks\PartitionsCheck;
use App\Support\Database\OwnerConnection;
use App\Support\Partitions\PartitionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithProcessEnv;
use Tests\TestCase;

/**
 * M4 (docs/scale/M4_DESIGN.md section 5, test plan 7.2): with split roles (DB_USERNAME = the DML-only role,
 * DB_OWNER_USERNAME = the schema owner; CI job tests-app-role) the partition commands do their DDL on the owner
 * connection, and without the owner's credentials they refuse at once, loudly, instead of failing only on the day a
 * month is due. These tests need that setup and say so when it is not there (the normal job runs as one role, where
 * the commands are covered by the other Partitions tests). Runs without the wrapping transaction: the owner's session
 * is another connection; the months it attaches are removed.
 */
class PartitionsOwnerRoleTest extends TestCase
{
    use RefreshDatabase;
    use WithProcessEnv;

    protected $connectionsToTransact = [];

    private function requireSplitRoles(): void
    {
        if (!OwnerConnection::configured() || app(PartitionManager::class)->maintenanceRoleProblem() === null) {
            // In CI with DB_OWNER_USERNAME set (tests-app-role) the split is the point of the job: a default connection
            // that can do the DDL means the job runs as the owner and must not stay green
            if (OwnerConnection::configured() && getenv('CI') !== false && getenv('CI') !== '') {
                $this->fail('DB_OWNER_USERNAME is set but DB_USERNAME can do the DDL: tests-app-role does not run as the application role.');
            }
            $this->markTestSkipped('Needs DB_USERNAME = a DML-only role and DB_OWNER_USERNAME = the owner (CI job tests-app-role).');
        }
    }

    /** @return array<string, list<string>> */
    private function partitionNames(): array
    {
        $names = [];
        foreach (array_keys(PartitionManager::tables()) as $table) {
            $names[$table] = array_column(app(PartitionManager::class)->partitions($table), 'name');
        }

        return $names;
    }

    public function test_ensure_attaches_a_month_as_the_owner_while_the_application_role_cannot_do_ddl(): void
    {
        $this->requireSplitRoles();
        $before = $this->partitionNames();
        $this->travelTo(PartitionManager::monthStart(now())->addMonth()->addDays(2)->setTime(12, 0));
        $created = [];

        try {
            $this->artisan('db:partitions:ensure')->assertSuccessful();

            $after = $this->partitionNames();
            foreach ($after as $table => $names) {
                $new = array_values(array_diff($names, $before[$table]));
                $this->assertSame([PartitionManager::monthName($table, PartitionManager::monthStart(now())->addMonths(3))], $new, "{$table}: one month attached");
                $created[$table] = $new[0];
                $row = DB::selectOne(
                    "SELECT pg_get_userbyid(relowner) AS owner, has_table_privilege(oid, 'INSERT') AS app_inserts, current_user AS app FROM pg_class WHERE oid = ?::regclass",
                    [$new[0]],
                );
                $this->assertSame(OwnerConnection::username(), $row->owner, "{$new[0]} belongs to the owner");
                $this->assertNotSame($row->owner, $row->app);
                $this->assertTrue((bool) $row->app_inserts, "{$new[0]}: the owner's default privileges give the application DML");
            }
            // Back on the application role after the command, and a till's write lands in a month the owner made
            $this->assertSame('pgsql', DB::getDefaultConnection());
            $this->travelTo(PartitionManager::monthStart(now())->addMonths(3)->addDays(1));
            $entry = AuditLog::create(['action' => 'm4.owner_month']);
            $this->assertSame($created['audit_logs'], DB::selectOne('SELECT tableoid::regclass::text AS t FROM audit_logs WHERE id = ?', [$entry->id])->t);
        } finally {
            OwnerConnection::prepare();
            foreach ($created as $table => $name) {
                DB::connection(OwnerConnection::NAME)->statement("ALTER TABLE {$table} DETACH PARTITION {$name}");
                DB::connection(OwnerConnection::NAME)->statement("DROP TABLE {$name}");
            }
            DB::disconnect(OwnerConnection::NAME);
        }
    }

    public function test_without_the_owner_credentials_ensure_and_archive_refuse_at_once_and_the_alert_says_why(): void
    {
        $this->requireSplitRoles();
        $before = $this->partitionNames();
        // A month is due: the refusal must come before any step, not only on such a day
        $this->travelTo(PartitionManager::monthStart(now())->addMonth()->addDays(2));

        $this->withEnv(['DB_OWNER_USERNAME' => null, 'DB_OWNER_PASSWORD' => null], function () {
            $this->artisan('db:partitions:ensure')
                ->expectsOutputToContain("jadvallar egasi emas (analytics_events, audit_logs, fiscal_receipts, notification_logs). Bu ish jadvallar egasi roli bilan bajariladi: scheduler konteyneriga DB_OWNER_USERNAME va DB_OWNER_PASSWORD bering")
                ->assertFailed();
            $this->artisan('db:partitions:archive')->expectsOutputToContain("XATO: bo'limlarga tegilmadi")->assertFailed();

            $findings = collect(app(PartitionsCheck::class)->findings((new AlertRule)->forceFill(['kind' => 'partitions', 'threshold' => 2])))->keyBy('subjectKey');
            $this->assertTrue($findings->has('maintenance_role'), 'the monitoring check names the missing owner at once');
            $this->assertSame('critical', $findings['maintenance_role']->severity);
            $this->assertStringContainsString('DB_OWNER_USERNAME', $findings['maintenance_role']->title);
        });
        $this->assertSame($before, $this->partitionNames(), 'nothing was attached or detached');

        // With the right owner credentials (the scheduler's) and every table owned by the owner the check is quiet
        $findings = $this->partitionFindings();
        $this->assertFalse($findings->has('maintenance_role'));
        $this->assertFalse($findings->has('owner_mismatch'));
    }

    /** @return \Illuminate\Support\Collection<string, \App\Services\Admin\Monitoring\AlertFinding> */
    private function partitionFindings(): \Illuminate\Support\Collection
    {
        return collect(app(PartitionsCheck::class)->findings((new AlertRule)->forceFill(['kind' => 'partitions', 'threshold' => 2])))->keyBy('subjectKey');
    }

    public function test_the_check_logs_in_as_the_owner_and_a_wrong_password_is_the_finding(): void
    {
        $this->requireSplitRoles();

        $this->withEnv(['DB_OWNER_PASSWORD' => 'not-the-owner-password'], function () {
            $findings = $this->partitionFindings();
            $this->assertTrue($findings->has('maintenance_role'));
            $this->assertSame('critical', $findings['maintenance_role']->severity);
            $this->assertStringContainsString("egasi roli bilan bazaga ulanib bo'lmadi", $findings['maintenance_role']->title);
        });
        $this->assertSame('pgsql', DB::getDefaultConnection(), 'the check gives the default connection back');
    }

    /**
     * Runs with one role too: an owner name that owns nothing (the tables were never moved to it, or it is misspelt)
     * is reported twice, as the role that cannot do the DDL and as the tables it does not own.
     */
    public function test_tables_the_configured_owner_does_not_own_are_reported(): void
    {
        $this->withEnv(['DB_OWNER_USERNAME' => 'm4_nobody_owns_this', 'DB_OWNER_PASSWORD' => 'x'], function () {
            $findings = $this->partitionFindings();
            $this->assertTrue($findings->has('maintenance_role'));
            $this->assertTrue($findings->has('owner_mismatch'));
            $this->assertSame('warning', $findings['owner_mismatch']->severity);
            $this->assertStringContainsString('m4_nobody_owns_this niki emas', $findings['owner_mismatch']->title);
            $this->assertStringContainsString('db-roles', $findings['owner_mismatch']->title);
        });
    }

    public function test_a_wrong_owner_password_is_refused_with_the_reason(): void
    {
        $this->requireSplitRoles();

        $this->withEnv(['DB_OWNER_PASSWORD' => 'not-the-owner-password'], function () {
            $this->artisan('db:partitions:ensure')
                ->expectsOutputToContain("bazaga ulanib bo'lmadi")
                ->assertFailed();
        });
        $this->assertSame('pgsql', DB::getDefaultConnection());
    }

    /**
     * Objects of an extension are not moved by db-roles and so are no owner_mismatch either. Needs a superuser (the
     * normal CI job): CREATE EXTENSION, and handing a table to another role. All inside a transaction that is rolled back.
     */
    public function test_extension_objects_are_not_reported_as_unmoved_tables(): void
    {
        $row = DB::selectOne("SELECT current_user AS u, rolsuper AS s, (SELECT count(*) FROM pg_available_extensions WHERE name = 'pg_stat_statements') AS a FROM pg_roles WHERE rolname = current_user");
        if (!$row->s || (int) $row->a === 0) {
            $this->markTestSkipped('Needs a superuser and pg_stat_statements: CREATE EXTENSION (the normal CI job runs as one).');
        }

        DB::beginTransaction();
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_stat_statements');
            DB::statement('CREATE TABLE m4_unmoved_probe (id int)');
            // Both belong to a role that is not the configured owner: the extension's view and a plain table
            DB::statement('ALTER VIEW pg_stat_statements OWNER TO pg_monitor');
            DB::statement('ALTER TABLE m4_unmoved_probe OWNER TO pg_monitor');

            $this->withEnv(['DB_OWNER_USERNAME' => (string) $row->u, 'DB_OWNER_PASSWORD' => (string) config('database.connections.pgsql.password')], function () {
                $findings = $this->partitionFindings();
                $this->assertTrue($findings->has('owner_mismatch'));
                $tables = $findings['owner_mismatch']->details['tables'];
                $this->assertContains('m4_unmoved_probe', $tables);
                $this->assertNotContains('pg_stat_statements', $tables);
                $this->assertNotContains('pg_stat_statements_info', $tables);
            });
        } finally {
            DB::rollBack();
        }
    }
}
