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
                ->expectsOutputToContain("jadvallar egasi emas (audit_logs, fiscal_receipts, notification_logs). Bu ish jadvallar egasi roli bilan bajariladi: scheduler konteyneriga DB_OWNER_USERNAME va DB_OWNER_PASSWORD bering")
                ->assertFailed();
            $this->artisan('db:partitions:archive')->expectsOutputToContain("XATO: bo'limlarga tegilmadi")->assertFailed();

            $findings = collect(app(PartitionsCheck::class)->findings((new AlertRule)->forceFill(['kind' => 'partitions', 'threshold' => 2])))->keyBy('subjectKey');
            $this->assertTrue($findings->has('maintenance_role'), 'the monitoring check names the missing owner at once');
            $this->assertSame('critical', $findings['maintenance_role']->severity);
            $this->assertStringContainsString('DB_OWNER_USERNAME', $findings['maintenance_role']->title);
        });
        $this->assertSame($before, $this->partitionNames(), 'nothing was attached or detached');

        // With the owner configured the check stays quiet (the scheduler has the credentials)
        $findings = collect(app(PartitionsCheck::class)->findings((new AlertRule)->forceFill(['kind' => 'partitions', 'threshold' => 2])))->keyBy('subjectKey');
        $this->assertFalse($findings->has('maintenance_role'));
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
}
