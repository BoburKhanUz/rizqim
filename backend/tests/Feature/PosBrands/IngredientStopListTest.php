<?php

namespace Tests\Feature\PosBrands;

use App\Enums\StopListSubject;
use App\Events\Broadcast\MenuAvailabilityChangedBroadcast;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\StopListEntry;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P6-55 / B9: the ingredient stop-list. "Tovuq tugadi" on a branch closes every dish critically linked to the
 * ingredient at once — in every brand and on every channel, through the existing availability pipeline (catalogue,
 * the till's menu, checkout) — and reopening restores them; a dish stopped by its own entry stays stopped; `until`
 * expires as for a dish; module gate, permissions and tenant isolation.
 */
class IngredientStopListTest extends TestCase
{
    use RefreshDatabase;
    use DarkKitchenFixtures;

    private \App\Models\Ingredient $chicken;

    /** @var array<string, array{0: \App\Models\Product, 1: \App\Models\ProductVariant}> */
    private array $dishes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDarkKitchen();

        $burger = $this->makeBrand('Burger Bro', 'BB');
        $lavash = $this->makeBrand('Lavash Lab', 'LL', ['color' => '#16A34A']);
        $this->dishes = [
            'burger' => $this->brandDish($burger, 'Tovuq burger', '45000'),
            'lavash' => $this->brandDish($lavash, 'Tovuq lavash', '38000'),
            'house' => $this->brandDish(null, 'Tovuq sho\'rva', '25000'),
            'salad' => $this->brandDish($lavash, 'Sezar salat', '30000'),
            'beef' => $this->brandDish($burger, 'Mol go\'shti burger', '52000'),
        ];
        $this->chicken = $this->makeIngredient('Tovuq');
        $this->chicken->products()->attach([
            $this->dishes['burger'][0]->id => ['is_critical' => true],
            $this->dishes['lavash'][0]->id => ['is_critical' => true],
            $this->dishes['house'][0]->id => ['is_critical' => true],
            $this->dishes['salad'][0]->id => ['is_critical' => false],
        ]);
    }

    private function stopChicken(array $extra = [], ?\App\Models\User $as = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as ?? $this->manager, 'sanctum')->postJson("/api/v1/owner/ingredients/{$this->chicken->id}/stop", $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function availability(?int $branchId = null): array
    {
        return $this->getJson("/api/v1/catalog/availability?store_id={$this->store->id}&branch_id=" . ($branchId ?? $this->branch->id))->assertOk()->json('data.branches.0');
    }

    private function buy(string $dish, string $channel = 'telegram'): \Illuminate\Testing\TestResponse
    {
        $items = [['variant_id' => $this->dishes[$dish][1]->uuid, 'quantity' => 1]];

        // A fresh customer each time: checkout is rate limited per customer
        return $channel === 'mobile' ? $this->mobileOrder($items, [], $this->makeCustomer()) : $this->order($items, [], $this->makeCustomer());
    }

    public function test_tovuq_tugadi_closes_every_brand_and_channel_at_once_and_reopening_restores_them(): void
    {
        Event::fake([MenuAvailabilityChangedBroadcast::class]);

        $closed = [$this->dishes['burger'][0]->id, $this->dishes['lavash'][0]->id, $this->dishes['house'][0]->id];
        sort($closed);
        $this->stopChicken()->assertCreated()
            ->assertJsonPath('data.branch_id', $this->branch->id)
            ->assertJsonPath('data.closed_product_ids', $closed);

        Event::assertDispatched(MenuAvailabilityChangedBroadcast::class, function (MenuAvailabilityChangedBroadcast $event) use ($closed) {
            return $event->reason === 'stop_list' && $event->branchId === $this->branch->id
                && collect($event->items)->where('type', 'product')->pluck('id')->sort()->values()->all() === $closed
                && collect($event->items)->every(fn (array $item) => $item['available'] === false)
                && collect($event->items)->last() === ['type' => 'ingredient', 'id' => $this->chicken->id, 'available' => false];
        });

        // The availability (catalogue and the till): the three dishes are stopped with the reason, the salad is marked
        $branch = $this->availability();
        $this->assertEqualsCanonicalizing($closed, $branch['stopped']['products']);
        $this->assertSame(['Tovuq tugadi'], collect($branch['stop_list'])->pluck('reason')->unique()->values()->all());
        $this->assertSame([['id' => StopListEntry::first()->id, 'ingredient_id' => $this->chicken->id, 'name' => 'Tovuq', 'reason' => null, 'until' => null]], $branch['stopped_ingredients']);
        $this->assertSame([['product_id' => $this->dishes['salad'][0]->id, 'ingredient_ids' => [$this->chicken->id]]], $branch['limited_products']);
        // Another branch is untouched
        $this->assertSame([], $this->availability($this->otherBranch->id)['stopped']['products']);

        // Checkout refuses them on every channel (Mini App and the mobile app), the others still sell
        foreach (['burger', 'lavash', 'house'] as $dish) {
            $this->buy($dish)->assertStatus(422);
            $this->buy($dish, 'mobile')->assertStatus(422);
        }
        $this->buy('salad')->assertCreated();
        $this->buy('beef')->assertCreated();
        $this->order([['variant_id' => $this->dishes['burger'][1]->uuid, 'quantity' => 1]], ['branch_id' => $this->otherBranch->id], $this->makeCustomer())->assertCreated();

        // The till's menu (POS channels) shows them stopped with the reason
        $menu = collect($this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/pos/catalog?channel=pos_takeaway')->assertOk()->json('data.products'))->keyBy('id');
        $this->assertFalse($menu[$this->dishes['lavash'][0]->id]['is_available']);
        $this->assertSame('stopped', $menu[$this->dishes['lavash'][0]->id]['unavailable_reason']);
        $this->assertTrue($menu[$this->dishes['beef'][0]->id]['is_available']);

        // The generic stop-list shows the ingredient entry
        $entries = $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/stop-list')->assertOk()->json('data.entries');
        $this->assertSame([['type' => 'ingredient', 'name' => 'Tovuq']], array_map(fn (array $entry) => ['type' => $entry['type'], 'name' => $entry['name']], $entries));

        // Reopen: everything comes back
        $this->actingAs($this->manager, 'sanctum')->deleteJson("/api/v1/owner/ingredients/{$this->chicken->id}/stop")->assertOk();
        $this->assertSame([], $this->availability()['stopped']['products']);
        $this->assertSame([], $this->availability()['stopped_ingredients']);
        foreach (['burger', 'lavash', 'house'] as $dish) {
            $this->buy($dish)->assertCreated();
        }
        Event::assertDispatched(MenuAvailabilityChangedBroadcast::class, fn (MenuAvailabilityChangedBroadcast $event) => collect($event->items)->every(fn (array $item) => $item['available'] === true) && count($event->items) === 4);
        $this->actingAs($this->manager, 'sanctum')->deleteJson("/api/v1/owner/ingredients/{$this->chicken->id}/stop")->assertNotFound();

        $this->assertSame(['menu.stop_list_added', 'menu.stop_list_removed'], AuditLog::where('store_id', $this->store->id)->where('action', 'like', 'menu.stop_list_%')->orderBy('id')->pluck('action')->all());
        $this->assertSame('Tovuq', AuditLog::where('action', 'menu.stop_list_added')->first()->payload_json['name']);
    }

    public function test_a_dish_stopped_by_its_own_entry_stays_stopped_when_the_ingredient_reopens(): void
    {
        $burger = $this->dishes['burger'][0];
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/owner/stop-list', ['type' => 'product', 'id' => $burger->id, 'reason' => 'Bulka yo\'q'])->assertCreated();
        $this->stopChicken(['reason' => 'Tovuq kelmadi'])->assertCreated();

        $branch = $this->availability();
        $rows = collect($branch['stop_list'])->keyBy('subject_id');
        $this->assertSame("Bulka yo'q", $rows[$burger->id]['reason']); // its own entry keeps its reason
        $this->assertSame('Tovuq kelmadi', $rows[$this->dishes['lavash'][0]->id]['reason']);
        $this->assertSame(1, collect($branch['stopped']['products'])->filter(fn ($id) => $id === $burger->id)->count());

        $this->actingAs($this->manager, 'sanctum')->deleteJson("/api/v1/owner/ingredients/{$this->chicken->id}/stop")->assertOk();
        $this->assertSame([$burger->id], $this->availability()['stopped']['products']);
        $this->buy('burger')->assertStatus(422);
        $this->buy('lavash')->assertCreated();
    }

    public function test_until_expires_by_itself_as_for_a_dish(): void
    {
        $this->stopChicken(['until' => now()->addMinutes(30)->toIso8601String()])->assertCreated();
        $this->stopChicken(['until' => now()->subMinute()->toIso8601String()])->assertStatus(422);
        $this->buy('lavash')->assertStatus(422);

        $this->travel(31)->minutes();
        $this->buy('lavash')->assertCreated();

        Event::fake([MenuAvailabilityChangedBroadcast::class]);
        $this->artisan('menu:expire-stop-list')->assertSuccessful();
        $this->assertSame(0, StopListEntry::count());
        Event::assertDispatched(MenuAvailabilityChangedBroadcast::class, fn ($event) => collect($event->items)->where('type', 'product')->count() === 3
            && collect($event->items)->contains(['type' => 'ingredient', 'id' => $this->chicken->id, 'available' => true]));
    }

    public function test_the_module_gates_the_stop_but_never_the_reopening(): void
    {
        $this->useDarkKitchen($this->branch, ['ingredient_stoplist' => false]);

        $this->stopChicken()->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        // The generic stop-list endpoint asks the same
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/owner/stop-list', ['type' => 'ingredient', 'id' => $this->chicken->id])
            ->assertForbidden();
        $this->assertSame(0, StopListEntry::count());

        // Stopped while the module was on, reopened after it was switched off
        $this->useDarkKitchen($this->branch);
        $this->stopChicken()->assertCreated();
        $this->useDarkKitchen($this->branch, ['ingredient_stoplist' => false]);
        $this->actingAs($this->manager, 'sanctum')->deleteJson("/api/v1/owner/ingredients/{$this->chicken->id}/stop")->assertOk();
        $this->buy('lavash')->assertCreated();
    }

    public function test_ingredients_and_links_are_managed_by_the_owner_and_audited(): void
    {
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/ingredients', ['name' => 'Pishloq', 'unit' => 'kg'])->assertCreated()
            ->assertJsonPath('data.name', 'Pishloq')->assertJsonPath('data.links', []);
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/ingredients', ['name' => 'tovuq'])->assertStatus(422);
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/owner/ingredients', ['name' => 'Non'])->assertForbidden(); // menu.manage

        $id = $this->chicken->id;
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/ingredients/{$id}/products", ['links' => [
            ['product_id' => $this->dishes['beef'][0]->id, 'is_critical' => true],
            ['product_id' => $this->dishes['salad'][0]->id],
        ]])->assertOk()->assertJsonPath('data.links.0.product_id', $this->dishes['salad'][0]->id)->assertJsonCount(2, 'data.links');

        // Bulk by category: every dish of a category (and its subcategories)
        $parent = Category::create(['name' => 'Lavashlar', 'slug' => 'lavash-' . Str::random(5), 'is_active' => true]);
        $child = Category::create(['name' => 'Mini lavash', 'slug' => 'mini-' . Str::random(5), 'is_active' => true, 'parent_id' => $parent->id]);
        $this->dishes['lavash'][0]->update(['category_id' => $parent->id]);
        $mini = $this->makeProduct('Mini lavash', ['category_id' => $child->id]);
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/ingredients/{$id}/link-categories", ['category_ids' => [$parent->id]])
            ->assertOk()->assertJsonPath('data.added', 2)->assertJsonCount(4, 'data.links');
        $this->assertTrue((bool) $this->chicken->products()->whereKey($mini->id)->first()->pivot->is_critical);

        $list = $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/ingredients')->assertOk();
        $this->assertSame(['Pishloq', 'Tovuq'], $list->json('data.ingredients.*.name'));
        $this->stopChicken()->assertCreated();
        $this->assertTrue($this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/ingredients')->json('data.ingredients.1.stopped_here'));

        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/ingredients/{$id}", ['name' => 'Tovuq go\'shti'])->assertOk();
        // Deleting reopens the dishes
        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/v1/owner/ingredients/{$id}")->assertOk();
        $this->assertSame([], $this->availability()['stopped']['products']);
        $this->assertSame(0, StopListEntry::where('subject_type', StopListSubject::INGREDIENT->value)->count());

        $this->assertSame(
            ['ingredient.created', 'ingredient.links_changed', 'ingredient.links_changed', 'ingredient.updated', 'ingredient.deleted'],
            AuditLog::where('store_id', $this->store->id)->where('action', 'like', 'ingredient.%')->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_another_stores_ingredient_or_dish_is_not_found(): void
    {
        $otherStore = $this->makeStore('Other Kitchen');
        $otherOwner = $this->makeStaff(UserRole::OWNER, null, $otherStore);
        $this->useDarkKitchen($this->makeBranch($otherStore, 'Other main'));

        $this->actingAs($otherOwner, 'sanctum')->postJson("/api/v1/owner/ingredients/{$this->chicken->id}/stop", ['branch_id' => $this->branch->id])->assertNotFound();
        $this->actingAs($otherOwner, 'sanctum')->patchJson("/api/v1/owner/ingredients/{$this->chicken->id}", ['name' => 'X'])->assertNotFound();
        $this->actingAs($otherOwner, 'sanctum')->deleteJson("/api/v1/owner/ingredients/{$this->chicken->id}")->assertNotFound();
        $this->assertSame([], $this->actingAs($otherOwner, 'sanctum')->getJson('/api/v1/owner/ingredients')->json('data.ingredients'));

        // A dish of another store cannot be linked; an owner cannot stop on another store's branch
        $foreign = \App\Models\Product::create(['store_id' => $otherStore->id, 'category_id' => $this->product->category_id, 'name' => 'Begona', 'slug' => 'begona-' . Str::random(5), 'status' => 'active']);
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/ingredients/{$this->chicken->id}/products", ['links' => [['product_id' => $foreign->id]]])->assertStatus(422);
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/stop-list", ['type' => 'ingredient', 'id' => $this->chicken->id, 'branch_id' => $this->branch->id])->assertCreated();

        // A manager bound to the other branch stops only there
        $otherManager = $this->makeStaff(UserRole::MANAGER, $this->otherBranch);
        $this->actingAs($otherManager, 'sanctum')->postJson("/api/v1/owner/ingredients/{$this->chicken->id}/stop", ['branch_id' => $this->branch->id])->assertForbidden();
        $this->actingAs($otherManager, 'sanctum')->postJson("/api/v1/owner/ingredients/{$this->chicken->id}/stop")->assertCreated()->assertJsonPath('data.branch_id', $this->otherBranch->id);
    }
}
