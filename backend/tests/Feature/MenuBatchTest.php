<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Events\Broadcast\MenuAvailabilityChangedBroadcast;
use App\Models\AuditLog;
use App\Models\MenuBatch;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P1-21 batch / kazan stock: orders take from the open batch, "tugadi" at zero or without a batch for a tracked
 * product, cancellations give back, staff open/adjust/close with audit and broadcasts, scoped by permission and branch.
 */
class MenuBatchTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use MenuFixtures;

    protected Product $osh;
    protected ProductVariant $portion;
    protected ProductVariant $small;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->osh = $this->makeProduct('Osh', ['batch_tracked' => true]);
        $this->portion = $this->makeVariant($this->osh, '55000', ['name' => '1 porsiya']);
        $this->small = $this->makeVariant($this->osh, '45000', ['name' => '0.7 porsiya', 'batch_consumption' => '0.7']);
    }

    protected function openBatch(string $quantity, ?ProductVariant $variant = null, ?\App\Models\Branch $branch = null): MenuBatch
    {
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/batches', [
            'branch_id' => ($branch ?? $this->branch)->id,
            'product_id' => $this->osh->id,
            'product_variant_id' => $variant?->id,
            'quantity' => $quantity,
            'label' => '1-qozon',
        ])->assertCreated();

        return MenuBatch::latest('id')->firstOrFail();
    }

    public function test_a_tracked_product_without_an_open_batch_is_sold_out(): void
    {
        $response = $this->order([['variant_id' => $this->portion->uuid, 'quantity' => 1]])->assertStatus(422);

        $this->assertSame("Uzr, 'Osh (1 porsiya)' tugadi. Iltimos, savatni yangilang.", $response->json('errors.items.0'));
        $this->assertContains($this->portion->id, $this->availability()['sold_out']['variants']);
    }

    public function test_orders_take_portions_by_each_variants_share_until_the_kazan_is_empty(): void
    {
        $batch = $this->openBatch('3');
        Event::fake([MenuAvailabilityChangedBroadcast::class]);

        // 1 + 2 × 0.7 = 2.4 taken
        $this->order([
            ['variant_id' => $this->portion->uuid, 'quantity' => 1],
            ['variant_id' => $this->small->uuid, 'quantity' => 2],
        ])->assertCreated();
        $this->assertSame('0.600', $batch->fresh()->getRawOriginal('quantity_left'));

        // 0.6 left: neither a whole portion nor a 0.7 one can be sold any more, and the menus are told so
        Event::assertDispatched(MenuAvailabilityChangedBroadcast::class, function (MenuAvailabilityChangedBroadcast $event) {
            $ids = array_column($event->items, 'id');
            sort($ids);

            return $event->reason === 'batch' && $event->branchId === $this->branch->id
                && $ids === [$this->portion->id, $this->small->id] && !$event->items[0]['available'];
        });

        $response = $this->order([['variant_id' => $this->small->uuid, 'quantity' => 1]])->assertStatus(422);
        $this->assertSame("Uzr, 'Osh (0.7 porsiya)' tugadi. Iltimos, savatni yangilang.", $response->json('errors.items.0'));
        $this->assertEqualsCanonicalizing([$this->portion->id, $this->small->id], $this->availability()['sold_out']['variants']);

        // Another branch has its own (no) batch
        $this->order([['variant_id' => $this->portion->uuid, 'quantity' => 1]], ['branch_id' => $this->otherBranch->id])->assertStatus(422);
    }

    public function test_an_order_asking_more_than_is_left_is_refused_with_what_is_left(): void
    {
        $batch = $this->openBatch('2');

        $response = $this->order([['variant_id' => $this->portion->uuid, 'quantity' => 3]])->assertStatus(422);
        $this->assertSame("Uzr, 'Osh (1 porsiya)' yetarli emas (faqat 2 ta qoldi). Iltimos, savatni yangilang.", $response->json('errors.items.0'));
        $this->assertSame('2.000', $batch->fresh()->getRawOriginal('quantity_left'));
        $this->assertSame(0, Order::count());
    }

    public function test_reaching_zero_marks_the_batch_sold_out_and_a_cancellation_gives_it_back(): void
    {
        $batch = $this->openBatch('2');

        $response = $this->order([['variant_id' => $this->portion->uuid, 'quantity' => 2]])->assertCreated();
        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        $this->assertSame('0.000', $batch->fresh()->getRawOriginal('quantity_left'));
        $this->assertNotNull($batch->fresh()->sold_out_at);
        $this->assertSame($batch->id, $order->items()->first()->batch_id);

        Event::fake([MenuAvailabilityChangedBroadcast::class]);
        $this->setStatus($order, $this->owner, 'canceled')->assertOk();

        $this->assertSame('2.000', $batch->fresh()->getRawOriginal('quantity_left'));
        $this->assertNull($batch->fresh()->sold_out_at);
        Event::assertDispatched(MenuAvailabilityChangedBroadcast::class, fn ($event) => $event->reason === 'batch' && $event->items[0]['available'] === true);
        $this->order([['variant_id' => $this->portion->uuid, 'quantity' => 1]])->assertCreated();
    }

    public function test_a_cancellation_after_the_batch_closed_leaves_the_closed_count_alone(): void
    {
        $batch = $this->openBatch('5');
        $response = $this->order([['variant_id' => $this->portion->uuid, 'quantity' => 2]])->assertCreated();
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/batches/{$batch->id}/close")->assertOk();

        $this->setStatus(Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail(), $this->owner, 'canceled')->assertOk();

        $this->assertSame('3.000', $batch->fresh()->getRawOriginal('quantity_left'));
    }

    public function test_a_variant_batch_and_an_untracked_product(): void
    {
        $this->osh->update(['batch_tracked' => false]);
        // Untracked and no batch: plain stock
        $this->order([['variant_id' => $this->small->uuid, 'quantity' => 1]])->assertCreated();

        // A batch of the 1-portion variant only: that variant counts down, the other one is untouched
        $batch = $this->openBatch('1', $this->portion);
        $this->order([['variant_id' => $this->portion->uuid, 'quantity' => 1]])->assertCreated();
        $this->order([['variant_id' => $this->portion->uuid, 'quantity' => 1]])->assertStatus(422);
        $this->order([['variant_id' => $this->small->uuid, 'quantity' => 1]])->assertCreated();
        $this->assertSame('0.000', $batch->fresh()->getRawOriginal('quantity_left'));

        // Closing it puts the untracked product back on plain stock
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/batches/{$batch->id}/close")->assertOk();
        $this->order([['variant_id' => $this->portion->uuid, 'quantity' => 1]])->assertCreated();
    }

    public function test_staff_open_adjust_and_close_batches_with_an_audit_trail(): void
    {
        $cook = $this->makeCook();
        Event::fake([MenuAvailabilityChangedBroadcast::class]);

        $response = $this->actingAs($cook, 'sanctum')->postJson('/api/v1/owner/batches', [
            'product_id' => $this->osh->id,
            'quantity' => 40,
        ])->assertCreated()
            ->assertJsonPath('data.branch_id', $this->branch->id)
            ->assertJsonPath('data.quantity_left', 40)
            ->assertJsonPath('data.is_open', true);
        $id = $response->json('data.id');
        Event::assertDispatched(MenuAvailabilityChangedBroadcast::class, fn ($event) => $event->items[0]['available'] === true);

        // A second open batch of the same product on the branch is refused
        $this->actingAs($cook, 'sanctum')->postJson('/api/v1/owner/batches', ['product_id' => $this->osh->id, 'quantity' => 10])
            ->assertStatus(422);

        $this->actingAs($cook, 'sanctum')->postJson("/api/v1/owner/batches/{$id}/adjust", ['add' => 20])
            ->assertOk()->assertJsonPath('data.quantity_produced', 60)->assertJsonPath('data.quantity_left', 60);
        $this->actingAs($cook, 'sanctum')->postJson("/api/v1/owner/batches/{$id}/adjust", ['quantity_left' => '12.5', 'reason' => "To'kildi"])
            ->assertOk()->assertJsonPath('data.quantity_left', 12.5);
        $this->actingAs($cook, 'sanctum')->postJson("/api/v1/owner/batches/{$id}/adjust", [])->assertStatus(422);

        $this->actingAs($cook, 'sanctum')->getJson('/api/v1/owner/batches')
            ->assertOk()->assertJsonPath('data.batches.0.quantity_left', 12.5);

        $this->actingAs($cook, 'sanctum')->postJson("/api/v1/owner/batches/{$id}/close")->assertOk()->assertJsonPath('data.is_open', false);
        $this->actingAs($cook, 'sanctum')->postJson("/api/v1/owner/batches/{$id}/adjust", ['add' => 1])->assertStatus(422);

        $logs = AuditLog::where('store_id', $this->store->id)->where('action', 'like', 'menu.batch_%')->orderBy('id')->get();
        $this->assertSame(['menu.batch_opened', 'menu.batch_adjusted', 'menu.batch_adjusted', 'menu.batch_closed'], $logs->pluck('action')->all());
        $this->assertSame($cook->id, $logs->first()->user_id);
        $this->assertSame($this->branch->id, $logs->first()->branch_id);
        $this->assertSame(['quantity_left' => 60], $logs[2]->before); // only what changed
        $this->assertSame(['quantity_left' => 12.5], $logs[2]->after);
        $this->assertSame("To'kildi", $logs[2]->payload_json['reason']);
    }

    public function test_batch_endpoints_respect_permissions_branches_and_stores(): void
    {
        $batch = $this->openBatch('5');
        $otherManager = $this->makeStaff(UserRole::MANAGER, $this->otherBranch);
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $otherStore = $this->makeStore('Other Osh');
        $otherOwner = $this->makeStaff(UserRole::OWNER, null, $otherStore);

        // A cashier has no batches.manage (but menu.view lets them read)
        $this->actingAs($cashier, 'sanctum')->postJson('/api/v1/owner/batches', ['product_id' => $this->osh->id, 'quantity' => 1])->assertForbidden();
        $this->actingAs($cashier, 'sanctum')->postJson("/api/v1/owner/batches/{$batch->id}/close")->assertForbidden();
        $this->actingAs($cashier, 'sanctum')->getJson('/api/v1/owner/batches')->assertOk();
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/owner/batches')->assertForbidden();

        // Another branch's manager: not this branch's batches
        $this->actingAs($otherManager, 'sanctum')->postJson("/api/v1/owner/batches/{$batch->id}/close")->assertForbidden();
        $this->actingAs($otherManager, 'sanctum')->postJson('/api/v1/owner/batches', ['branch_id' => $this->branch->id, 'product_id' => $this->osh->id, 'quantity' => 1])->assertForbidden();
        $this->actingAs($otherManager, 'sanctum')->getJson("/api/v1/owner/batches?branch_id={$this->branch->id}")->assertForbidden();

        // Another store: nothing of this one exists
        $this->actingAs($otherOwner, 'sanctum')->postJson("/api/v1/owner/batches/{$batch->id}/adjust", ['add' => 1])->assertNotFound();
        $this->actingAs($otherOwner, 'sanctum')->getJson("/api/v1/owner/batches?branch_id={$this->branch->id}")->assertNotFound();
        $otherBranch = $this->makeBranch($otherStore, 'Other main');
        $this->actingAs($otherOwner, 'sanctum')->postJson('/api/v1/owner/batches', ['branch_id' => $otherBranch->id, 'product_id' => $this->osh->id, 'quantity' => 1])->assertNotFound();

        // A store may take batches.manage away from its cooks
        $cook = $this->makeCook();
        app(\App\Services\Auth\PermissionService::class)->setOverride($this->store, UserRole::COOK, \App\Enums\Permission::BATCHES_MANAGE, false);
        $this->actingAs($cook, 'sanctum')->postJson("/api/v1/owner/batches/{$batch->id}/close")->assertForbidden();

        $this->assertNull($batch->fresh()->closed_at);
    }

    /**
     * @return array<string, mixed> the main branch's entry of GET /catalog/availability
     */
    protected function availability(): array
    {
        return $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/v1/catalog/availability?store_id={$this->store->uuid}&branch_id={$this->branch->id}")
            ->assertOk()
            ->json('data.branches.0');
    }
}
