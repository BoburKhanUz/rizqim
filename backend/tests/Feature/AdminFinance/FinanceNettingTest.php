<?php

namespace Tests\Feature\AdminFinance;

use App\Enums\StoreTransactionType;
use App\Enums\SubscriptionInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\StoreSubscription;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-23 (owner decision 28.10.2026, docs/BILLING.md "Netting"): money RIZQIM collected for a store — Click, Payme,
 * keshbek — pays the store's debt before it reaches the wallet: the oldest subscription invoices first, then the
 * commission debt, each its own ledger row naming what it closed. Taking that money back (a refund) reopens the
 * invoices it had paid.
 */
class FinanceNettingTest extends TestCase
{
    use FinanceFixtures;
    use RefreshDatabase;

    public function test_a_click_settlement_pays_the_oldest_invoices_then_the_commission_and_the_rest_goes_to_the_wallet(): void
    {
        $store = $this->storeWithTwoInvoices();
        $this->ledger()->deductCommission($store, '50000.00', $this->financeOrder($store));
        $this->assertSame(['348000.00', '298000.00'], [$store->fresh()->debt_balance, $store->fresh()->subscription_debt]);

        $order = $this->platformOrder($store, 'click', '400000.00');
        $this->assertTrue($this->ledger()->settlePlatformOrder($order));

        [$september, $october] = $this->invoicesOf($store);
        $this->assertSame(SubscriptionInvoiceStatus::PAID, $september->status);
        $this->assertSame(SubscriptionInvoiceStatus::PAID, $october->status);

        $rows = StoreTransaction::where('order_id', $order->id)->orderBy('id')->get();
        $this->assertSame([StoreTransactionType::TOPUP], $rows->pluck('type')->unique()->values()->all());
        $this->assertSame(['-149000.00', '-149000.00', '-50000.00'], $rows->pluck('amount')->all());
        $this->assertSame([$september->id, $october->id, null], $rows->pluck('subscription_invoice_id')->all());
        $this->assertSame(['348000.00', '199000.00', '50000.00'], $rows->pluck('balance_before')->all());
        $this->assertSame(['199000.00', '50000.00', '0.00'], $rows->pluck('balance_after')->all());
        $this->assertStringContainsString($september->number, $rows[0]->notes);
        $this->assertStringContainsString($october->number, $rows[1]->notes);
        $this->assertStringContainsString('commission debt', $rows[2]->notes);

        $wallet = StoreWalletTransaction::where('order_id', $order->id)->sole();
        $this->assertSame(StoreTransactionType::SETTLEMENT->value, $wallet->type);
        $this->assertEquals(52000, $wallet->amount);

        $store->refresh();
        $this->assertSame(['0.00', '0.00', '52000.00'], [$store->debt_balance, $store->subscription_debt, $store->wallet_balance]);

        // Never twice
        $this->assertFalse($this->ledger()->settlePlatformOrder($order->fresh()));
        $this->assertSame(3, StoreTransaction::where('order_id', $order->id)->count());
        $this->assertSame('52000.00', $store->fresh()->wallet_balance);
    }

    public function test_a_payme_settlement_smaller_than_the_debt_pays_part_of_the_oldest_invoice_only(): void
    {
        $store = $this->storeWithTwoInvoices();
        $order = $this->platformOrder($store, 'payme', '100000.00');

        $this->ledger()->settlePlatformOrder($order);

        [$september, $october] = $this->invoicesOf($store);
        $this->assertSame(SubscriptionInvoiceStatus::ISSUED, $september->status);
        $this->assertSame('100000.00', $september->paid_amount);
        $this->assertSame('0.00', $october->paid_amount);
        $this->assertSame(0, StoreWalletTransaction::where('order_id', $order->id)->count());
        $store->refresh();
        $this->assertSame(['198000.00', '198000.00', '0.00'], [$store->debt_balance, $store->subscription_debt, $store->wallet_balance]);
    }

    public function test_a_settlement_of_a_store_without_debt_goes_to_the_wallet_as_before(): void
    {
        $store = $this->v2Store('Clean');
        $order = $this->platformOrder($store, 'payme', '80000.00');

        $this->ledger()->processOrderDelivered($order);

        $this->assertSame(0, StoreTransaction::where('order_id', $order->id)->count());
        $this->assertSame(['0.00', '80000.00'], [$store->fresh()->debt_balance, $store->fresh()->wallet_balance]);
    }

