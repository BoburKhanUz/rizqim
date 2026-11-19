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
