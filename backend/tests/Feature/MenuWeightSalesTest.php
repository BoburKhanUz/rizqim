<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Notifications\TelegramNotificationService;
use App\Services\Order\OrderLineBuilder;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1-21 sold by weight: price per kg, fractional quantities in steps, exact money math rounded half-up to whole so'm
 * per line, and everything downstream (stock, cancellation, keshbek, cards, analytics) on decimal quantities.
 */
class MenuWeightSalesTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use MenuFixtures;

    protected \App\Models\Product $somsa;
    protected \App\Models\ProductVariant $kg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->somsa = $this->makeProduct('Mini somsa');
        $this->kg = $this->makeVariant($this->somsa, '33333.00', [
            'name' => 'kg',
            'unit' => 'kg',
            'quantity_step' => '0.1',
            'min_quantity' => '0.3',
            'stock' => 10,
        ]);
    }

    public function test_a_weighed_line_is_priced_per_kilo_and_rounded_half_up_to_whole_som(): void
    {
        $response = $this->order([['variant_id' => $this->kg->uuid, 'quantity' => 0.5]])->assertCreated();

        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        // 33 333 × 0.5 = 16 666.5 → 16 667
        $this->assertSame('16667.00', $order->getRawOriginal('subtotal'));
        $this->assertSame('16667.00', $order->getRawOriginal('total_price'));
        $this->assertSame('1666.70', $order->getRawOriginal('commission_amount'));

        $item = $order->items()->firstOrFail();
        $this->assertSame('0.500', $item->getRawOriginal('quantity'));
        $this->assertSame(0.5, $item->quantity);
        $this->assertSame('kg', $item->unit->value);
        $this->assertSame('33333.00', $item->getRawOriginal('unit_price'));
        $this->assertSame(9.5, $this->kg->fresh()->stock);
    }

    public function test_the_rounding_rule_is_exact_and_once_per_line(): void
    {
        $lines = app(OrderLineBuilder::class);
        $kg = \App\Enums\MenuUnit::KG;
        $piece = \App\Enums\MenuUnit::PIECE;

        // 12 345.67 × 0.333 = 4 111.10811 → 4 111 (no double rounding through tiyin)
        $this->assertSame('4111.00', $lines->lineTotal(Money::of('12345.67'), Quantity::of('0.333'), Money::zero(), $kg)->toString());
        // 0.5 so'm exactly rounds up
        $this->assertSame('2.00', $lines->lineTotal(Money::of('3'), Quantity::of('0.5'), Money::zero(), $kg)->toString());
        // 1.495 → 1 (rounded once from the exact value, not 1.50 → 2)
        $this->assertSame('1.00', $lines->lineTotal(Money::of('2.99'), Quantity::of('0.5'), Money::zero(), $kg)->toString());
        // Modifiers: once per weighed line, per piece otherwise
        $this->assertSame('43000.00', $lines->lineTotal(Money::of('80000'), Quantity::of('0.5'), Money::of('3000'), $kg)->toString());
        $this->assertSame('106000.00', $lines->lineTotal(Money::of('50000'), Quantity::of('2'), Money::of('3000'), $piece)->toString());
    }

    public function test_weighed_quantities_follow_the_step_and_the_minimum(): void
    {
        $errors = fn ($response) => $response->assertStatus(422)->json('errors');

        $this->assertSame(
            ["'Mini somsa (kg)' miqdori 0.1 kg qadam bilan bo'lishi kerak (masalan 0.5 yoki 1.2)."],
            $errors($this->order([['variant_id' => $this->kg->uuid, 'quantity' => 0.35]]))['items.0.quantity'],
        );
        $this->assertSame(
            ["'Mini somsa (kg)' kamida 0.3 kg buyurtma qilinadi."],
            $errors($this->order([['variant_id' => $this->kg->uuid, 'quantity' => 0.2]]))['items.0.quantity'],
        );
        // More than three decimals, zero, negative, text
        foreach ([0.1234, 0, -1, 'bir'] as $quantity) {
            $this->order([['variant_id' => $this->kg->uuid, 'quantity' => $quantity]])->assertStatus(422);
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $this->kg->fresh()->stock);
    }

    public function test_pieces_stay_whole(): void
    {
        $response = $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1.5]])->assertStatus(422);

        $this->assertSame(["'Cheeseburger (Regular)' dona bilan sotiladi: miqdor butun son bo'lishi kerak."], $response->json('errors')['items.0.quantity']);
        // "2.0" is a whole number of pieces
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => '2.0']])->assertCreated();
        $this->assertSame(8, $this->variant->fresh()->stock);
    }

    public function test_weighed_stock_is_checked_against_the_fraction_left(): void
    {
        $this->kg->update(['stock' => '0.4']);

        $response = $this->order([['variant_id' => $this->kg->uuid, 'quantity' => 0.5]])->assertStatus(422);
        $this->assertStringContainsString('(mavjud: 0.4 kg)', $response->json('errors.items.0'));

        $this->order([['variant_id' => $this->kg->uuid, 'quantity' => 0.4]])->assertCreated();
        $this->assertSame(0, $this->kg->fresh()->stock);
    }

    public function test_a_mixed_order_and_its_cancellation_end_to_end(): void
    {
        $customer = $this->makeCustomer(200000);

        $response = $this->order([
            ['variant_id' => $this->kg->uuid, 'quantity' => '1.2'],
            ['variant_id' => $this->variant->uuid, 'quantity' => 2],
        ], ['payment_method' => 'keshbek'], $customer)->assertCreated();

        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        // 33 333 × 1.2 = 39 999.6 → 40 000; + 2 × 50 000
        $this->assertSame('140000.00', $order->getRawOriginal('total_price'));
        $this->assertSame('60000.00', $customer->fresh()->getRawOriginal('balance'));
        $this->assertEquals(8.8, $this->kg->fresh()->stock);
        $this->assertSame(8, $this->variant->fresh()->stock);

        // The Telegram card and the order list show the weight as sold
        [$card] = app(TelegramNotificationService::class)->buildMessageAndKeyboard($order->fresh(['items', 'user']));
        $this->assertStringContainsString('🍔 1.2 kg Mini somsa (kg)', $card);
        $this->assertStringContainsString('🍔 2x Cheeseburger (Regular)', $card);
        $this->assertStringNotContainsString('Xizmat haqi', $card);

        $this->actingAs($customer, 'sanctum')->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.quantity', 1.2)
            ->assertJsonPath('data.0.items.0.unit', 'kg')
            ->assertJsonPath('data.0.items.1.quantity', 2);

        // Cancelling gives back the exact fractions and the keshbek
        $this->setStatus($order, $this->owner, 'canceled')->assertOk();
        $this->assertSame(10, $this->kg->fresh()->stock);
        $this->assertSame(10, $this->variant->fresh()->stock);
        $this->assertSame('200000.00', $customer->fresh()->getRawOriginal('balance'));
    }

    public function test_analytics_and_reports_sum_fractional_quantities(): void
    {
        foreach (['0.5', '1.2'] as $quantity) {
            $response = $this->order([['variant_id' => $this->kg->uuid, 'quantity' => $quantity]])->assertCreated();
            Order::where('uuid', $response->json('data.order_uuid'))->update(['status' => OrderStatus::DELIVERED->value]);
        }

        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/analytics?period=today')
            ->assertOk()
            ->assertJsonPath('data.top_products.0.name', 'Mini somsa')
            ->assertJsonPath('data.top_products.0.quantity', 1.7)
            ->assertJsonPath('data.by_channel.0.channel', 'telegram')
            ->assertJsonPath('data.by_channel.0.orders', 2);
    }

    public function test_the_owner_api_sets_units_on_variants(): void
    {
        $this->actingAs($this->owner, 'sanctum')->post('/api/v1/owner/products', [
            'name' => 'Osh',
            'category_id' => $this->product->category_id,
            'is_active' => true,
            'batch_tracked' => true,
            'variants' => json_encode([
                ['name' => '1 porsiya', 'price' => 55000],
                ['name' => 'kg', 'price' => 120000, 'unit' => 'kg', 'quantity_step' => 0.1, 'min_quantity' => 0.5],
            ]),
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.batch_tracked', true)
            ->assertJsonPath('data.variants.0.unit', 'piece')
            ->assertJsonPath('data.variants.1.unit', 'kg')
            ->assertJsonPath('data.variants.1.quantity_step', '0.100')
            ->assertJsonPath('data.variants.1.stock', 999);

        $this->actingAs($this->owner, 'sanctum')->post('/api/v1/owner/products', [
            'name' => 'Xato',
            'category_id' => $this->product->category_id,
            'variants' => json_encode([['name' => 'x', 'price' => 1, 'unit' => 'tonna']]),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_quantity_value_object(): void
    {
        $this->assertSame('1.500', Quantity::of('1.5')->toString());
        $this->assertSame('0.3', Quantity::of(0.1)->plus(0.2)->format());
        $this->assertSame(2, Quantity::of('2.000')->toNumber());
        $this->assertSame(1.25, Quantity::of('1.25')->toNumber());
        $this->assertTrue(Quantity::of('0.3')->isMultipleOf('0.1'));
        $this->assertFalse(Quantity::of('0.35')->isMultipleOf('0.1'));
        $this->assertNull(Quantity::tryOf('0.0001'));
        $this->assertNull(Quantity::tryOf('1e3'));
        $this->assertNull(Quantity::tryOf(['1']));
        $this->assertSame('0.700', Quantity::of(3)->times('0.7')->minus(Quantity::of('1.4'))->toString());
    }
}
