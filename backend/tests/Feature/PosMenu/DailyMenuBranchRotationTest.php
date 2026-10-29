<?php

namespace Tests\Feature\PosMenu;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchProductRotation;
use App\Models\DailyMenu;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * P6-15 review fix (S#1, C#L2): the daily rotation is per branch like the menu itself. A manager bound to a branch
 * cannot stop or free dishes in another branch (not by the rotation call, not by saving their own menu), and one
 * branch's menu never touches another branch's availability.
 */
class DailyMenuBranchRotationTest extends TestCase
{
    use PosMenuFixtures;
    use RefreshDatabase;

    protected Product $lagman;

    protected Product $mastava;

    protected Product $blackTea;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosHall();
        $this->enableMenuModules();
        $this->enableMenuModules($this->otherBranch);
        // Lag'mon and Mastava rotate on both branches; the tea rotates nowhere
        $this->lagman = $this->dish('Lag\'mon', '28000', true);
        $this->mastava = $this->dish('Mastava', '22000', true);
        $this->rotate($this->lagman, $this->otherBranch);
        $this->rotate($this->mastava, $this->otherBranch);
        $this->blackTea = $this->dish('Qora choy', '3000');
    }

    /** @return list<int> */
    private function stoppedAt(Branch $branch): array
    {
        $branches = $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/catalog/availability?store_id={$this->store->id}")->assertOk()->json('data.branches');

        return collect($branches)->firstWhere('branch_id', $branch->id)['stopped']['products'];
    }

    /** @return list<int> */
    private function rotating(Branch $branch): array
    {
        return BranchProductRotation::where('branch_id', $branch->id)->orderBy('product_id')->pluck('product_id')->map(fn ($id) => (int) $id)->all();
    }

    private function putMenu(Branch $branch, array $productIds, $as = null)
    {
        return $this->actingAs($as ?? $this->owner, 'sanctum')->putJson('/api/v1/owner/daily-menu', ['branch_id' => $branch->id, 'product_ids' => $productIds]);
    }

    public function test_a_branch_manager_changes_the_rotation_of_their_own_branch_only(): void
    {
        $this->putMenu($this->otherBranch, [$this->lagman->id])->assertOk();
        $this->assertSame([$this->mastava->id], $this->stoppedAt($this->otherBranch));

        // The manager (stoplist.manage, no menu.manage, bound to $this->branch) rotates the tea in their own branch
        $this->actingAs($this->manager, 'sanctum')->putJson('/api/v1/owner/daily-menu/rotation', [
            'product_ids' => [$this->lagman->id, $this->mastava->id, $this->blackTea->id],
        ])->assertOk()->assertJsonPath('data.branch_id', $this->branch->id);

        $this->assertSame([$this->lagman->id, $this->mastava->id, $this->blackTea->id], $this->rotating($this->branch));
        // The other branch is as it was: the tea is still on sale there, the menu there still stops Mastava only
        $this->assertSame([$this->lagman->id, $this->mastava->id], $this->rotating($this->otherBranch));
        $this->assertNotContains($this->blackTea->id, $this->stoppedAt($this->otherBranch));
        $this->assertSame([$this->mastava->id], $this->stoppedAt($this->otherBranch));

        // Clearing the manager's rotation does not free the other branch's off-menu dish
        $this->actingAs($this->manager, 'sanctum')->putJson('/api/v1/owner/daily-menu/rotation', ['product_ids' => []])->assertOk();
        $this->assertSame([], $this->rotating($this->branch));
        $this->assertContains($this->mastava->id, $this->stoppedAt($this->otherBranch));
    }

    public function test_a_branch_manager_cannot_address_another_branch(): void
    {
        $this->actingAs($this->manager, 'sanctum')->putJson('/api/v1/owner/daily-menu/rotation', ['branch_id' => $this->otherBranch->id, 'product_ids' => []])->assertForbidden();
        $this->putMenu($this->otherBranch, [], $this->manager)->assertForbidden();
        $this->assertSame([$this->lagman->id, $this->mastava->id], $this->rotating($this->otherBranch));

        // A foreign branch id is a 404, not a store-wide change
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/daily-menu/rotation', ['branch_id' => 999999, 'product_ids' => []])->assertNotFound();
        // The cook has no right at all
        $this->actingAs($this->makeCook(), 'sanctum')->putJson('/api/v1/owner/daily-menu/rotation', ['product_ids' => []])->assertForbidden();
        $this->assertCount(2, $this->rotating($this->branch));
    }

    public function test_the_owner_names_the_branch_and_it_is_audited(): void
    {
        // A store-wide caller must name the branch: there is no store-wide rotation any more
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/daily-menu/rotation', ['product_ids' => []])->assertStatus(422);
        $this->assertCount(2, $this->rotating($this->branch));

        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/daily-menu/rotation', ['branch_id' => $this->otherBranch->id, 'product_ids' => [$this->lagman->id]])
            ->assertOk()->assertJsonPath('data.product_ids', [$this->lagman->id]);

        $this->assertSame([$this->lagman->id], $this->rotating($this->otherBranch));
        $this->assertSame([$this->lagman->id, $this->mastava->id], $this->rotating($this->branch));
        $log = AuditLog::where('action', 'menu.daily_rotation_saved')->firstOrFail();
        $this->assertSame($this->otherBranch->id, (int) $log->branch_id);
    }

    public function test_saving_one_branchs_menu_never_makes_a_dish_rotate_in_another(): void
    {
        $this->putMenu($this->otherBranch, [$this->lagman->id])->assertOk();
        $this->assertNotContains($this->blackTea->id, $this->stoppedAt($this->otherBranch));

        // The manager saves their OWN branch's menu with the tea on it
        $this->actingAs($this->manager, 'sanctum')->putJson('/api/v1/owner/daily-menu', ['product_ids' => [$this->blackTea->id]])->assertOk();

        $this->assertContains($this->blackTea->id, $this->rotating($this->branch));
        $this->assertNotContains($this->blackTea->id, $this->rotating($this->otherBranch));
        $this->assertNotContains($this->blackTea->id, $this->stoppedAt($this->otherBranch));
        // ...and in their own branch the tea is on the menu while the dishes left off are stopped
        $this->assertEqualsCanonicalizing([$this->lagman->id, $this->mastava->id], $this->stoppedAt($this->branch));
    }

    public function test_branch_b_is_not_affected_by_branch_as_menu_at_checkout_and_in_the_panel(): void
    {
        $this->putMenu($this->branch, [$this->lagman->id])->assertOk();
        $variant = $this->mastava->variants->first();

        // Branch A's menu has no Mastava: A refuses it. Branch B has no menu for the day: B sells everything
        $this->order([['variant_id' => $variant->uuid, 'quantity' => 1]], ['branch_id' => $this->branch->id])->assertStatus(422);
        $this->assertSame([], $this->stoppedAt($this->otherBranch));
        $this->assertSame(0, DailyMenu::where('branch_id', $this->otherBranch->id)->count());

        // The panel of each branch shows that branch's own rotation flags
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/daily-menu/rotation', ['branch_id' => $this->branch->id, 'product_ids' => [$this->lagman->id]])->assertOk();
        $panel = fn (Branch $branch) => collect($this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/daily-menu?branch_id={$branch->id}")->assertOk()->json('data.products'))->keyBy('id');
        $this->assertFalse($panel($this->branch)[$this->mastava->id]['daily_rotation']);
        $this->assertTrue($panel($this->otherBranch)[$this->mastava->id]['daily_rotation']);
    }

    public function test_the_migration_moves_the_store_wide_flags_to_every_branch_of_the_store(): void
    {
        $migration = require database_path('migrations/2026_11_11_300100_create_branch_product_rotation_table.php');

        // Back to the old shape: a store-wide flag on the dishes
        $migration->down();
        $this->assertTrue(Schema::hasColumn('products', 'daily_rotation'));
        $this->assertFalse(Schema::hasTable('branch_product_rotation'));
        DB::table('products')->update(['daily_rotation' => false]);
        DB::table('products')->where('id', $this->lagman->id)->update(['daily_rotation' => true]);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('products', 'daily_rotation'));
        $this->assertSame([$this->lagman->id], $this->rotating($this->branch));
        $this->assertSame([$this->lagman->id], $this->rotating($this->otherBranch));
        $this->assertSame(0, BranchProductRotation::where('product_id', $this->blackTea->id)->count());
    }
}
