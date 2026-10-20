<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\SubscriptionInvoice;
use App\Models\TariffPlan;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Finance\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1-23: GET /owner/subscription and GET /owner/invoices — finance.view, the caller's own store only.
 */
class BillingOwnerApiTest extends TestCase
{
    use RefreshDatabase;
    use AccessFixtures;
    use BillingFixtures;

    private SubscriptionService $billing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->billing = app(SubscriptionService::class);
    }

    public function test_a_store_on_no_plan_sees_its_own_commission_and_no_subscription(): void
    {
        $this->terminal($this->store, '2026-09-01 10:00');

        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/subscription')
            ->assertOk()
            ->assertJsonPath('data.subscription', null)
            ->assertJsonPath('data.commission_rate', 10)
            ->assertJsonPath('data.terminals.active_now', 1)
            ->assertJsonPath('data.next_invoice', null);
    }

    public function test_the_current_plan_period_terminals_and_next_invoice_estimate(): void
    {
        $this->travelTo($this->tashkent('2026-10-20 12:00'));
        $this->terminal($this->store, '2026-09-01 10:00');
        $this->terminal($this->store, '2026-10-02 10:00', '2026-10-05 10:00');
        $this->terminal($this->store, '2026-10-03 10:00');
        $this->terminal($this->store, '2026-10-04 10:00');
        $this->billing->assignPlan($this->store, TariffPlan::where('code', 'mixed')->sole(), false, null, $this->tashkent('2026-10-01 00:00'));
        $this->billing->assignPlan($this->store, TariffPlan::where('code', 'pos')->sole(), false, null, $this->tashkent('2026-10-10 00:00'));

        $data = $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/subscription')->assertOk()->json('data');

        $this->assertSame('mixed', $data['subscription']['plan']['code']);
        $this->assertSame('trialing', $data['subscription']['status']);
        $this->assertSame('2026-10', $data['subscription']['current_period']['period']);
        $this->assertSame('pos', $data['subscription']['next_plan']['code']);
        $this->assertEquals(5, $data['commission_rate']);
        $this->assertSame(['active_now' => 3, 'counted' => 4], $data['terminals']);
        // Still in the 14-day trial: the month is free
        $this->assertTrue($data['next_invoice']['trial']);
        $this->assertEquals(0, $data['next_invoice']['amount']);
        $this->assertSame('2026-11-01', $data['next_invoice']['issued_on']);
    }

    public function test_the_estimate_outside_a_trial_follows_the_invoice_formula(): void
    {
        $this->travelTo($this->tashkent('2026-10-20 12:00'));
        $this->terminal($this->store, '2026-09-01 10:00');
        $this->terminal($this->store, '2026-09-02 10:00');
        $this->terminal($this->store, '2026-09-03 10:00');
        $plan = $this->plan(['monthly_base_fee' => 100000, 'monthly_fee_per_terminal' => '45000.50', 'included_terminals' => 1]);
        $this->billing->assignPlan($this->store, $plan, false, null, $this->tashkent('2026-10-01 00:00'));

        $next = $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/subscription')->assertOk()->json('data.next_invoice');

        $this->assertFalse($next['trial']);
        $this->assertEquals(190001, $next['amount']);   // 100000 + 2 × 45000.50
        $this->assertSame(['base', 'terminals'], array_column($next['lines'], 'type'));
        $this->assertSame(2, $next['lines'][1]['quantity']);
    }

    public function test_invoices_list_only_the_callers_store_newest_first(): void
    {
        $other = $this->makeStore('Other Store');
        $plan = $this->plan(['monthly_base_fee' => 70000]);
        $this->billing->assignPlan($this->store, $plan, false, null, $this->tashkent('2026-08-01 00:00'));
        $this->billing->assignPlan($other, $plan, false, null, $this->tashkent('2026-08-01 00:00'));
        $this->billing->run($this->tashkent('2026-10-01 00:15'));
        $this->billing->voidInvoice(SubscriptionInvoice::where('store_id', $this->store->id)->where('period', '2026-08')->sole());

        $response = $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/invoices')->assertOk();
        $this->assertSame(['2026-09', '2026-08'], array_column($response->json('data.data'), 'period'));
        $this->assertSame(2, $response->json('data.total'));
        $this->assertSame(sprintf('OB-202609-%06d', $this->store->id), $response->json('data.data.0.number'));
        $this->assertEquals(70000, $response->json('data.data.0.amount'));
        $this->assertSame('issued', $response->json('data.data.0.status'));
        $this->assertSame(['type' => 'base', 'label' => "Oylik asosiy to'lov", 'quantity' => 1, 'unit_price' => 70000, 'amount' => 70000], $response->json('data.data.0.lines.0'));

        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/invoices?status=void')
            ->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.period', '2026-08');
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/invoices?period=2026-13x')->assertUnprocessable();
    }

    public function test_finance_view_is_required(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/subscription')->assertForbidden();
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/invoices')->assertForbidden();
        $this->actingAs($this->courier, 'sanctum')->getJson('/api/v1/owner/invoices')->assertForbidden();
        $this->actingAs($customer, 'sanctum')->getJson('/api/v1/owner/subscription')->assertForbidden();
        $this->bearerJson('GET', '/api/v1/owner/subscription', null)->assertUnauthorized();

        // An owner may let managers see the store's finances
        app(PermissionService::class)->setOverride($this->store, UserRole::MANAGER, Permission::FINANCE_VIEW, true, $this->owner);
        $this->actingAs($this->manager->fresh(), 'sanctum')->getJson('/api/v1/owner/invoices')->assertOk();
    }

    public function test_a_staff_pin_session_on_a_terminal_cannot_read_billing(): void
    {
        $terminal = $this->pairTerminal($this->owner, $this->branch);
        $this->givePin($this->owner);
        $session = $this->bearerJson('POST', "/api/v1/devices/{$terminal['device']->uuid}/staff-session", $terminal['token'], ['user_id' => $this->owner->id, 'pin' => '4831'])
            ->assertOk()->json('data.token');

        $this->bearerJson('GET', '/api/v1/owner/subscription', $session)->assertForbidden();
        $this->bearerJson('GET', '/api/v1/owner/invoices', $session)->assertForbidden();
    }

    public function test_a_super_admin_without_a_store_has_no_store_here(): void
    {
        $admin = User::create(['name' => 'Admin', 'phone' => '+998901110009', 'password' => bcrypt('x'), 'role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/owner/subscription')->assertNotFound();
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/owner/invoices')->assertNotFound();
    }
}
