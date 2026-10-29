<?php

namespace Tests\Feature\PosFormat;

use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosSettings;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\AutoGuestItem;
use App\Models\Branch;
use App\Models\MenuBatchPlan;
use App\Models\RolePermissionOverride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosBilling\PosBillingFixtures;
use Tests\TestCase;

/**
 * Review P6 S#2: `pos.module:<key>` gates the branch the caller acts in, never one the caller picks: a branch-bound
 * member or a PIN session naming another branch is refused (403 branch_forbidden) even where that branch has the
 * module; a malformed `branch_id` is 422 and another store's branch 404 — never the store-wide fallback, which stays
 * only for a store-wide caller naming no branch. On the route classes the review named: the daily menu rotation,
 * meal sets, deleting a batch plan, changing a choy-non item.
 */
class PosModuleBranchGateTest extends TestCase
{
    use PosBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosBilling();
        // The manager's own branch has none of the modules; the other branch has them all
        $this->modules($this->branch, ['daily_menu' => false, 'combo_meal' => false, 'batches' => false, 'guest_items' => false, 'queue_numbers' => false]);
        $this->modules($this->otherBranch, ['daily_menu' => true, 'combo_meal' => true, 'batches' => true, 'guest_items' => true, 'queue_numbers' => true]);
    }

    public function test_a_branch_bound_manager_is_gated_on_their_own_branch_whatever_branch_id_says(): void
    {
        $other = $this->otherBranch->id;

        // Their own branch (none named, or named): module_disabled
        $this->disabled($this->as($this->manager, 'PUT', '/api/v1/owner/daily-menu/rotation', ['product_ids' => []]), 'daily_menu');
        $this->disabled($this->as($this->manager, 'PUT', '/api/v1/owner/daily-menu/rotation', ['branch_id' => $this->branch->id, 'product_ids' => []]), 'daily_menu');
        $this->disabled($this->as($this->manager, 'GET', '/api/v1/owner/meal-sets'), 'combo_meal');

        // Naming the other branch (body or query) no longer passes the gate
        $this->forbidden($this->as($this->manager, 'PUT', "/api/v1/owner/daily-menu/rotation?branch_id={$other}", ['product_ids' => []]));
        $this->forbidden($this->as($this->manager, 'PUT', '/api/v1/owner/daily-menu/rotation', ['branch_id' => $other, 'product_ids' => []]));
        $this->forbidden($this->as($this->manager, 'GET', "/api/v1/owner/meal-sets?branch_id={$other}"));

        // A malformed id is refused, never read as "no branch" (the store-wide fallback)
        $this->as($this->manager, 'PUT', '/api/v1/owner/daily-menu/rotation', ['branch_id' => 0, 'product_ids' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->as($this->manager, 'GET', '/api/v1/owner/meal-sets?branch_id=abc')->assertUnprocessable()->assertJsonValidationErrors('branch_id');

        // Deleting their branch's batch plan by its id: their branch's gate, whatever they name
        $plan = $this->plan($this->branch);
        $this->disabled($this->as($this->manager, 'DELETE', "/api/v1/owner/batch-plans/{$plan->id}"), 'batches');
        $this->forbidden($this->as($this->manager, 'DELETE', "/api/v1/owner/batch-plans/{$plan->id}?branch_id={$other}"));
        $this->assertSame(MenuBatchPlan::PLANNED, $plan->refresh()->status);

        // Switched on for their branch: the same requests pass the gate
        $this->modules($this->branch, ['daily_menu' => true, 'batches' => true]);
        $this->as($this->manager, 'PUT', '/api/v1/owner/daily-menu/rotation', ['product_ids' => []])->assertOk();
        $this->as($this->manager, 'DELETE', "/api/v1/owner/batch-plans/{$plan->id}")->assertOk();
    }

    public function test_a_branch_bound_member_cannot_change_a_choy_non_item_through_another_branch(): void
    {
        // A store that lets managers change settings (settings.manage), still bound to their branch
        RolePermissionOverride::create(['store_id' => $this->store->id, 'role' => UserRole::MANAGER->value, 'permission' => Permission::SETTINGS_MANAGE->value, 'allowed' => true]);
        Cache::flush();
        $item = AutoGuestItem::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'product_variant_id' => $this->variant->id, 'quantity_per_guest' => '1', 'is_active' => true, 'sort_order' => 0]);

        $this->disabled($this->as($this->manager, 'PATCH', "/api/v1/owner/auto-guest-items/{$item->id}", ['is_active' => false]), 'guest_items');
        $this->forbidden($this->as($this->manager, 'PATCH', "/api/v1/owner/auto-guest-items/{$item->id}?branch_id={$this->otherBranch->id}", ['is_active' => false]));
        $this->forbidden($this->as($this->manager, 'PATCH', "/api/v1/owner/auto-guest-items/{$item->id}", ['branch_id' => $this->otherBranch->id, 'is_active' => false]));
        $this->assertTrue((bool) $item->refresh()->is_active);
    }

    public function test_a_pin_session_and_a_terminal_are_gated_on_the_terminal_branch(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'pos.module:queue_numbers'])->post('/api/v1/test/p6/gate', fn () => response()->json(['success' => true]));
        Route::middleware(['api', 'auth:device', 'pos.module:queue_numbers'])->post('/api/v1/test/p6/gate-device', fn () => response()->json(['success' => true]));

        // The cashier's PIN session on the till of the branch without the module
        $this->disabled($this->withBearer($this->cashierToken)->postJson('/api/v1/test/p6/gate'), 'queue_numbers');
        $this->forbidden($this->withBearer($this->cashierToken)->postJson('/api/v1/test/p6/gate', ['branch_id' => $this->otherBranch->id]));
        $this->forbidden($this->withBearer($this->till['token'])->postJson('/api/v1/test/p6/gate-device', ['branch_id' => $this->otherBranch->id]));

        // Even the owner's PIN session acts in the terminal's branch
        $ownerSession = $this->pinSession($this->till, $this->owner, '7319');
        $this->forbidden($this->withBearer($ownerSession)->postJson('/api/v1/test/p6/gate', ['branch_id' => $this->otherBranch->id]));

    }

    public function test_a_store_wide_owner_names_a_branch_or_gets_the_store_wide_answer(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'pos.module:queue_numbers'])->post('/api/v1/test/p6/owner', fn () => response()->json(['success' => true]));

        $this->disabled($this->as($this->owner, 'POST', '/api/v1/test/p6/owner', ['branch_id' => $this->branch->id]), 'queue_numbers');
        $this->as($this->owner, 'POST', '/api/v1/test/p6/owner', ['branch_id' => $this->otherBranch->id])->assertOk();
        // Nothing named: on while one branch of the store has it
        $this->as($this->owner, 'POST', '/api/v1/test/p6/owner')->assertOk();
        $this->as($this->owner, 'POST', '/api/v1/test/p6/owner', ['branch_id' => null])->assertOk();

        // A bad or foreign id is an error, not the store-wide fallback
        $foreign = $this->makeBranch($this->makeStore('Boshqa'), 'Boshqa filial');
        $this->modules($foreign, ['queue_numbers' => true]);
        $this->as($this->owner, 'POST', '/api/v1/test/p6/owner', ['branch_id' => $foreign->id])->assertNotFound()->assertJsonPath('data.reason', 'branch_not_found');
        $this->as($this->owner, 'POST', '/api/v1/test/p6/owner', ['branch_id' => 999999])->assertNotFound();
        $this->as($this->owner, 'POST', '/api/v1/test/p6/owner', ['branch_id' => -3])->assertUnprocessable()->assertJsonValidationErrors('branch_id');
        $this->as($this->owner, 'POST', '/api/v1/test/p6/owner', ['branch_id' => '1x'])->assertUnprocessable();
        $this->as($this->owner, 'POST', '/api/v1/test/p6/owner', ['branch_id' => [$this->otherBranch->id]])->assertUnprocessable();

        // The owner's meal sets of a foreign branch: 404 at the gate
        $this->as($this->owner, 'GET', "/api/v1/owner/meal-sets?branch_id={$foreign->id}")->assertNotFound();
        $this->as($this->owner, 'GET', "/api/v1/owner/meal-sets?branch_id={$this->otherBranch->id}")->assertOk();
    }

    private function plan(Branch $branch): MenuBatchPlan
    {
        return MenuBatchPlan::create([
            'store_id' => $this->store->id,
            'branch_id' => $branch->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'planned_at' => now()->addHour(),
            'business_date' => now('Asia/Tashkent')->toDateString(),
            'status' => MenuBatchPlan::PLANNED,
        ]);
    }

    /**
     * @param  array<string, bool>  $modules
     */
    private function modules(Branch $branch, array $modules): void
    {
        $branch->forceFill(['pos_settings' => PosSettings::withFormat($branch->pos_settings, FormatProfile::CHOYXONA, $modules)])->save();
        app()->forgetScopedInstances();
    }

    private function disabled(TestResponse $response, string $module): void
    {
        $response->assertForbidden()->assertJsonPath('code', 'module_disabled')->assertJsonPath('module', $module);
    }

    private function forbidden(TestResponse $response): void
    {
        $response->assertForbidden()->assertJsonPath('data.reason', 'branch_forbidden');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function as(User $user, string $method, string $uri, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->actingAs($user, 'sanctum')->json($method, $uri, $data);
    }
}
