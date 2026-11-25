<?php

namespace Tests\Feature\Tenancy;

use App\Enums\OrderStatus;
use App\Enums\StoreTransactionType;
use App\Jobs\SendTelegramRequest;
use App\Models\Order;
use App\Models\StoreTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\AccessFixtures;
use Tests\TestCase;

/**
 * M4 stage 2: reads a tenant request makes across stores on purpose (`withoutGlobalScope(StoreScope::class)`), each
 * shown to still see the other store while the request is bound to its own.
 */
class CrossStoreReadsTest extends TestCase
{
    use AccessFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake([SendTelegramRequest::class]);
        $this->setUpLifecycleStore();
        $this->customer->forceFill(['referred_by' => $this->makeCustomer()->id])->save();
    }

    private function deliverByCourier(): Order
    {
        // Owner decision 3: paid in a way the platform verified (Paylov's read-back), so it earns the referral bonus
        $order = $this->payThroughPaylov($this->makeOrder(OrderStatus::ON_THE_WAY, ['courier_id' => $this->courier->id]));
        $this->bearerJson('POST', "/api/v1/orders/{$order->uuid}/complete", $this->courier->createToken('t')->plainTextToken)->assertOk();

        return $order;
    }

    private function referralRewards(Order $order): int
    {
        return StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::COMMISSION->value)
            ->where('notes', 'like', 'Referral%')->count();
    }

    public function test_the_referral_reward_follows_the_customers_first_delivery_at_any_store(): void
    {
        // The customer's first order was delivered at another store: this one, at the courier's store, is not the first
        $other = $this->makeStore('Boshqa');
        $this->payThroughPaylov($this->makeOrder(OrderStatus::DELIVERED, ['store_id' => $other->id, 'branch_id' => $this->makeBranch($other, 'Boshqa filial')->id]));

        $this->assertSame(0, $this->referralRewards($this->deliverByCourier()));
    }

    public function test_control_the_first_delivery_is_rewarded(): void
    {
        $this->assertSame(1, $this->referralRewards($this->deliverByCourier()));
    }
}
