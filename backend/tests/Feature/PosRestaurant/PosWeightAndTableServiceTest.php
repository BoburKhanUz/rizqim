<?php

namespace Tests\Feature\PosRestaurant;

use App\Domain\Pos\Setup\FormatProfile;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-25 (B3, B4, C7): "weight later" (a fish chosen, weighed afterwards: no bill while it waits, the price follows the
 * real kg, who weighed it), a dish's skewer buttons and grill time (data on the product, on the POS catalog and the
 * kitchen ticket), and the kafe's table plate on a counter order.
 */
class PosWeightAndTableServiceTest extends TestCase
{
    use PosRestaurantFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRestaurant(FormatProfile::BALIQXONA);
    }

    private function fishOrder(string $estimate = '1.5'): Order
    {
        return $this->openedOrder($this->openPos($this->waiter, $this->table, 2, [
            'items' => [$this->line($this->fish, $estimate), $this->line($this->tea, 1)],
        ]));
    }

    public function test_a_fish_waits_for_its_weight_and_no_bill_is_made_until_it_is_weighed(): void
    {
        $order = $this->fishOrder();
        $fish = OrderItem::where('order_id', $order->id)->where('product_variant_id', $this->fish->id)->firstOrFail();
        $this->assertTrue($fish->awaitingWeight());
        $this->assertSame('raw', $fish->weight_basis->value);
        $this->assertSame('367500.00', (string) $fish->total_price); // the estimate: 1.5 kg × 245 000
        $this->assertSame('98.500', \App\Support\Quantity::of(ProductVariant::whereKey($this->fish->id)->value('stock'))->toString());

        $this->pos($this->waiter, 'GET', "/pos/orders/{$order->uuid}")->assertOk()
            ->assertJsonPath('data.weight_pending', 1)
            ->assertJsonPath('data.items.0.weight_status', 'awaiting')
            ->assertJsonPath('data.items.1.weight_status', null);

        // No bill while it waits
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/bills", ['mode' => 'single', 'version' => 1])
            ->assertStatus(409)
            ->assertJsonPath('data.reason', 'weight_pending')
            ->assertJsonPath('data.items.0.id', $fish->id)
            ->assertJsonPath('data.items.0.name', 'Sazan (Mangal)');

        // The kitchen sees it is waiting for its weight
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/send", ['version' => 1])->assertOk();
        $ticket = $this->actingAs($this->makeCook(), 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertOk()->json('data.0');
        $this->assertSame('awaiting', collect($ticket['items'])->firstWhere('product_name', 'Sazan')['weight_status']);

        // The real weight: 1.35 kg
        $response = $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$fish->id}/weight", ['version' => $this->versionOf($order), 'quantity' => '1.35'])->assertOk()
            ->assertJsonPath('data.weight_pending', 0)
            ->assertJsonPath('data.items.0.weight_status', 'weighed')
            ->assertJsonPath('data.items.0.quantity_label', '1.35 kg')
            ->assertJsonPath('data.items.0.total_price', '330750.00')
            ->assertJsonPath('data.items.0.weighed_by.id', $this->waiter->id);
        $this->assertSame('335750.00', $response->json('data.subtotal'));
        $this->assertNotNull($response->json('data.items.0.weighed_at'));
        $this->assertSame('98.650', \App\Support\Quantity::of(ProductVariant::whereKey($this->fish->id)->value('stock'))->toString()); // 0.15 kg back
        $log = AuditLog::where('action', 'pos.item_weighed')->firstOrFail();
        $this->assertSame('1.500', $log->before['quantity'] ?? null);
        $this->assertSame('1.350', $log->after['quantity'] ?? null);

        // Now the bill is made, with the real kg
        $bill = $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/bills", ['mode' => 'single', 'version' => $this->versionOf($order)])->assertSuccessful();
        $line = collect($bill->json('data.bills.0.items'))->firstWhere('product_name', 'Sazan');
        $this->assertEquals(1.35, $line['quantity']);
        $this->assertSame('330750.00', $line['amount']);
    }

    public function test_weighing_again_needs_a_manager_and_only_a_weight_later_line_takes_a_weight(): void
    {
        $order = $this->fishOrder();
        $fish = OrderItem::where('order_id', $order->id)->where('product_variant_id', $this->fish->id)->firstOrFail();
        $tea = OrderItem::where('order_id', $order->id)->where('product_variant_id', $this->tea->id)->firstOrFail();

        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$tea->id}/weight", ['version' => 1, 'quantity' => '1'])
            ->assertStatus(422)->assertJsonPath('data.reason', 'not_weighed_line');
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$fish->id}/weight", ['version' => 1, 'quantity' => '0'])
            ->assertUnprocessable();
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$fish->id}/weight", ['version' => 1, 'quantity' => '1.2'])->assertOk();

        // A correction changes the price the guests were told: orders.void_item or a manager's approval
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$fish->id}/weight", ['version' => 2, 'quantity' => '1.25'])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required');
        $token = $this->approval('orders.void_item', $this->manager);
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$fish->id}/weight", ['version' => 2, 'quantity' => '1.25', 'approval_token' => $token])
            ->assertOk()->assertJsonPath('data.items.0.total_price', '306250.00');
    }

    public function test_voiding_the_waiting_line_frees_the_bill(): void
    {
        $order = $this->fishOrder();
        $fish = OrderItem::where('order_id', $order->id)->where('product_variant_id', $this->fish->id)->firstOrFail();

        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$fish->id}/void", ['version' => 1])->assertOk();
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/bills", ['mode' => 'single', 'version' => 2])->assertSuccessful();
    }

    public function test_which_lines_wait_for_their_weight(): void
    {
        // Explicitly not weighed later: an ordinary kg line
        $order = $this->openedOrder($this->openPos($this->waiter, $this->table, 2, ['items' => [$this->line($this->fish, '1', ['awaiting_weight' => false])]]));
        $this->assertNull(OrderItem::where('order_id', $order->id)->value('weight_status'));

        // A piece dish cannot wait for a weight; asked explicitly: 422
        $this->openPos($this->waiter, $this->tapchan, 2, ['items' => [$this->line($this->shashlik, 2, ['awaiting_weight' => true])]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.awaiting_weight');
        // A kazan dish neither
        $this->openPos($this->waiter, $this->tapchan, 2, ['items' => [$this->line($this->plov, 1, ['awaiting_weight' => true])]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.awaiting_weight');

        // Module off (a choyxona): asked explicitly is 403, the product's default just sells it as typed
        $this->profile(FormatProfile::CHOYXONA);
        $this->openPos($this->waiter, $this->tapchan, 2, ['items' => [$this->line($this->fish, 1, ['awaiting_weight' => true])]])
            ->assertForbidden()->assertJsonPath('code', 'module_disabled')->assertJsonPath('module', 'weight_later');
        $plain = $this->openedOrder($this->openPos($this->waiter, $this->tapchan, 2, ['items' => [$this->line($this->fish, 1)]]));
        $this->assertNull(OrderItem::where('order_id', $plain->id)->value('weight_status'));
    }

    public function test_the_owner_sets_a_dishs_skewer_buttons_grill_time_and_weight_rule(): void
    {
        $this->profile(FormatProfile::KABOBXONA);
        $product = $this->shashlik->product;

        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/products/{$product->id}/pos-service", [
            'quick_multipliers' => [5, 2, 3, 10],
            'wait_minutes' => 15,
        ])->assertOk()
            ->assertJsonPath('data.quick_multipliers', [2, 3, 5, 10])
            ->assertJsonPath('data.wait_minutes', 15)
            ->assertJsonPath('data.weight_mode', null);
        $this->assertSame([2, 3, 5, 10], Product::find($product->id)->quick_multipliers);
        $this->assertTrue(AuditLog::where('action', 'menu.product_pos_service_updated')->exists());

        // Out of range or twice
        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/products/{$product->id}/pos-service", ['quick_multipliers' => [11]])->assertUnprocessable();
        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/products/{$product->id}/pos-service", ['quick_multipliers' => [2, 2]])->assertUnprocessable();

        // A kabobxona has no weight_later: setting it is 403, clearing never is
        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/products/{$this->fish->product_id}/pos-service", ['weight_mode' => 'cooked'])
            ->assertForbidden()->assertJsonPath('code', 'module_disabled');
        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/products/{$this->fish->product_id}/pos-service", ['weight_mode' => null])
            ->assertOk()->assertJsonPath('data.weight_mode', null);

        // The till and the kitchen read them
        $catalog = $this->pos($this->cashier, 'GET', '/pos/catalog')->assertOk()->json('data.products');
        $row = collect($catalog)->firstWhere('id', $product->id);
        $this->assertSame([2, 3, 5, 10], $row['quick_multipliers']);
        $this->assertSame(15, $row['wait_minutes']);

        $order = $this->openedOrder($this->openPos($this->waiter, $this->table, 2, ['items' => [$this->line($this->shashlik, 5)], 'send' => true]));
        $ticket = $this->actingAs($this->makeCook(), 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertOk()->json('data.0');
        $this->assertSame($order->uuid, $ticket['uuid']);
        $this->assertSame(15, $ticket['items'][0]['wait_minutes']);

        // A baliqxona: the weight rule needs a kg dish
        $this->profile(FormatProfile::BALIQXONA);
        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/products/{$product->id}/pos-service", ['weight_mode' => 'raw'])
            ->assertUnprocessable()->assertJsonValidationErrors('weight_mode');
        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/products/{$this->fish->product_id}/pos-service", ['weight_mode' => 'cooked'])
            ->assertOk()->assertJsonPath('data.weight_mode', 'cooked');

        // menu.manage only
        $this->actingAs($this->waiter, 'sanctum')->patchJson("/api/v1/owner/products/{$product->id}/pos-service", ['wait_minutes' => 5])->assertForbidden();
    }

    public function test_a_kafe_counter_order_carries_its_table_plate(): void
    {
        $this->profile(FormatProfile::KAFE);

        $order = $this->openedOrder($this->pos($this->cashier, 'POST', '/pos/orders', [
            'channel' => 'pos_takeaway',
            'table_label' => ' 12 ',
            'items' => [$this->line($this->shashlik, 2)],
            'send' => true,
        ]));
        $this->assertSame('12', $order->table_label);

        $ticket = $this->actingAs($this->makeCook(), 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertOk()->json('data.0');
        $this->assertSame('12', $ticket['table_label']);
        $this->pos($this->cashier, 'GET', '/pos/orders')->assertOk()->assertJsonPath('data.0.table_label', '12');

        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/table-label", ['version' => $this->versionOf($order), 'table_label' => 'A7'])->assertOk()
            ->assertJsonPath('data.table_label', 'A7');
        $this->assertTrue(AuditLog::where('action', 'pos.table_label_set')->exists());
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/table-label", ['version' => $this->versionOf($order), 'table_label' => null])->assertOk()
            ->assertJsonPath('data.table_label', null);

        // Without the module (a choyxona): 403 on both
        $this->profile(FormatProfile::CHOYXONA);
        $this->pos($this->cashier, 'POST', '/pos/orders', ['channel' => 'pos_takeaway', 'table_label' => '5'])
            ->assertForbidden()->assertJsonPath('module', 'table_service');
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/table-label", ['version' => $this->versionOf($order), 'table_label' => '5'])
            ->assertForbidden()->assertJsonPath('code', 'module_disabled');
        // Another role without orders.manage
        $this->pos($this->makeStaff(UserRole::COOK, $this->branch), 'POST', "/pos/orders/{$order->uuid}/table-label", ['version' => 1, 'table_label' => '5'])
            ->assertForbidden();
    }
}
