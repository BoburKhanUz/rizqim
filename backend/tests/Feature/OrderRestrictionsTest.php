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

    protected function createFailedCashPickup(string $status, array $historyStatuses, ?int $cancelledBy = null, ?\DateTimeInterface $createdAt = null): Order
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->customer->id,
            'status' => $status,
            'order_type' => 'pickup',
            'payment_method' => 'cash',
            'total_price' => 50000,
            'commission_amount' => 5000,
        ]);

        foreach ($historyStatuses as $historyStatus) {
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => in_array($historyStatus, ['canceled', 'rejected']) ? $cancelledBy : null,
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

    public function test_cannot_use_cash_if_blocked_by_past_failed_pickup_orders(): void
    {
        // Accepted and prepared by the store, then cancelled because it was never collected
        $this->createFailedCashPickup('canceled', ['new', 'accepted', 'ready', 'canceled']);

        $response = $this->postCashPickupOrder();

        $response->assertStatus(400);
        $this->assertStringContainsString("bekor qilish", $response->json('message'));
    }

    public function test_cannot_use_cash_after_cancelling_own_pickup_order(): void
    {
        $this->createFailedCashPickup('canceled', ['new', 'canceled'], $this->customer->id);

        $response = $this->postCashPickupOrder();

        $response->assertStatus(400);
        $this->assertStringContainsString("bekor qilish", $response->json('message'));
    }

    public function test_store_rejected_pickup_order_does_not_ban_cash(): void
    {
        $staff = User::create([
            'name' => 'Manager',
            'phone' => '+998907654321',
            'password' => bcrypt('password'),
            'store_id' => $this->store->id,
            'role' => 'manager',
        ]);

        $this->createFailedCashPickup('rejected', ['new', 'rejected'], $staff->id);
        // Telegram "Rad etish" button records a store refusal as `canceled` while the order is still new
        $this->createFailedCashPickup('canceled', ['new', 'canceled'], $staff->id);

        $this->postCashPickupOrder()->assertStatus(201);
    }

    public function test_cancellation_older_than_window_does_not_ban_cash(): void
    {
        $this->createFailedCashPickup('canceled', ['new', 'accepted', 'ready', 'canceled'], null, now()->subDays(31));

        $this->postCashPickupOrder()->assertStatus(201);
    }

    public function test_cash_ban_threshold_is_configurable(): void
    {
        config(['orders.cash_pickup_ban.threshold' => 2]);
        $this->createFailedCashPickup('canceled', ['new', 'accepted', 'ready', 'canceled']);

        $this->postCashPickupOrder()->assertStatus(201);

        $this->createFailedCashPickup('canceled', ['new', 'accepted', 'ready', 'canceled']);

        $this->postCashPickupOrder()->assertStatus(400);
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
