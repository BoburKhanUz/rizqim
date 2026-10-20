<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Events\Broadcast\MenuAvailabilityChangedBroadcast;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\StopListEntry;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P1-21 branch stop-list: checkout refuses stopped items with a clear 422, staff changes are audited and broadcast
 * (menu.availability_changed), the catalog shows availability, `until` expires by itself; scoped by permission/branch.
 */
class MenuStopListTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use MenuFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    protected function stop(string $type, int $id, array $extra = [], ?\App\Models\User $as = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as ?? $this->manager, 'sanctum')->postJson('/api/v1/owner/stop-list', array_merge([
            'type' => $type,
            'id' => $id,
        ], $extra));
    }

    public function test_a_stopped_variant_is_refused_at_checkout_with_a_clear_message(): void
    {
        $this->stop('variant', $this->variant->id, ['reason' => 'tugadi'])->assertCreated()
            ->assertJsonPath('data.branch_id', $this->branch->id)
            ->assertJsonPath('data.name', 'Regular');

        $response = $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertStatus(422);
        $this->assertSame("Uzr, 'Cheeseburger (Regular)' hozir ushbu filialda mavjud emas (tugadi). Iltimos, savatni yangilang.", $response->json('errors.items.0'));
        $this->assertSame(10, $this->variant->fresh()->stock);

        // Only on that branch
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]], ['branch_id' => $this->otherBranch->id])->assertCreated();
    }

    public function test_a_stopped_product_and_a_stopped_modifier(): void
    {
        $sauce = $this->makeGroup('Sous', ['Ketchup' => 0, 'Pishloqli' => 3000]);
        $cheese = $this->modifier($sauce, 'Pishloqli');

        $this->stop('modifier', $cheese->id)->assertCreated();
        $response = $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1, 'modifiers' => [$cheese->uuid]]])->assertStatus(422);
        $this->assertSame(["Uzr, 'Pishloqli' hozir ushbu filialda mavjud emas. Iltimos, boshqasini tanlang."], $response->json('errors')['items.0.modifiers']);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1, 'modifiers' => [$this->modifier($sauce, 'Ketchup')->uuid]]])->assertCreated();

        $this->stop('product', $this->product->id)->assertCreated();
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertStatus(422);
    }

    public function test_changes_are_broadcast_to_staff_and_menu_channels_and_audited(): void
    {
        Event::fake([MenuAvailabilityChangedBroadcast::class]);

        $id = $this->stop('variant', $this->variant->id, ['reason' => 'tugadi'])->assertCreated()->json('data.id');

        Event::assertDispatched(MenuAvailabilityChangedBroadcast::class, function (MenuAvailabilityChangedBroadcast $event) {
            $channels = array_map(fn (Channel $channel) => ($channel instanceof PrivateChannel ? 'private:' : 'public:') . $channel->name, $event->broadcastOn());

            return $event->broadcastAs() === 'menu.availability_changed'
                && $event->broadcastQueue() === 'broadcasts'
                && $channels === ["private:private-store.{$this->store->id}", "private:private-branch.{$this->branch->id}", "public:menu.{$this->store->id}"]
                && $event->broadcastWith()['items'] === [['type' => 'variant', 'id' => $this->variant->id, 'available' => false]]
                && $event->broadcastWith()['reason'] === 'stop_list';
        });

        $this->actingAs($this->manager, 'sanctum')->deleteJson("/api/v1/owner/stop-list/{$id}")->assertOk();
        Event::assertDispatched(MenuAvailabilityChangedBroadcast::class, fn ($event) => $event->items[0]['available'] === true);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertCreated();

        $logs = AuditLog::where('store_id', $this->store->id)->where('action', 'like', 'menu.stop_list_%')->orderBy('id')->get();
        $this->assertSame(['menu.stop_list_added', 'menu.stop_list_removed'], $logs->pluck('action')->all());
        $this->assertSame($this->manager->id, $logs[0]->user_id);
        $this->assertSame($this->branch->id, $logs[0]->branch_id);
        $this->assertSame('tugadi', $logs[0]->payload_json['reason']);
        $this->assertSame('Cheeseburger (Regular)', $logs[0]->payload_json['name']);
    }

    public function test_the_catalog_and_the_availability_endpoint_show_it(): void
    {
        $this->stop('variant', $this->variant->id, ['reason' => 'tugadi', 'until' => now()->addHour()->toIso8601String()])->assertCreated();

        $branch = collect($this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$this->store->uuid}")
            ->assertOk()->json('data.availability'))->firstWhere('branch_id', $this->branch->id);
        $this->assertSame([$this->variant->id], $branch['stopped']['variants']);
        $this->assertSame('tugadi', $branch['stop_list'][0]['reason']);
        $this->assertNotNull($branch['stop_list'][0]['until']);

        $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog/availability?store_id={$this->store->id}&branch_id={$this->otherBranch->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.branches')
            ->assertJsonPath('data.branches.0.stopped.variants', []);
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/catalog/availability?store_id=nope')->assertNotFound();

        // The older per-branch switch shows up the same way
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/owner/branch-variants', [
            'branch_id' => $this->branch->id, 'product_id' => $this->product->id, 'product_variant_id' => $this->variant->id, 'is_active' => false,
        ])->assertOk();
        $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog/availability?store_id={$this->store->uuid}&branch_id={$this->branch->id}")
            ->assertJsonPath('data.branches.0.stopped.variants', [$this->variant->id]);
    }

    public function test_an_entry_with_until_expires_by_itself(): void
    {
        $this->stop('variant', $this->variant->id, ['until' => now()->addMinutes(30)->toIso8601String()])->assertCreated();
        $this->stop('variant', $this->variant->id, ['until' => now()->subMinute()->toIso8601String()])->assertStatus(422);

        $this->travel(31)->minutes();
        // Already ignored by checkout before the scheduler removes it
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertCreated();

        Event::fake([MenuAvailabilityChangedBroadcast::class]);
        $this->artisan('menu:expire-stop-list')->assertSuccessful();
        $this->assertSame(0, StopListEntry::count());
        Event::assertDispatched(MenuAvailabilityChangedBroadcast::class, fn ($event) => $event->items === [['type' => 'variant', 'id' => $this->variant->id, 'available' => true]]);
    }

    public function test_stop_list_endpoints_respect_permissions_branches_and_stores(): void
    {
        $otherManager = $this->makeStaff(UserRole::MANAGER, $this->otherBranch);
        $cook = $this->makeCook();
        $otherStore = $this->makeStore('Other Burger');
        $otherOwner = $this->makeStaff(UserRole::OWNER, null, $otherStore);
        $otherBranch = $this->makeBranch($otherStore, 'Other main');

        // A cook has no stoplist.manage
        $this->stop('variant', $this->variant->id, [], $cook)->assertForbidden();
        // A branch manager names another branch: refused; without a branch: their own
        $this->stop('variant', $this->variant->id, ['branch_id' => $this->branch->id], $otherManager)->assertForbidden();
        $id = $this->stop('variant', $this->variant->id, [], $otherManager)->assertCreated()->assertJsonPath('data.branch_id', $this->otherBranch->id)->json('data.id');
        $this->actingAs($this->manager, 'sanctum')->deleteJson("/api/v1/owner/stop-list/{$id}")->assertForbidden();
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/stop-list')->assertOk()->assertJsonCount(0, 'data.entries');
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/stop-list?branch_id={$this->otherBranch->id}")->assertOk()->assertJsonCount(1, 'data.entries');
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/stop-list')->assertStatus(422);

        // Another store can neither see nor stop this store's items
        $this->actingAs($otherOwner, 'sanctum')->deleteJson("/api/v1/owner/stop-list/{$id}")->assertNotFound();
        $this->actingAs($otherOwner, 'sanctum')->getJson("/api/v1/owner/stop-list?branch_id={$this->branch->id}")->assertNotFound();
        $this->stop('variant', $this->variant->id, ['branch_id' => $otherBranch->id], $otherOwner)->assertStatus(422);
        $this->stop('variant', $this->variant->id, ['branch_id' => $this->branch->id], $otherOwner)->assertNotFound();

        $this->assertSame(1, StopListEntry::count());
    }

    public function test_the_older_branch_switch_only_takes_the_products_own_variant(): void
    {
        $otherStore = $this->makeStore('Other Burger');
        $otherProduct = \App\Models\Product::create(['store_id' => $otherStore->id, 'category_id' => $this->product->category_id, 'name' => 'O', 'slug' => 'o-' . uniqid(), 'status' => 'active']);
        $foreignVariant = $this->makeVariant($otherProduct, '1000');

        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/owner/branch-variants', [
            'branch_id' => $this->branch->id, 'product_id' => $this->product->id, 'product_variant_id' => $foreignVariant->id, 'is_active' => false,
        ])->assertNotFound();
        $this->assertDatabaseMissing('branch_product_variants', ['variant_id' => $foreignVariant->id]);
    }

    public function test_the_stop_list_screen_in_the_store_panel(): void
    {
        $this->stop('variant', $this->variant->id, ['reason' => 'Muzlatgich buzildi'])->assertCreated();

        $this->panelAs($this->owner, 'stop-list')->assertOk()->assertSee('Muzlatgich buzildi');
        $this->panelAs($this->manager, 'stop-list')->assertOk()->assertSee('Muzlatgich buzildi');
        $this->panelAs($this->makeStaff(UserRole::MANAGER, $this->otherBranch), 'stop-list')->assertOk()->assertDontSee('Muzlatgich buzildi');

        \Filament\Facades\Filament::setCurrentPanel('admin');
        \Filament\Facades\Filament::setTenant($this->store);
        $this->actingAs($this->owner, 'web');
        \Livewire\Livewire::test(\App\Filament\Resources\StopList\Pages\ManageStopList::class)
            ->callAction('add', ['branch_id' => $this->otherBranch->id, 'type' => 'product', 'subject_id' => $this->product->id, 'reason' => 'Panel'])
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('stop_list_entries', ['branch_id' => $this->otherBranch->id, 'subject_type' => 'product', 'reason' => 'Panel']);

        $entry = StopListEntry::where('reason', 'Muzlatgich buzildi')->firstOrFail();
        \Livewire\Livewire::test(\App\Filament\Resources\StopList\Pages\ManageStopList::class)->callTableAction('remove', $entry);
        $this->assertModelMissing($entry);
    }
}
