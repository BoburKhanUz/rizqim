<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\SubscriptionInvoice;
use App\Services\Finance\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * P1-34 (review A, findings 2 and 3): a plan change or cancel made after a month ended but before the 00:15 run
 * bills that month first (no lost month, no period ending before it starts), and a deleted store's subscription is
 * closed after billing what it owes instead of failing the run every day.
 */
class BillingPeriodCatchUpTest extends TestCase
{
    use RefreshDatabase;
    use BillingFixtures;

    private SubscriptionService $billing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->billing = app(SubscriptionService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_cancelling_now_after_the_month_ended_still_bills_that_month(): void
    {
        $store = $this->billingStore('Gap');
        $sub = $this->billing->assignPlan($store, $this->plan(['monthly_base_fee' => 500000]), false, null, $this->tashkent('2026-10-01 00:00'));

        // October is over, the 00:15 run has not happened yet; the superadmin cancels "now" at 00:05
        $this->billing->cancel($sub, true, null, $this->tashkent('2026-11-01 00:05'));

        $this->assertSame(['2026-10'], SubscriptionInvoice::pluck('period')->all());
        $this->assertSame('500000.00', SubscriptionInvoice::sole()->getRawOriginal('amount'));
        $this->assertSame(SubscriptionStatus::CANCELLED, $sub->fresh()->status);

        // The runs bill nothing more (November was cancelled immediately, so it is not billed)
        $this->billing->run($this->tashkent('2026-11-01 00:15'));
        $this->billing->run($this->tashkent('2026-12-01 00:15'));
        $this->assertSame(1, SubscriptionInvoice::count());
        $this->assertSame(0, StoreSubscription::query()->current()->count());
    }

    public function test_cancelling_at_period_end_after_the_month_ended_bills_once_and_ends(): void
    {
        $store = $this->billingStore('Gap end');
        $sub = $this->billing->assignPlan($store, $this->plan(['monthly_base_fee' => 300000]), false, null, $this->tashkent('2026-10-01 00:00'));

        $result = $this->billing->cancel($sub, false, null, $this->tashkent('2026-11-01 00:05'));

        // October billed; the subscription rolled into November and ends when November does
        $this->assertSame(['2026-10'], SubscriptionInvoice::pluck('period')->all());
        $this->assertTrue($result->cancel_at_period_end);
        $this->billing->run($this->tashkent('2026-12-01 00:15'));
        $this->assertSame(['2026-10', '2026-11'], SubscriptionInvoice::orderBy('period')->pluck('period')->all());
        $this->assertSame(0, StoreSubscription::query()->current()->count());
    }

    public function test_an_immediate_plan_change_after_the_month_ended_bills_the_old_plan_first(): void
    {
        $store = $this->billingStore('Gap 2');
        $old = $this->plan(['monthly_base_fee' => 500000]);
        $new = $this->plan(['monthly_base_fee' => 100000]);
        $this->billing->assignPlan($store, $old, false, null, $this->tashkent('2026-10-01 00:00'));

        $second = $this->billing->assignPlan($store, $new, true, null, $this->tashkent('2026-11-01 00:05'));

        // October on the plan it was used on
        $october = SubscriptionInvoice::where('period', '2026-10')->sole();
        $this->assertSame('500000.00', $october->getRawOriginal('amount'));
        $this->assertSame($old->id, $october->tariff_plan_id);

        // The new plan's period runs forward: from the change to the end of November
        $this->assertTrue($second->current_period_end->greaterThan($second->current_period_start));
        $this->assertTrue($second->current_period_end->equalTo($this->tashkent('2026-12-01 00:00')));

        $this->billing->run($this->tashkent('2026-11-01 00:15'));
        $this->billing->run($this->tashkent('2026-12-01 00:15'));

        $invoices = SubscriptionInvoice::orderBy('period')->get();
        $this->assertSame(['2026-10', '2026-11'], $invoices->pluck('period')->all());
        $this->assertSame('100000.00', $invoices[1]->getRawOriginal('amount'));
        foreach ($invoices as $invoice) {
            $this->assertTrue($invoice->period_end->greaterThan($invoice->period_start), "{$invoice->period} ends after it starts");
        }
        $this->assertSame(0, StoreSubscription::query()->whereColumn('current_period_end', '<=', 'current_period_start')->count());
    }

    public function test_a_plan_change_after_a_scheduled_one_took_effect_changes_the_new_row(): void
    {
        $store = $this->billingStore('Gap 3');
        $first = $this->plan(['monthly_base_fee' => 200000]);
        $scheduled = $this->plan(['monthly_base_fee' => 300000]);
        $third = $this->plan(['monthly_base_fee' => 400000]);
        $this->billing->assignPlan($store, $first, false, null, $this->tashkent('2026-10-01 00:00'));
        $this->billing->assignPlan($store, $scheduled, false, null, $this->tashkent('2026-10-20 12:00'));

        // After October ended: October is billed on the first plan, the scheduled plan's row opens, and the new
        // immediate change applies to it
        $current = $this->billing->assignPlan($store, $third, true, null, $this->tashkent('2026-11-01 00:05'));

        $this->assertSame('200000.00', SubscriptionInvoice::where('period', '2026-10')->sole()->getRawOriginal('amount'));
        $this->assertSame($third->id, $current->tariff_plan_id);
        $this->assertSame(1, StoreSubscription::query()->current()->where('store_id', $store->id)->count());
        $this->assertTrue($current->current_period_end->greaterThan($current->current_period_start));
    }

    public function test_a_period_that_would_end_before_it_starts_is_refused(): void
    {
        $store = $this->billingStore('Invariant');
        $plan = $this->plan(['monthly_base_fee' => 100000]);
        $open = new \ReflectionMethod(SubscriptionService::class, 'open');

        $this->expectException(\LogicException::class);
        $open->invoke($this->billing, $store->id, $plan, $this->tashkent('2026-11-01 00:05'), null, $this->tashkent('2026-11-01 00:00'), null, $this->tashkent('2026-11-01 00:05'));
    }

    public function test_deleting_a_store_bills_its_ended_month_and_closes_the_subscription(): void
    {
        $store = $this->billingStore('Deleted');
        $sub = $this->billing->assignPlan($store, $this->plan(['monthly_base_fee' => 500000]), false, null, $this->tashkent('2026-10-01 00:00'));

        Carbon::setTestNow($this->tashkent('2026-11-01 00:05'));
        $store->delete();

        $this->assertSame(['2026-10'], SubscriptionInvoice::pluck('period')->all());
        $this->assertSame(SubscriptionStatus::CANCELLED, $sub->fresh()->status);
        $this->assertSame(0, StoreSubscription::query()->current()->count());
        // Charged to the deleted store's debt ledger
        $this->assertSame('500000.00', Store::withTrashed()->find($store->id)->getRawOriginal('debt_balance'));

        $this->billing->run($this->tashkent('2026-11-01 00:15'));
        $this->billing->run($this->tashkent('2026-12-01 00:15'));
        $this->assertSame(1, SubscriptionInvoice::count());
    }

    public function test_a_subscription_left_on_a_deleted_store_is_billed_and_closed_by_the_run_without_errors(): void
    {
        $errors = 0;
        Log::listen(function ($event) use (&$errors) {
            if ($event->level === 'error') {
                $errors++;
            }
        });

        // Deleted after October ended, before the run, without the deletion hook (a row from before P1-34)
        $late = $this->billingStore('Deleted late');
        $lateSub = $this->billing->assignPlan($late, $this->plan(['monthly_base_fee' => 500000]), false, null, $this->tashkent('2026-10-01 00:00'));
        Carbon::setTestNow($this->tashkent('2026-11-01 00:05'));
        Store::withoutEvents(fn () => $late->delete());

        // Deleted in the middle of October: October was not over, so it is not billed (as an immediate cancel)
        Carbon::setTestNow($this->tashkent('2026-10-15 12:00'));
        $early = $this->billingStore('Deleted early');
        $earlySub = $this->billing->assignPlan($early, $this->plan(['monthly_base_fee' => 700000]), false, null, $this->tashkent('2026-10-01 00:00'));
        Store::withoutEvents(fn () => $early->delete());
        Carbon::setTestNow();

        $this->billing->run($this->tashkent('2026-11-01 00:15'));
        $this->billing->run($this->tashkent('2026-11-02 00:15'));
        $this->billing->run($this->tashkent('2026-12-01 00:15'));

        $this->assertSame(0, $errors);
        $this->assertSame([$late->id], SubscriptionInvoice::where('period', '2026-10')->pluck('store_id')->all());
        $this->assertSame(1, SubscriptionInvoice::count());
        $this->assertSame(SubscriptionStatus::CANCELLED, $lateSub->fresh()->status);
        $this->assertSame(SubscriptionStatus::CANCELLED, $earlySub->fresh()->status);
    }
}
