<?php

namespace Tests\Feature\BillingV2;

use App\Enums\BillingPeriod;
use App\Enums\InvoiceKind;
use App\Enums\PlatformRole;
use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Order;
use App\Models\StoreSubscription;
use App\Models\SubscriptionInvoice;
use App\Models\TariffPlan;
use App\Services\Finance\Billing\CommissionStats;
use App\Services\Finance\Billing\Entitlements;
use App\Services\Finance\Billing\PlanMigration;
use App\Services\Finance\Billing\SubscriptionStatusResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\AdminFinance\FinanceFixtures;
use Tests\TestCase;

/**
 * Wave 2 money review (P4-21 / P4-23): a new yearly term in the month an earlier one started gets its own invoice
 * (crediting the live one), only a v2 plan's invoice locks, a partial refund takes only its share of the commission
 * out of the discount count, an immediate upgrade drops the migration price lock and its credit follows the discount,
 * and reopening an invoice never undoes a manual "To'landi" nor turns a void invoice's share into commission debt.
 */
class BillingV2ReviewFixesTest extends TestCase
{
    use FinanceFixtures;
    use RefreshDatabase;

    // --- 1. A new yearly term in the same month ------------------------------------------------------------------

    public function test_a_yearly_term_started_again_after_its_invoice_was_voided_is_billed_in_full(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $sub = $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-11-02 10:00'), BillingPeriod::YEARLY);
        $first = SubscriptionInvoice::where('kind', InvoiceKind::YEARLY->value)->sole();
        $this->assertSame('1490000.00', $first->amount);

        // Cancelled now, finance gives the year back, then the store takes Pro yearly in the same month
        $this->billing()->cancel($sub->refresh(), true, null, $this->at('2026-11-05 10:00'));
        $first->update(['status' => SubscriptionInvoiceStatus::VOID, 'voided_at' => $this->at('2026-11-05 11:00')]);
        $this->ledger()->reverseSubscriptionCharge($first->fresh());
        $this->assertSame('0.00', $store->fresh()->debt_balance);
        $new = $this->billing()->assignPlan($store->refresh(), $this->v2('pro'), false, null, $this->at('2026-11-10 10:00'), BillingPeriod::YEARLY);

        $invoices = SubscriptionInvoice::where('kind', InvoiceKind::YEARLY->value)->orderBy('id')->get();
        $this->assertCount(2, $invoices);
        $this->assertSame(SubscriptionInvoiceStatus::VOID, $invoices[0]->status);
        $pro = $invoices[1];
        $this->assertSame($new->id, $pro->store_subscription_id);
        $this->assertSame($this->v2('pro')->id, $pro->tariff_plan_id);
        $this->assertSame(SubscriptionInvoiceStatus::ISSUED, $pro->status);
        $this->assertSame(2, $pro->sequence);
        $this->assertSame(sprintf('OBY-202611-%06d-2', $store->id), $pro->number);
        // Nothing live to credit: the whole Pro year
        $this->assertSame('4490000.00', $pro->amount);
        $this->assertSame(['yearly'], array_column($pro->lines, 'type'));
        $this->assertNotNull($pro->ledger_transaction_id);
        $this->assertSame('4490000.00', $store->fresh()->debt_balance);
    }

    public function test_a_yearly_term_started_again_in_the_same_month_credits_the_live_yearly_invoice(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $sub = $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-11-02 10:00'), BillingPeriod::YEARLY);
        $this->billing()->cancel($sub->refresh(), true, null, $this->at('2026-11-05 10:00'));
        $new = $this->billing()->assignPlan($store->refresh(), $this->v2('pro'), false, null, $this->at('2026-11-10 10:00'), BillingPeriod::YEARLY);

        $this->assertNotNull($new->refresh()->term_ends_at);
        $pro = SubscriptionInvoice::where('store_subscription_id', $new->id)->sole();
        $this->assertSame(InvoiceKind::YEARLY, $pro->kind);
        // 4 490 000 - the 1 490 000 Start year it already owes for the same 12 months
        $this->assertSame(['yearly', 'yearly_credit'], array_column($pro->lines, 'type'));
        $this->assertSame('-1490000.00', $pro->lines[1]['amount']);
        $this->assertSame('3000000.00', $pro->amount);
        $this->assertSame('4490000.00', $store->fresh()->debt_balance);

        // The same plan again: the live invoice already covers the term, nothing more is billed
        $this->billing()->cancel($new->refresh(), true, null, $this->at('2026-11-12 10:00'));
        $again = $this->billing()->assignPlan($store->refresh(), $this->v2('start'), false, null, $this->at('2026-11-15 10:00'), BillingPeriod::YEARLY);
        $this->assertNotNull($again->refresh()->term_ends_at);
        $this->assertSame(0, SubscriptionInvoice::where('store_subscription_id', $again->id)->count());
        $this->assertSame('4490000.00', $store->fresh()->debt_balance);
    }