    public function test_the_orders_own_commission_booked_as_debt_is_paid_from_its_settlement(): void
    {
        $store = $this->v2Store('Own commission');
        $order = $this->platformOrder($store, 'click', '80000.00', '8000.00');

        $this->ledger()->processOrderDelivered($order);

        // Commission 8 000 (the wallet was empty: debt), then the 80 000 pays that debt first, 72 000 to the wallet
        $payoff = StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::TOPUP->value)->sole();
        $this->assertSame('-8000.00', $payoff->amount);
        $this->assertNull($payoff->subscription_invoice_id);
        $this->assertSame(['0.00', '72000.00'], [$store->fresh()->debt_balance, $store->fresh()->wallet_balance]);

        // Refunded: the commission, the payoff and the settlement all come back; the store ends where it started
        $this->ledger()->reverseDeliveredOrder($order->fresh());
        $this->assertSame(['0.00', '0.00'], [$store->fresh()->debt_balance, $store->fresh()->wallet_balance]);
    }

    public function test_a_refund_takes_the_money_back_and_reopens_the_invoices_it_had_paid(): void
    {
        $store = $this->storeWithTwoInvoices();
        $this->ledger()->deductCommission($store, '50000.00', $this->financeOrder($store));
        $order = $this->platformOrder($store, 'click', '200000.00');
        $this->ledger()->settlePlatformOrder($order);
        [$september, $october] = $this->invoicesOf($store);
        $this->assertSame(SubscriptionInvoiceStatus::PAID, $september->status);
        $this->assertSame('51000.00', $october->paid_amount);
        $this->assertSame(['148000.00', '98000.00'], [$store->fresh()->debt_balance, $store->fresh()->subscription_debt]);

        $reversed = $this->ledger()->reverseDeliveredOrder($order->fresh());

        $this->assertSame('200000.00', $reversed['settlement']->toString());
        [$september, $october] = $this->invoicesOf($store);
        $this->assertSame(SubscriptionInvoiceStatus::ISSUED, $september->status);
        $this->assertNull($september->paid_at);
        $this->assertSame('0.00', $september->paid_amount);
        $this->assertSame('0.00', $october->paid_amount);
        $store->refresh();
        $this->assertSame(['348000.00', '298000.00', '50000.00'], [$store->debt_balance, $store->subscription_debt, $store->commissionDebt()->toString()]);

        $refunds = StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::REFUND->value)->orderBy('id')->get();
        $this->assertSame(['149000.00', '51000.00'], $refunds->pluck('amount')->all());
        $this->assertSame([$september->id, $october->id], $refunds->pluck('subscription_invoice_id')->all());

        // Never twice
        $again = $this->ledger()->reverseDeliveredOrder($order->fresh());
        $this->assertTrue(collect($again)->every->isZero());
        $this->assertSame('348000.00', $store->fresh()->debt_balance);
    }

    public function test_a_reversed_keshbek_payoff_reopens_the_invoice_instead_of_turning_into_commission_debt(): void
    {
        $store = $this->storeWithTwoInvoices();
        $order = $this->platformOrder($store, 'keshbek', '149000.00');
        $this->ledger()->settlePlatformOrder($order);
        $this->assertSame(SubscriptionInvoiceStatus::PAID, $this->invoicesOf($store)[0]->status);
        $this->assertSame('149000.00', $store->fresh()->subscription_debt);

        $this->travelTo($this->at('2026-11-25 12:00'));
        $this->ledger()->reverseDeliveredOrder($order->fresh());

        $this->assertSame(SubscriptionInvoiceStatus::ISSUED, $this->invoicesOf($store)[0]->status);
        $store->refresh();
        $this->assertSame(['298000.00', '298000.00', '0.00'], [$store->debt_balance, $store->subscription_debt, $store->commissionDebt()->toString()]);
        // Overdue again: the subscription goes back to the dunning routine
        $this->assertNotSame(SubscriptionStatus::ACTIVE, StoreSubscription::where('store_id', $store->id)->latest('id')->first()->status);
    }

    public function test_a_voided_invoice_is_not_reopened_its_share_returns_as_commission_debt(): void
    {
        $store = $this->storeWithTwoInvoices();
        $order = $this->platformOrder($store, 'payme', '149000.00');
        $this->ledger()->settlePlatformOrder($order);
        [$september] = $this->invoicesOf($store);
        $this->billing()->voidInvoice($september->fresh(), null, 'Xato');
        $before = $store->fresh();

        $this->ledger()->reverseDeliveredOrder($order->fresh());

        $this->assertSame(SubscriptionInvoiceStatus::VOID, $september->fresh()->status);
        $store->refresh();
        $this->assertSame(\App\Support\Money::of($before->debt_balance)->plus('149000.00')->toString(), $store->debt_balance);
        $this->assertSame($before->subscription_debt, $store->subscription_debt);
    }
}
