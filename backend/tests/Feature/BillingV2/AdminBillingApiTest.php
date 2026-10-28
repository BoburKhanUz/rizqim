<?php

namespace Tests\Feature\BillingV2;

use App\Enums\PlatformRole;
use App\Models\AuditLog;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\TariffPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AdminFixtures;
use Tests\TestCase;

/**
 * P4-21: the admin API for the tariff catalog (read: subscriptions.manage or finance.view; change: system.manage,
 * step-up, reason) and a store's subscription and commission (subscriptions.manage, step-up, reason), every change
 * audited (docs/API.md, "Billing v2").
 */
class AdminBillingApiTest extends TestCase
{
    use AdminFixtures;
    use BillingV2Fixtures;
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->v2Store('Admin Cafe');
    }

    public function test_the_catalog_is_read_by_sales_finance_and_viewers_only(): void
    {
        foreach ([PlatformRole::SALES, PlatformRole::FINANCE, PlatformRole::VIEWER] as $role) {
            $response = $this->adminJson('GET', '/billing/plans', $this->signIn($this->makeMember($role)))->assertOk();
            $this->assertSame(['start', 'asosiy', 'pro'], array_column($response->json('data.plans'), 'code'));
        }

        $plans = collect($response->json('data.plans'))->keyBy('code');
        $this->assertSame(['149000.00', '1490000.00', 1, 0, '59000.00', '29000.00', 14], [
            $plans['start']['monthly_fee'], $plans['start']['yearly_fee'], $plans['start']['included_pos'], $plans['start']['included_kds'],
            $plans['start']['extra_pos_fee'], $plans['start']['extra_kds_fee'], $plans['start']['trial_days'],
        ]);
        $this->assertContains('bookings', $plans['asosiy']['features']);
        $this->assertNotContains('bookings', $plans['start']['features']);
        $this->assertContains('hall', $plans['start']['features']);
        $this->assertSame('bookings', collect($response->json('data.feature_catalog'))->firstWhere('key', 'bookings')['key']);

        $all = $this->adminJson('GET', '/billing/plans?all=1', $this->signIn($this->makeMember(PlatformRole::FINANCE)))->assertOk();
        $this->assertContains('pos', array_column($all->json('data.plans'), 'code'));

        foreach ([PlatformRole::SUPPORT, PlatformRole::OPERATOR] as $role) {
            $this->adminJson('GET', '/billing/plans', $this->signIn($this->makeMember($role)))->assertForbidden()->assertJsonPath('code', 'forbidden');
        }
    }

    public function test_changing_a_plan_needs_system_manage_a_step_up_and_a_reason_and_is_audited(): void
    {
        $finance = $this->makeMember(PlatformRole::FINANCE);
        $this->adminJson('PUT', '/billing/plans/start', $this->confirmedSession($finance), ['monthly_fee' => '159000', 'reason' => 'Yangi narx'])->assertForbidden();

        $owner = $this->makeMember(PlatformRole::SUPERADMIN);
        $token = $this->signIn($owner);
        $this->adminJson('PUT', '/billing/plans/start', $token, ['monthly_fee' => '159000', 'reason' => 'Yangi narx'])
            ->assertForbidden()->assertJsonPath('code', 'reauth_required');

        $this->stepUp($owner, $token);
        $this->adminJson('PUT', '/billing/plans/start', $token, ['monthly_fee' => '159000'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('PUT', '/billing/plans/start', $token, ['features' => ['hall'], 'reason' => 'Xato'])->assertStatus(422);
        $this->adminJson('PUT', '/billing/plans/pos', $token, ['monthly_fee' => '1', 'reason' => 'Eski tarif'])->assertNotFound();

        $this->adminJson('PUT', '/billing/plans/start', $token, [
            'monthly_fee' => '159000.50', 'yearly_fee' => 1590005, 'extra_pos_fee' => '60000', 'features' => ['bookings'], 'included_kds' => 1,
            'reason' => 'Egasi narxni oshirdi',
        ])->assertOk()->assertJsonPath('data.monthly_fee', '159000.50')->assertJsonPath('data.yearly_fee', '1590005.00')
            ->assertJsonPath('data.extra_pos_fee', '60000.00')->assertJsonPath('data.included_kds', 1);

        $plan = TariffPlan::where('code', 'start')->sole();
        $this->assertSame(['159000.50', '1590005.00', ['bookings'], 2], [$plan->monthly_base_fee, $plan->yearly_fee, $plan->features, $plan->included_terminals]);

        $entry = AuditLog::where('action', 'admin.billing.plan_updated')->sole();
        $this->assertSame('149000.00', $entry->before['monthly_fee']);
        $this->assertSame('159000.50', $entry->after['monthly_fee']);
        $this->assertSame('Egasi narxni oshirdi', $entry->payload_json['reason']);
        $this->assertSame($owner->id, (int) $entry->user_id);
    }

    public function test_sales_assigns_a_plan_changes_its_period_extends_the_trial_and_cancels_it(): void
    {
        $sales = $this->makeMember(PlatformRole::SALES);
        $token = $this->confirmedSession($sales);
        $id = $this->store->id;

        $this->adminJson('POST', "/billing/stores/{$id}/subscription", $token, ['plan' => 'asosiy'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('POST', "/billing/stores/{$id}/subscription", $token, ['plan' => 'nope', 'reason' => 'Ariza'])->assertStatus(422)->assertJsonPath('code', 'plan_not_found');
        $this->adminJson('POST', '/billing/stores/999999/subscription', $token, ['plan' => 'start', 'reason' => 'Ariza'])->assertNotFound();

        $this->adminJson('POST', "/billing/stores/{$id}/subscription", $token, ['plan' => 'asosiy', 'reason' => 'Ulash: sinov'])
            ->assertOk()->assertJsonPath('data.subscription.plan.code', 'asosiy')->assertJsonPath('data.subscription.status', 'trialing')
            ->assertJsonPath('data.entitlements.features.bookings', true)->assertJsonPath('data.debt.subscription_debt', '0.00')
            ->assertJsonPath('data.commission.rates.pickup.rate', '5.00');
        $entry = AuditLog::where('action', 'admin.billing.subscription_assigned')->sole();
        $this->assertNull($entry->before['plan'] ?? null);
        $this->assertSame('asosiy', $entry->after['plan']);
        $this->assertSame('Ulash: sinov', $entry->payload_json['reason']);
        $this->assertSame($id, (int) $entry->store_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.started', 'store_id' => $id, 'user_id' => $sales->id]);

        // During the trial the period switch applies at once; the trial gets a week more
        $this->adminJson('POST', "/billing/stores/{$id}/subscription/period", $token, ['period' => 'yearly', 'reason' => 'Yillik to\'laydi'])
            ->assertOk()->assertJsonPath('data.subscription.billing_period', 'yearly');
        $trialEnds = StoreSubscription::query()->current()->sole()->trial_ends_at;
        $this->adminJson('POST', "/billing/stores/{$id}/subscription/trial", $token, ['days' => 7, 'reason' => 'Menyu kech kiritildi'])
            ->assertOk()->assertJsonPath('data.subscription.trial_ends_at', $trialEnds->copy()->addDays(7)->toIso8601String());
        $this->adminJson('POST', "/billing/stores/{$id}/subscription/trial", $token, ['days' => 90, 'reason' => 'Juda uzoq'])
            ->assertStatus(422)->assertJsonPath('code', 'billing_rule');

        $this->adminJson('POST', "/billing/stores/{$id}/subscription/cancel", $token, ['immediately' => false, 'reason' => 'Ega yopmoqchi'])
            ->assertOk()->assertJsonPath('data.subscription.cancel_at_period_end', true);
        $this->assertSame(4, AuditLog::where('action', 'like', 'admin.billing.%')->count());

        // A viewer reads the card but changes nothing
        $viewer = $this->makeMember(PlatformRole::VIEWER);
        $viewerToken = $this->confirmedSession($viewer);
        $this->adminJson('GET', "/billing/stores/{$id}", $viewerToken)->assertOk()->assertJsonCount(1, 'data.history');
        $this->adminJson('POST', "/billing/stores/{$id}/subscription/cancel", $viewerToken, ['immediately' => true, 'reason' => 'Sinov'])->assertForbidden();

        $list = $this->adminJson('GET', '/billing/subscriptions?plan=asosiy', $viewerToken)->assertOk();
        $this->assertSame([$id], array_column(array_column($list->json('data.data'), 'store'), 'id'));
        $this->assertSame(1, $list->json('data.counts.trialing'));
    }

    public function test_the_commission_override_is_per_channel_audited_and_reaches_only_new_orders(): void
    {
        $finance = $this->makeMember(PlatformRole::FINANCE);
        $token = $this->confirmedSession($finance);
        $id = $this->store->id;

        $this->adminJson('PUT', "/billing/stores/{$id}/commission", $token, ['overrides' => ['bogus' => '1'], 'reason' => 'Shartnoma'])->assertStatus(422);
        $this->adminJson('PUT', "/billing/stores/{$id}/commission", $token, ['overrides' => ['pickup' => '101'], 'reason' => 'Shartnoma'])->assertStatus(422);

        $this->adminJson('PUT', "/billing/stores/{$id}/commission", $token, [
            'overrides' => ['pickup' => '3', 'own_delivery' => '8.5'], 'delivery_courier' => 'fleet', 'reason' => 'Shartnoma bo\'yicha',
        ])->assertOk()
            ->assertJsonPath('data.rates.pickup.rate', '3.00')->assertJsonPath('data.rates.pickup.source', 'override')
            ->assertJsonPath('data.rates.own_delivery.rate', '8.50')->assertJsonPath('data.rates.rizqim_courier.rate', '15.00')
            ->assertJsonPath('data.delivery_courier', 'fleet')->assertJsonPath('data.flat_rate', null);

        $this->adminJson('PUT', "/billing/stores/{$id}/commission", $token, ['overrides' => ['own_delivery' => null], 'flat_rate' => '7', 'reason' => 'Tuzatish'])
            ->assertOk()->assertJsonPath('data.overrides', ['pickup' => '3.00'])->assertJsonPath('data.rates.own_delivery.rate', '7.00')
            ->assertJsonPath('data.rates.own_delivery.source', 'flat');

        $entries = AuditLog::where('action', 'admin.billing.commission_updated')->orderBy('id')->get();
        $this->assertCount(2, $entries);
        $this->assertSame('5.00', $entries[0]->before['rates']['pickup']['rate']);
        $this->assertSame('3.00', $entries[0]->after['rates']['pickup']['rate']);
        $this->assertSame('Tuzatish', $entries[1]->payload_json['reason']);

        // Support cannot
        $support = $this->makeMember(PlatformRole::SUPPORT);
        $this->adminJson('PUT', "/billing/stores/{$id}/commission", $this->confirmedSession($support), ['flat_rate' => '1', 'reason' => 'Sinov'])->assertForbidden();
    }
}
