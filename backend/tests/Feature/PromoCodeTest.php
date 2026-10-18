<?php

namespace Tests\Feature;

use App\Enums\BranchStatus;
use App\Events\OrderCreated;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PromoCode;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class PromoCodeTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;
    protected Branch $branch;
    protected User $customer;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::forceCreate([
            'name' => 'Promo Pizza',
            'slug' => 'promo-pizza',
            'status' => 'active',
            'is_open' => true,
            'open_time' => '00:00:00',
            'close_time' => '00:00:00',
            'commission_rate' => 10,
            'payment_settings' => [
                'cash' => ['enabled' => true],
                'delivery_enabled' => true,
                'pickup_enabled' => true,
                'delivery_fee' => 10000,
            ],
        ]);

        $this->branch = Branch::create([
            'store_id' => $this->store->id,
            'name' => 'Main Branch',
            'latitude' => 41.311081,
            'longitude' => 69.240562,
            'radius_km' => 5,
            'status' => BranchStatus::ACTIVE->value,
        ]);

        $this->customer = $this->makeCustomer('+998910000001');

        $category = Category::create(['name' => 'Pizza', 'slug' => 'pizza', 'is_active' => true]);

        $product = Product::create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'name' => 'Margherita',
            'slug' => 'margherita',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Large',
            'price' => 50000,
            'stock' => 100,
            'is_active' => true,
            'uuid' => (string) Str::uuid(),
        ]);
    }

    protected function makeCustomer(string $phone): User
    {
        return User::create([
            'name' => 'Customer ' . $phone,
            'phone' => $phone,
            'password' => bcrypt('password'),
        ]);
    }

    protected function makePromo(array $attributes = []): PromoCode
    {
        return PromoCode::create(array_merge([
            'code' => 'RIZQ10',
            'type' => 'percentage',
            'value' => 10,
            'min_order_amount' => 0,
            'is_active' => true,
        ], $attributes));
    }

    protected function placeOrder(array $overrides = [], ?User $user = null)
    {
        $payload = array_merge([
            'store_id' => $this->store->id,
            'type' => 'pickup',
            'branch_id' => $this->branch->id,
            'payment_method' => 'cash',
            'items' => [['variant_id' => $this->variant->uuid, 'quantity' => 2]],
        ], $overrides);

        return $this->actingAs($user ?? $this->customer, 'sanctum')->postJson('/api/v1/orders', $payload);
    }

    public function test_order_with_promo_code_gets_server_side_discount(): void
    {
        Event::fake([OrderCreated::class]);
        $promo = $this->makePromo();

        $response = $this->placeOrder(['promo_code' => ' rizq10 ', 'discount' => 999999])->assertStatus(201);

        Event::assertDispatched(OrderCreated::class);

        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        $this->assertEquals(100000, (float) $order->subtotal);
        $this->assertEquals(10000, (float) $order->discount);
        $this->assertEquals(90000, (float) $order->total_price);
        $this->assertSame($promo->id, $order->promo_code_id);
        $this->assertEquals(90000, (float) $order->payments()->first()->amount);
    }

    public function test_percentage_discount_is_rounded_to_whole_sum_and_capped_by_max(): void
    {
        $this->variant->update(['price' => 33333]);
        $this->makePromo();
        $this->makePromo(['code' => 'CAP5K', 'max_discount_amount' => 5000]);

        $first = $this->placeOrder(['promo_code' => 'RIZQ10', 'items' => [['variant_id' => $this->variant->uuid, 'quantity' => 1]]])->assertStatus(201);
        $order = Order::where('uuid', $first->json('data.order_uuid'))->firstOrFail();
        $this->assertEquals(3333, (float) $order->discount);
        $this->assertEquals(30000, (float) $order->total_price);

        $second = $this->placeOrder(['promo_code' => 'CAP5K', 'items' => [['variant_id' => $this->variant->uuid, 'quantity' => 3]]])->assertStatus(201);
        $order = Order::where('uuid', $second->json('data.order_uuid'))->firstOrFail();
        $this->assertEquals(5000, (float) $order->discount);
        $this->assertEquals(94999, (float) $order->total_price);
    }

    public function test_fixed_discount_is_capped_at_items_subtotal_and_keeps_delivery_fee(): void
    {
        $this->makePromo(['code' => 'BIGFIX', 'type' => 'fixed', 'value' => 500000]);

        $response = $this->placeOrder([
            'promo_code' => 'BIGFIX',
            'type' => 'delivery',
            'branch_id' => null,
            'address' => 'Amir Temur 1',
            'latitude' => 41.311081,
            'longitude' => 69.240562,
        ])->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        $this->assertEquals(100000, (float) $order->discount);
        $this->assertEquals(10000, (float) $order->total_price);
        $this->assertEquals(1000, (float) $order->commission_amount);
    }

    public function test_unknown_promo_code_returns_422_with_promo_code_error_and_no_side_effects(): void
    {
        Event::fake([OrderCreated::class]);

        $response = $this->placeOrder(['promo_code' => 'NOPE'])->assertStatus(422);

        Event::assertNotDispatched(OrderCreated::class);

        $response->assertJsonValidationErrors('promo_code')
            ->assertJsonPath('message', 'Bunday promo-kod mavjud emas yoki muddati tugagan')
            ->assertJsonPath('errors.promo_code.0', 'Bunday promo-kod mavjud emas yoki muddati tugagan');
        $this->assertSame(0, Order::count());
        $this->assertSame(100, $this->variant->fresh()->stock);
    }

    public function test_inactive_expired_foreign_store_and_min_amount_promos_are_rejected_at_order_time(): void
    {
        $otherStore = Store::forceCreate(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);

        $this->makePromo(['code' => 'OFF', 'is_active' => false]);
        $this->makePromo(['code' => 'OLD', 'expires_at' => now()->subDay()]);
        $this->makePromo(['code' => 'OTHER', 'store_id' => $otherStore->id]);
        $this->makePromo(['code' => 'BIGCART', 'min_order_amount' => 150000]);

        foreach (['OFF', 'OLD', 'OTHER', 'BIGCART'] as $code) {
            $this->placeOrder(['promo_code' => $code])
                ->assertStatus(422)
                ->assertJsonValidationErrors('promo_code');
        }

        $this->assertSame(0, Order::count());
    }

    public function test_per_user_limit_blocks_second_use_but_not_other_users(): void
    {
        $this->makePromo(['per_user_limit' => 1]);

        $this->placeOrder(['promo_code' => 'RIZQ10'])->assertStatus(201);
        $this->placeOrder(['promo_code' => 'RIZQ10'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('promo_code');

        $this->placeOrder(['promo_code' => 'RIZQ10'], $this->makeCustomer('+998910000002'))->assertStatus(201);

        // Ordering without the code still works for the first customer.
        $this->placeOrder()->assertStatus(201);
    }

    public function test_usage_limit_is_enforced_across_users(): void
    {
        $this->makePromo(['usage_limit' => 2]);

        $this->placeOrder(['promo_code' => 'RIZQ10'])->assertStatus(201);
        $this->placeOrder(['promo_code' => 'RIZQ10'], $this->makeCustomer('+998910000003'))->assertStatus(201);
        $this->placeOrder(['promo_code' => 'RIZQ10'], $this->makeCustomer('+998910000004'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('promo_code');
    }

    public function test_canceled_order_releases_promo_usage(): void
    {
        $this->makePromo(['per_user_limit' => 1, 'usage_limit' => 1]);

        $first = $this->placeOrder(['promo_code' => 'RIZQ10'])->assertStatus(201);
        Order::where('uuid', $first->json('data.order_uuid'))->update(['status' => 'canceled']);

        $this->placeOrder(['promo_code' => 'RIZQ10'])->assertStatus(201);
    }

    public function test_null_limits_keep_unlimited_reuse(): void
    {
        $this->makePromo();

        $this->placeOrder(['promo_code' => 'RIZQ10'])->assertStatus(201);
        $this->placeOrder(['promo_code' => 'RIZQ10'])->assertStatus(201);
    }

    public function test_validate_endpoint_keeps_shape_and_returns_server_discount(): void
    {
        $this->makePromo(['max_discount_amount' => 5000]);

        $response = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/promo/validate', [
            'code' => 'rizq10',
            'store_id' => $this->store->id,
            'subtotal' => 100000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'RIZQ10')
            ->assertJsonPath('data.type', 'percentage')
            ->assertJsonStructure(['data' => ['id', 'code', 'type', 'amount', 'discount_amount', 'description']]);
        $this->assertEquals(5000, $response->json('data.discount_amount'));
    }

    public function test_validate_endpoint_reports_exhausted_per_user_limit(): void
    {
        $this->makePromo(['per_user_limit' => 1]);
        $this->placeOrder(['promo_code' => 'RIZQ10'])->assertStatus(201);

        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/promo/validate', [
            'code' => 'RIZQ10',
            'store_id' => $this->store->id,
            'subtotal' => 100000,
        ])->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_validate_endpoint_has_no_side_effects(): void
    {
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/promo/validate', [
            'code' => 'TEZFOOD',
            'store_id' => $this->store->id,
            'subtotal' => 100000,
        ])->assertStatus(404);

        $this->assertSame(0, PromoCode::count());
    }
}
