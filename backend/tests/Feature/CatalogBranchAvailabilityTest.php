<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The catalog is written by the store owner (menu.manage); a manager switches items on or off for their own branch.
 */
class CatalogBranchAvailabilityTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
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
