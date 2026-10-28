<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-24 (found by the stores agent): suspending a store only hid it from the catalog, so a stale cart or a direct
 * request still placed Mini App orders. CreateOrderAction refuses suspended, rejected and switched-off stores.
 */
class OrderStoreStatusTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    private function place(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $this->store->id,
            'type' => 'pickup',
            'branch_id' => $this->branch->id,
            'payment_method' => 'cash',
            'items' => [['variant_id' => $this->variant->uuid, 'quantity' => 1]],
        ]);
    }

    public function test_an_active_store_takes_orders(): void
    {
        $this->place()->assertStatus(201);
        $this->assertSame(1, Order::where('store_id', $this->store->id)->count());
    }

    public function test_a_suspended_store_takes_no_orders(): void
    {
        $this->store->forceFill(['status' => StoreStatus::SUSPENDED->value, 'is_active' => false])->save();

        $this->place()->assertStatus(400);
        $this->assertSame(0, Order::where('store_id', $this->store->id)->count());
        $this->assertSame(10, $this->variant->refresh()->stock);

        // Back to active: orders flow again
        $this->store->forceFill(['status' => StoreStatus::ACTIVE->value, 'is_active' => true])->save();
        $this->place()->assertStatus(201);
    }

    public function test_a_rejected_or_switched_off_store_takes_no_orders(): void
    {
        $this->store->forceFill(['status' => StoreStatus::REJECTED->value])->save();
        $this->place()->assertStatus(400)->assertJsonPath('message', 'Restoran hozircha buyurtma qabul qilmayapti.');

        $this->store->forceFill(['status' => StoreStatus::ACTIVE->value, 'is_active' => false])->save();
        $this->place()->assertStatus(400);

        $this->assertSame(0, Order::where('store_id', $this->store->id)->count());
    }

    public function test_the_action_itself_refuses_a_suspended_store(): void
    {
        $this->store->forceFill(['status' => StoreStatus::SUSPENDED->value, 'is_active' => false])->save();

        $this->expectExceptionMessage('Restoran hozircha buyurtma qabul qilmayapti.');
        $this->checkout($this->customer, 1);
    }
}
