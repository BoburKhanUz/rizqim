<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Events\Broadcast\OrderAssignedBroadcast;
use App\Events\Broadcast\OrderBroadcast;
use App\Events\Broadcast\OrderCreatedBroadcast;
use App\Events\Broadcast\OrderStatusChangedBroadcast;
use App\Enums\UserRole;
use App\Models\Order;
use App\Services\Order\OrderStatusService;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * order.created / order.status_changed / order.assigned reach exactly the channels of routes/channels.php, carry only
 * the order's ids and status, go through the queue and are sent only once the change has committed.
 */
class BroadcastOrderEventsTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    protected function fakeBroadcasts(): void
    {
        Event::fake([OrderCreatedBroadcast::class, OrderStatusChangedBroadcast::class, OrderAssignedBroadcast::class]);
    }

    /**
     * @return array<int, string>
     */
    protected function channels(OrderBroadcast $event): array
    {
        $channels = array_map(fn ($channel) => (string) $channel, $event->broadcastOn());
        sort($channels);

        return $channels;
    }

    /**
     * @param  array<int, string>  $channels
     */
    protected function assertChannels(array $channels, OrderBroadcast $event): void
    {
        sort($channels);
        $this->assertSame($channels, $this->channels($event));
    }

    protected function assertMinimalPayload(Order $order, OrderBroadcast $event): void
    {
        $order->refresh();

        $this->assertSame(['order' => [
            'uuid' => $order->uuid,
            'status' => $order->status->value,
            'store_id' => $order->store_id,
            'branch_id' => $order->branch_id,
            'courier_id' => $order->courier_id,
            'updated_at' => $order->updated_at->toJSON(),
        ]], $event->broadcastWith());
    }

    protected function only(string $class): OrderBroadcast
    {
        Event::assertDispatchedTimes($class, 1);

        return Event::dispatched($class)->first()[0];
    }

    public function test_checkout_announces_the_new_order_to_the_store_and_its_branch(): void
    {
        $this->fakeBroadcasts();

        $order = $this->checkout($this->customer);

        $event = $this->only(OrderCreatedBroadcast::class);
        $this->assertSame('order.created', $event->broadcastAs());
        $this->assertChannels(["private-store.{$this->store->id}", "private-branch.{$this->branch->id}"], $event);
        $this->assertMinimalPayload($order, $event);
        $this->assertSame('new', $event->broadcastWith()['order']['status']);
        Event::assertNotDispatched(OrderStatusChangedBroadcast::class);
        Event::assertNotDispatched(OrderAssignedBroadcast::class);
    }

    public function test_a_rolled_back_checkout_is_never_announced(): void
    {
        $this->fakeBroadcasts();

        try {
            DB::transaction(function () {
                $this->checkout($this->customer);
                Event::assertNotDispatched(OrderCreatedBroadcast::class);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }

        Event::assertNotDispatched(OrderCreatedBroadcast::class);
        $this->assertSame(0, Order::count());
    }

    public function test_a_status_change_reaches_the_store_branch_and_customer(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);
        $this->fakeBroadcasts();

        app(OrderStatusService::class)->transition($order, OrderStatus::ACCEPTED, $this->manager);

        $event = $this->only(OrderStatusChangedBroadcast::class);
        $this->assertSame('order.status_changed', $event->broadcastAs());
        $this->assertChannels([
            "private-store.{$this->store->id}",
            "private-branch.{$this->branch->id}",
            "private-user.{$this->customer->id}",
        ], $event);
        $this->assertMinimalPayload($order, $event);
        $this->assertSame('accepted', $event->broadcastWith()['order']['status']);
        Event::assertNotDispatched(OrderAssignedBroadcast::class);
    }

    public function test_an_order_without_a_branch_goes_to_the_store_channel_only(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW, ['branch_id' => null]);
        $this->fakeBroadcasts();

        app(OrderStatusService::class)->transition($order, OrderStatus::ACCEPTED, $this->owner);

        $this->assertChannels(
            ["private-store.{$this->store->id}", "private-user.{$this->customer->id}"],
            $this->only(OrderStatusChangedBroadcast::class)
        );
    }

    public function test_assigning_a_courier_tells_the_courier_and_the_staff(): void
    {
        $order = $this->makeOrder(OrderStatus::READY);
        $this->fakeBroadcasts();

        app(OrderStatusService::class)->transition($order, OrderStatus::COURIER_ASSIGNED, $this->manager, ['courier_id' => $this->courier->id]);

        $assigned = $this->only(OrderAssignedBroadcast::class);
        $this->assertSame('order.assigned', $assigned->broadcastAs());
        $this->assertChannels([
            "private-courier.{$this->courier->id}",
            "private-store.{$this->store->id}",
            "private-branch.{$this->branch->id}",
        ], $assigned);
        $this->assertMinimalPayload($order, $assigned);
        $this->assertSame($this->courier->id, $assigned->broadcastWith()['order']['courier_id']);

        $changed = $this->only(OrderStatusChangedBroadcast::class);
        $this->assertChannels([
            "private-store.{$this->store->id}",
            "private-branch.{$this->branch->id}",
            "private-user.{$this->customer->id}",
            "private-courier.{$this->courier->id}",
        ], $changed);
        $this->assertMinimalPayload($order, $changed);
    }

    public function test_a_reassignment_reaches_the_new_and_the_previous_courier(): void
    {
        $order = $this->makeOrder(OrderStatus::COURIER_ASSIGNED, ['courier_id' => $this->courier->id]);
        $next = $this->makeStaff(UserRole::COURIER, $this->branch);
        $this->fakeBroadcasts();

        app(OrderStatusService::class)->transition($order, OrderStatus::COURIER_ASSIGNED, $this->manager, ['courier_id' => $next->id]);

        $this->assertChannels([
            "private-courier.{$next->id}",
            "private-store.{$this->store->id}",
            "private-branch.{$this->branch->id}",
        ], $this->only(OrderAssignedBroadcast::class));
        $this->assertChannels([
            "private-store.{$this->store->id}",
            "private-branch.{$this->branch->id}",
            "private-user.{$this->customer->id}",
            "private-courier.{$next->id}",
            "private-courier.{$this->courier->id}",
        ], $this->only(OrderStatusChangedBroadcast::class));
    }

    public function test_the_courier_and_a_cancellation_keep_everyone_in_the_loop(): void
    {
        $order = $this->makeOrder(OrderStatus::COURIER_ASSIGNED, ['courier_id' => $this->courier->id]);
        $this->fakeBroadcasts();

        app(OrderStatusService::class)->transition($order, OrderStatus::ON_THE_WAY, $this->courier);
        app(OrderStatusService::class)->transition($order, OrderStatus::CANCELED, $this->manager, ['cancel_reason' => 'other']);

        Event::assertDispatchedTimes(OrderStatusChangedBroadcast::class, 2);
        Event::assertNotDispatched(OrderAssignedBroadcast::class);
        $statuses = Event::dispatched(OrderStatusChangedBroadcast::class)->map(fn (array $args) => $args[0]->broadcastWith()['order']['status'])->all();
        $this->assertSame(['on_the_way', 'canceled'], $statuses);

        foreach (Event::dispatched(OrderStatusChangedBroadcast::class) as [$event]) {
            $this->assertContains("private-courier.{$this->courier->id}", $this->channels($event));
            $this->assertContains("private-user.{$this->customer->id}", $this->channels($event));
        }
    }

    public function test_a_repeated_status_broadcasts_nothing(): void
    {
        $order = $this->makeOrder(OrderStatus::ACCEPTED);
        $this->fakeBroadcasts();

        app(OrderStatusService::class)->transition($order, OrderStatus::ACCEPTED, $this->manager);

        Event::assertNotDispatched(OrderStatusChangedBroadcast::class);
    }

    public function test_status_events_wait_for_the_commit_and_vanish_with_a_rollback(): void
    {
        $order = $this->makeOrder(OrderStatus::READY);
        $this->fakeBroadcasts();

        DB::beginTransaction();
        app(OrderStatusService::class)->transition($order, OrderStatus::COURIER_ASSIGNED, $this->manager, ['courier_id' => $this->courier->id]);
        Event::assertNotDispatched(OrderStatusChangedBroadcast::class);
        Event::assertNotDispatched(OrderAssignedBroadcast::class);
        DB::rollBack();

        Event::assertNotDispatched(OrderStatusChangedBroadcast::class);
        Event::assertNotDispatched(OrderAssignedBroadcast::class);

        DB::beginTransaction();
        app(OrderStatusService::class)->transition($order, OrderStatus::COURIER_ASSIGNED, $this->manager, ['courier_id' => $this->courier->id]);
        Event::assertNotDispatched(OrderStatusChangedBroadcast::class);
        DB::commit();

        Event::assertDispatchedTimes(OrderStatusChangedBroadcast::class, 1);
        Event::assertDispatchedTimes(OrderAssignedBroadcast::class, 1);
    }

    public function test_broadcasts_go_through_the_queue(): void
    {
        Queue::fake([BroadcastEvent::class]);
        $order = $this->makeOrder(OrderStatus::READY);

        app(OrderStatusService::class)->transition($order, OrderStatus::COURIER_ASSIGNED, $this->manager, ['courier_id' => $this->courier->id]);

        $names = Queue::pushed(BroadcastEvent::class)->map(fn (BroadcastEvent $job) => $job->event->broadcastAs())->sort()->values()->all();
        $this->assertSame(['order.assigned', 'order.status_changed'], $names);
    }

    public function test_the_broadcaster_receives_the_exact_wire_payload(): void
    {
        $sent = [];
        $spy = new class($sent) extends Broadcaster {
            public function __construct(public array &$sent) {}

            public function auth($request) {}

            public function validAuthenticationResponse($request, $result) {}

            public function broadcast(array $channels, $event, array $payload = []): void
            {
                // Pusher/Reverb drop the socket key before sending, as here
                unset($payload['socket']);
                $this->sent[] = [$event, array_map('strval', $channels), $payload];
            }
        };
        Broadcast::extend('spy', fn () => $spy);
        config(['broadcasting.connections.spy' => ['driver' => 'spy'], 'broadcasting.default' => 'spy']);

        $order = $this->checkout($this->customer);
        app(OrderStatusService::class)->transition($order, OrderStatus::ACCEPTED, $this->manager);
        $order->refresh();

        $this->assertSame(['order.created', 'order.status_changed'], array_column($sent, 0));
        $this->assertSame(["private-store.{$this->store->id}", "private-branch.{$this->branch->id}"], $sent[0][1]);
        $this->assertSame(['order' => [
            'uuid' => $order->uuid,
            'status' => 'accepted',
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'courier_id' => null,
            'updated_at' => $order->updated_at->toJSON(),
        ]], $sent[1][2]);
        $this->assertSame(['uuid', 'status', 'store_id', 'branch_id', 'courier_id', 'updated_at'], array_keys($sent[0][2]['order']));
    }

    public function test_the_log_and_null_broadcasters_change_nothing_else(): void
    {
        foreach (['log', 'null'] as $connection) {
            config(['broadcasting.default' => $connection]);

            $order = $this->checkout($this->customer, 1);
            $result = app(OrderStatusService::class)->transition($order, OrderStatus::ACCEPTED, $this->manager);

            $this->assertTrue($result->changed);
            $this->assertSame(OrderStatus::ACCEPTED, $order->refresh()->status);
        }
    }
}
