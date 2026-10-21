<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\NotificationMessage;
use App\Services\Notifications\NotificationRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P2-33 (review A, findings 1 and 5): the kitchen display is keyed on the order's lines. A courier assigned while the
 * kitchen is still cooking keeps the order's pending lines on the display, where they are bumped and recalled without
 * any status move; once the order leaves with the courier its lines are closed. Couriers are paged on `ready` for
 * delivery orders only, and a recall from `ready` with the bump after it tells nobody outside the kitchen again.
 */
class KitchenCourierAssignedTest extends TestCase
{
    use RefreshDatabase;
    use StaffOrderFixtures;

    private User $cook;

    /** @var list<string> recipient role:notification type of every notification sent */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->cook = $this->makeStaff(UserRole::COOK, $this->branch);
    }

    private function bump(Order $order, int $itemId, string $action = 'ready')
    {
        return $this->actingAs($this->cook, 'sanctum')->postJson("/api/v1/staff/orders/{$order->uuid}/items/{$itemId}/{$action}");
    }

    private function tickets(bool $includeReady = false): array
    {
        return $this->actingAs($this->cook, 'sanctum')
            ->getJson('/api/v1/staff/kitchen/tickets' . ($includeReady ? '?include_ready=1' : ''))
            ->assertOk()
            ->json('data');
    }

    /**
     * Every NotificationRouter::send is recorded (recipient role and notification type); nothing is sent.
     */
    private function captureNotifications(): void
    {
        $sent = &$this->sent;
        $this->app->extend(NotificationRouter::class, function ($router) use (&$sent) {
            $mock = \Mockery::mock($router)->makePartial();
            $mock->shouldReceive('send')->andReturnUsing(function (User $user, NotificationMessage $message) use (&$sent) {
                $sent[] = $user->role->value . ':' . $message->type->value;

                return [];
            });
            $mock->shouldReceive('reachable')->andReturn(true);
            $mock->shouldReceive('onlyReachable')->andReturnUsing(fn ($query) => $query);

            return $mock;
        });
    }

    // ---- finding 1: courier assigned while cooking ------------------------------------------------------------------

    public function test_a_courier_assigned_while_cooking_keeps_the_pending_lines_on_the_display(): void
    {
        $order = $this->orderWithItems(OrderStatus::PREPARING, ['order_type' => 'delivery']);
        [$first, $second] = $order->items()->orderBy('id')->pluck('id')->all();

        $this->bump($order, $first)->assertOk()->assertJsonPath('data.status', 'preparing');
        $this->actingAs($this->manager, 'sanctum')->postJson("/api/v1/orders/{$order->uuid}/assign-courier", ['courier_id' => $this->courier->id])
            ->assertOk();
        $this->assertSame(OrderStatus::COURIER_ASSIGNED, $order->fresh()->status);

        // Still on the display, with its kitchen progress
        $tickets = $this->tickets();
        $this->assertCount(1, $tickets);
        $this->assertSame('courier_assigned', $tickets[0]['status']);
        $this->assertSame([2, 1], [$tickets[0]['items_total'], $tickets[0]['items_ready']]);

        // Recall and bump work there, with no status move
        $this->bump($order, $first, 'recall')->assertOk()->assertJsonPath('data.status', 'courier_assigned')->assertJsonPath('data.items_ready', 0);
        $this->bump($order, $first)->assertOk()->assertJsonPath('data.items_ready', 1);
        $this->bump($order, $second)->assertOk()
            ->assertJsonPath('data.status', 'courier_assigned')
            ->assertJsonPath('data.items_ready', 2);
        $this->assertSame(OrderStatus::COURIER_ASSIGNED, $order->fresh()->status);
        $this->assertSame(['new', 'accepted', 'courier_assigned'], $order->statusHistories()->orderBy('id')->pluck('status')->map->value->all());

        // Nothing left to cook: off the display, but listed with include_ready (the courier has not picked it up yet)
        $this->assertSame([], $this->tickets());
        $this->assertSame([$order->uuid], array_column($this->tickets(true), 'uuid'));

        // The board agrees
        $this->actingAs($this->manager, 'sanctum')->getJson("/api/v1/staff/orders/{$order->uuid}")->assertOk()
            ->assertJsonPath('data.items_ready', 2)->assertJsonPath('data.status', 'courier_assigned');
    }

    public function test_an_order_on_its_way_closes_its_lines_and_leaves_the_kitchen(): void
    {
        $order = $this->orderWithItems(OrderStatus::PREPARING, ['order_type' => 'delivery']);
        $first = (int) $order->items()->orderBy('id')->value('id');
        $this->actingAs($this->manager, 'sanctum')->postJson("/api/v1/orders/{$order->uuid}/assign-courier", ['courier_id' => $this->courier->id])
            ->assertOk();
        $this->assertCount(1, $this->tickets());

        $this->actingAs($this->manager, 'sanctum')->postJson("/api/v1/staff/orders/{$order->uuid}/status", ['status' => 'on_the_way'])
            ->assertOk()->assertJsonPath('data.items_ready', 2);

        $this->assertSame([], $this->tickets(true));
        $this->bump($order, $first, 'recall')->assertUnprocessable()->assertJsonPath('data.reason', 'kitchen_status');
        $this->bump($order, $first)->assertOk()->assertJsonPath('message', "O'zgarish yo'q.");
    }

    public function test_a_new_or_cancelled_order_is_still_not_the_kitchens(): void
    {
        $new = $this->orderWithItems(OrderStatus::NEW, ['order_type' => 'delivery']);
        $this->bump($new, (int) $new->items()->value('id'))->assertUnprocessable()->assertJsonPath('data.reason', 'kitchen_status');

        $cancelled = $this->orderWithItems(OrderStatus::CANCELED, ['order_type' => 'delivery']);
        $this->bump($cancelled, (int) $cancelled->items()->value('id'))->assertUnprocessable()->assertJsonPath('data.reason', 'kitchen_status');
        $this->assertSame([], $this->tickets(true));
    }

    // ---- finding 5: alerts ------------------------------------------------------------------------------------------

    public function test_a_ready_pickup_order_does_not_page_couriers(): void
    {
        $this->captureNotifications();
        $order = $this->orderWithItems(OrderStatus::PREPARING, ['order_type' => 'pickup']);
        foreach ($order->items()->orderBy('id')->pluck('id') as $id) {
            $this->bump($order, (int) $id)->assertOk();
        }

        $this->assertSame(OrderStatus::READY, $order->fresh()->status);
        $this->assertSame(['customer:order_updates'], $this->sent);
    }

    public function test_a_ready_delivery_order_pages_the_branch_couriers(): void
    {
        $this->captureNotifications();
        $order = $this->orderWithItems(OrderStatus::PREPARING, ['order_type' => 'delivery']);
        foreach ($order->items()->orderBy('id')->pluck('id') as $id) {
            $this->bump($order, (int) $id)->assertOk();
        }

        $this->assertSame(['customer:order_updates', 'courier:courier_tasks'], $this->sent);
    }

    public function test_a_recall_from_ready_and_the_bump_after_it_tell_nobody_again(): void
    {
        $this->captureNotifications();
        foreach (['pickup', 'delivery'] as $type) {
            $this->sent = [];
            $order = $this->orderWithItems(OrderStatus::PREPARING, ['order_type' => $type]);
            $ids = $order->items()->orderBy('id')->pluck('id')->all();
            foreach ($ids as $id) {
                $this->bump($order, (int) $id)->assertOk();
            }
            $this->assertNotSame([], $this->sent, $type);

            $this->sent = [];
            $this->bump($order, (int) $ids[1], 'recall')->assertOk()->assertJsonPath('data.status', 'preparing');
            $this->bump($order, (int) $ids[1])->assertOk()->assertJsonPath('data.status', 'ready');
            $this->assertSame([], $this->sent, "{$type}: a kitchen correction is not news");

            // The history and the real-time events still say what happened
            $this->assertSame(['new', 'accepted', 'ready', 'preparing', 'ready'], $order->statusHistories()->orderBy('id')->pluck('status')->map->value->all());
        }

        // A staff member marking it ready again by hand after a recall is quiet too
        $order = $this->orderWithItems(OrderStatus::PREPARING, ['order_type' => 'pickup']);
        $ids = $order->items()->orderBy('id')->pluck('id')->all();
        foreach ($ids as $id) {
            $this->bump($order, (int) $id)->assertOk();
        }
        $this->bump($order, (int) $ids[0], 'recall')->assertOk();
        $this->sent = [];
        $this->actingAs($this->manager, 'sanctum')->postJson("/api/v1/staff/orders/{$order->uuid}/status", ['status' => 'ready'])->assertOk();
        $this->assertSame([], $this->sent);

        // ... but handing it over is news again
        $this->actingAs($this->manager, 'sanctum')->postJson("/api/v1/staff/orders/{$order->uuid}/status", ['status' => 'delivered'])->assertOk();
        $this->assertSame(['customer:order_updates'], $this->sent);
    }
}
