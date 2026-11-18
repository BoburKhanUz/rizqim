<?php

namespace Tests\Feature\PosBooking;

use App\Domain\Pos\Orders\PosOrderService;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Wave M4 (residual H3): a modifier id of another store is not found at the id lookup (booking items, a booking's held
 * lines), before the product's modifier rules or any price see it. Even a product wrongly linked to a foreign store's
 * modifier group never prices that modifier.
 */
class ForeignModifierLookupTest extends TestCase
{
    use RefreshDatabase;
    use PosBookingFixtures;

    private ProductVariant $osh;

    private Modifier $foreignModifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBookings();

        $category = Category::create(['name' => 'Osh', 'slug' => 'osh-' . Str::random(5), 'is_active' => true]);
        $product = Product::create(['store_id' => $this->store->id, 'category_id' => $category->id, 'name' => 'Osh', 'slug' => 'osh-' . Str::random(5), 'status' => 'active']);
        $this->osh = ProductVariant::create(['product_id' => $product->id, 'name' => 'Porsiya', 'price' => 50000, 'stock' => 50, 'is_active' => true, 'uuid' => (string) Str::uuid()]);

        $this->foreignModifier = $this->modifierOf($this->foreignStore, 'Begona qazi');
        // The worst case: the product is (wrongly) linked to the foreign group, so its rules would accept the modifier
        DB::table('product_modifier_groups')->insert([
            'product_id' => $product->id, 'modifier_group_id' => $this->foreignModifier->modifier_group_id,
            'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function modifierOf(Store $store, string $name): Modifier
    {
        $group = ModifierGroup::create(['store_id' => $store->id, 'name' => "Qo'shimcha", 'is_active' => true]);

        return Modifier::create(['modifier_group_id' => $group->id, 'name' => $name, 'price_delta' => 25000, 'max_quantity' => 2, 'is_active' => true]);
    }

    public function test_a_booking_item_with_a_foreign_modifier_is_refused_at_the_lookup(): void
    {
        $uuid = $this->booked();

        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/items", [
            'product_variant_id' => $this->osh->id, 'quantity' => 1, 'modifiers' => [['modifier_id' => $this->foreignModifier->id]],
        ])->assertStatus(422)->assertJsonPath('errors.modifiers.0', "Qo'shimcha topilmadi.");
        $this->assertSame(0, DB::table('booking_items')->count());

        // The store's own modifier still works
        $own = $this->modifierOf($this->store, 'Qazi');
        DB::table('product_modifier_groups')->insert([
            'product_id' => $this->osh->product_id, 'modifier_group_id' => $own->modifier_group_id,
            'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/items", [
            'product_variant_id' => $this->osh->id, 'quantity' => 1, 'modifiers' => [['modifier_id' => $own->id]],
        ])->assertCreated()->assertJsonPath('data.items.0.modifiers.0.quantity', 1);
    }

    public function test_held_lines_with_a_foreign_modifier_are_refused_at_the_lookup(): void
    {
        $service = app(PosOrderService::class);
        $heldLines = new \ReflectionMethod($service, 'heldLines');

        try {
            $heldLines->invoke($service, $this->branch, [
                ['product_variant_id' => (int) $this->osh->id, 'quantity' => '1', 'modifiers' => [['modifier_id' => (int) $this->foreignModifier->id]]],
            ]);
            $this->fail('A foreign modifier id was mapped to a held line.');
        } catch (ValidationException $e) {
            $this->assertSame(['items.0.modifiers' => ["Qo'shimcha topilmadi."]], $e->errors());
        }

        $lines = $heldLines->invoke($service, $this->branch, [['product_variant_id' => (int) $this->osh->id, 'quantity' => '2']]);
        $this->assertCount(1, $lines);
        $this->assertSame(strtolower($this->osh->uuid), $lines[0]['variant_uuid']);
    }
}
