<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\StoreTransactionType;
use App\Models\Order;
use App\Models\StoreTransaction;
use App\Models\TariffPlan;
use App\Services\Finance\LedgerService;
use App\Services\Finance\SubscriptionService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1-23: the commission charged on online orders is the current tariff plan's when the plan sets one, otherwise the
 * store's own commission_rate exactly as before; checkout and the ledger use that one resolved rate.
 */
class BillingCommissionTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use BillingFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    public function test_a_store_without_a_plan_keeps_exactly_its_own_commission(): void
    {
        $this->assertSame('10.00', $this->store->fresh()->commissionRate());

        $order = $this->checkout($this->customer, 2);
        $this->assertSame(Money::of($order->total_price)->percent($this->store->commission_rate)->toString(), $order->fresh()->commission_amount);
        $this->assertSame('10000.00', $order->fresh()->commission_amount);

        // A fractional rate rounds exactly as before
        $this->store->forceFill(['commission_rate' => '7.25'])->save();
        $this->assertSame('7.25', $this->store->fresh()->commissionRate());
        $this->assertSame('7250.00', $this->checkout($this->customer, 2)->fresh()->commission_amount);
    }

    public function test_a_plan_without_a_commission_keeps_the_store_rate(): void
    {
        app(SubscriptionService::class)->assignPlan($this->store, TariffPlan::where('code', 'commission')->sole());

        $this->assertSame('10.00', $this->store->fresh()->commissionRate());
        $this->assertSame('10000.00', $this->checkout($this->customer, 2)->fresh()->commission_amount);
    }

    public function test_the_plan_commission_is_snapshotted_at_checkout_and_charged_on_delivery(): void
    {
        app(SubscriptionService::class)->assignPlan($this->store, $this->plan(['commission_percent' => '5.50']));
        $this->assertSame('5.50', $this->store->fresh()->commissionRate());

        $order = $this->checkout($this->customer, 2);
        $this->assertSame('5500.00', $order->fresh()->commission_amount);

        $order = Order::findOrFail($order->id);
        $order->forceFill(['status' => OrderStatus::DELIVERED])->save();
        app(LedgerService::class)->processOrderDelivered($order);

        $this->assertSame(['5500.00'], StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::COMMISSION->value)->pluck('amount')->all());
        $this->assertSame('5500.00', $this->store->fresh()->debt_balance);
    }

    public function test_the_ledger_fallback_uses_the_same_resolved_rate(): void
    {
        app(SubscriptionService::class)->assignPlan($this->store, $this->plan(['commission_percent' => 4]));

        // An in-memory order without a commission snapshot: the ledger works it out from the resolved rate
        $order = Order::create([
            'store_id' => $this->store->id,
            'user_id' => $this->customer->id,
            'status' => OrderStatus::DELIVERED,
            'order_type' => 'pickup',
            'payment_method' => 'cash',
            'total_price' => 80000,
        ]);
        $this->assertFalse(isset($order->commission_amount));

        app(LedgerService::class)->processOrderDelivered($order);

        $this->assertSame(['3200.00'], StoreTransaction::where('order_id', $order->id)->pluck('amount')->all());
    }

    public function test_a_subscription_only_plan_charges_no_commission_and_cancelling_restores_the_store_rate(): void
    {
        $billing = app(SubscriptionService::class);
        $subscription = $billing->assignPlan($this->store, TariffPlan::where('code', 'pos')->sole());

        $order = $this->checkout($this->customer, 2);
        $this->assertSame('0.00', $order->fresh()->commission_amount);

        $billing->cancel($subscription, true);
        $this->assertSame('10.00', $this->store->fresh()->commissionRate());
        $this->assertSame('10000.00', $this->checkout($this->customer, 2)->fresh()->commission_amount);
    }

    public function test_the_owner_finance_endpoint_shows_the_rate_actually_charged(): void
    {
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/finance')
            ->assertOk()->assertJsonPath('data.commission_rate', 10);

        app(SubscriptionService::class)->assignPlan($this->store, TariffPlan::where('code', 'mixed')->sole());

        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/finance')
            ->assertOk()->assertJsonPath('data.commission_rate', 5);
    }
}
