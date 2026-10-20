<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Store;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Enums\BranchStatus;

class OrderRestrictionsTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;
    protected User $customer;
    protected Branch $branch;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create([
            'name' => 'Pizza House',
            'slug' => 'pizza-house',
            'status' => 'active',
            'is_open' => true,
            'open_time' => '00:00:00',
            'close_time' => '00:00:00',
            'payment_settings' => [
                'cash' => ['enabled' => true],
                'delivery_enabled' => true,
                'pickup_enabled' => true,
                'delivery_fee' => 10000,
            ]
        ]);

        $this->branch = Branch::create([
            'store_id' => $this->store->id,
            'name' => 'Main Branch',
            'latitude' => 41.311081,
            'longitude' => 69.240562,
            'radius_km' => 5,
            'status' => BranchStatus::ACTIVE->value,
        ]);

        $this->customer = User::create([
            'name' => 'Alex',
            'phone' => '+998901234567',
            'telegram_id' => '123456',
            'password' => bcrypt('password'),
        ]);

        $category = \App\Models\Category::create([
            'name' => 'Pizza',
            'slug' => 'pizza',
            'is_active' => true,
        ]);

        $product = Product::create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'name' => 'Margherita Pizza',
            'slug' => 'margherita-pizza',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Standard',
            'price' => 50000,
            'is_active' => true,
            'stock' => 10,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    }

    public function test_cannot_order_delivery_if_disabled_for_store(): void
    {
        $this->store->update([
            'payment_settings' => array_merge($this->store->payment_settings, ['delivery_enabled' => false])
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'store_id' => $this->store->id,
                'type' => 'delivery',
                'payment_method' => 'cash',
                'address' => 'Tashkent',
                'latitude' => 41.311081,
                'longitude' => 69.240562,
                'items' => [
                    [
                        'variant_id' => $this->variant->uuid,
                        'quantity' => 1
                    ]
                ]
            ]);

        $response->assertStatus(400);
        $this->assertStringContainsString("yetkazib berish", $response->json('message'));
    }

    public function test_cannot_order_pickup_if_disabled_for_store(): void
    {
        $this->store->update([
            'payment_settings' => array_merge($this->store->payment_settings, ['pickup_enabled' => false])
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'store_id' => $this->store->id,
                'type' => 'pickup',
                'branch_id' => $this->branch->id,
                'payment_method' => 'cash',
                'items' => [
                    [
                        'variant_id' => $this->variant->uuid,
                        'quantity' => 1
                    ]
                ]
            ]);

        $response->assertStatus(400);
        $this->assertStringContainsString("olib ketish", $response->json('message'));
    }

    protected function createFailedCashPickup(string $status, array $historyStatuses, ?string $reason, ?\DateTimeInterface $createdAt = null, array $attributes = []): Order
    {
        $order = Order::create(array_merge([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->customer->id,
            'status' => $status,
            'order_type' => 'pickup',
            'payment_method' => 'cash',
            'total_price' => 50000,
            'commission_amount' => 5000,
            'cancel_reason' => $reason,
        ], $attributes));

        foreach ($historyStatuses as $historyStatus) {
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $historyStatus,
            ]);
        }

        if ($createdAt) {
            $order->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        return $order;
    }

    protected function postCashPickupOrder()
    {
        return $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'store_id' => $this->store->id,
                'type' => 'pickup',
                'branch_id' => $this->branch->id,
                'payment_method' => 'cash',
                'items' => [
                    [
                        'variant_id' => $this->variant->uuid,
                        'quantity' => 1
                    ]
                ]
            ]);
    }

    public function test_cannot_use_cash_after_not_collecting_a_pickup_order(): void
    {
        // Accepted and prepared by the store, then cancelled because the customer never came
        $this->createFailedCashPickup('canceled', ['new', 'accepted', 'ready', 'canceled'], 'no_show');

        $response = $this->postCashPickupOrder();

        $response->assertStatus(400);
        $this->assertStringContainsString("bekor qilish", $response->json('message'));
    }

    public function test_customer_cancelling_a_new_order_does_not_ban_cash(): void
    {
        $this->createFailedCashPickup('canceled', ['new', 'canceled'], 'customer_request', null, ['cancelled_by' => $this->customer->id]);

        $this->postCashPickupOrder()->assertStatus(201);
    }

    public function test_store_side_reasons_do_not_ban_cash(): void
    {
        $this->createFailedCashPickup('rejected', ['new', 'rejected'], 'out_of_stock');
        $this->createFailedCashPickup('canceled', ['new', 'canceled'], 'store_closed');
        $this->createFailedCashPickup('canceled', ['new', 'accepted', 'ready', 'canceled'], 'out_of_stock');
        $this->createFailedCashPickup('canceled', ['new', 'accepted', 'canceled'], 'other');
        $this->createFailedCashPickup('canceled', ['new', 'accepted', 'canceled'], 'customer_request');

        $this->postCashPickupOrder()->assertStatus(201);
    }

    public function test_cancellations_from_before_reasons_were_recorded_do_not_ban_cash(): void
    {
        // Not backfilled: a null reason never counts, whoever cancelled and however far the order got
        $this->createFailedCashPickup('canceled', ['new', 'accepted', 'ready', 'canceled'], null);
        $this->createFailedCashPickup('canceled', ['new', 'canceled'], null);

        $this->postCashPickupOrder()->assertStatus(201);
    }

    public function test_only_cash_pickup_no_shows_count(): void
    {
        $this->createFailedCashPickup('canceled', ['new', 'accepted', 'canceled'], 'no_show', null, ['order_type' => 'delivery']);
        $this->createFailedCashPickup('canceled', ['new', 'accepted', 'canceled'], 'no_show', null, ['payment_method' => 'click']);

        $this->postCashPickupOrder()->assertStatus(201);
    }

    public function test_cancellation_older_than_window_does_not_ban_cash(): void
    {
        $this->createFailedCashPickup('canceled', ['new', 'accepted', 'ready', 'canceled'], 'no_show', now()->subDays(31));

        $this->postCashPickupOrder()->assertStatus(201);
    }

    public function test_cash_ban_threshold_is_configurable(): void
    {
        config(['orders.cash_pickup_ban.threshold' => 2]);
        $this->createFailedCashPickup('canceled', ['new', 'accepted', 'ready', 'canceled'], 'no_show');

        $this->postCashPickupOrder()->assertStatus(201);

        $this->createFailedCashPickup('canceled', ['new', 'accepted', 'ready', 'canceled'], 'no_show');

        $this->postCashPickupOrder()->assertStatus(400);

        config(['orders.cash_pickup_ban.threshold' => 0]);
        $this->postCashPickupOrder()->assertStatus(201);
    }

    public function test_can_order_with_cash_if_no_failed_pickup_orders(): void
    {
        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/v1/orders', [
                'store_id' => $this->store->id,
                'type' => 'pickup',
                'branch_id' => $this->branch->id,
                'payment_method' => 'cash',
                'items' => [
                    [
                        'variant_id' => $this->variant->uuid,
                        'quantity' => 1
                    ]
                ]
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
    }
}
