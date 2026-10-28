<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\StoreStatus;
use App\Enums\StoreTransactionType;
use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Jobs\BillSubscriptionsJob;
use App\Models\Device;
use App\Models\StoreSubscription;
use App\Models\StoreTransaction;
use App\Models\SubscriptionInvoice;
use App\Models\TariffPlan;
use App\Models\User;
use App\Services\Finance\LedgerService;
use App\Services\Finance\SubscriptionService;
use App\Services\Finance\TerminalCounter;
use App\Support\Money;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1-23: tariff plans, subscriptions and the monthly invoices posted to the store's debt ledger (docs/BILLING.md).
 */
class BillingSubscriptionInvoiceTest extends TestCase
{
    use RefreshDatabase;
    use BillingFixtures;

    private SubscriptionService $billing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->billing = app(SubscriptionService::class);
    }

    public function test_the_default_plans_are_seeded_with_the_current_commission_behaviour(): void
    {
        $commission = TariffPlan::where('code', 'commission')->sole();
        $this->assertNull($commission->commission_percent);
        $this->assertFalse($commission->hasSubscriptionFee());

        $pos = TariffPlan::where('code', 'pos')->sole();
        $this->assertSame('0.00', $pos->commission_percent);
        $this->assertTrue($pos->hasSubscriptionFee());

        $mixed = TariffPlan::where('code', 'mixed')->sole();
        $this->assertSame('5.00', $mixed->commission_percent);
        $this->assertSame(1, $mixed->included_terminals);
    }

    public function test_the_fee_is_base_plus_the_terminals_over_the_included_ones(): void
    {
        $plan = $this->plan(['monthly_base_fee' => 100000, 'monthly_fee_per_terminal' => 50000, 'included_terminals' => 2]);

        $this->assertSame('100000.00', $this->billing->quote($plan, 0)['amount']->toString());
        $this->assertSame('100000.00', $this->billing->quote($plan, 2)['amount']->toString());

        $quote = $this->billing->quote($plan, 5);
        $this->assertSame('250000.00', $quote['amount']->toString());
        $this->assertSame(3, $quote['extra_terminals']);
        $this->assertSame([
            ['type' => 'base', 'label' => "Oylik asosiy to'lov", 'quantity' => 1, 'unit_price' => '100000.00', 'amount' => '100000.00'],
            ['type' => 'terminals', 'label' => 'Terminallar: 5 ta, 2 tasi tarifga kiradi', 'quantity' => 3, 'unit_price' => '50000.00', 'amount' => '150000.00'],
        ], $quote['lines']);

        // Only a per-terminal fee, to the tiyin: no float drift
        $perTerminal = $this->plan(['monthly_fee_per_terminal' => '33333.33']);
        $this->assertSame('99999.99', $this->billing->quote($perTerminal, 3)['amount']->toString());

        // Commission only: nothing to bill
        $this->assertTrue($this->billing->quote($this->plan(['commission_percent' => 7]), 4)['amount']->isZero());
    }

    public function test_terminals_are_the_most_pos_and_kds_terminals_active_at_once_in_the_period(): void
    {
        $store = $this->billingStore();
        $other = $this->billingStore('Other Cafe');

        $this->terminal($store, '2026-09-01 10:00', '2026-10-10 10:00');   // replaced by the next one at the same moment
        $this->terminal($store, '2026-10-10 10:00');
        $this->terminal($store, '2026-10-15 09:00', '2026-10-20 18:00');
        $this->terminal($store, '2026-10-16 12:00', null, DeviceType::KDS);  // 3 at once from Oct 16 to Oct 20, 2 after
        $this->terminal($store, '2026-08-01 10:00', '2026-09-30 23:59');   // revoked before the period
        $this->terminal($store, '2026-11-01 00:00');                       // paired when the period had ended
        $this->terminal($other, '2026-10-01 10:00');
        $this->terminal($other, '2026-10-01 11:00');
        $this->terminal($other, '2026-10-01 12:00');
        $this->terminal($other, '2026-10-01 13:00');

        // A person's own app install is not a terminal
        $owner = User::create(['name' => 'Owner', 'phone' => '+998901110000', 'password' => bcrypt('x'), 'store_id' => $store->id, 'role' => 'owner']);
        Device::create(['user_id' => $owner->id, 'store_id' => $store->id, 'type' => DeviceType::PARTNER_MOBILE, 'name' => 'Telefon']);

        $counter = app(TerminalCounter::class);
        $this->assertSame(3, $counter->maxConcurrent($store->id, $this->tashkent('2026-10-01'), $this->tashkent('2026-11-01')));
        $this->assertSame(2, $counter->maxConcurrent($store->id, $this->tashkent('2026-10-21'), $this->tashkent('2026-11-01')));
        $this->assertSame(2, $counter->maxConcurrent($store->id, $this->tashkent('2026-09-01'), $this->tashkent('2026-10-01')));
        $this->assertSame(4, $counter->maxConcurrent($other->id, $this->tashkent('2026-10-01'), $this->tashkent('2026-11-01')));
    }

    public function test_the_month_is_invoiced_on_the_1st_posted_to_the_ledger_and_running_again_is_a_no_op(): void
    {
        $store = $this->billingStore();
        $plan = $this->plan(['monthly_base_fee' => 100000, 'monthly_fee_per_terminal' => 100000, 'included_terminals' => 1]);
        foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $day) {
            $this->terminal($store, "{$day} 10:00");
        }

        $subscription = $this->billing->assignPlan($store, $plan, false, null, $this->tashkent('2026-10-05 12:00'));
        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertEquals($this->tashkent('2026-11-01'), $subscription->current_period_end);

        // Before the month ends nothing is billed
        $this->assertSame(0, $this->billing->run($this->tashkent('2026-10-31 23:59')));
        $this->assertSame(0, SubscriptionInvoice::count());

        $this->assertSame(1, $this->billing->run($this->tashkent('2026-11-01 00:15')));
        $invoice = SubscriptionInvoice::sole();
        $this->assertSame('2026-10', $invoice->period);
        $this->assertSame(sprintf('OB-202610-%06d', $store->id), $invoice->number);
        $this->assertSame(3, $invoice->terminals_count);
        $this->assertSame(1, $invoice->included_terminals);
        $this->assertSame('300000.00', $invoice->amount);
        $this->assertSame(SubscriptionInvoiceStatus::ISSUED, $invoice->status);
        $this->assertSame('2026-11-11', $invoice->due_date->toDateString());
        $this->assertEquals($this->tashkent('2026-10-05 12:00'), $invoice->period_start);
        $this->assertSame(['base', 'terminals'], array_column($invoice->lines, 'type'));

        // The debit is on the owner's debt ledger, as its own entry type
        $charge = StoreTransaction::where('store_id', $store->id)->sole();
        $this->assertSame(StoreTransactionType::SUBSCRIPTION, $charge->type);
        $this->assertSame('300000.00', $charge->amount);
        $this->assertSame('0.00', $charge->balance_before);
        $this->assertSame('300000.00', $charge->balance_after);
        $this->assertSame($invoice->uuid, $charge->reference_id);
        $this->assertSame($charge->id, $invoice->ledger_transaction_id);
        $this->assertSame('300000.00', $store->fresh()->debt_balance);

        // The next period is November
        $subscription->refresh();
        $this->assertEquals($this->tashkent('2026-11-01'), $subscription->current_period_start);
        $this->assertEquals($this->tashkent('2026-12-01'), $subscription->current_period_end);

        // Running again (same day, a day later, or via the scheduled job) changes nothing
        $this->assertSame(0, $this->billing->run($this->tashkent('2026-11-01 00:20')));
        $this->assertSame(0, $this->billing->run($this->tashkent('2026-11-02 00:15')));
        $this->travelTo($this->tashkent('2026-11-05 00:15'));
        (new BillSubscriptionsJob)->handle($this->billing);
        $this->assertSame(1, SubscriptionInvoice::count());
        $this->assertSame(1, StoreTransaction::where('store_id', $store->id)->count());
        $this->assertSame('300000.00', $store->fresh()->debt_balance);
    }

    public function test_months_the_trial_touches_create_no_invoice_and_only_the_first_subscription_gets_a_trial(): void
    {
        $store = $this->billingStore();
        $this->terminal($store, '2026-09-01 10:00');
        $plan = $this->plan(['monthly_fee_per_terminal' => 150000, 'trial_days' => 14]);

        $subscription = $this->billing->assignPlan($store, $plan, false, null, $this->tashkent('2026-10-25 10:00'));
        $this->assertSame(SubscriptionStatus::TRIALING, $subscription->status);
        $this->assertEquals($this->tashkent('2026-11-08 10:00'), $subscription->trial_ends_at);

        $this->billing->run($this->tashkent('2026-11-01 00:15'));   // October: all trial
        $this->assertSame(SubscriptionStatus::TRIALING, $subscription->fresh()->status);
        $this->billing->run($this->tashkent('2026-12-01 00:15'));   // November: the trial ran to Nov 8
        $this->assertSame(0, SubscriptionInvoice::count());
        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->fresh()->status);

        $this->billing->run($this->tashkent('2027-01-01 00:15'));   // December is billed
        $this->assertSame(['2026-12'], SubscriptionInvoice::pluck('period')->all());
        $this->assertSame('150000.00', SubscriptionInvoice::sole()->amount);

        // A store that had a subscription before gets no new trial
        $this->billing->cancel($subscription->fresh(), true, null, $this->tashkent('2027-01-05 10:00'));
        $again = $this->billing->assignPlan($store, $plan, false, null, $this->tashkent('2027-01-06 10:00'));
        $this->assertNull($again->trial_ends_at);
        $this->assertSame(SubscriptionStatus::ACTIVE, $again->status);
    }

    public function test_a_missed_run_catches_up_month_by_month(): void
    {
        $store = $this->billingStore();
        $plan = $this->plan(['monthly_fee_per_terminal' => 10000]);
        $this->terminal($store, '2026-08-01 10:00');
        $this->terminal($store, '2026-09-15 10:00', '2026-10-15 10:00');

        $this->billing->assignPlan($store, $plan, false, null, $this->tashkent('2026-08-10 10:00'));

        $this->assertSame(3, $this->billing->run($this->tashkent('2026-11-03 09:00')));
        $this->assertSame(
            ['2026-08' => '10000.00', '2026-09' => '20000.00', '2026-10' => '20000.00'],
            SubscriptionInvoice::orderBy('period')->pluck('amount', 'period')->all()
        );
        $this->assertSame('50000.00', $store->fresh()->debt_balance);
        $this->assertSame(0, $this->billing->run($this->tashkent('2026-11-03 09:05')));
    }

    public function test_a_plan_change_applies_from_next_month_by_default(): void
    {
        $store = $this->billingStore();
        $old = $this->plan(['monthly_base_fee' => 100000, 'commission_percent' => 5]);
        $new = $this->plan(['monthly_base_fee' => 200000, 'commission_percent' => 2]);

        $first = $this->billing->assignPlan($store, $old, false, null, $this->tashkent('2026-09-01 00:00'));
        $this->billing->run($this->tashkent('2026-10-01 00:15'));

        $same = $this->billing->assignPlan($store, $new, false, null, $this->tashkent('2026-10-10 12:00'));
        $this->assertSame($first->id, $same->id);
        $this->assertSame($new->id, $same->next_tariff_plan_id);
        $this->assertSame('5.00', $store->fresh()->commissionRate()); // still the old plan this month

        $this->billing->run($this->tashkent('2026-11-01 00:15'));
        $october = SubscriptionInvoice::where('period', '2026-10')->sole();
        $this->assertSame($old->id, $october->tariff_plan_id);
        $this->assertSame('100000.00', $october->amount);

        $first->refresh();
        $this->assertSame(SubscriptionStatus::CANCELLED, $first->status);
        $this->assertEquals($this->tashkent('2026-11-01'), $first->cancelled_at);
        $current = StoreSubscription::query()->current()->where('store_id', $store->id)->sole();
        $this->assertSame($new->id, $current->tariff_plan_id);
        $this->assertEquals($this->tashkent('2026-11-01'), $current->started_at);
        $this->assertSame('2.00', $store->fresh()->commissionRate());

        $this->billing->run($this->tashkent('2026-12-01 00:15'));
        $this->assertSame('200000.00', SubscriptionInvoice::where('period', '2026-11')->sole()->amount);
        $this->assertSame(2, StoreSubscription::where('store_id', $store->id)->count());
    }

    public function test_an_immediate_change_bills_the_month_once_under_the_new_plan(): void
    {
        $store = $this->billingStore();
        $old = $this->plan(['monthly_base_fee' => 100000]);
        $new = $this->plan(['monthly_base_fee' => 250000]);

        $first = $this->billing->assignPlan($store, $old, false, null, $this->tashkent('2026-09-01 00:00'));
        $this->billing->run($this->tashkent('2026-10-01 00:15'));
        $second = $this->billing->assignPlan($store, $new, true, null, $this->tashkent('2026-10-10 12:00'));

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(SubscriptionStatus::CANCELLED, $first->fresh()->status);
        $this->assertEquals($this->tashkent('2026-11-01'), $second->current_period_end);

        $this->billing->run($this->tashkent('2026-11-01 00:15'));
        $october = SubscriptionInvoice::where('period', '2026-10')->sole();
        $this->assertSame($new->id, $october->tariff_plan_id);
        $this->assertSame('250000.00', $october->amount);
        $this->assertSame(2, SubscriptionInvoice::count()); // September and October
    }

    public function test_cancelling(): void
    {
        $atEnd = $this->billingStore('At End');
        $now = $this->billingStore('Now');
        $plan = $this->plan(['monthly_base_fee' => 50000]);

        $a = $this->billing->assignPlan($atEnd, $plan, false, null, $this->tashkent('2026-10-01 00:00'));
        $b = $this->billing->assignPlan($now, $plan, false, null, $this->tashkent('2026-10-01 00:00'));
        $this->billing->cancel($a, false, null, $this->tashkent('2026-10-20 10:00'));
        $this->billing->cancel($b, true, null, $this->tashkent('2026-10-20 10:00'));

        $this->assertSame(SubscriptionStatus::CANCELLED, $b->fresh()->status);
        $this->assertTrue($a->fresh()->cancel_at_period_end);

        $this->billing->run($this->tashkent('2026-11-01 00:15'));
        $this->billing->run($this->tashkent('2026-12-01 00:15'));

        // At period end: October billed, then ended; immediately: October not billed
        $this->assertSame(['2026-10'], SubscriptionInvoice::where('store_id', $atEnd->id)->pluck('period')->all());
        $this->assertSame(0, SubscriptionInvoice::where('store_id', $now->id)->count());
        $this->assertSame(SubscriptionStatus::CANCELLED, $a->fresh()->status);
        $this->assertEquals($this->tashkent('2026-11-01'), $a->fresh()->cancelled_at);
        $this->assertSame('10.00', $atEnd->fresh()->commissionRate());
    }

    public function test_a_commission_only_plan_never_invoices(): void
    {
        $store = $this->billingStore();
        $this->terminal($store, '2026-09-01 10:00');
        $this->billing->assignPlan($store, TariffPlan::where('code', 'commission')->sole(), false, null, $this->tashkent('2026-09-01 00:00'));

        $this->billing->run($this->tashkent('2026-12-01 00:15'));

        $this->assertSame(0, SubscriptionInvoice::count());
        $this->assertSame(0, StoreTransaction::count());
    }

    public function test_voiding_reverses_the_ledger_charge_once_and_an_invoice_never_blocks_the_store(): void
    {
        $store = $this->billingStore('Tight Cafe', ['credit_limit' => -100000]);
        $admin = User::create(['name' => 'Admin', 'phone' => '+998901110001', 'password' => bcrypt('x'), 'role' => 'super_admin']);
        $this->billing->assignPlan($store, $this->plan(['monthly_base_fee' => '300000.50']), false, null, $this->tashkent('2026-10-01 00:00'));

        $this->billing->run($this->tashkent('2026-11-01 00:15'));

        // P4-21 (owner decision 28.10.2026): over the credit limit, an invoice no longer blocks the store; only the
        // commission debt counts (the invoice goes through the dunning routine instead)
        $store->refresh();
        $this->assertSame('300000.50', $store->debt_balance);
        $this->assertSame('300000.50', $store->subscription_debt);
        $this->assertSame(StoreStatus::ACTIVE, $store->status);
        // A store blocked by its commission debt before is unblocked once the void brings that back within the limit
        $store->forceFill(['status' => StoreStatus::PAYMENT_REQUIRED->value])->save();

        $invoice = SubscriptionInvoice::sole();
        $this->billing->voidInvoice($invoice, $admin, 'Xato hisoblandi', $this->tashkent('2026-11-02 10:00'));
        $this->billing->voidInvoice($invoice->fresh(), $admin, 'Yana', $this->tashkent('2026-11-02 10:01'));

        $invoice->refresh();
        $this->assertSame(SubscriptionInvoiceStatus::VOID, $invoice->status);
        $this->assertSame('Xato hisoblandi', $invoice->void_reason);
        $this->assertSame($admin->id, $invoice->voided_by);

        $rows = StoreTransaction::where('store_id', $store->id)->orderBy('id')->get();
        $this->assertSame(['300000.50', '-300000.50'], $rows->pluck('amount')->all());
        $this->assertSame([StoreTransactionType::SUBSCRIPTION, StoreTransactionType::SUBSCRIPTION], $rows->pluck('type')->all());
        $this->assertSame($rows[1]->id, $invoice->void_transaction_id);
        $this->assertSame($admin->id, $rows[1]->performed_by);

        $store->refresh();
        $this->assertSame('0.00', $store->debt_balance);
        $this->assertSame('0.00', $store->subscription_debt);
        $this->assertSame(StoreStatus::ACTIVE, $store->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'subscription_invoice.voided', 'store_id' => $store->id, 'user_id' => $admin->id]);
    }

    public function test_an_unpaid_invoice_past_its_due_date_makes_the_subscription_past_due_until_the_debt_is_paid(): void
    {
        $store = $this->billingStore();
        $subscription = $this->billing->assignPlan($store, $this->plan(['monthly_base_fee' => 120000]), false, null, $this->tashkent('2026-10-01 00:00'));

        $this->billing->run($this->tashkent('2026-11-01 00:15'));
        $this->billing->run($this->tashkent('2026-11-11 00:15'));   // due today: not late yet
        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->fresh()->status);

        $this->billing->run($this->tashkent('2026-11-12 00:15'));
        $this->assertSame(SubscriptionStatus::PAST_DUE, $subscription->fresh()->status);

        // The owner tops up: a store that owes nothing has paid its invoices
        app(LedgerService::class)->reduceDebt($store, Money::of(120000), 'Click top-up');
        $this->billing->run($this->tashkent('2026-11-13 00:15'));

        $invoice = SubscriptionInvoice::sole();
        $this->assertSame(SubscriptionInvoiceStatus::PAID, $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->fresh()->status);
    }

    public function test_marking_paid_by_hand_does_not_touch_the_ledger(): void
    {
        $store = $this->billingStore();
        $this->billing->assignPlan($store, $this->plan(['monthly_base_fee' => 80000]), false, null, $this->tashkent('2026-10-01 00:00'));
        $this->billing->run($this->tashkent('2026-11-01 00:15'));

        $this->billing->markPaid(SubscriptionInvoice::sole());

        $this->assertSame(SubscriptionInvoiceStatus::PAID, SubscriptionInvoice::sole()->status);
        $this->assertSame('80000.00', $store->fresh()->debt_balance);
        $this->assertSame(1, StoreTransaction::count());
    }

    public function test_an_inactive_plan_cannot_be_assigned_and_one_store_has_one_current_subscription(): void
    {
        $store = $this->billingStore();

        try {
            $this->billing->assignPlan($store, $this->plan(['is_active' => false]));
            $this->fail('An inactive plan was assigned');
        } catch (\DomainException) {
        }

        $plan = $this->plan(['monthly_base_fee' => 1000]);
        $this->billing->assignPlan($store, $plan);
        $this->billing->assignPlan($store, $plan);
        $this->billing->assignPlan($store, $this->plan(), true);

        $this->assertSame(1, StoreSubscription::query()->current()->where('store_id', $store->id)->count());
        $this->assertSame(2, StoreSubscription::where('store_id', $store->id)->count());
    }

    public function test_the_job_is_scheduled_daily_in_tashkent_time(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->description, BillSubscriptionsJob::class));

        $this->assertNotNull($event);
        $this->assertSame('15 0 * * *', $event->expression);
        $this->assertSame('Asia/Tashkent', $event->timezone);
    }
}
