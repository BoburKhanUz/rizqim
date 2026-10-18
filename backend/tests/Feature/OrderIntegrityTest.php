<?php

namespace Tests\Feature;

use App\Enums\BranchStatus;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PromoCode;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;
    protected Branch $branch;
    protected User $customer;
    protected Product $product;
    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::forceCreate([
            'name' => 'Integrity Burger',
            'slug' => 'integrity-burger',
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

        $this->customer = $this->makeCustomer('+998900000001');

        $category = Category::create(['name' => 'Burgers', 'slug' => 'burgers', 'is_active' => true]);

        $this->product = Product::create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
            'name' => 'Cheeseburger',
            'slug' => 'cheeseburger',
            'status' => 'active',
        ]);

        $this->variant = $this->makeVariant(50000, 10);
    }

    protected function makeCustomer(string $phone, float $balance = 0): User
    {
        $user = User::create([
            'name' => 'Customer ' . $phone,
            'phone' => $phone,
            'password' => bcrypt('password'),
        ]);

        if ($balance > 0) {
            DB::table('users')->where('id', $user->id)->update(['balance' => $balance]);
            $user->refresh();
        }

        return $user;
    }

    protected function makeVariant(float $price, int $stock): ProductVariant
    {
        return ProductVariant::create([
            'product_id' => $this->product->id,
            'name' => 'Size ' . Str::random(4),
            'price' => $price,
            'stock' => $stock,
            'is_active' => true,
            'uuid' => (string) Str::uuid(),
        ]);
    }

    protected function placeOrder(array $overrides = [], ?User $user = null)
    {
        $payload = array_merge([
            'store_id' => $this->store->id,
            'type' => 'pickup',
            'branch_id' => $this->branch->id,
            'payment_method' => 'cash',
            'items' => [['variant_id' => $this->variant->uuid, 'quantity' => 1]],
        ], $overrides);

        return $this->actingAs($user ?? $this->customer, 'sanctum')->postJson('/api/v1/orders', $payload);
    }

    protected function deliveryOverrides(): array
    {
        return [
            'type' => 'delivery',
            'branch_id' => null,
            'address' => 'Amir Temur 1',
            'latitude' => 41.311081,
            'longitude' => 69.240562,
        ];
    }

    public function test_client_sent_discount_is_ignored(): void
    {
        $response = $this->placeOrder(['discount' => 999999999]);

        $response->assertStatus(201);
        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        $this->assertEquals(50000, (float) $order->total_price);
        $this->assertEquals(0, (float) $order->discount);
        $this->assertNull($order->promo_code_id);
    }

    public function test_second_order_for_last_unit_gets_422_and_stock_stays_zero(): void
    {
        $variant = $this->makeVariant(30000, 1);
        $items = [['variant_id' => $variant->uuid, 'quantity' => 1]];

        $this->placeOrder(['items' => $items])->assertStatus(201);

        $other = $this->makeCustomer('+998900000002');
        $this->placeOrder(['items' => $items], $other)
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $this->assertSame(0, $variant->fresh()->stock);
        $this->assertSame(1, Order::count());
    }

    public function test_stock_decrement_is_conditional_when_stock_changes_after_it_was_read(): void
    {
        $variant = $this->makeVariant(30000, 1);

        // Simulates a concurrent buyer taking the last unit right after this request read the row.
        $sold = false;
        ProductVariant::retrieved(function (ProductVariant $retrieved) use ($variant, &$sold) {
            if (!$sold && $retrieved->id === $variant->id) {
                $sold = true;
                DB::table('product_variants')->where('id', $variant->id)->update(['stock' => 0]);
            }
        });

        $this->placeOrder(['items' => [['variant_id' => $variant->uuid, 'quantity' => 1]]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');

        $this->assertTrue($sold);
        $this->assertGreaterThanOrEqual(0, $variant->fresh()->stock);
        $this->assertSame(0, Order::count());
    }

    public function test_same_variant_twice_is_aggregated_before_stock_check(): void
    {
        $variant = $this->makeVariant(20000, 3);
        $items = [
            ['variant_id' => $variant->uuid, 'quantity' => 2],
            ['variant_id' => $variant->uuid, 'quantity' => 2],
        ];

        $this->placeOrder(['items' => $items])->assertStatus(422)->assertJsonValidationErrors('items');
        $this->assertSame(3, $variant->fresh()->stock);

        $variant->update(['stock' => 4]);
        $response = $this->placeOrder(['items' => $items])->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        $this->assertEquals(80000, (float) $order->subtotal);
        $this->assertSame(4, (int) $order->items()->sum('quantity'));
        $this->assertSame(0, $variant->fresh()->stock);
    }

    public function test_multi_item_delivery_order_totals_and_stock(): void
    {
        $second = $this->makeVariant(15000, 5);

        $response = $this->placeOrder(array_merge($this->deliveryOverrides(), [
            'items' => [
                ['variant_id' => $this->variant->uuid, 'quantity' => 2],
                ['variant_id' => strtoupper($second->uuid), 'quantity' => 1],
            ],
        ]))->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        $this->assertEquals(115000, (float) $order->subtotal);
        $this->assertEquals(10000, (float) $order->delivery_price);
        $this->assertEquals(125000, (float) $order->total_price);
        $this->assertSame(8, $this->variant->fresh()->stock);
        $this->assertSame(4, $second->fresh()->stock);
    }

    public function test_keshbek_payment_deducts_balance_once_and_rejects_when_insufficient(): void
    {
        $customer = $this->makeCustomer('+998900000003', 60000);

        $this->placeOrder(['payment_method' => 'keshbek'], $customer)->assertStatus(201);
        $this->assertEquals(10000, (float) $customer->fresh()->balance);

        $this->placeOrder(['payment_method' => 'keshbek'], $customer)->assertStatus(400);
        $this->assertEquals(10000, (float) $customer->fresh()->balance);
        $this->assertSame(1, Order::where('user_id', $customer->id)->count());
    }

    public function test_keshbek_balance_never_goes_negative_with_stale_user_instance(): void
    {
        $customer = $this->makeCustomer('+998900000004', 60000);

        // Another order spent most of the balance after this request's user was loaded.
        DB::table('users')->where('id', $customer->id)->update(['balance' => 10000]);

        $this->placeOrder(['payment_method' => 'keshbek'], $customer)->assertStatus(400);

        $this->assertEquals(10000, (float) DB::table('users')->where('id', $customer->id)->value('balance'));
        $this->assertSame(0, Order::count());
    }

    public function test_commission_amount_equals_settlement_formula_after_discount(): void
    {
        PromoCode::create([
            'code' => 'SALE10',
            'type' => 'percentage',
            'value' => 10,
            'min_order_amount' => 0,
            'is_active' => true,
        ]);

        $response = $this->placeOrder(array_merge($this->deliveryOverrides(), [
            'promo_code' => 'sale10',
            'items' => [['variant_id' => $this->variant->uuid, 'quantity' => 2]],
        ]))->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        $this->assertEquals(100000, (float) $order->subtotal);
        $this->assertEquals(10000, (float) $order->discount);
        $this->assertEquals(100000, (float) $order->total_price);
        $this->assertEquals(10000, (float) $order->commission_amount);
        $this->assertEquals(round((float) $order->total_price * 10 / 100, 2), (float) $order->commission_amount);
    }

    public function test_commission_is_rounded_to_column_precision(): void
    {
        $this->store->forceFill(['commission_rate' => 12.5])->save();
        $variant = $this->makeVariant(33333, 5);

        $response = $this->placeOrder(['items' => [['variant_id' => $variant->uuid, 'quantity' => 1]]])->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        $this->assertEquals('4166.63', $order->getRawOriginal('commission_amount'));
    }

    public function test_store_on_finance_hold_cannot_receive_orders(): void
    {
        $this->store->forceFill(['status' => 'finance_hold'])->save();

        $this->placeOrder()->assertStatus(400);

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $this->variant->fresh()->stock);
    }

    public function test_inactive_variant_cannot_be_ordered(): void
    {
        $this->variant->update(['is_active' => false]);

        $this->placeOrder()->assertStatus(400);

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $this->variant->fresh()->stock);
    }

    public function test_online_payment_order_is_still_accepted(): void
    {
        $this->store->forceFill([
            'payment_settings' => array_merge($this->store->payment_settings, ['click' => ['enabled' => true]]),
        ])->save();

        $response = $this->placeOrder(array_merge($this->deliveryOverrides(), ['payment_method' => 'click']))->assertStatus(201);

        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        $this->assertSame('click', $order->payment_method);
        $this->assertEquals(60000, (float) $order->total_price);
        $this->assertEquals(6000, (float) $order->commission_amount);
    }

    public function test_unknown_payment_method_is_rejected(): void
    {
        $this->placeOrder(['payment_method' => 'naqd'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        $this->assertSame(0, Order::count());
    }
}
