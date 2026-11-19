<?php

namespace Tests\Feature;

use App\Models\AlertRule;
use App\Services\Admin\Monitoring\Checks\PartitionsCheck;
use App\Support\Database\OwnerConnection;
use App\Support\Tenancy\TenantRls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Tenancy\TenantRlsPolicyTest;
use Tests\TestCase;

/**
 * M4 stage 3 (docs/scale/M4_DESIGN.md 4.3): app:check-production and row-level security. It refuses a login role that
 * the policies bind (whatever TENANT_RLS says), and with TENANT_RLS on a tenant role that is missing, cannot be taken
 * or is not bound by the policies. With TENANT_RLS off nothing about the tenant role is asked.
 */
class CheckProductionTenantRlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.env' => 'staging', 'app.debug' => false, 'telegram.allow_mock_auth' => false, 'services.telegram.webhook_secret' => 'valid_secret-1',
            'services.telegram.webapp_url' => 'https://staging.rizqim.example/', 'app.key' => 'base64:' . base64_encode(str_repeat('k', 32)),
            'broadcasting.default' => 'null']);
    }

    private function tenantRole(): string
    {
        $role = TenantRlsPolicyTest::tenantRole();
        if ($role === null) {
            $this->markTestSkipped('Needs the tenant role (split roles, scripts/db-roles.sh): runs in the CI job tests-app-role.');
        }

        return $role;
    }

    public function test_with_tenant_rls_off_nothing_is_asked_about_the_tenant_role(): void
    {
        config(['database.tenant_rls' => ['enabled' => false, 'role' => 'rls_test_no_such_role']]);

        $this->artisan('app:check-production')
            ->doesntExpectOutputToContain('TENANT_RLS')
            ->doesntExpectOutputToContain('Qator himoyasi (RLS)')
            ->doesntExpectOutputToContain('RLS) ta\'sir qiladi');
    }

    public function test_tenant_rls_on_without_the_role_is_refused(): void
    {
        config(['database.tenant_rls' => ['enabled' => true, 'role' => 'rls_test_no_such_role']]);

        $this->artisan('app:check-production')
            ->expectsOutputToContain("TENANT_RLS=1, lekin bazada rls_test_no_such_role roli yo'q")
            ->assertFailed();

        config(['database.tenant_rls.role' => 'Robert"; DROP']);
        $this->artisan('app:check-production')->expectsOutputToContain("TENANT_RLS=1, lekin DB_TENANT_ROLE noto'g'ri")->assertFailed();
    }

    /**
     * A tenant role the policies do not bind protects nothing: here the role that owns the tables (the owner itself on
     * an install with one role; DB_OWNER_USERNAME when the roles are split).
     */
    public function test_tenant_rls_on_with_a_role_the_policies_do_not_bind_is_refused(): void
    {
        $owner = (string) DB::scalar("SELECT pg_get_userbyid(relowner) FROM pg_class WHERE oid = 'orders'::regclass");
        config(['database.tenant_rls' => ['enabled' => true, 'role' => $owner]]);

        $this->artisan('app:check-production')
            ->expectsOutputToContain("TENANT_RLS=1, lekin {$owner} roli himoyani chetlab o'tadi")
            ->assertFailed();
    }

    public function test_tenant_rls_on_with_a_role_the_application_cannot_take_is_refused(): void
    {
        $this->tenantRole();
        // pg_read_all_data: a role every cluster has, bound by the policies, and the application is not its member
        config(['database.tenant_rls' => ['enabled' => true, 'role' => 'pg_read_all_data']]);

        $this->artisan('app:check-production')
            ->expectsOutputToContain("TENANT_RLS=1, lekin ilova pg_read_all_data roliga o'ta olmaydi (SET ROLE)")
            ->assertFailed();
    }

    public function test_tenant_rls_on_with_the_roles_of_db_roles_passes_and_says_so(): void
    {
        $role = $this->tenantRole();
        config(['database.tenant_rls.enabled' => true]);

        $this->artisan('app:check-production')
            ->expectsOutputToContain("Qator himoyasi (RLS): yoqilgan, do'kon so'rovlarining tranzaksiyalari {$role} roli bilan ishlaydi.")
            ->doesntExpectOutputToContain('TENANT_RLS=1, lekin');
        $this->assertSame(DB::scalar('SELECT session_user'), DB::scalar('SELECT current_user'), 'the probe transaction left no role behind');
    }

    /**
     * A privilege the application has and the tenant role lacks (scripts/db-roles.sh not run after a table was made by
     * hand, a REVOKE): every tenant transaction that uses it would fail with "permission denied" at the first sale.
     * Met at start-up instead, and by the monitoring check while it lasts. The REVOKE is the owner's and committed
     * (this session must see it); put back whatever happens.
     */
    public function test_tenant_rls_on_with_a_grant_missing_on_the_tenant_role_is_refused(): void
    {
        $role = $this->tenantRole();
        if (!OwnerConnection::prepare()) {
            $this->markTestSkipped('Needs the owner connection (DB_OWNER_USERNAME): runs in the CI job tests-app-role.');
        }
        config(['database.tenant_rls.enabled' => true]);
        $owner = DB::connection(OwnerConnection::NAME);

        try {
            $owner->statement("REVOKE UPDATE ON vouchers FROM \"{$role}\"");
            $this->artisan('app:check-production')
                ->expectsOutputToContain("TENANT_RLS=1, lekin {$role} rolida ilova rolidagi huquqlar yetishmaydi (1 ta obyekt: vouchers)")
                ->assertFailed();
            $finding = collect(app(PartitionsCheck::class)->findings((new AlertRule)->forceFill(['kind' => 'partitions', 'threshold' => 2])))
                ->keyBy('subjectKey')->get('tenant_rls_unavailable');
            $this->assertSame('critical', $finding?->severity);
            $this->assertStringContainsString('huquqlar yetishmaydi (1 ta obyekt: vouchers)', $finding->title);
        } finally {
            $owner->statement("GRANT UPDATE ON vouchers TO \"{$role}\"");
            DB::disconnect(OwnerConnection::NAME);
        }

        $this->artisan('app:check-production')->doesntExpectOutputToContain('TENANT_RLS=1, lekin');
    }

    /**
     * Security review M1: the roles can be right and the protection off all the same. Row-level security disabled on a
     * table, a second policy that lets everything through, or the policy itself rewritten: the tenant role then reads
     * and writes every store's rows of that table. Met at start-up and by the monitoring check, by name. The break is
     * the owner's and committed (this session must see it); repaired whatever happens.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function breaks(): array
    {
        $policy = "store_id = nullif(current_setting('app.store_id', true), '')::bigint";

        return [
            'row-level security disabled on the table' => ['ALTER TABLE vouchers DISABLE ROW LEVEL SECURITY', 'ALTER TABLE vouchers ENABLE ROW LEVEL SECURITY'],
            'a second policy that lets every row through' => ['CREATE POLICY rls_test_open ON vouchers USING (true)', 'DROP POLICY IF EXISTS rls_test_open ON vouchers'],
            'the policy itself rewritten' => ['ALTER POLICY tenant ON vouchers USING (true)', "ALTER POLICY tenant ON vouchers USING ({$policy})"],
            'a check weaker than the policy' => ['ALTER POLICY tenant ON vouchers WITH CHECK (true)', "DROP POLICY tenant ON vouchers; CREATE POLICY tenant ON vouchers USING ({$policy})"],
            'the policy on a partitioned journal dropped' => ['DROP POLICY tenant ON audit_logs', "CREATE POLICY tenant ON audit_logs USING ({$policy})"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('breaks')]
    public function test_tenant_rls_on_with_a_table_that_is_not_protected_is_refused(string $break, string $repair): void
    {
        $this->tenantRole();
        if (!OwnerConnection::prepare()) {
            $this->markTestSkipped('Needs the owner connection (DB_OWNER_USERNAME): runs in the CI job tests-app-role.');
        }
        config(['database.tenant_rls.enabled' => true]);
        $this->assertSame([], TenantRls::setupProblems());
        $table = str_contains($break, 'audit_logs') ? 'audit_logs' : 'vouchers';
        $owner = DB::connection(OwnerConnection::NAME);
        $owner->statement("SET lock_timeout = '5s'");

        try {
            $owner->unprepared($break);

            $problems = TenantRls::setupProblems();
            $this->assertSame(['unprotected'], array_keys($problems));
            $this->assertStringContainsString("TENANT_RLS=1, lekin 1 ta jadval himoyasiz ({$table})", $problems['unprotected']);
            $this->artisan('app:check-production')->expectsOutputToContain("1 ta jadval himoyasiz ({$table})")->assertFailed();
            $finding = collect(app(PartitionsCheck::class)->findings((new AlertRule)->forceFill(['kind' => 'partitions', 'threshold' => 2])))
                ->keyBy('subjectKey')->get('tenant_rls_unprotected');
            $this->assertSame('critical', $finding?->severity);
            $this->assertStringContainsString($table, $finding->title);

            // With TENANT_RLS off the tenant role is not used: nothing to refuse
            config(['database.tenant_rls.enabled' => false]);
            $this->assertSame([], TenantRls::setupProblems());
        } finally {
            $owner->unprepared($repair);
            DB::disconnect(OwnerConnection::NAME);
        }

        config(['database.tenant_rls.enabled' => true]);
        $this->assertSame([], TenantRls::setupProblems(), 'repaired');
    }

    /**
     * Security review L6: a check that could not run is not a check that passed. With TENANT_RLS on, start-up is
     * refused when the state cannot be read, and the monitoring check says so instead of saying nothing. With
     * TENANT_RLS off (and on an install with one role) a failed read stays a warning, as before.
     */
    public function test_a_check_that_cannot_run_does_not_read_as_all_fine(): void
    {
        // A role name that makes the catalog question itself fail (pg_has_role on a name that is not a role is an
        // error inside has_table_privilege): here simply a broken connection for the question
        config(['database.tenant_rls.enabled' => true]);
        DB::beginTransaction();
        try {
            DB::select('SELECT 1 / 0');
        } catch (\Throwable) {
            // the transaction is aborted: every statement of setupProblems() now fails
        }
        try {
            $this->artisan('app:check-production')
                ->expectsOutputToContain("TENANT_RLS=1, lekin qator darajasidagi himoyani (RLS) tekshirib bo'lmadi")
                ->assertFailed();

            config(['database.tenant_rls.enabled' => false]);
            $this->artisan('app:check-production')
                ->expectsOutputToContain("OGOHLANTIRISH: qator darajasidagi himoyani (RLS) tekshirib bo'lmadi")
                ->doesntExpectOutputToContain('TENANT_RLS=1, lekin');
        } finally {
            DB::rollBack();
        }

        // Monitoring: a state that could not be read is a warning while the protection is on, and nothing otherwise
        $this->app->bind(PartitionsCheck::class, fn () => new class(app(\App\Support\Partitions\PartitionManager::class)) extends PartitionsCheck
        {
            protected function tenantRlsSetup(): ?array
            {
                return null;
            }
        });
        $finding = fn () => collect(app(PartitionsCheck::class)->findings((new AlertRule)->forceFill(['kind' => 'partitions', 'threshold' => 2])))
            ->keyBy('subjectKey')->get('tenant_rls_unverified');
        $this->assertNull($finding(), 'TENANT_RLS off');
        config(['database.tenant_rls.enabled' => true]);
        $this->assertSame('warning', $finding()?->severity);
        $this->assertStringContainsString("tekshirib bo'lmadi", $finding()->title);
    }

    /**
     * A login role that is itself bound by the policies (no BYPASSRLS, not the owner): outside tenant transactions it
     * would see no row of the money tables. Shown by running the check as the tenant role.
     */
    public function test_a_login_role_bound_by_the_policies_is_refused_whatever_tenant_rls_says(): void
    {
        $role = $this->tenantRole();
        config(['database.tenant_rls.enabled' => false]);
        DB::unprepared("SET LOCAL ROLE \"{$role}\"");

        try {
            $this->artisan('app:check-production')
                ->expectsOutputToContain("Ilova roli {$role} ga qator darajasidagi himoya (RLS) ta'sir qiladi (" . count(TenantRls::TABLES) . ' ta jadval')
                ->assertFailed();
        } finally {
            DB::unprepared('SET LOCAL ROLE NONE');
        }
    }
}
