<?php

namespace Tests\Feature\BillingV2;

use App\Enums\BillingPeriod;
use App\Enums\DeviceType;
use App\Enums\InvoiceKind;
use App\Enums\SubscriptionStatus;
use App\Models\Order;
use App\Models\StoreSubscription;
use App\Models\StoreTransaction;
use App\Models\SubscriptionInvoice;
use App\Models\TariffPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-21: the v2 catalog (Start 149 000, Asosiy 249 000, Pro 449 000; yearly = 10 months; 14-day trial; extra kassa
 * 59 000, extra KDS 29 000) and its invoices: base fee, extra devices by type, the 50% discount after a month with
 * more than 1 000 000 in commission (from the ledger, Tashkent months), the prorated upgrade, the downgrade at the
 * period's end, yearly terms in advance, period changes, trial extension (docs/BILLING.md).
 */
class BillingV2InvoiceTest extends TestCase
{
    use BillingV2Fixtures;
    use RefreshDatabase;

    public function test_the_catalog_is_seeded_with_the_owner_prices_and_the_old_plans_stay(): void
    {
        $rows = TariffPlan::query()->catalog()->get()->keyBy('code');
        $this->assertSame(['start', 'asosiy', 'pro'], $rows->keys()->all());

        foreach (['start' => ['149000.00', '1490000.00', 1, 0], 'asosiy' => ['249000.00', '2490000.00', 1, 1], 'pro' => ['449000.00', '4490000.00', 2, 1]] as $code => [$monthly, $yearly, $pos, $kds]) {
            $plan = $rows[$code];
            $this->assertSame(2, $plan->version);
            $this->assertSame($code, $plan->tier);
            $this->assertSame($monthly, $plan->monthly_base_fee);
            $this->assertSame($yearly, $plan->yearly_fee);
            $this->assertSame($plan->baseFee()->times(10)->toString(), $plan->yearlyFee()->toString());
            $this->assertSame([$pos, $kds], [$plan->included_pos, $plan->included_kds]);
            $this->assertSame(['59000.00', '29000.00'], [$plan->extra_pos_fee, $plan->extra_kds_fee]);
            $this->assertSame(14, $plan->trial_days);
            $this->assertNull($plan->commission_percent);
        }

        $this->assertSame([], $rows['start']->features);
        $this->assertSame(['bookings', 'deposits', 'customer_accounts', 'kds', 'service_charge'], $rows['asosiy']->features);
        $this->assertContains('multi_branch_reports', $rows['pro']->features);
        $this->assertGreaterThan($rows['start']->rank(), $rows['asosiy']->rank());

        // The P1-23 plans are still there, readable, version 1
        $this->assertSame([1, 1, 1], TariffPlan::whereIn('code', ['commission', 'pos', 'mixed'])->pluck('version')->all());
    }

