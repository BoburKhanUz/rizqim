<?php

namespace Tests\Feature\PosHostess;

use App\Domain\Pos\Orders\OrderTransferredBroadcast;
use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosSettings;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\Broadcast\OrderItemsChangedBroadcast;
use App\Models\AuditLog;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P6-24 (C5): an open table goes to another waiter. orders.transfer (manager, owner) or a manager's approval token for
 * it; audited; the old and the new waiter are told on their own channels; the waiter rule follows the new waiter.
 */
class PosOrderTransferTest extends TestCase
{
    use PosHostessFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRestaurantFloor();
    }

    private function table5(): Order
    {
        return $this->openedOrder($this->openPos($this->waiter, $this->table, 3, ['items' => [$this->line($this->tea, 2)]]));
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Order $order, ?int $waiterId = null, array $extra = []): array
    {
        return ['version' => $this->versionOf($order), 'waiter_id' => $waiterId ?? $this->waiter2->id] + $extra;
    }

    public function test_a_manager_hands_the_table_to_another_waiter_and_it_is_audited(): void
    {
        $order = $this->table5();
        $version = $this->versionOf($order);

        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order, extra: ['reason' => 'Smena tugadi']))
            ->assertOk()
            ->assertJsonPath('data.waiter.id', $this->waiter2->id)
            ->assertJsonPath('data.version', $version + 1);

        $this->assertSame((int) $this->waiter2->id, (int) $order->fresh()->waiter_id);
        $log = AuditLog::where('action', 'pos.order_transferred')->firstOrFail();
        $this->assertSame((int) $this->manager->id, (int) $log->user_id);
        $this->assertSame((int) $order->id, (int) $log->model_id);
        $this->assertSame((int) $this->waiter->id, $log->payload_json['from_waiter_id']);
        $this->assertSame((int) $this->waiter2->id, $log->payload_json['to_waiter_id']);
        $this->assertSame('Smena tugadi', $log->payload_json['reason']);
    }

    public function test_the_old_and_the_new_waiter_are_told_and_the_floor_refetches(): void
    {
        $order = $this->table5();
        Event::fake([OrderTransferredBroadcast::class, OrderItemsChangedBroadcast::class]);

        $this->pos($this->owner, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order))->assertOk();

        Event::assertDispatched(OrderTransferredBroadcast::class, function (OrderTransferredBroadcast $event) {
            $channels = array_map(fn ($channel) => $channel->name, $event->broadcastOn());
            $this->assertEqualsCanonicalizing(
                ["private-pos-branch.{$this->branch->id}", "private-user.{$this->waiter->id}", "private-user.{$this->waiter2->id}"],
                $channels,
            );
            $this->assertSame('order.transferred', $event->broadcastAs());
            $this->assertSame($this->waiter->id, $event->fromWaiterId);
            $this->assertSame($this->waiter2->id, $event->toWaiterId);
            $this->assertSame('Stol 5', $event->broadcastWith()['table_ref']);

            return true;
        });
        Event::assertDispatched(OrderItemsChangedBroadcast::class, fn (OrderItemsChangedBroadcast $event) => $event->change === 'waiter_changed');
    }

    public function test_the_waiter_rule_follows_the_new_waiter(): void
    {
        $order = $this->table5();
        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order))->assertOk();

        // The old waiter now needs a manager for this table; the new one works it freely
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => $this->versionOf($order), 'items' => [$this->line($this->bread, 1)]])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required');
        $this->pos($this->waiter2, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => $this->versionOf($order), 'items' => [$this->line($this->bread, 1)]])
            ->assertOk();
    }

    public function test_a_cashier_needs_a_managers_approval_and_a_waiter_cannot_hand_over_their_own_table_alone(): void
    {
        $order = $this->table5();

        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order))
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required')->assertJsonPath('data.permission', 'orders.transfer');
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order))
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required');
        $this->assertSame((int) $this->waiter->id, (int) $order->fresh()->waiter_id);

        // With a manager's one-time approval for orders.transfer it goes through, once
        $token = $this->approval('orders.transfer', $this->manager);
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order, extra: ['approval_token' => $token]))->assertOk();
        $this->assertSame((int) $this->waiter2->id, (int) $order->fresh()->waiter_id);
        $this->assertSame((int) $this->manager->id, AuditLog::where('action', 'pos.order_transferred')->firstOrFail()->payload_json['approved_by']);

        $this->pos($this->waiter2, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order, $this->waiter->id, ['approval_token' => $token]))
            ->assertForbidden();
    }

    public function test_the_approval_is_for_this_permission_only(): void
    {
        $order = $this->table5();
        $wrong = $this->approval('orders.manage', $this->manager);

        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order, extra: ['approval_token' => $wrong]))->assertForbidden();
        $this->assertSame((int) $this->waiter->id, (int) $order->fresh()->waiter_id);
    }

    public function test_only_an_active_waiter_of_the_branch_can_take_the_table(): void
    {
        $order = $this->table5();
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $elsewhere = $this->makeStaff(UserRole::WAITER, $this->otherBranch);
        $blocked = $this->makeStaff(UserRole::WAITER, $this->branch);
        $blocked->forceFill(['blocked_at' => now()])->save();
        $storeWide = $this->makeStaff(UserRole::WAITER);

        foreach ([$cook, $elsewhere, $blocked] as $notAWaiter) {
            $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order, $notAWaiter->id))
                ->assertNotFound()->assertJsonPath('data.reason', 'waiter_not_found');
        }
        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order, $this->waiter->id))
            ->assertStatus(422)->assertJsonPath('data.reason', 'same_waiter');
        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order, $storeWide->id))->assertOk();
    }

    public function test_an_order_without_a_waiter_can_be_given_one(): void
    {
        $order = $this->openedOrder($this->openPos($this->cashier, $this->table, 2));
        $this->assertNull($order->fresh()->waiter_id);

        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order))->assertOk();
        $this->assertSame((int) $this->waiter2->id, (int) $order->fresh()->waiter_id);
    }

    public function test_a_closed_order_a_stale_version_and_another_branch_are_refused(): void
    {
        $order = $this->table5();

        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/transfer", ['version' => 99, 'waiter_id' => $this->waiter2->id])
            ->assertStatus(409)->assertJsonPath('data.reason', 'version_conflict');

        $this->otherBranch->forceFill(['pos_settings' => PosSettings::withFormat(null, FormatProfile::RESTORAN, [])])->save();
        $otherManager = $this->makeStaff(UserRole::MANAGER, $this->otherBranch);
        $this->pos($otherManager, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order))->assertNotFound();

        $order->forceFill(['status' => OrderStatus::DELIVERED, 'closed_at' => now()])->save();
        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order))
            ->assertStatus(422)->assertJsonPath('data.reason', 'order_closed');
    }

    public function test_a_retried_transfer_is_one_transfer(): void
    {
        $order = $this->table5();
        $key = 'transfer-key-1';
        $body = $this->body($order);

        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/transfer", $body, $key)->assertOk();
        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/transfer", $body, $key)->assertOk();

        $this->assertSame(1, AuditLog::where('action', 'pos.order_transferred')->count());
        $this->assertSame(2, $this->versionOf($order));
    }

    public function test_the_transfer_needs_the_module_and_the_choyxona_has_none(): void
    {
        $order = $this->table5();
        $this->profile(FormatProfile::CHOYXONA);

        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order))
            ->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');

        $this->profile(FormatProfile::CHOYXONA, ['waiter_transfer' => true]);
        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order))->assertOk();
    }

    public function test_the_owner_can_grant_the_transfer_to_the_cashier_in_the_permission_matrix(): void
    {
        $order = $this->table5();
        $this->assertContains('orders.transfer', array_column($this->pos($this->owner, 'GET', '/owner/permissions')->assertOk()->json('data.permissions'), 'key'));
        $this->pos($this->owner, 'PUT', '/owner/permissions', ['role' => 'cashier', 'permission' => 'orders.transfer', 'allowed' => true])->assertOk();

        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/transfer", $this->body($order))->assertOk();
    }

    public function test_a_store_wide_owner_is_gated_on_the_orders_own_branch(): void
    {
        // The second branch stays a choyxona (no waiter_transfer): the owner may use the module on the first branch only
        $hall = \App\Models\Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->otherBranch->id, 'name' => 'Zal', 'sort_order' => 1]);
        $table = $this->place($hall, 'Stol 1', 'table', ['seats' => 4]);
        $order = $this->openedOrder($this->openPos($this->owner, $table, 2, ['branch_id' => $this->otherBranch->id]));
        $waiter = $this->makeStaff(UserRole::WAITER, $this->otherBranch);

        $this->pos($this->owner, 'POST', "/pos/orders/{$order->uuid}/transfer", ['version' => $this->versionOf($order), 'waiter_id' => $waiter->id])
            ->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $this->assertNull($order->fresh()->waiter_id);
    }
}
