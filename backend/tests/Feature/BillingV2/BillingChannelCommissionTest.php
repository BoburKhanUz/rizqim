<?php

namespace Tests\Feature\BillingV2;

use App\Enums\OrderStatus;
use App\Enums\StoreTransactionType;
use App\Models\Order;
use App\Models\StoreTransaction;
use App\Services\Finance\Billing\CommissionPolicy;
use App\Services\Finance\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BillingFixtures;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * P4-21 (owner decision 24.10.2026): commission by channel at checkout — RIZQIM courier 15%, own delivery 10%,
 * pickup 5%, the hall 0% — with a store's own rate per channel; the channel, rate and amount are snapshotted, so
 * open orders keep theirs; a store with a legacy flat rate keeps it until it is migrated (docs/BILLING.md).
 */
class BillingChannelCommissionTest extends TestCase
{
    use BillingFixtures;
    use OrderLifecycleFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->store->forceFill(['commission_rate' => null])->save();
    }

    public function test_each_channel_pays_its_rate_and_the_order_keeps_the_snapshot(): void
    {
        $pickup = $this->checkout($this->customer, 2, 'cash', 'pickup')->fresh();
        $this->assertSame(['pickup', '5.00', '5000.00'], [$pickup->commission_channel, $pickup->commission_rate, $pickup->commission_amount]);

        $own = $this->checkout($this->customer, 2, 'cash', 'delivery')->fresh();
        $this->assertSame(['own_delivery', '10.00', '10000.00'], [$own->commission_channel, $own->commission_rate, $own->commission_amount]);

        $this->store->forceFill(['delivery_courier' => 'fleet'])->save();
        $fleet = $this->checkout($this->customer, 2, 'cash', 'delivery')->fresh();
        $this->assertSame(['rizqim_courier', '15.00', '15000.00'], [$fleet->commission_channel, $fleet->commission_rate, $fleet->commission_amount]);

        $policy = app(CommissionPolicy::class);
        $this->assertSame('0.00', $policy->rateFor($this->store, \App\Enums\CommissionChannel::POS));
        $this->assertSame('0.00', $policy->rateFor($this->store, \App\Enums\CommissionChannel::AGGREGATOR));
        $this->assertSame(\App\Enums\CommissionChannel::POS, $policy->channelFor($this->store, \App\Enums\OrderChannel::POS_HALL, 'dine_in'));

        // A rate change later never reaches an open order: delivery charges the snapshot
        $this->store->forceFill(['commission_overrides' => ['pickup' => '2.00']])->save();
        $pickup->forceFill(['status' => OrderStatus::DELIVERED])->save();
        app(LedgerService::class)->processOrderDelivered(Order::findOrFail($pickup->id));
        $this->assertSame(['5000.00'], StoreTransaction::where('order_id', $pickup->id)->where('type', StoreTransactionType::COMMISSION->value)->pluck('amount')->all());
    }

    public function test_a_store_override_applies_per_channel(): void
    {
        $this->store->forceFill(['commission_overrides' => ['pickup' => '3.50', 'own_delivery' => '8', 'bogus' => '1', 'rizqim_courier' => 'abc']])->save();

        $this->assertSame('3500.00', $this->checkout($this->customer, 2, 'cash', 'pickup')->fresh()->commission_amount);
        $this->assertSame('8000.00', $this->checkout($this->customer, 2, 'cash', 'delivery')->fresh()->commission_amount);

        $table = app(CommissionPolicy::class)->table($this->store->fresh());
        $this->assertSame(['rate' => '3.50', 'source' => 'override', 'default' => '5.00'], $table['pickup']);
        $this->assertSame(['rate' => '15.00', 'source' => 'default', 'default' => '15.00'], $table['rizqim_courier']);
    }

    public function test_a_legacy_flat_rate_or_plan_rate_is_kept_for_every_online_channel(): void
    {
        $this->store->forceFill(['commission_rate' => '12.00'])->save();
        $this->assertSame('12000.00', $this->checkout($this->customer, 2, 'cash', 'pickup')->fresh()->commission_amount);
        $this->assertSame('12000.00', $this->checkout($this->customer, 2, 'cash', 'delivery')->fresh()->commission_amount);
        $this->assertSame('12.00', $this->store->fresh()->commissionRate());

        // An override beats it
        $this->store->forceFill(['commission_overrides' => ['pickup' => '6']])->save();
        $this->assertSame('6000.00', $this->checkout($this->customer, 2, 'cash', 'pickup')->fresh()->commission_amount);

        // A v2 plan has no commission of its own: the channel rules (here the flat rate) stay
        app(\App\Services\Finance\SubscriptionService::class)->assignPlan($this->store, \App\Models\TariffPlan::where('code', 'start')->sole());
        $this->assertSame('12000.00', $this->checkout($this->customer, 2, 'cash', 'delivery')->fresh()->commission_amount);
    }
}
