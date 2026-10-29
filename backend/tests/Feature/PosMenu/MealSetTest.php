<?php

namespace Tests\Feature\PosMenu;

use App\Models\AuditLog;
use App\Models\DailySet;
use App\Models\DailyMenu;
use App\Models\DailyMenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StopListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-15 "kompleks": a menu product sold at its own price as one line whose parts the kitchen sees. Composition rules,
 * the module gate, the snapshot on sold lines (POS and Telegram), the kitchen ticket, the POS catalog and the bootstrap,
 * and a set closed by a stopped or off-menu part.
 */
class MealSetTest extends TestCase
{
    use PosMenuFixtures;
    use RefreshDatabase;

    protected Product $complex;

    protected ProductVariant $complexVariant;

    protected Product $soup;

    protected Product $salad;

    protected Product $patir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosHall();
        $this->enableMenuModules();
        $this->complex = $this->dish('Kompleks 1', '35000');
        $this->complexVariant = $this->complex->variants->first();
        $this->soup = $this->dish('Mastava', '22000');
        $this->salad = $this->dish('Achichuk', '9000');
        $this->patir = $this->dish('Patir', '4000');
    }

    /** @return list<array<string, mixed>> */
    protected function parts(): array
    {
        return [
            ['variant_id' => $this->soup->variants->first()->id, 'quantity' => 1, 'label' => 'Birinchi'],
            ['variant_id' => $this->salad->variants->first()->id, 'quantity' => 1, 'label' => 'Salat'],
            ['variant_id' => $this->patir->variants->first()->id, 'quantity' => 2, 'label' => 'Non'],
        ];
    }

    protected function makeSet(?array $parts = null, ?Product $product = null)
    {
        return $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/meal-sets', [
            'product_id' => ($product ?? $this->complex)->id,
            'parts' => $parts ?? $this->parts(),
        ]);
    }

    public function test_the_owner_makes_a_product_a_kompleks_with_its_parts(): void
    {
        $response = $this->makeSet()->assertCreated();
        $response->assertJsonPath('data.product_name', 'Kompleks 1')->assertJsonPath('data.prices', ['35000.00'])->assertJsonPath('data.is_active', true);
        $parts = $response->json('data.parts');
        $this->assertSame(['Mastava', 'Achichuk', 'Patir'], array_column($parts, 'name'));
        $this->assertSame(['Birinchi', 'Salat', 'Non'], array_column($parts, 'label'));
        $this->assertSame('2.000', $parts[2]['quantity']);

        $listed = $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/meal-sets')->assertOk()->json('data.sets');
        $this->assertCount(1, $listed);
        $this->assertSame($this->complex->id, $listed[0]['product_id']);
        $this->assertTrue(AuditLog::where('action', 'menu.meal_set_saved')->exists());

        // Saving the same product again replaces its parts
        $this->makeSet([['variant_id' => $this->soup->variants->first()->id]])->assertCreated();
        $this->assertSame(1, DailySet::count());
        $this->assertSame(1, DailySet::firstOrFail()->items()->count());
    }

    public function test_the_parts_are_checked(): void
    {
        $soupVariant = $this->soup->variants->first()->id;

        $this->makeSet([])->assertStatus(422)->assertJsonValidationErrors('parts');
        $this->makeSet(array_fill(0, 13, ['variant_id' => $soupVariant]))->assertStatus(422);
        $this->makeSet([['variant_id' => 999999]])->assertStatus(422)->assertJsonValidationErrors('parts.0.variant_id');
        $this->makeSet([['variant_id' => $soupVariant, 'quantity' => 0]])->assertStatus(422);
        $this->makeSet([['variant_id' => $soupVariant, 'quantity' => 100]])->assertStatus(422);
        // Not itself, nor another kompleks
        $this->makeSet([['variant_id' => $this->complexVariant->id]])->assertStatus(422)->assertJsonValidationErrors('parts.0.variant_id');
        $other = $this->dish('Kompleks 2', '40000');
        $this->makeSet([['variant_id' => $soupVariant]], $other)->assertCreated();
        $this->makeSet([['variant_id' => $other->variants->first()->id]])->assertStatus(422);
        // Another store's dish, an inactive variant
        $foreign = Product::create(['store_id' => $this->makeStore('Boshqa')->id, 'category_id' => $this->complex->category_id, 'name' => 'Begona', 'slug' => 'begona-k', 'status' => 'active']);
        $foreignVariant = $this->makeVariant($foreign, '1000');
        $this->makeSet([['variant_id' => $foreignVariant->id]])->assertStatus(422);
        $this->makeSet(null, $foreign)->assertNotFound();
        $this->soup->variants->first()->forceFill(['is_active' => false])->save();
        $this->makeSet([['variant_id' => $soupVariant]])->assertStatus(422);
    }

    public function test_a_kompleks_can_be_changed_switched_off_and_deleted(): void
    {
        $id = $this->makeSet()->assertCreated()->json('data.id');

        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/meal-sets/{$id}", ['parts' => [['variant_id' => $this->patir->variants->first()->id, 'quantity' => 3]]])
            ->assertOk()->assertJsonCount(1, 'data.parts')->assertJsonPath('data.parts.0.name', 'Patir');
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/meal-sets/{$id}", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonCount(1, 'data.parts');
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/meal-sets/{$id}", ['product_id' => 1])->assertStatus(422);

        // Switched off: sold as a plain product, no parts on the line
        $this->openPos($this->waiter, $this->room, 2, ['items' => [$this->line($this->complexVariant)]])->assertCreated();
        $this->assertNull(OrderItem::firstOrFail()->set_parts);

        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/v1/owner/meal-sets/{$id}")->assertOk();
        $this->assertSame(0, DailySet::count());
        $this->assertTrue(AuditLog::where('action', 'menu.meal_set_deleted')->exists());
        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/v1/owner/meal-sets/{$id}")->assertNotFound();
    }

    public function test_the_module_and_the_permission_gate_the_endpoints(): void
    {
        $this->enableMenuModules(modules: ['combo_meal' => false]);
        $this->makeSet()->assertForbidden()->assertJsonPath('code', 'module_disabled')->assertJsonPath('data.module', 'combo_meal');
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/meal-sets')->assertForbidden();

        $this->enableMenuModules();
        // Managers read the menu but do not edit it
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/meal-sets')->assertOk();
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/owner/meal-sets', ['product_id' => $this->complex->id, 'parts' => $this->parts()])->assertForbidden();
        $this->assertSame(0, DailySet::count());
    }

    public function test_a_sold_kompleks_is_one_line_that_keeps_its_parts(): void
    {
        $this->makeSet()->assertCreated();

        $opened = $this->openPos($this->waiter, $this->room, 2, ['items' => [$this->line($this->complexVariant, 2)]])->assertCreated();
        $items = $opened->json('data.items');
        // One line at the set's price, not three
        $this->assertCount(1, $items);
        $this->assertSame('70000.00', $items[0]['total_price']);
        $parts = $items[0]['set_parts'];
        $this->assertSame(['Mastava', 'Achichuk', 'Patir'], array_column($parts, 'name'));
        $this->assertSame(['1.000', '1.000', '2.000'], array_column($parts, 'quantity'));
        $this->assertSame('Birinchi', $parts[0]['label']);

        // Plain dishes carry no parts
        $plain = $this->openPos($this->waiter, $this->table, 2, ['items' => [$this->line($this->soup->variants->first())]])->assertCreated();
        $this->assertArrayNotHasKey('set_parts', $plain->json('data.items.0'));

        // Editing the composition later does not touch the sold line
        $id = DailySet::firstOrFail()->id;
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/meal-sets/{$id}", ['parts' => [['variant_id' => $this->patir->variants->first()->id]]])->assertOk();
        $line = OrderItem::where('product_variant_id', $this->complexVariant->id)->firstOrFail();
        $this->assertCount(3, $line->set_parts);
        $again = $this->openPos($this->waiter, $this->tapchan, 2, ['items' => [$this->line($this->complexVariant)]])->assertCreated();
        $this->assertSame(['Patir'], array_column($again->json('data.items.0.set_parts'), 'name'));
    }

    public function test_a_telegram_order_keeps_the_parts_and_the_kitchen_ticket_lists_them(): void
    {
        $this->makeSet()->assertCreated();
        $cook = $this->makeCook();

        $uuid = $this->order([['variant_id' => $this->complexVariant->uuid, 'quantity' => 1]])->assertCreated()->json('data.order_uuid');
        $this->assertSame(3, count(OrderItem::firstOrFail()->set_parts));
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->setStatus($order, $this->manager, 'accepted')->assertOk();

        $tickets = $this->actingAs($cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertOk()->json('data');
        $item = collect($tickets)->firstWhere('uuid', $uuid)['items'][0];
        $this->assertSame('Kompleks 1', $item['product_name']);
        $this->assertSame(['Mastava', 'Achichuk', 'Patir'], array_column($item['set_parts'], 'name'));
    }

    public function test_the_pos_catalog_and_the_bootstrap_list_the_parts(): void
    {
        $this->makeSet()->assertCreated();

        $catalog = $this->actingAs($this->waiter, 'sanctum')->getJson('/api/v1/pos/catalog?channel=pos_hall&branch_id=' . $this->branch->id)->assertOk()->json('data.products');
        $byName = collect($catalog)->keyBy('name');
        $this->assertSame(['Mastava', 'Achichuk', 'Patir'], array_column($byName['Kompleks 1']['set_parts'], 'name'));
        $this->assertNull($byName['Mastava']['set_parts']);

        $bootstrap = $this->actingAs($this->waiter, 'sanctum')->getJson('/api/v1/pos/bootstrap?branch_id=' . $this->branch->id)->assertOk()->json('data.meal_sets');
        $this->assertCount(1, $bootstrap);
        $this->assertSame($this->complex->id, $bootstrap[0]['product_id']);
        $this->assertCount(3, $bootstrap[0]['parts']);

        $this->enableMenuModules(modules: ['combo_meal' => false]);
        $this->assertSame([], $this->actingAs($this->waiter, 'sanctum')->getJson('/api/v1/pos/bootstrap?branch_id=' . $this->branch->id)->json('data.meal_sets'));
    }

    public function test_a_kompleks_closes_while_one_of_its_parts_is_stopped_or_off_the_menu(): void
    {
        $this->makeSet()->assertCreated();
        $this->openPos($this->waiter, $this->room, 2, ['items' => [$this->line($this->complexVariant)]])->assertCreated();

        // The soup is on the stop-list: the kompleks cannot be sold, and says why
        $entry = StopListEntry::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'subject_type' => 'product', 'subject_id' => $this->soup->id, 'reason' => 'Tugadi']);
        $refused = $this->openPos($this->waiter, $this->table, 2, ['items' => [$this->line($this->complexVariant)]]);
        $refused->assertStatus(422);
        $this->assertStringContainsString('Mastava', json_encode($refused->json(), JSON_UNESCAPED_UNICODE));
        $this->order([['variant_id' => $this->complexVariant->uuid, 'quantity' => 1]])->assertStatus(422);
        $availability = collect($this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/catalog/availability?store_id={$this->store->id}")->json('data.branches'))->firstWhere('branch_id', $this->branch->id);
        $this->assertContains($this->complex->id, $availability['stopped']['products']);

        // Back on sale with the soup
        $entry->delete();
        $this->openPos($this->waiter, $this->table, 2, ['items' => [$this->line($this->complexVariant)]])->assertCreated();

        // A dish of the rotation that is off today's menu closes it too
        $this->rotate($this->soup);
        $menu = DailyMenu::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'menu_date' => app(\App\Domain\Pos\Menu\DailyMenuService::class)->today($this->branch)]);
        DailyMenuItem::create(['daily_menu_id' => $menu->id, 'product_id' => $this->complex->id]);
        $this->order([['variant_id' => $this->complexVariant->uuid, 'quantity' => 1]])->assertStatus(422);
        $this->rotate($this->complex);
        $this->order([['variant_id' => $this->complexVariant->uuid, 'quantity' => 1]])->assertStatus(422);
        DailyMenuItem::create(['daily_menu_id' => $menu->id, 'product_id' => $this->soup->id]);
        $this->order([['variant_id' => $this->complexVariant->uuid, 'quantity' => 1]])->assertCreated();
    }

    public function test_a_kompleks_closes_while_a_batch_tracked_part_is_sold_out(): void
    {
        $this->makeSet()->assertCreated();
        $this->openPos($this->waiter, $this->room, 2, ['items' => [$this->line($this->complexVariant)]])->assertCreated();

        // The soup is cooked in batches and the branch has none open: the soup is "tugadi", and so is the kompleks
        $this->soup->forceFill(['batch_tracked' => true])->save();
        $availability = fn () => collect($this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/catalog/availability?store_id={$this->store->id}")->json('data.branches'))->firstWhere('branch_id', $this->branch->id);
        $this->assertContains($this->soup->variants->first()->id, $availability()['sold_out']['variants']);
        $this->assertContains($this->complex->id, $availability()['stopped']['products']);
        $reason = collect($availability()['stop_list'])->firstWhere('subject_id', $this->complex->id)['reason'];
        $this->assertStringContainsString('Mastava', $reason);

        $refused = $this->openPos($this->waiter, $this->table, 2, ['items' => [$this->line($this->complexVariant)]]);
        $refused->assertStatus(422);
        $this->assertStringContainsString('Mastava', json_encode($refused->json(), JSON_UNESCAPED_UNICODE));
        $this->order([['variant_id' => $this->complexVariant->uuid, 'quantity' => 1]])->assertStatus(422);
        $catalog = collect($this->actingAs($this->waiter, 'sanctum')->getJson('/api/v1/pos/catalog?channel=pos_hall&branch_id=' . $this->branch->id)->assertOk()->json('data.products'))->keyBy('name');
        $this->assertFalse($catalog['Kompleks 1']['is_available']);

        // The cook opens a batch of soup: the kompleks is on sale again
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/batches', ['branch_id' => $this->branch->id, 'product_id' => $this->soup->id, 'quantity' => '10'])->assertCreated();
        $this->assertNotContains($this->complex->id, $availability()['stopped']['products']);
        $this->openPos($this->waiter, $this->table, 2, ['items' => [$this->line($this->complexVariant)]])->assertCreated();
        $this->order([['variant_id' => $this->complexVariant->uuid, 'quantity' => 1]])->assertCreated();
    }
}