    // --- 2. Only a v2 plan's invoice locks ----------------------------------------------------------------------

    public function test_an_old_p1_23_invoice_never_locks_a_store_now_on_a_v2_plan(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $legacy = TariffPlan::where('code', 'pos')->sole();
        $legacy->update(['trial_days' => 0]);
        $this->terminal($store, '2026-09-01 10:00');
        $this->billing()->assignPlan($store, $legacy, false, null, $this->at('2026-09-01 00:00'));
        $this->billing()->run($this->at('2026-10-01 00:15'));   // September on the P1-23 plan, due 11 October
        $this->billing()->assignPlan($store->refresh(), $this->v2('start'), false, null, $this->at('2026-10-15 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));   // October too, Start from November

        $now = $this->at('2026-11-02 12:00');
        $sub = StoreSubscription::query()->current()->where('store_id', $store->id)->sole();
        $this->assertSame('start', $sub->plan->code);
        $this->assertSame(SubscriptionStatus::PAST_DUE, app(SubscriptionStatusResolver::class)->statusFor($sub, $now));
        $this->assertSame(SubscriptionStatus::PAST_DUE, $sub->fresh()->status);
        $entitlements = $this->entitlementsAt($store->id, $now);
        $this->assertSame('past_due', $entitlements['status']);
        $this->assertNull($entitlements['lock_at']);
        $this->assertNull($entitlements['locked_since']);
        $this->assertFalse($entitlements['settings_locked']);
        $this->assertSame('reminder', $entitlements['notice']['level']);
        $this->assertNull($entitlements['notice']['lock_at']);

        // November's Start invoice (due 11 December) locks a week after its due date, the old ones still unpaid
        $this->billing()->run($this->at('2026-12-01 00:15'));
        $this->assertSame(SubscriptionStatus::PAST_DUE, app(SubscriptionStatusResolver::class)->statusFor($sub->fresh(), $this->at('2026-12-18 23:00')));
        $this->assertSame(SubscriptionStatus::LOCKED, app(SubscriptionStatusResolver::class)->statusFor($sub->fresh(), $this->at('2026-12-19 00:00')));
        $locked = $this->entitlementsAt($store->id, $this->at('2026-12-19 10:00'));
        $this->assertSame('locked', $locked['status']);
        $this->assertSame($this->at('2026-12-19 00:00')->toIso8601String(), $locked['lock_at']);
        $this->assertTrue($locked['settings_locked']);
    }

    // --- 3. A partial refund and the 50% discount ---------------------------------------------------------------

    public function test_a_partial_refund_takes_only_its_share_of_the_commission_out_of_the_discount_count(): void
    {
        Carbon::setTestNow($this->at('2026-10-10 12:00'));
        $store = $this->v2Store('Stats');
        $order = $this->platformOrder($store, 'click', '10500000.00', '1050000.00');
        $this->ledger()->processOrderDelivered($order);
        $stats = app(CommissionStats::class);
        $this->assertSame('1050000.00', $stats->inMonth($store->id, '2026-10')->toString());

        // 10 000 of 10 500 000 given back: 1 000 of the commission is reversed, the rest still counts
        $this->refundPart($order->refresh(), '10000.00');
        $this->assertSame('1049000.00', $stats->inMonth($store->id, '2026-10')->toString());
        $this->assertTrue($stats->earnsDiscount($store->id, '2026-10'));

        // A second order refunded in full counts nothing
        $other = $this->financeOrder($store, ['commission_amount' => '30000.00']);
        $this->ledger()->deductCommission($store->fresh(), '30000.00', $other);
        $this->assertSame('1079000.00', $stats->inMonth($store->id, '2026-10')->toString());
        $this->ledger()->reverseDeliveredOrder($other->fresh());
        $this->assertSame('1049000.00', $stats->inMonth($store->id, '2026-10')->toString());

        // A refund a month later still comes out of October's count, never below zero for the order
        Carbon::setTestNow($this->at('2026-11-05 12:00'));
        $this->ledger()->reverseDeliveredOrder($order->fresh());
        $this->assertSame('0.00', $stats->inMonth($store->id, '2026-10')->toString());
        $this->assertSame('0.00', $stats->inMonth($store->id, '2026-11')->toString());
    }

    // --- 4. The migration price lock and an immediate upgrade ---------------------------------------------------

    public function test_an_immediate_upgrade_drops_the_migration_price_lock(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $legacy = TariffPlan::where('code', 'pos')->sole();
        $legacy->update(['trial_days' => 0]);
        $this->terminal($store, '2026-09-01 10:00');
        $this->billing()->assignPlan($store, $legacy, false, null, $this->at('2026-10-01 00:00'));
        app(PlanMigration::class)->run(false, [$store->id], $this->at('2026-10-15 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));
        $start = StoreSubscription::query()->current()->where('store_id', $store->id)->sole();
        $this->assertSame('start', $start->plan->code);
        $this->assertNotNull($start->price_lock_plan_id);

        // The first v2 month, price-locked to the P1-23 plan: the admin moves the store up to Pro on 2 November
        $pro = $this->billing()->assignPlan($store->refresh(), $this->v2('pro'), true, null, $this->at('2026-11-02 10:00'));
        $this->assertNull($pro->price_lock_plan_id);
        $this->assertNull($pro->price_locked_until);
        $this->billing()->run($this->at('2026-12-01 00:15'));

        $november = SubscriptionInvoice::where('period', '2026-11')->sole();
        $this->assertSame(['base', 'proration'], array_column($november->lines, 'type'));
        // 449 000 - (449 000 - 149 000) × 1 / 30
        $this->assertSame('439000.00', $november->amount);
    }

    public function test_a_change_to_a_lower_or_the_same_rank_keeps_the_price_lock(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $legacy = TariffPlan::where('code', 'pos')->sole();
        $legacy->update(['trial_days' => 0]);
        $this->terminal($store, '2026-09-01 10:00');
        $this->billing()->assignPlan($store, $legacy, false, null, $this->at('2026-10-01 00:00'));
        app(PlanMigration::class)->run(false, [$store->id], $this->at('2026-10-15 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));

        // A downgrade waits for the month's end: the row (and its lock) stays
        $current = StoreSubscription::query()->current()->where('store_id', $store->id)->sole();
        $kept = $this->billing()->assignPlan($store->refresh(), $this->v2('start'), true, null, $this->at('2026-11-02 10:00'));
        $this->assertSame($current->id, $kept->id);
        $this->assertNotNull($kept->price_lock_plan_id);
    }

    // --- 5. The upgrade credit and the 50% discount -------------------------------------------------------------

    public function test_the_upgrade_credit_follows_the_discount(): void
    {
        $this->withoutTrials();
        $store = $this->v2Store();
        $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-10-01 00:00'));
        $this->bookCommission($store, '1050000.00', '2026-10-10 10:00');
        $this->billing()->run($this->at('2026-11-01 00:15'));

        // 11 November: 10 days at Start, 20 at Asosiy, the whole month at 50% off
        $this->billing()->assignPlan($store->refresh(), $this->v2('asosiy'), true, null, $this->at('2026-11-11 15:00'));
        $this->billing()->run($this->at('2026-12-01 00:15'));

        $november = SubscriptionInvoice::where('period', '2026-11')->sole();
        $this->assertSame(['base', 'discount', 'proration'], array_column($november->lines, 'type'));
        $this->assertSame('-124500.00', $november->lines[1]['amount']);
        // (249 000 - 149 000) × 10 / 30 × 50%
        $this->assertSame('-16666.67', $november->lines[2]['amount']);
        // = 149 000 × 10/30 × 50% + 249 000 × 20/30 × 50%
        $this->assertSame('107833.33', $november->amount);
    }

    // --- 6. Reopening never undoes a manual "To'landi" ----------------------------------------------------------

    public function test_a_refund_keeps_an_invoice_marked_paid_by_hand_paid(): void
    {
        $store = $this->storeWithTwoInvoices();
        $order = $this->platformOrder($store, 'click', '100000.00');
        $this->ledger()->settlePlatformOrder($order);
        [$september] = $this->invoicesOf($store);
        $this->assertSame(['100000.00', SubscriptionInvoiceStatus::ISSUED], [$september->paid_amount, $september->status]);
        $this->billing()->markPaid($september);   // the rest came in by hand
        $this->assertSame(SubscriptionInvoiceStatus::PAID, $september->fresh()->status);

        $this->ledger()->reverseDeliveredOrder($order->fresh());

        $september = $september->fresh();
        $this->assertSame('0.00', $september->paid_amount);
        $this->assertSame(SubscriptionInvoiceStatus::PAID, $september->status);
        $this->assertNotNull($september->paid_at);
        $store->refresh();
        $this->assertSame(['298000.00', '298000.00'], [$store->debt_balance, $store->subscription_debt]);
    }

    // --- 7. A void invoice's share stays subscription debt ------------------------------------------------------

    public function test_a_refund_of_money_that_paid_a_since_voided_invoice_reopens_what_the_void_credit_paid(): void
    {
        $store = $this->storeWithTwoInvoices();
        $order = $this->platformOrder($store, 'payme', '149000.00');
        $this->ledger()->settlePlatformOrder($order);
        [$september, $october] = $this->invoicesOf($store);
        $this->assertSame(SubscriptionInvoiceStatus::PAID, $september->status);

        // September voided after it was paid (as before wave 2): its money became credit, which paid October
        $this->voidTheOldWay($september);
        $this->assertSame(SubscriptionInvoiceStatus::PAID, $october->fresh()->status);
        $this->assertSame(['0.00', '0.00'], [$store->fresh()->debt_balance, $store->fresh()->subscription_debt]);

        $this->ledger()->reverseDeliveredOrder($order->fresh());

        $this->assertSame(SubscriptionInvoiceStatus::VOID, $september->fresh()->status);
        $this->assertSame(SubscriptionInvoiceStatus::ISSUED, $october->fresh()->status);
        $this->assertSame('0.00', $october->fresh()->paid_amount);
        $store->refresh();
        $this->assertSame(['149000.00', '149000.00', '0.00'], [$store->debt_balance, $store->subscription_debt, $store->commissionDebt()->toString()]);
    }

    public function test_a_void_credit_still_held_as_credit_reopens_nothing_else(): void
    {
        $store = $this->storeWithTwoInvoices();
        $order = $this->platformOrder($store, 'payme', '298000.00');
        $this->ledger()->settlePlatformOrder($order);
        [$september, $october] = $this->invoicesOf($store);
        $this->voidTheOldWay($september);
        $this->assertSame('-149000.00', $store->fresh()->debt_balance);

        $this->ledger()->reverseDeliveredOrder($order->fresh());

        $this->assertSame(SubscriptionInvoiceStatus::ISSUED, $october->fresh()->status);
        $store->refresh();
        $this->assertSame(['149000.00', '149000.00', '0.00'], [$store->debt_balance, $store->subscription_debt, $store->commissionDebt()->toString()]);
    }

    /**
     * What voidInvoice did before wave 2 for a paid invoice: void it, then reverse its whole charge.
     */
    private function voidTheOldWay(SubscriptionInvoice $invoice): void
    {
        $invoice->fresh()->update(['status' => SubscriptionInvoiceStatus::VOID, 'voided_at' => now(), 'void_reason' => 'Xato']);
        $this->ledger()->reverseSubscriptionCharge($invoice->fresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function entitlementsAt(int $storeId, Carbon $now): array
    {
        $entitlements = app(Entitlements::class);
        $entitlements->forget();

        return $entitlements->toArray($storeId, $now);
    }

    /**
     * @return array<string, mixed> the approved refund request
     */
    private function refundPart(Order $order, string $amount): array
    {
        [$requester, $approver] = [$this->confirmedSession($this->makeMember(PlatformRole::OPERATOR)), $this->confirmedSession($this->makeMember(PlatformRole::FINANCE))];
        $request = $this->financeJson('POST', "/orders/{$order->uuid}/refund-requests", $requester, ['amount' => $amount, 'reason' => 'Qisman qaytarish'])
            ->assertCreated()->json('data');

        return $this->financeJson('POST', "/refund-requests/{$request['id']}/approve", $approver, ['reason' => 'Tasdiqlandi'])->assertOk()->json('data');
    }
}
