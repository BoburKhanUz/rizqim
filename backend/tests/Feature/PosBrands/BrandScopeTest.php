<?php

namespace Tests\Feature\PosBrands;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\BranchBrand;
use App\Models\StopListEntry;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6 wave 5 review fixes (H3) on the dark kitchen pages.
 *  - S3: switching a brand on or off needs menu.manage on each branch sent: a manager bound to one branch (granted
 *    menu.manage) switches their own branch only;
 *  - I3: GET /owner/ingredients lists where an ingredient is stopped on the caller's branch only when they are bound
 *    to one (the owner sees every branch);
 *  - I4: a brand's colour (#RRGGBB) is validated and stored on create and on edit.
 */
class BrandScopeTest extends TestCase
{
    use RefreshDatabase;
    use DarkKitchenFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDarkKitchen();
    }

    public function test_a_branch_bound_manager_switches_a_brand_on_their_own_branch_only(): void
    {
        app(PermissionService::class)->setOverride($this->store, UserRole::MANAGER, Permission::MENU_MANAGE, true);
        $brand = $this->makeBrand('Burger Bro', 'BB');

        $this->actingAs($this->manager, 'sanctum')->putJson("/api/v1/owner/brands/{$brand->id}/branches", ['branches' => [['branch_id' => $this->otherBranch->id, 'is_active' => false]]])
            ->assertForbidden();
        $this->actingAs($this->manager, 'sanctum')->putJson("/api/v1/owner/brands/{$brand->id}/branches", ['branches' => [
            ['branch_id' => $this->branch->id, 'is_active' => false],
            ['branch_id' => $this->otherBranch->id, 'is_active' => false],
        ]])->assertForbidden();
        $this->assertSame(0, BranchBrand::where('brand_id', $brand->id)->count());

        $this->actingAs($this->manager, 'sanctum')->putJson("/api/v1/owner/brands/{$brand->id}/branches", ['branches' => [['branch_id' => $this->branch->id, 'is_active' => false]]])
            ->assertOk()->assertJsonPath('data.off_branch_ids', [$this->branch->id]);
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/brands/{$brand->id}/branches", ['branches' => [['branch_id' => $this->otherBranch->id, 'is_active' => false]]])
            ->assertOk()->assertJsonPath('data.off_branch_ids', [$this->branch->id, $this->otherBranch->id]);
        // Another store's branch: still a validation error
        $foreign = $this->makeBranch($this->makeStore('Begona'), 'Begona filial');
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/brands/{$brand->id}/branches", ['branches' => [['branch_id' => $foreign->id, 'is_active' => false]]])
            ->assertUnprocessable();
    }

    public function test_ingredients_list_stops_of_the_callers_branch_only(): void
    {
        $chicken = $this->makeIngredient('Tovuq');
        [$dish] = $this->brandDish(null, 'Tovuq lavash');
        $chicken->products()->attach([$dish->id => ['is_critical' => true]]);
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/ingredients/{$chicken->id}/stop", ['branch_id' => $this->branch->id])->assertCreated();
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/ingredients/{$chicken->id}/stop", ['branch_id' => $this->otherBranch->id])->assertCreated();
        $this->assertSame(2, StopListEntry::count());

        $owner = $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/ingredients')->assertOk();
        $this->assertEqualsCanonicalizing([$this->branch->id, $this->otherBranch->id], array_column($owner->json('data.ingredients.0.stopped'), 'branch_id'));

        $manager = $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/ingredients')->assertOk();
        $this->assertSame([$this->branch->id], array_column($manager->json('data.ingredients.0.stopped'), 'branch_id'));
        $this->assertTrue($manager->json('data.ingredients.0.stopped_here'));
    }

    public function test_a_brand_colour_is_validated_and_stored_on_create_and_edit(): void
    {
        $id = $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/brands', ['name' => 'Lavash Lab', 'short_code' => 'LL', 'color' => '#16a34a'])
            ->assertCreated()->assertJsonPath('data.color', '#16A34A')->json('data.id');
        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/brands/{$id}", ['color' => '#2563eb'])->assertOk()->assertJsonPath('data.color', '#2563EB');
        foreach (['blue', '#12345', '#1234567', '2563EB', '#GGGGGG'] as $bad) {
            $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/brands/{$id}", ['color' => $bad])->assertUnprocessable()->assertJsonValidationErrors('color');
        }
        $this->assertSame('#2563EB', \App\Models\Brand::findOrFail($id)->color);
    }
}
