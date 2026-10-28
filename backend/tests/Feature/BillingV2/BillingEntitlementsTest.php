<?php

namespace Tests\Feature\BillingV2;

use App\Enums\DeviceType;
use App\Models\Payment;
use App\Models\TariffPlan;
use App\Services\Finance\Billing\Entitlements;
use App\Services\Finance\LedgerService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\Feature\PosBilling\PosBillingFixtures;
use Tests\TestCase;

/**
 * P4-21: Entitlements — the plan's features and devices, enforced on the backend for gated writes (403
 * plan_feature_missing with the cheapest plan that has it; subscription_locked and device_limit while locked) and
 * NEVER on taking payments, closing bills, receipts, shifts or refunds (docs/BILLING.md, "Funksiyalar va cheklovlar").
 */
class BillingEntitlementsTest extends TestCase
{
    use BillingV2Fixtures;
    use PosBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo($this->at('2026-10-20 12:00'));
        $this->setUpPosBilling();
    }

    public function test_the_payload_for_a_store_on_no_plan_and_on_each_v2_plan(): void
    {
        // Never on a v2 plan: every feature, as before Billing v2
        $legacy = $this->ownerGet()->assertOk();
        $legacy->assertJsonPath('data.plan', null)->assertJsonPath('data.legacy', true)->assertJsonPath('data.status', 'none')
            ->assertJsonPath('data.features.bookings', true)->assertJsonPath('data.features.kds', true)
            ->assertJsonPath('data.devices.pos.included', null)->assertJsonPath('data.devices.pos.active', 1);

        $this->billing()->assignPlan($this->store, $this->v2('start'));
        $start = $this->ownerGet()->assertOk();
        $start->assertJsonPath('data.plan.code', 'start')->assertJsonPath('data.legacy', false)
            ->assertJsonPath('data.status', 'trialing')->assertJsonPath('data.period', 'monthly')
            ->assertJsonPath('data.features.hall', true)->assertJsonPath('data.features.shifts', true)
            ->assertJsonPath('data.features.bookings', false)->assertJsonPath('data.features.customer_accounts', false)
            ->assertJsonPath('data.features.kds', false)
            ->assertJsonPath('data.required_plans.bookings', 'asosiy')->assertJsonPath('data.required_plans.multi_branch_reports', 'pro')
            ->assertJsonPath('data.devices.pos.included', 1)->assertJsonPath('data.devices.pos.active', 1)
            ->assertJsonPath('data.devices.pos.extra', 0)->assertJsonPath('data.devices.pos.extra_price', '59000.00')
            ->assertJsonPath('data.devices.pos.can_add', true)
            ->assertJsonPath('data.devices.kds.included', 0)->assertJsonPath('data.devices.kds.can_add', false)
            ->assertJsonPath('data.devices.waiter.unlimited', true)
            ->assertJsonPath('data.notice', null)->assertJsonPath('data.settings_locked', false);
        $this->assertSame(array_map(fn ($feature) => $feature->value, \App\Enums\PlanFeature::cases()), array_keys($start->json('data.features')));

        $this->billing()->assignPlan($this->store, $this->v2('asosiy'), true);
        $this->ownerGet()->assertJsonPath('data.plan.code', 'asosiy')->assertJsonPath('data.features.bookings', true)
            ->assertJsonPath('data.features.multi_branch_reports', false)->assertJsonPath('data.devices.kds.included', 1);

        // Every member of the store reads it, a terminal PIN session too; the till's bootstrap carries it
        $this->withBearer($this->cashierToken)->getJson('/api/v1/owner/billing/entitlements')->assertOk()->assertJsonPath('data.plan.code', 'asosiy');
        $this->withBearer($this->cashierToken)->getJson('/api/v1/pos/bootstrap')->assertOk()
            ->assertJsonPath('data.entitlements.plan.code', 'asosiy')->assertJsonPath('data.entitlements.features.kds', true);
    }

    public function test_gated_writes_on_start_answer_plan_feature_missing_with_the_plan_that_has_it(): void
    {
        $this->billing()->assignPlan($this->store, $this->v2('start'));

        $this->posAs($this->owner, 'POST', '/bookings', [])->assertForbidden()
            ->assertJsonPath('code', 'plan_feature_missing')->assertJsonPath('feature', 'bookings')
            ->assertJsonPath('required_plan', 'asosiy')->assertJsonPath('data.reason', 'plan_feature_missing')
            ->assertJsonPath('data.required_plan_name', 'Asosiy');
        $this->posAs($this->owner, 'POST', '/bookings/' . \Illuminate\Support\Str::uuid() . '/deposits', [])->assertForbidden()->assertJsonPath('feature', 'deposits');
        $this->posAs($this->owner, 'POST', '/customer-accounts', ['name' => 'Aziz aka'])->assertForbidden()->assertJsonPath('feature', 'customer_accounts');

        $fees = ['service_charge_percent' => '10', 'place_fee_amount' => '0', 'place_fee_mode' => 'per_person'];
        $this->ownerJson('PUT', '/owner/service-fees', $fees)->assertForbidden()->assertJsonPath('feature', 'service_charge');
        $this->ownerJson('PUT', '/owner/service-fees', ['service_charge_percent' => '0'] + $fees)->assertOk();

        $this->ownerJson('POST', '/owner/devices/pairing-codes', ['type' => 'kds', 'branch_id' => $this->branch->id])->assertForbidden()->assertJsonPath('feature', 'kds');
        // A second kassa is allowed: billed as an extra device
        $this->ownerJson('POST', '/owner/devices/pairing-codes', ['type' => 'pos', 'branch_id' => $this->branch->id])->assertCreated();

        // Asosiy has them
        $this->billing()->assignPlan($this->store, $this->v2('asosiy'), true);
        app(Entitlements::class)->forget();
        $this->assertNotSame(403, $this->posAs($this->owner, 'POST', '/bookings', [])->status());
        $this->assertNotSame(403, $this->posAs($this->owner, 'POST', '/customer-accounts', ['name' => 'Aziz aka'])->status());
        $this->ownerJson('PUT', '/owner/service-fees', $fees)->assertOk();
        $this->ownerJson('POST', '/owner/devices/pairing-codes', ['type' => 'kds', 'branch_id' => $this->branch->id])->assertCreated();
    }

    public function test_a_locked_store_keeps_selling_and_refunding_but_changes_no_settings(): void
    {
        $this->withoutTrials();
        $this->billing()->assignPlan($this->store, $this->v2('start'), false, null, $this->at('2026-09-01 00:00'));
        $this->travelTo($this->at('2026-10-20 12:00'));
        $this->billing()->run($this->at('2026-10-20 00:15'));   // September's invoice, due 11 October: locked from 19 October
        $this->assertSame('locked', app(Entitlements::class)->toArray($this->store)['status']);

        // The till: shift, bill, payment, receipt, close, refund, closing the shift
        $shift = $this->pos('POST', '/shifts', ['opening_cash' => '100000'])->assertCreated()->json('data.uuid');
        $order = $this->posOrder([['Osh', '1', '120000']]);
        $bill = $this->bill($order)->assertCreated()->json('data.bills.0.uuid');
        $this->pay($bill, [['tender' => 'cash', 'amount' => '120000']])->assertOk()
            ->assertJsonPath('data.bill.status', 'paid')->assertJsonPath('data.bill.fiscal.status', 'issued')
            ->assertJsonPath('data.order.status', 'delivered');
        $payment = Payment::whereNotNull('bill_id')->firstOrFail();
        $this->pos('POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => '10000', 'reason' => 'Mijoz qaytardi', 'approval_token' => $this->approval('pos.refund')])->assertCreated();
        $this->pos('POST', "/shifts/{$shift}/close", ['counted_breakdown' => []])->assertOk();

        // Settings: refused while locked; reading is fine
        $fees = ['service_charge_percent' => '0', 'place_fee_amount' => '5000', 'place_fee_mode' => 'per_person'];
        $this->ownerJson('PUT', '/owner/service-fees', $fees)->assertForbidden()
            ->assertJsonPath('code', 'subscription_locked')->assertJsonPath('data.locked_since', $this->at('2026-10-19 00:00')->toIso8601String());
        $this->ownerJson('POST', '/owner/kitchen-stations', ['branch_id' => $this->branch->id, 'name' => 'Mangal'])->assertForbidden()->assertJsonPath('code', 'subscription_locked');
        $this->ownerJson('GET', '/owner/service-fees')->assertOk();
        // One kassa is the plan's and it is paired: no extra one while locked
        $this->ownerJson('POST', '/owner/devices/pairing-codes', ['type' => 'pos', 'branch_id' => $this->branch->id])->assertForbidden()
            ->assertJsonPath('code', 'device_limit')->assertJsonPath('data.included', 1);
        // Printers still change, so the till keeps printing
        $this->assertNotSame(403, $this->ownerJson('POST', '/owner/printers', ['branch_id' => $this->branch->id, 'name' => 'Kassa printer', 'purpose' => 'receipt', 'connection' => 'lan', 'address' => '192.168.1.50'])->status());

        // Paying unlocks at once
        app(LedgerService::class)->reduceDebt($this->store, Money::of('149000.00'), 'Click top-up');
        $this->ownerJson('PUT', '/owner/service-fees', $fees)->assertOk();
        $this->ownerJson('POST', '/owner/devices/pairing-codes', ['type' => 'pos', 'branch_id' => $this->branch->id])->assertCreated();
    }

    public function test_the_routes_that_take_money_print_receipts_run_shifts_or_refund_carry_no_plan_check(): void
    {
        $neverGated = [
            'api/v1/pos/bills/{uuid}/payments', 'api/v1/pos/orders/{uuid}/close', 'api/v1/pos/orders/{uuid}/bills',
            'api/v1/pos/bills/{uuid}/print', 'api/v1/pos/bills/{uuid}/fiscal-retry', 'api/v1/pos/fiscal-receipts/{uuid}',
            'api/v1/pos/refunds', 'api/v1/pos/shifts', 'api/v1/pos/shifts/{uuid}/close', 'api/v1/pos/shifts/{uuid}/cash-movements',
            'api/v1/pos/bookings/{uuid}/deposits/{payment}/refund', 'api/v1/pos/customer-accounts/{uuid}/repayments', 'api/v1/pos/sync',
        ];

        $checked = 0;
        foreach (Router::getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            if (!in_array($route->uri(), $neverGated, true)) {
                continue;
            }
            $checked++;
            foreach ($route->gatherMiddleware() as $middleware) {
                $this->assertFalse(is_string($middleware) && (str_starts_with($middleware, 'plan.feature') || $middleware === 'billing.unlocked'), "{$route->uri()} has {$middleware}");
            }
        }
        $this->assertGreaterThanOrEqual(count($neverGated), $checked);
    }

    public function test_a_store_whose_v2_subscription_ended_keeps_only_the_base_features(): void
    {
        $subscription = $this->billing()->assignPlan($this->store, $this->v2('pro'));
        $this->billing()->cancel($subscription, true);

        $this->ownerGet()->assertJsonPath('data.legacy', false)->assertJsonPath('data.status', 'none')
            ->assertJsonPath('data.features.hall', true)->assertJsonPath('data.features.bookings', false);

        // A P1-23 plan keeps every feature
        $this->billing()->assignPlan($this->store, TariffPlan::where('code', 'mixed')->sole());
        app(Entitlements::class)->forget();
        $this->ownerGet()->assertJsonPath('data.legacy', true)->assertJsonPath('data.features.bookings', true);
        $this->assertTrue(app(Entitlements::class)->allows($this->store, \App\Enums\PlanFeature::KDS));
        $this->assertSame(DeviceType::KDS, DeviceType::from('kds'));
    }

    public function test_the_notice_names_the_invoice_only_to_members_with_finance_view_on_their_own_sign_in(): void
    {
        $this->withoutTrials();
        $this->billing()->assignPlan($this->store, $this->v2('start'), false, null, $this->at('2026-10-01 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));   // October's invoice, due 11 November
        $this->travelTo($this->at('2026-11-14 12:00'));
        $number = \App\Models\SubscriptionInvoice::sole()->number;

        // The owner (finance.view) sees which invoice and how much
        $owner = $this->ownerGet()->assertOk();
        $owner->assertJsonPath('data.notice.level', 'reminder')->assertJsonPath('data.notice.days_overdue', 3)
            ->assertJsonPath('data.notice.invoice.number', $number)->assertJsonPath('data.notice.invoice.outstanding', '149000.00');
        $this->assertStringContainsString($number, $owner->json('data.notice.message'));

        // A manager (no finance.view), a cashier's PIN session, the owner's own PIN session and the till's bootstrap:
        // the level, the days, the lock date and a message that names neither the invoice nor the amount
        app(Entitlements::class)->forget();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $manager = $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/billing/entitlements')->assertOk();
        $responses = [$manager->json('data.notice')];
        // One PIN session at a time on the till
        foreach ([[$this->cashier, '4831'], [$this->owner, '7391']] as [$member, $pin]) {
            $token = $this->pinSession($this->till, $member, $pin);
            $responses[] = $this->withBearer($token)->getJson('/api/v1/owner/billing/entitlements')->assertOk()->json('data.notice');
            $responses[] = $this->withBearer($token)->getJson('/api/v1/pos/bootstrap')->assertOk()->json('data.entitlements.notice');
        }
        foreach ($responses as $notice) {
            $this->assertSame('reminder', $notice['level']);
            $this->assertSame(3, $notice['days_overdue']);
            $this->assertSame($this->at('2026-11-19 00:00')->toIso8601String(), $notice['lock_at']);
            $this->assertNull($notice['invoice']);
            $this->assertStringNotContainsString($number, $notice['message']);
            $this->assertStringNotContainsString('149', $notice['message']);
            $this->assertStringContainsString("muddati 3 kun o'tdi", $notice['message']);
        }
    }

    private function ownerGet(): \Illuminate\Testing\TestResponse
    {
        app(Entitlements::class)->forget();

        return $this->ownerJson('GET', '/owner/billing/entitlements');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ownerJson(string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        app(Entitlements::class)->forget();

        return $this->actingAs($this->owner, 'sanctum')->json($method, '/api/v1' . $uri, $data);
    }
}
