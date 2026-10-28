<?php

namespace Tests\Feature\BillingV2;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreStatus;
use App\Enums\SubscriptionInvoiceStatus;
use App\Models\Order;
use App\Models\Store;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Services\Finance\LedgerService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-21 (owner decision 28.10.2026): the credit limit that stops Mini App orders counts only the commission debt,
 * never subscription invoices. debt_balance stays one ledger; stores.subscription_debt is its invoice part; payments
 * pay the oldest invoices first; credit pays new invoices at once (docs/BILLING.md, "Qarzning ikki qismi").
 */
class BillingV2DebtSplitTest extends TestCase
{
    use BillingV2Fixtures;
    use RefreshDatabase;

    private LedgerService $ledger;

    private ?User $customer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutTrials();
        $this->ledger = app(LedgerService::class);
    }

    public function test_an_invoice_over_the_credit_limit_never_blocks_but_commission_still_does(): void
    {
        $store = $this->v2Store('Tight', ['credit_limit' => -100000]);
        $this->billing()->assignPlan($store, $this->v2('pro'), false, null, $this->at('2026-10-01 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));

        $store->refresh();
        $this->assertSame('449000.00', $store->debt_balance);
        $this->assertSame('449000.00', $store->subscription_debt);
        $this->assertSame('0.00', $store->commissionDebt()->toString());
        $this->assertSame(StoreStatus::ACTIVE, $store->status);
        $this->assertFalse($store->exceedsCreditLimit());
        $this->assertFalse($store->is_order_locked);

        // Commission up to the limit is fine; one tiyin over it blocks (the invoice part is not counted)
        $order = $this->order($store);
        $this->ledger->deductCommission($store, '100000.00', $order);
        $this->assertSame(StoreStatus::ACTIVE, $store->fresh()->status);
        $this->ledger->deductCommission($store, '0.01', $this->order($store));
        $store->refresh();
        $this->assertSame(StoreStatus::PAYMENT_REQUIRED, $store->status);
        $this->assertSame('100000.01', $store->commissionDebt()->toString());
        $this->assertTrue($store->is_order_locked);
    }

    public function test_a_payment_pays_the_oldest_invoices_first_then_the_commission(): void
    {
        $store = $this->v2Store();
        $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-09-01 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));   // September and October: 149 000 each
        $this->ledger->deductCommission($store, '50000.00', $this->order($store));
        $this->assertSame(['348000.00', '298000.00'], [$store->fresh()->debt_balance, $store->fresh()->subscription_debt]);

        $this->ledger->reduceDebt($store, Money::of('200000.00'), 'Click top-up');

        [$september, $october] = SubscriptionInvoice::orderBy('period')->get()->all();
        $this->assertSame(SubscriptionInvoiceStatus::PAID, $september->status);
        $this->assertSame('149000.00', $september->paid_amount);
        $this->assertSame(SubscriptionInvoiceStatus::ISSUED, $october->status);
        $this->assertSame('51000.00', $october->paid_amount);
        $store->refresh();
        $this->assertSame('148000.00', $store->debt_balance);
        $this->assertSame('98000.00', $store->subscription_debt);
        $this->assertSame('50000.00', $store->commissionDebt()->toString());

        // The rest pays October, then the commission
        $this->ledger->reduceDebt($store, Money::of('120000.00'), 'Payme top-up');
        $store->refresh();
        $this->assertSame(SubscriptionInvoiceStatus::PAID, $october->fresh()->status);
        $this->assertSame(['28000.00', '0.00', '28000.00'], [$store->debt_balance, $store->subscription_debt, $store->commissionDebt()->toString()]);
    }

    public function test_credit_the_store_already_has_pays_a_new_invoice_at_once(): void
    {
        $store = $this->v2Store();
        $this->ledger->reduceDebt($store, Money::of('200000.00'), 'Prepaid');
        $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-10-01 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));

        $invoice = SubscriptionInvoice::sole();
        $this->assertSame(SubscriptionInvoiceStatus::PAID, $invoice->status);
        $this->assertSame('149000.00', $invoice->paid_amount);
        $store->refresh();
        $this->assertSame(['-51000.00', '0.00'], [$store->debt_balance, $store->subscription_debt]);
    }

    /**
     * Review W2: a partly paid invoice is no longer voided (its paid part would turn into credit on one member's word);
     * the unpaid one next to it is, and only its own amount leaves the subscription part of the debt.
     */
    public function test_a_partly_paid_invoice_is_not_voided_and_voiding_the_unpaid_one_takes_only_its_amount(): void
    {
        $store = $this->v2Store();
        $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-09-01 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));
        $this->ledger->reduceDebt($store, Money::of('100000.00'), 'Click top-up');
        [$september, $october] = SubscriptionInvoice::orderBy('period')->get()->all();
        $this->assertSame('100000.00', $september->paid_amount);

        try {
            $this->billing()->voidInvoice($september, null, 'Xato hisoblandi');
            $this->fail('A partly paid invoice was voided');
        } catch (\App\Services\Finance\InvoiceVoidException $e) {
            $this->assertSame('invoice_paid', $e->reason);
        }
        $store->refresh();
        $this->assertSame(SubscriptionInvoiceStatus::ISSUED, $september->fresh()->status);
        $this->assertSame(['198000.00', '198000.00'], [$store->debt_balance, $store->subscription_debt]);

        $this->billing()->voidInvoice($october, null, 'Xato hisoblandi');

        // 298 000 - 100 000 paid - 149 000 voided = 49 000 owed, all of it September's remaining part
        $store->refresh();
        $this->assertSame(SubscriptionInvoiceStatus::VOID, $october->fresh()->status);
        $this->assertSame('49000.00', $store->debt_balance);
        $this->assertSame('49000.00', $store->subscription_debt);
        $this->assertSame('100000.00', $september->fresh()->paid_amount);
        $this->assertSame('0.00', $store->commissionDebt()->toString());
    }

    public function test_keshbek_money_collected_by_rizqim_pays_the_invoices_first(): void
    {
        $store = $this->v2Store();
        $this->billing()->assignPlan($store, $this->v2('start'), false, null, $this->at('2026-10-01 00:00'));
        $this->billing()->run($this->at('2026-11-01 00:15'));

        $order = $this->order($store, ['payment_method' => 'keshbek', 'payment_owner' => 'PLATFORM', 'payment_status' => PaymentStatus::PAID, 'total_price' => 200000, 'commission_amount' => 0]);
        $this->assertTrue($this->ledger->settlePlatformOrder($order));

        $this->assertSame(SubscriptionInvoiceStatus::PAID, SubscriptionInvoice::sole()->status);
        $store->refresh();
        $this->assertSame(['0.00', '0.00', '51000.00'], [$store->debt_balance, $store->subscription_debt, $store->wallet_balance]);
    }

    public function test_a_store_read_without_the_column_counts_its_whole_debt(): void
    {
        $store = $this->v2Store('Partial', ['credit_limit' => -100000]);
        $store->forceFill(['debt_balance' => '150000.00', 'subscription_debt' => '150000.00'])->save();

        $this->assertFalse(Store::findOrFail($store->id)->exceedsCreditLimit());
        $this->assertTrue(Store::select(['id', 'debt_balance', 'credit_limit'])->findOrFail($store->id)->exceedsCreditLimit());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function order(Store $store, array $attributes = []): Order
    {
        $this->customer ??= User::create(['name' => 'Mijoz', 'phone' => '+998901110099', 'password' => bcrypt('x')]);

        return Order::create($attributes + [
            'store_id' => $store->id,
            'user_id' => $this->customer->id,
            'status' => OrderStatus::DELIVERED,
            'order_type' => 'pickup',
            'payment_method' => 'cash',
            'total_price' => 100000,
        ]);
    }
}
