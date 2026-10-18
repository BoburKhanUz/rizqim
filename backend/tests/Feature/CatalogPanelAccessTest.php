<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The catalog is written by the store owner only, in the panel as in the API (/owner/products is owner-only); a
 * manager reads it and switches items on or off for their own branch.
 */
class CatalogPanelAccessTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    public function test_the_owner_creates_and_edits_products_in_the_panel(): void
    {
        $tenant = $this->store->uuid;

        $this->actingAs($this->owner)->get("/admin/{$tenant}/products")->assertOk()->assertSee('Cheeseburger');
        $this->actingAs($this->owner)->get("/admin/{$tenant}/products/create")->assertOk();
        $this->actingAs($this->owner)->get("/admin/{$tenant}/products/{$this->product->getRouteKey()}/edit")->assertOk();

        $this->assertTrue($this->owner->can('delete', $this->product));
    }

    public function test_a_manager_reads_the_catalog_but_cannot_change_it(): void
    {
        $tenant = $this->store->uuid;

        $this->actingAs($this->manager)->get("/admin/{$tenant}/products")->assertOk()->assertSee('Cheeseburger');
        $this->actingAs($this->manager)->get("/admin/{$tenant}/products/create")->assertForbidden();
        $this->actingAs($this->manager)->get("/admin/{$tenant}/products/{$this->product->getRouteKey()}/edit")->assertForbidden();

        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->store);
        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$this->product])
            ->assertActionHidden('create')
            ->assertTableActionHidden('edit', $this->product);

        $this->assertFalse(ProductResource::canCreate());
        foreach (['update', 'delete', 'restore', 'forceDelete'] as $ability) {
            $this->assertFalse($this->manager->can($ability, $this->product), $ability);
        }
    }

    public function test_a_manager_still_switches_items_on_and_off_for_their_branch(): void
    {
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/owner/branch-variants', [
            'branch_id' => $this->branch->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'is_active' => false,
            'hidden_reason' => 'Tugadi',
        ])->assertOk();

        $this->assertDatabaseHas('branch_product_variants', ['branch_id' => $this->branch->id, 'variant_id' => $this->variant->id, 'is_active' => false]);
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/branch-variants')
            ->assertOk()
            ->assertJsonPath('data.0.variants.0.is_active', false);
    }
}
