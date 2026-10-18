<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Store;
use App\Models\User;
use App\Models\Order;
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

    public function test_cannot_use_cash_if_blocked_by_past_failed_pickup_orders(): void
    {
        // Create a failed cash pickup order for the customer
        Order::create([
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->customer->id,
            'status' => 'canceled',
            'order_type' => 'pickup',
            'payment_method' => 'cash',
            'total_price' => 50000,
            'commission_amount' => 5000,
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
        $this->assertStringContainsString("bekor qilish", $response->json('message'));
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