    public function test_a_month_bills_the_base_fee_and_each_extra_kassa_and_kds_over_the_plan(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $this->terminal($store, '2026-09-01 10:00');                                  // kassa 1 (included)
        $this->terminal($store, '2026-10-05 10:00');                                  // kassa 2: extra
        $this->terminal($store, '2026-10-06 10:00', '2026-10-20 10:00');              // kassa 3 for two weeks: extra
        $this->terminal($store, '2026-10-07 10:00', null, DeviceType::KDS);           // KDS: Start includes none

        $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-10-01 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));

        $invoice = SubscriptionInvoice::sole();
        $this->assertSame(InvoiceKind::MONTHLY, $invoice->kind);
        $this->assertSame(sprintf('OB-202610-%06d', $store->id), $invoice->number);
        // jsonb keeps its own key order: compared as maps
        $this->assertEquals([
            ['type' => 'base', 'label' => "Oylik to'lov: Start", 'quantity' => 1, 'unit_price' => '149000.00', 'amount' => '149000.00'],
            ['type' => 'extra_pos', 'label' => "Qo'shimcha kassa: 3 ta, 1 tasi tarifga kiradi", 'quantity' => 2, 'unit_price' => '59000.00', 'amount' => '118000.00'],
            ['type' => 'extra_kds', 'label' => "Qo'shimcha oshxona ekrani: 1 ta, 0 tasi tarifga kiradi", 'quantity' => 1, 'unit_price' => '29000.00', 'amount' => '29000.00'],
        ], $invoice->lines);
        $this->assertSame('296000.00', $invoice->amount);
        $this->assertSame(4, $invoice->terminals_count);
        $this->assertSame('149000.00', $invoice->base_fee);

        // Asosiy includes one kassa and one KDS: the same devices in November cost the base fee and two extra kassas...
        $this->billing()->assignPlan($store, $this->v2('asosiy'), false, null, $this->at('2026-11-02 10:00'));
        $this->billing()->run($this->at('2026-12-01 00:15'));   // November still on Start (scheduled change)
        $this->billing()->run($this->at('2027-01-01 00:15'));
        $december = SubscriptionInvoice::where('period', '2026-12')->sole();
        // ... in December only one extra kassa is left (the third was revoked in October)
        $this->assertSame(['base', 'extra_pos'], array_column($december->lines, 'type'));
        $this->assertSame('308000.00', $december->amount); // 249 000 + 59 000
    }

    public function test_waiter_phones_and_personal_apps_are_not_billed(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $this->terminal($store, '2026-09-01 10:00');
        foreach ([DeviceType::PARTNER_MOBILE, DeviceType::PARTNER_MOBILE, DeviceType::COURIER] as $type) {
            $this->terminal($store, '2026-09-01 11:00', null, $type);
        }

        $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-10-01 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));

        $this->assertSame('149000.00', SubscriptionInvoice::sole()->amount);
    }

    public function test_the_trial_months_create_no_invoice(): void
    {
        $store = $this->v2Store();
        $subscription = $this->billing()->assignPlan($store, $this->v2('asosiy'), false, null, $this->at('2026-10-25 10:00'));
        $this->assertSame(SubscriptionStatus::TRIALING, $subscription->status);
        $this->assertEquals($this->at('2026-11-08 10:00'), $subscription->trial_ends_at);

        $this->billing()->run($this->at('2026-11-01 00:15'));
        $this->billing()->run($this->at('2026-12-01 00:15'));
        $this->assertSame(0, SubscriptionInvoice::count());

        $this->billing()->run($this->at('2027-01-01 00:15'));
        $this->assertSame(['2026-12' => '249000.00'], SubscriptionInvoice::pluck('amount', 'period')->all());
    }

    public function test_half_the_base_fee_after_a_month_with_more_than_a_million_in_commission(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $this->terminal($store, '2026-09-01 10:00');
        $this->terminal($store, '2026-09-01 11:00');
        // September (Tashkent): 600 000 onto the debt + 400 000.01 from the wallet = 1 000 000.01
        $this->bookCommission($store, '600000.00', '2026-09-01 00:00');
        $this->bookCommission($store, '400000.01', '2026-09-30 23:59', fromWallet: true);
        // Not September: 1 October 00:00 Tashkent; not commission: the referral reward's cost
        $this->bookCommission($store, '900000.00', '2026-10-01 00:00');
        $this->bookCommission($store, '900000.00', '2026-09-15 12:00', notes: 'Referral cashback reward cost for referred user #1');

        $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-10-01 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));

        $october = SubscriptionInvoice::sole();
        $this->assertSame(['base', 'discount', 'extra_pos'], array_column($october->lines, 'type'));
        $this->assertSame('-74500.00', $october->lines[1]['amount']);
        $this->assertStringContainsString('2026-09', $october->lines[1]['label']);
        // 149 000 - 74 500 + 59 000: the extra kassa is not discounted
        $this->assertSame('133500.00', $october->amount);

        // October had 900 000: November is billed in full
        $this->billing()->run($this->at('2026-12-01 00:15'));
        $this->assertSame('208000.00', SubscriptionInvoice::where('period', '2026-11')->sole()->amount);
    }

    public function test_exactly_a_million_or_a_refunded_order_earns_no_discount(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $this->bookCommission($store, '1000000.00', '2026-09-10 10:00');

        $other = $this->v2Store('Refunded Cafe');
        $customer = \App\Models\User::create(['name' => 'Mijoz', 'phone' => '+998901112233', 'password' => bcrypt('x')]);
        $order = Order::create(['store_id' => $other->id, 'user_id' => $customer->id, 'status' => 'refunded', 'order_type' => 'pickup', 'payment_method' => 'cash', 'total_price' => 20000000]);
        $this->bookCommission($other, '2000000.00', '2026-09-10 10:00', order: $order);
        $refund = new StoreTransaction();
        $refund->forceFill(['store_id' => $other->id, 'type' => 'refund', 'amount' => '-2000000.00', 'balance_before' => '0', 'balance_after' => '0', 'order_id' => $order->id, 'notes' => 'Commission reversed'])->save();

        foreach ([$store, $other] as $s) {
            $this->billing()->assignPlan($s, $this->v2('start'), false, null, $this->at('2026-10-01 00:00'));
        }
        $this->billing()->run($this->at('2026-11-01 00:15'));

        $this->assertSame(['149000.00', '149000.00'], SubscriptionInvoice::orderBy('store_id')->pluck('amount')->all());
    }

    public function test_an_immediate_upgrade_is_prorated_and_a_downgrade_waits_for_the_month_end(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-10-01 00:00'));

        // 11 October: 10 days at Start, 21 days at Asosiy
        $upgraded = $this->billing()->assignPlan($store, $this->v2('asosiy'), true, null, $this->at('2026-10-11 15:00'));
        $this->assertSame($this->v2('asosiy')->id, $upgraded->tariff_plan_id);

        // A downgrade asked "now" is scheduled for the month end
        $same = $this->billing()->assignPlan($store, $this->v2('start'), true, null, $this->at('2026-10-20 10:00'));
        $this->assertSame($upgraded->id, $same->id);
        $this->assertSame($this->v2('start')->id, $same->next_tariff_plan_id);

        $this->billing()->run($this->at('2026-11-01 00:15'));
        $october = SubscriptionInvoice::sole();
        $this->assertSame($this->v2('asosiy')->id, $october->tariff_plan_id);
        // 249 000 - (249 000 - 149 000) × 10 / 31 = 249 000 - 32 258.06
        $this->assertSame(['base', 'proration'], array_column($october->lines, 'type'));
        $this->assertSame('-32258.06', $october->lines[1]['amount']);
        $this->assertSame(10, $october->lines[1]['quantity']);
        $this->assertSame('216741.94', $october->amount);

        // November on Start, no credit any more
        $this->billing()->run($this->at('2026-12-01 00:15'));
        $november = SubscriptionInvoice::where('period', '2026-11')->sole();
        $this->assertSame($this->v2('start')->id, $november->tariff_plan_id);
        $this->assertSame('149000.00', $november->amount);
    }

    public function test_an_upgrade_on_the_first_day_has_no_credit(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-09-01 00:00'));
        $this->billing()->run($this->at('2026-10-01 00:15'));
        $this->billing()->assignPlan($store, $this->v2('pro'), true, null, $this->at('2026-10-01 09:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));

        $this->assertSame('449000.00', SubscriptionInvoice::where('period', '2026-10')->sole()->amount);
    }

    public function test_a_yearly_subscription_pays_twelve_months_in_advance_and_only_extras_monthly(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $this->terminal($store, '2026-09-01 10:00');
        $this->terminal($store, '2026-11-10 10:00', '2026-11-20 10:00');   // an extra kassa in November only

        $subscription = $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-10-05 12:00'), BillingPeriod::YEARLY);
        $this->assertSame(BillingPeriod::YEARLY, $subscription->billing_period);
        $this->assertEquals($this->at('2027-10-01 00:00'), $subscription->fresh()->term_ends_at);

        $yearly = SubscriptionInvoice::sole();
        $this->assertSame(InvoiceKind::YEARLY, $yearly->kind);
        $this->assertSame(sprintf('OBY-202610-%06d', $store->id), $yearly->number);
        $this->assertSame('1490000.00', $yearly->amount);
        $this->assertSame('2026-10-15', $yearly->due_date->toDateString());
        $this->assertSame('1490000.00', $store->fresh()->debt_balance);

        // October: nothing more; November: the extra kassa only, no base fee
        $this->billing()->run($this->at('2026-11-01 00:15'));
        $this->billing()->run($this->at('2026-12-01 00:15'));
        $november = SubscriptionInvoice::where('period', '2026-11')->where('kind', 'monthly')->sole();
        $this->assertSame(['extra_pos'], array_column($november->lines, 'type'));
        $this->assertSame('59000.00', $november->amount);
        $this->assertSame(2, SubscriptionInvoice::count());

        // The next term is billed when this one ends; the runs are idempotent
        $this->billing()->run($this->at('2027-10-01 00:15'));
        $this->billing()->run($this->at('2027-10-01 00:20'));
        $next = SubscriptionInvoice::where('kind', 'yearly')->orderBy('id')->get();
        $this->assertSame(['2026-10', '2027-10'], $next->pluck('period')->all());
        $this->assertEquals($this->at('2028-10-01 00:00'), StoreSubscription::query()->current()->sole()->term_ends_at);
    }

    public function test_a_yearly_term_starts_after_the_trial(): void
    {
        $store = $this->v2Store();
        $this->billing()->assignPlan($store, $this->v2('asosiy'), false, null, $this->at('2026-10-25 10:00'), BillingPeriod::YEARLY);
        $this->assertSame(0, SubscriptionInvoice::count());

        $this->billing()->run($this->at('2026-11-01 00:15'));   // November is still touched by the trial
        $this->assertSame(0, SubscriptionInvoice::count());

        $this->billing()->run($this->at('2026-12-01 00:15'));   // December starts the term
        $invoice = SubscriptionInvoice::sole();
        $this->assertSame(['yearly', '2026-12', '2490000.00'], [$invoice->kind->value, $invoice->period, $invoice->amount]);
        $this->assertEquals($this->at('2027-12-01 00:00'), StoreSubscription::query()->current()->sole()->term_ends_at);
    }

    public function test_upgrading_a_yearly_subscription_bills_the_difference_for_the_months_left(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-10-01 00:00'), BillingPeriod::YEARLY);
        $this->billing()->run($this->at('2027-03-01 00:15'));

        $this->billing()->assignPlan($store, $this->v2('asosiy'), true, null, $this->at('2027-03-15 12:00'));

        // March to September: 7 months of (2 490 000 - 1 490 000) / 12
        $upgrade = SubscriptionInvoice::where('kind', 'upgrade')->sole();
        $this->assertSame(sprintf('OBU-202703-%06d-1', $store->id), $upgrade->number);
        $this->assertSame(7, $upgrade->lines[0]['quantity']);
        $this->assertSame('83333.33', $upgrade->lines[0]['unit_price']);
        $this->assertSame('583333.33', $upgrade->amount);

        $current = StoreSubscription::query()->current()->sole();
        $this->assertSame($this->v2('asosiy')->id, $current->tariff_plan_id);
        $this->assertSame(BillingPeriod::YEARLY, $current->billing_period);
        $this->assertEquals($this->at('2027-10-01 00:00'), $current->term_ends_at);

        // The term's months still bill no base fee; the next term is Asosiy's yearly price
        $this->billing()->run($this->at('2027-10-01 00:15'));
        $this->assertSame(['1490000.00', '583333.33', '2490000.00'], SubscriptionInvoice::orderBy('id')->pluck('amount')->all());
    }

    public function test_a_yearly_downgrade_cancel_or_switch_to_monthly_waits_for_the_term_end(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $subscription = $this->billing()->assignPlan($store, $this->v2('pro'), false, null, $this->at('2026-10-01 00:00'), BillingPeriod::YEARLY);
        $this->billing()->assignPlan($store, $this->v2('start'), true, null, $this->at('2026-11-10 10:00'));
        $this->billing()->changePeriod($subscription->fresh(), BillingPeriod::MONTHLY, null, $this->at('2026-11-10 10:05'));

        // Every month of the term passes without a change
        $this->billing()->run($this->at('2027-09-01 00:15'));
        $current = StoreSubscription::query()->current()->sole();
        $this->assertSame($subscription->id, $current->id);
        $this->assertSame($this->v2('pro')->id, $current->tariff_plan_id);
        $this->assertSame(1, SubscriptionInvoice::count());

        // At the term's end: Start, monthly; its first month is billed when it ends
        $this->billing()->run($this->at('2027-10-01 00:15'));
        $current = StoreSubscription::query()->current()->sole();
        $this->assertSame($this->v2('start')->id, $current->tariff_plan_id);
        $this->assertSame(BillingPeriod::MONTHLY, $current->billing_period);
        $this->assertNull($current->term_ends_at);
        $this->billing()->run($this->at('2027-11-01 00:15'));
        $this->assertSame(['4490000.00', '149000.00'], SubscriptionInvoice::orderBy('id')->pluck('amount')->all());

        // A yearly cancel at period end also waits for the term's end
        $other = $this->v2Store('Other');
        $yearly = $this->billing()->assignPlan($other, $this->v2('start'), false, null, $this->at('2026-10-01 00:00'), BillingPeriod::YEARLY);
        $this->billing()->cancel($yearly, false, null, $this->at('2026-12-15 10:00'));
        $this->billing()->run($this->at('2027-05-01 00:15'));
        $this->assertTrue($yearly->fresh()->isCurrent());
        $this->billing()->run($this->at('2027-10-01 00:15'));
        $this->assertSame(SubscriptionStatus::CANCELLED, $yearly->fresh()->status);
        $this->assertSame(1, SubscriptionInvoice::where('store_id', $other->id)->count());
    }

    public function test_switching_a_monthly_subscription_to_yearly_starts_the_term_next_month(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $subscription = $this->billing()->assignPlan($store, $this->v2('asosiy'), false, null, $this->at('2026-09-01 00:00'));
        $this->billing()->run($this->at('2026-10-01 00:15'));

        $this->billing()->changePeriod($subscription->fresh(), BillingPeriod::YEARLY, null, $this->at('2026-10-10 10:00'));
        $this->assertSame(BillingPeriod::YEARLY, $subscription->fresh()->next_billing_period);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.period_changed', 'store_id' => $store->id]);

        $this->billing()->run($this->at('2026-11-01 00:15'));
        $this->assertSame(
            ['2026-09/monthly' => '249000.00', '2026-10/monthly' => '249000.00', '2026-11/yearly' => '2490000.00'],
            SubscriptionInvoice::orderBy('id')->get()->mapWithKeys(fn ($i) => ["{$i->period}/{$i->kind->value}" => $i->amount])->all()
        );
        $this->assertSame(BillingPeriod::YEARLY, $subscription->fresh()->billing_period);

        // A P1-23 plan has no yearly period
        $legacy = $this->billingStore('Legacy');
        $this->expectException(\DomainException::class);
        $this->billing()->assignPlan($legacy, TariffPlan::where('code', 'pos')->sole(), false, null, null, BillingPeriod::YEARLY);
    }

    public function test_extending_the_trial_keeps_the_months_it_touches_free(): void
    {
        $store = $this->v2Store();
        $subscription = $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-10-25 10:00'));

        $this->billing()->extendTrial($subscription, $this->at('2026-12-05 00:00'), null, $this->at('2026-11-01 10:00'));
        $this->assertEquals($this->at('2026-12-05 00:00'), $subscription->fresh()->trial_ends_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription.trial_extended']);

        $this->billing()->run($this->at('2027-01-01 00:15'));
        $this->assertSame(0, SubscriptionInvoice::count());   // October, November and December touched by the trial
        $this->billing()->run($this->at('2027-02-01 00:15'));
        $this->assertSame(['2027-01'], SubscriptionInvoice::pluck('period')->all());

        // Only during a trial, never shorter, never far away
        foreach ([[$subscription->fresh(), '2027-03-01 00:00', '2027-02-02 10:00']] as [$sub, $until, $now]) {
            try {
                $this->billing()->extendTrial($sub, $this->at($until), null, $this->at($now));
                $this->fail('A trial outside the trial was extended');
            } catch (\DomainException) {
            }
        }
        $fresh = $this->billing()->assignPlan($this->v2Store('Fresh'), $this->v2('start'), false, null, $this->at('2027-02-01 10:00'));
        foreach (['2027-02-10 00:00', '2027-06-01 00:00'] as $until) {
            try {
                $this->billing()->extendTrial($fresh, $this->at($until), null, $this->at('2027-02-02 10:00'));
                $this->fail("Extended to {$until}");
            } catch (\DomainException) {
            }
        }
    }

    public function test_the_owner_estimate_follows_the_v2_formula(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $this->terminal($store, '2026-09-01 10:00');
        $this->terminal($store, '2026-09-01 11:00');
        $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-10-01 00:00'));

        $overview = $this->billing()->overview($store, $this->at('2026-10-15 10:00'));
        $this->assertSame(208000.0, $overview['next_invoice']['amount']);
        $this->assertSame(['base', 'extra_pos'], array_column($overview['next_invoice']['lines'], 'type'));
        $this->assertSame('monthly', $overview['subscription']['billing_period']);
    }
}
