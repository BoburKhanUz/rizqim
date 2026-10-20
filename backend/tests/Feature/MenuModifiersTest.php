<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1-21 modifiers: server-side pricing, the group rules at checkout, the order line snapshot, the catalog and the
 * owner API.
 */
class MenuModifiersTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use MenuFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    public function test_modifier_prices_are_added_per_piece_on_the_server(): void
    {
        $sauce = $this->makeGroup('Sous', ['Ketchup' => 0, 'Pishloqli' => 3000], ['is_required' => true, 'max_select' => 1]);
        $extras = $this->makeGroup("Qo'shimchalar", ["Go'sht" => 12000, 'Bekon' => 7000], ['max_select' => 3]);
        $this->modifier($extras, "Go'sht")->update(['max_quantity' => 2]);

        $response = $this->order([[
            'variant_id' => $this->variant->uuid,
            'quantity' => 2,
            // Prices sent by the client are ignored
            'price' => 1,
            'modifiers' => [
                $this->modifier($sauce, 'Pishloqli')->uuid,
                ['id' => $this->modifier($extras, "Go'sht")->uuid, 'quantity' => 2, 'price_delta' => 0],
            ],
        ]])->assertCreated();

        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        // (50 000 + 3 000 + 2 × 12 000) × 2
        $this->assertSame('154000.00', $order->getRawOriginal('subtotal'));
        $this->assertSame('154000.00', $order->getRawOriginal('total_price'));

        $item = $order->items()->firstOrFail();
        $this->assertSame('50000.00', $item->getRawOriginal('unit_price'));
        $this->assertSame('27000.00', $item->getRawOriginal('modifiers_total'));
        $this->assertSame('154000.00', $item->getRawOriginal('total_price'));
        $this->assertSame(['Sous', "Qo'shimchalar"], array_column($item->modifiers, 'group_name'));
        // jsonb keeps its own key order
        $this->assertEquals([
            'id' => $this->modifier($extras, "Go'sht")->id,
            'uuid' => $this->modifier($extras, "Go'sht")->uuid,
            'group_id' => $extras->id,
            'group_name' => "Qo'shimchalar",
            'name' => "Go'sht",
            'price_delta' => '12000.00',
            'quantity' => 2,
            'total' => '24000.00',
        ], $item->modifiers[1]);
        $this->assertSame(8, $this->variant->fresh()->stock);
    }

    public function test_a_required_group_must_be_chosen(): void
    {
        $this->makeGroup('Sous', ['Ketchup' => 0, 'Mayonez' => 0], ['is_required' => true]);

        $response = $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.modifiers']);
        $this->assertSame("'Cheeseburger (Regular)': 'Sous' tanlovi majburiy.", $response->json('errors')['items.0.modifiers'][0]);

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $this->variant->fresh()->stock);
    }

    public function test_group_minimum_and_maximum_are_enforced(): void
    {
        $extras = $this->makeGroup("Qo'shimchalar", ['A' => 1000, 'B' => 1000, 'C' => 1000], ['min_select' => 2, 'max_select' => 2]);
        [$a, $b, $c] = [$this->modifier($extras, 'A')->uuid, $this->modifier($extras, 'B')->uuid, $this->modifier($extras, 'C')->uuid];

        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1, 'modifiers' => [$a]]])
            ->assertStatus(422)->assertJsonValidationErrors(['items.0.modifiers']);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1, 'modifiers' => [$a, $b, $c]]])
            ->assertStatus(422)->assertJsonValidationErrors(['items.0.modifiers']);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1, 'modifiers' => [$a, $b]]])->assertCreated();
    }

    public function test_per_product_overrides_win_over_the_group_rules(): void
    {
        // The group itself is optional, but this product makes it required with at most one choice
        $sauce = $this->makeGroup('Sous', ['Ketchup' => 0, 'Mayonez' => 500], [], ['is_required' => true, 'max_select' => 1]);

        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertStatus(422);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1, 'modifiers' => [
            $this->modifier($sauce, 'Ketchup')->uuid, $this->modifier($sauce, 'Mayonez')->uuid,
        ]]])->assertStatus(422);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1, 'modifiers' => [$this->modifier($sauce, 'Mayonez')->uuid]]])
            ->assertCreated();
    }

    public function test_a_modifier_may_be_taken_at_most_max_quantity_times(): void
    {
        $extras = $this->makeGroup("Qo'shimchalar", ['Pishloq' => 2000]);
        $cheese = $this->modifier($extras, 'Pishloq');
        $cheese->update(['max_quantity' => 2]);

        $response = $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1, 'modifiers' => [['id' => $cheese->uuid, 'quantity' => 3]]]])
            ->assertStatus(422);
        $this->assertSame("'Pishloq' ko'pi bilan 2 marta tanlanishi mumkin.", $response->json('errors')['items.0.modifiers'][0]);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1, 'modifiers' => [['id' => $cheese->uuid, 'quantity' => 2]]]])
            ->assertCreated();
    }

    public function test_modifiers_of_another_product_inactive_or_deleted_ones_are_refused(): void
    {
        $other = $this->makeProduct('Lavash');
        $foreign = $this->makeGroup('Lavash sousi', ['Achchiq' => 1000], [], [], $other);
        $own = $this->makeGroup('Sous', ['Ketchup' => 0, 'Eski' => 0, "O'chirilgan" => 0]);
        $this->modifier($own, 'Eski')->update(['is_active' => false]);
        $this->modifier($own, "O'chirilgan")->delete();
        $inactiveGroup = $this->makeGroup('Yopiq guruh', ['X' => 0], ['is_active' => false]);

        foreach ([$this->modifier($foreign, 'Achchiq'), $this->modifier($own, 'Eski'), $own->modifiers()->withTrashed()->where('name', "O'chirilgan")->first(), $this->modifier($inactiveGroup, 'X')] as $modifier) {
            $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1, 'modifiers' => [$modifier->uuid]]])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['items.0.modifiers']);
        }

        // Another store's modifier, by uuid
        $otherStore = $this->makeStore('Other Burger');
        $foreignGroup = \App\Models\ModifierGroup::create(['store_id' => $otherStore->id, 'name' => 'Begona']);
        $foreignModifier = $foreignGroup->modifiers()->create(['name' => 'Begona', 'price_delta' => 0]);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1, 'modifiers' => [$foreignModifier->uuid]]])->assertStatus(422);

        // Malformed choices
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1, 'modifiers' => ['not-a-uuid']]])->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_lines_with_different_modifiers_stay_separate_and_identical_ones_merge(): void
    {
        $sauce = $this->makeGroup('Sous', ['Ketchup' => 0, 'Pishloqli' => 3000]);
        $ketchup = $this->modifier($sauce, 'Ketchup')->uuid;
        $cheese = $this->modifier($sauce, 'Pishloqli')->uuid;

        $response = $this->order([
            ['variant_id' => $this->variant->uuid, 'quantity' => 1, 'modifiers' => [$ketchup]],
            ['variant_id' => $this->variant->uuid, 'quantity' => 2, 'modifiers' => [$cheese]],
            ['variant_id' => strtoupper($this->variant->uuid), 'quantity' => 1, 'modifiers' => [strtoupper($ketchup)]],
        ])->assertCreated();

        $items = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail()->items()->orderBy('id')->get();
        $this->assertCount(2, $items);
        $this->assertSame([2, 2], $items->pluck('quantity')->all());
        $this->assertSame(['100000.00', '106000.00'], $items->map(fn (OrderItem $item) => $item->getRawOriginal('total_price'))->all());
        // Stock is checked against the variant's total
        $this->assertSame(6, $this->variant->fresh()->stock);
    }

    public function test_the_snapshot_does_not_follow_later_menu_edits(): void
    {
        $sauce = $this->makeGroup('Sous', ['Pishloqli' => 3000]);
        $response = $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1, 'modifiers' => [$this->modifier($sauce, 'Pishloqli')->uuid]]])->assertCreated();
        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();

        $this->modifier($sauce, 'Pishloqli')->update(['name' => 'Cheddar', 'price_delta' => 9000]);
        $sauce->update(['name' => 'Yangi sous']);
        $this->variant->update(['price' => 99000, 'name' => 'Katta']);
        $this->product->update(['name' => 'Yangi nom']);
        $sauce->delete();

        $item = $order->items()->firstOrFail();
        $this->assertSame('Cheeseburger', $item->product_name);
        $this->assertSame('Regular', $item->variant_name);
        $this->assertSame('50000.00', $item->getRawOriginal('unit_price'));
        $this->assertSame('Pishloqli', $item->modifiers[0]['name']);
        $this->assertSame('Sous', $item->modifiers[0]['group_name']);
        $this->assertSame('3000.00', $item->modifiers[0]['price_delta']);

        // The order list shows the snapshot (additive fields)
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.modifiers.0.name', 'Pishloqli')
            ->assertJsonPath('data.0.items.0.unit', 'piece')
            ->assertJsonPath('data.0.items.0.quantity', 1)
            ->assertJsonPath('data.0.channel', 'telegram');
    }

    public function test_the_catalog_shows_each_products_groups_with_effective_rules(): void
    {
        $sauce = $this->makeGroup('Sous', ['Ketchup' => 0, 'Pishloqli' => 3000], ['max_select' => 3], ['is_required' => true, 'max_select' => 1]);
        $this->modifier($sauce, 'Ketchup')->update(['is_active' => false]);
        $this->makeGroup('Yopiq', ['X' => 0], ['is_active' => false]);

        $response = $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$this->store->uuid}")->assertOk();

        $product = collect($response->json('data.products'))->firstWhere('id', $this->product->id);
        $this->assertCount(1, $product['modifier_groups']);
        $group = $product['modifier_groups'][0];
        $this->assertSame('Sous', $group['name']);
        $this->assertSame(1, $group['min_select']);
        $this->assertSame(1, $group['max_select']);
        $this->assertTrue($group['is_required']);
        $this->assertArrayNotHasKey('pivot', $group);
        $this->assertSame(['Pishloqli'], array_column($group['modifiers'], 'name'));
        $this->assertSame('3000.00', $group['modifiers'][0]['price_delta']);
        // Old fields are unchanged; the new ones are additive
        $variant = $product['variants'][0];
        $this->assertSame('50000.00', $variant['price']);
        $this->assertSame(10, $variant['stock']);
        $this->assertSame('piece', $variant['unit']);
    }

    public function test_the_owner_manages_groups_and_attaches_them_to_products(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/modifier-groups', [
            'name' => 'Sous',
            'name_i18n' => ['ru' => 'Соус'],
            'is_required' => true,
            'max_select' => 2,
            'modifiers' => [
                ['name' => 'Ketchup', 'price_delta' => 0],
                ['name' => 'Pishloqli', 'price_delta' => 3000, 'max_quantity' => 2, 'product_variant_id' => $this->variant->id],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.name_i18n.ru', 'Соус')
            ->assertJsonPath('data.modifiers.1.price_delta', '3000.00');
        $groupId = $response->json('data.id');
        $ketchupId = $response->json('data.modifiers.0.id');

        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/modifier-groups/{$groupId}", [
            'name' => 'Sous tanlang',
            'modifiers' => [['id' => $ketchupId, 'name' => 'Ketchup', 'price_delta' => 500]],
        ])->assertOk()->assertJsonCount(1, 'data.modifiers');
        $this->assertSoftDeleted('modifiers', ['name' => 'Pishloqli']);

        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/products/{$this->product->id}/modifier-groups", [
            'groups' => [['modifier_group_id' => $groupId, 'max_select' => 1, 'is_required' => false]],
        ])->assertOk()->assertJsonPath('data.modifier_groups.0.product_rules.max_select', 1);

        $this->assertDatabaseHas('product_modifier_groups', ['product_id' => $this->product->id, 'modifier_group_id' => $groupId, 'max_select' => 1, 'is_required' => false]);
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/modifier-groups')->assertOk()->assertJsonPath('data.0.product_ids.0', $this->product->id);
    }

    public function test_modifier_endpoints_respect_permissions_and_the_store(): void
    {
        $group = $this->makeGroup('Sous', ['Ketchup' => 0]);
        $otherStore = $this->makeStore('Other Burger');
        $otherOwner = $this->makeStaff(\App\Enums\UserRole::OWNER, null, $otherStore);

        // A manager reads but may not write (menu.manage is the owner's)
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/owner/modifier-groups', ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->manager, 'sanctum')->putJson("/api/v1/owner/products/{$this->product->id}/modifier-groups", ['groups' => []])->assertForbidden();
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/owner/modifier-groups')->assertForbidden();

        // Another store sees none of it and cannot touch it
        $this->actingAs($otherOwner, 'sanctum')->getJson('/api/v1/owner/modifier-groups')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($otherOwner, 'sanctum')->putJson("/api/v1/owner/modifier-groups/{$group->id}", ['name' => 'Hack'])->assertNotFound();
        $this->actingAs($otherOwner, 'sanctum')->deleteJson("/api/v1/owner/modifier-groups/{$group->id}")->assertNotFound();
        $this->actingAs($otherOwner, 'sanctum')->putJson("/api/v1/owner/products/{$this->product->id}/modifier-groups", ['groups' => []])->assertNotFound();

        // Nor attach this store's group to its own product, nor link this store's variant
        $otherProduct = \App\Models\Product::create(['store_id' => $otherStore->id, 'category_id' => $this->product->category_id, 'name' => 'O', 'slug' => 'o-' . uniqid(), 'status' => 'active']);
        $this->actingAs($otherOwner, 'sanctum')->putJson("/api/v1/owner/products/{$otherProduct->id}/modifier-groups", [
            'groups' => [['modifier_group_id' => $group->id]],
        ])->assertStatus(422);
        $this->actingAs($otherOwner, 'sanctum')->postJson('/api/v1/owner/modifier-groups', [
            'name' => 'Y', 'modifiers' => [['name' => 'Z', 'product_variant_id' => $this->variant->id]],
        ])->assertStatus(422);

        $this->assertSame('Sous', $group->fresh()->name);
    }
}
