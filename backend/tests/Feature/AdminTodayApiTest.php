<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Services\Admin\Today\PlatformStatsRefresher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-13: GET /admin/today and GET /admin/today/attention: who may read them (admin.today.view: superadmin, operator,
 * viewer), the answer's shape, and that other tokens never get in.
 */
class AdminTodayApiTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;
    use OrderLifecycleFixtures;
    use AdminTodayFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    public function test_roles_with_today_view_read_and_the_others_are_refused(): void
    {
        foreach ([PlatformRole::SUPERADMIN, PlatformRole::OPERATOR, PlatformRole::VIEWER] as $role) {
            $token = $this->signIn($this->makeMember($role));
            $this->adminJson('GET', '/today', $token)->assertOk();
            $this->adminJson('GET', '/today/attention', $token)->assertOk()->assertJsonStructure(['data' => ['groups', 'generated_at']]);
        }

        foreach ([PlatformRole::SUPPORT, PlatformRole::FINANCE, PlatformRole::SALES] as $role) {
            $token = $this->signIn($this->makeMember($role));
            $this->adminJson('GET', '/today', $token)->assertForbidden();
            $this->adminJson('GET', '/today/attention', $token)->assertForbidden();
        }
    }

    public function test_without_an_admin_session_nothing_is_shown(): void
    {
        $this->adminJson('GET', '/today')->assertUnauthorized();
        $this->adminJson('GET', '/today/attention')->assertUnauthorized();

        // A store owner's token is not an admin session
        $ownerToken = $this->owner->createToken('test')->plainTextToken;
        $this->adminJson('GET', '/today', $ownerToken)->assertUnauthorized();
    }

    public function test_a_permission_taken_away_from_a_role_closes_the_page(): void
    {
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $token = $this->signIn($operator);
        app(\App\Services\Admin\AdminPermissionService::class)->setOverride(PlatformRole::OPERATOR, \App\Enums\AdminPermission::TODAY_VIEW, false);

        $this->adminJson('GET', '/today', $token)->assertForbidden();
    }

    public function test_the_summary_answer(): void
    {
        $this->at('2026-11-10 14:30');
        $this->orderAt('2026-11-10 10:00', attributes: ['total_price' => '120000.00', 'commission_amount' => '12000.00']);
        $this->orderAt('2026-11-09 10:00', attributes: ['total_price' => '100000.00', 'commission_amount' => '10000.00']);
        $this->invoicePaidAt('2026-11-10 09:00', '249000.00');
        app(PlatformStatsRefresher::class)->refreshRecent();

        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));
        $response = $this->adminJson('GET', '/today', $token)->assertOk();

        $response->assertJsonPath('data.date', '2026-11-10')
            ->assertJsonPath('data.timezone', 'Asia/Tashkent')
            ->assertJsonPath('data.kpis.gmv.value', '120000.00')
            ->assertJsonPath('data.kpis.gmv.yesterday_same_time', '100000.00')
            ->assertJsonPath('data.kpis.gmv.vs_yesterday', ['direction' => 'up', 'percent' => '20.0'])
            ->assertJsonPath('data.kpis.orders.value', 1)
            ->assertJsonPath('data.kpis.commission.value', '12000.00')
            ->assertJsonPath('data.kpis.subscription_revenue.value', '249000.00')
            ->assertJsonPath('data.kpis.subscription_revenue.vs_yesterday', ['direction' => 'up', 'percent' => null])
            ->assertJsonPath('data.kpis.canceled_orders.value', 0)
            ->assertJsonStructure(['data' => [
                'generated_at', 'stats_computed_at', 'live_from',
                'channels' => [['channel', 'label', 'marketplace', 'orders', 'gmv']],
                'regions' => [['region_id', 'name', 'orders', 'gmv', 'active_stores']],
            ]]);

        $this->assertStringEndsWith('+05:00', $response->json('data.generated_at'));
    }

    public function test_attention_limit_is_validated(): void
    {
        $token = $this->signIn($this->makeMember());

        $this->adminJson('GET', '/today/attention?limit=0', $token)->assertStatus(422);
        $this->adminJson('GET', '/today/attention?limit=21', $token)->assertStatus(422);
        $this->adminJson('GET', '/today/attention?limit=20', $token)->assertOk();
    }
}
