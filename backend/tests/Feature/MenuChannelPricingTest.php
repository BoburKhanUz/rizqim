<?php

namespace Tests\Feature;

use App\Enums\OrderChannel;
use App\Models\AuditLog;
use App\Models\ChannelPrice;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P1-21 channels: orders record their channel (Mini App: telegram), the catalog and checkout use the channel's price
 * and visibility, the owner manages channel prices, analytics break revenue down by channel.
 */
class MenuChannelPricingTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use MenuFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    protected function setPrices(array $prices): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/products/{$this->product->id}/channel-prices", ['prices' => $prices]);
    }

    public function test_mini_app_orders_are_telegram_orders_and_old_rows_were_backfilled(): void
    {
        $response = $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertCreated();
        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        $this->assertSame(OrderChannel::TELEGRAM, $order->channel);
        $this->assertNull($order->external_id);

        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]], ['channel' => 'mobile'])->assertCreated();
        $this->assertSame(1, Order::where('channel', 'mobile')->count());

        // A customer cannot claim to be the POS or an aggregator
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]], ['channel' => 'pos_hall'])->assertStatus(422);

        // The column default fills rows written without it (what the migration did for existing orders)
        $id = DB::table('orders')->insertGetId([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'store_id' => $this->store->id, 'user_id' => $this->customer->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame('telegram', DB::table('orders')->where('id', $id)->value('channel'));
    }

    public function test_external_ids_are_unique_per_store_and_channel(): void
    {
        $this->makeOrder(\App\Enums\OrderStatus::NEW, ['channel' => 'aggregator', 'external_id' => 'YA-1']);
        $this->makeOrder(\App\Enums\OrderStatus::NEW, ['channel' => 'pos_takeaway', 'external_id' => 'YA-1']);
        $this->makeOrder(\App\Enums\OrderStatus::NEW, ['channel' => 'aggregator']);
        $this->makeOrder(\App\Enums\OrderStatus::NEW, ['channel' => 'aggregator']);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->makeOrder(\App\Enums\OrderStatus::NEW, ['channel' => 'aggregator', 'external_id' => 'YA-1']);
    }

    public function test_the_telegram_price_is_shown_and_charged(): void
    {
        $this->setPrices([
            ['channel' => 'telegram', 'product_variant_id' => $this->variant->id, 'price' => 47000],
            ['channel' => 'pos_hall', 'product_variant_id' => $this->variant->id, 'price' => 52000],
        ])->assertOk()->assertJsonCount(2, 'data.prices');

        $variant = collect($this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$this->store->uuid}")
            ->assertOk()->json('data.products'))->firstWhere('id', $this->product->id)['variants'][0];
        $this->assertSame('47000.00', $variant['price']);
        $this->assertSame('50000.00', $variant['base_price']);

        $mobile = collect($this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$this->store->uuid}&channel=mobile")
            ->json('data.products'))->firstWhere('id', $this->product->id)['variants'][0];
        $this->assertSame('50000.00', $mobile['price']);

        $response = $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 2]])->assertCreated();
        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        $this->assertSame('94000.00', $order->getRawOriginal('total_price'));
        $this->assertSame('47000.00', $order->items()->first()->getRawOriginal('unit_price'));

        $response = $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]], ['channel' => 'mobile'])->assertCreated();
        $this->assertSame('50000.00', Order::where('uuid', $response->json('data.order_uuid'))->value('total_price'));
    }

    public function test_hidden_products_and_variants_are_left_out_and_refused(): void
    {
        $second = $this->makeVariant($this->product, '60000', ['name' => 'Katta']);
        $this->setPrices([['channel' => 'telegram', 'product_variant_id' => $second->id, 'is_hidden' => true]])->assertOk();

        $catalog = fn (string $query = '') => collect($this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$this->store->uuid}{$query}")
            ->assertOk()->json('data.products'))->firstWhere('id', $this->product->id);

        $this->assertSame(['Regular'], array_column($catalog()['variants'], 'name'));
        $this->assertSame(['Regular', 'Katta'], array_column($catalog('&channel=mobile')['variants'], 'name'));
        $response = $this->order([['variant_id' => $second->uuid, 'quantity' => 1]])->assertStatus(422);
        $this->assertSame("Uzr, 'Cheeseburger (Katta)' bu kanal orqali sotilmaydi. Iltimos, savatni yangilang.", $response->json('errors.items.0'));
        $this->order([['variant_id' => $second->uuid, 'quantity' => 1]], ['channel' => 'mobile'])->assertCreated();

        // The whole product
        $this->setPrices([['channel' => 'telegram', 'is_hidden' => true]])->assertOk();
        $this->assertNull($catalog());
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertStatus(422);
        // The global feed too
        $feed = $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/catalog')->assertOk()->json('data.products');
        $this->assertNotContains($this->product->id, array_column($feed, 'id'));
    }

    public function test_the_channel_price_endpoints_validate_and_stay_in_the_store(): void
    {
        $otherStore = $this->makeStore('Other Burger');
        $otherOwner = $this->makeStaff(\App\Enums\UserRole::OWNER, null, $otherStore);
        $otherProduct = \App\Models\Product::create(['store_id' => $otherStore->id, 'category_id' => $this->product->category_id, 'name' => 'O', 'slug' => 'o-' . uniqid(), 'status' => 'active']);
        $foreignVariant = $this->makeVariant($otherProduct, '1000');

        $this->setPrices([['channel' => 'telegram', 'product_variant_id' => $foreignVariant->id, 'price' => 1]])->assertStatus(422);
        $this->setPrices([['channel' => 'telegram', 'price' => 1]])->assertStatus(422);
        $this->setPrices([['channel' => 'yandex', 'product_variant_id' => $this->variant->id, 'price' => 1]])->assertStatus(422);
        $this->setPrices([
            ['channel' => 'telegram', 'product_variant_id' => $this->variant->id, 'price' => 1],
            ['channel' => 'telegram', 'product_variant_id' => $this->variant->id, 'price' => 2],
        ])->assertStatus(422);

        $this->actingAs($otherOwner, 'sanctum')->putJson("/api/v1/owner/products/{$this->product->id}/channel-prices", ['prices' => []])->assertNotFound();
        $this->actingAs($this->manager, 'sanctum')->putJson("/api/v1/owner/products/{$this->product->id}/channel-prices", ['prices' => []])->assertForbidden();
        $this->actingAs($this->manager, 'sanctum')->getJson("/api/v1/owner/products/{$this->product->id}/channel-prices")->assertOk();

        $this->setPrices([['channel' => 'telegram', 'product_variant_id' => $this->variant->id, 'price' => 45000]])->assertOk();
        $this->setPrices([])->assertOk()->assertJsonCount(0, 'data.prices');
        $this->assertSame(0, ChannelPrice::count());
        $this->assertSame(2, AuditLog::where('action', 'product.channel_prices_changed')->count());
    }

    public function test_analytics_break_revenue_down_by_channel(): void
    {
        $this->makeOrder(\App\Enums\OrderStatus::DELIVERED, ['total_price' => 50000]);
        $this->makeOrder(\App\Enums\OrderStatus::DELIVERED, ['total_price' => 70000, 'channel' => 'pos_hall']);
        $this->makeOrder(\App\Enums\OrderStatus::DELIVERED, ['total_price' => 30000, 'channel' => 'pos_hall']);
        $this->makeOrder(\App\Enums\OrderStatus::CANCELED, ['total_price' => 99000, 'channel' => 'aggregator']);

        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/analytics?period=today')
            ->assertOk()
            ->assertJsonPath('data.by_channel', [
                ['channel' => 'pos_hall', 'orders' => 2, 'revenue' => 100000],
                ['channel' => 'telegram', 'orders' => 1, 'revenue' => 50000],
            ]);
    }
}
