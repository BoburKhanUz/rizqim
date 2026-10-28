<?php

namespace Tests\Feature\AdminOrders;

use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Events\Broadcast\OpsOrderCreated;
use App\Events\Broadcast\OpsOrderUpdated;
use App\Jobs\WatchOrderSlaJob;
use App\Models\AuditLog;
use App\Models\OrderSlaEvent;
use App\Services\Admin\Attention\AttentionSource;
use App\Services\Admin\Orders\Attention\LateOrdersSource;
use App\Services\Admin\Orders\Attention\NoCourierOrdersSource;
use App\Services\Admin\Orders\Attention\UnacceptedOrdersSource;
use App\Services\Admin\Orders\OrderSlaWatcher;
use App\Services\Order\OrderStatusService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P4-14: SLA limits (GET/PUT /admin/orders/sla), the every-minute watcher's once-only crossings, the platform.ops order
 * events and the attention sources.
 */
class AdminOrderSlaTest extends TestCase
{
    use RefreshDatabase;
    use AdminOrdersFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    public function test_limits_are_read_by_orders_view_and_changed_by_system_manage_with_step_up_and_reason(): void
    {
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $operatorToken = $this->confirmedSession($operator);
        $this->adminJson('GET', '/orders/sla', $operatorToken)->assertOk()
            ->assertJsonPath('data.limits', ['accept' => 5, 'prepare' => 30, 'deliver' => 45])
            ->assertJsonPath('data.warning_ratio', 0.8);
        $this->adminJson('PUT', '/orders/sla', $operatorToken, ['accept' => 7, 'reason' => 'Tushlik vaqti'])->assertForbidden();

        $superadmin = $this->makeMember();
        $token = $this->signIn($superadmin);
        $this->adminJson('PUT', '/orders/sla', $token, ['accept' => 7, 'reason' => 'Tushlik vaqti'])
            ->assertForbidden()->assertJsonPath('code', 'reauth_required');

        $this->stepUp($superadmin, $token);
        $this->adminJson('PUT', '/orders/sla', $token, ['accept' => 7])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('PUT', '/orders/sla', $token, ['accept' => 0, 'reason' => 'Tushlik vaqti'])->assertStatus(422)->assertJsonValidationErrors('accept');
        $this->adminJson('PUT', '/orders/sla', $token, ['accept' => 7, 'deliver' => 60, 'reason' => 'Tushlik vaqti'])->assertOk()
            ->assertJsonPath('data.limits', ['accept' => 7, 'prepare' => 30, 'deliver' => 60]);

        $entry = AuditLog::where('action', 'admin.orders.sla.updated')->sole();
        $this->assertSame(['accept' => 5, 'deliver' => 45], $entry->before);
        $this->assertSame(['accept' => 7, 'deliver' => 60], $entry->after);
        $this->assertSame('Tushlik vaqti', $entry->payload_json['reason']);

        // The list uses the new limit at once: 6 minutes is no longer late
        $order = $this->orderWithHistory(6);
        $row = collect($this->adminJson('GET', '/orders', $token)->json('data.data'))->firstWhere('uuid', (string) $order->uuid);
        $this->assertSame('warning', $row['sla']['state']);
    }

    public function test_watcher_records_and_broadcasts_each_crossing_once(): void
    {
        Event::fake([OpsOrderUpdated::class]);
        $this->travelTo(now()->startOfMinute());
        $order = $this->orderWithHistory(3); // ok

        app(OrderSlaWatcher::class)->run();
        $this->assertSame(0, OrderSlaEvent::count());

        $this->travel(1)->minutes(); // 4 of 5: warning
        (new WatchOrderSlaJob())->handle(app(OrderSlaWatcher::class));
        app(OrderSlaWatcher::class)->run();
        $this->assertSame(['accept:warning'], OrderSlaEvent::all()->map(fn ($e) => "{$e->stage}:{$e->state}")->all());
        Event::assertDispatchedTimes(OpsOrderUpdated::class, 1);

        $this->travel(2)->minutes(); // late
        app(OrderSlaWatcher::class)->run();
        app(OrderSlaWatcher::class)->run();
        $this->assertSame(['accept:warning', 'accept:late'], OrderSlaEvent::orderBy('id')->get()->map(fn ($e) => "{$e->stage}:{$e->state}")->all());
        Event::assertDispatchedTimes(OpsOrderUpdated::class, 2);
        Event::assertDispatched(OpsOrderUpdated::class, fn (OpsOrderUpdated $event) => $event->change === 'sla'
            && $event->sla === ['stage' => 'accept', 'state' => 'late', 'late_by_minutes' => 1]
            && $event->uuid === (string) $order->uuid
            && $event->broadcastAs() === 'ops.order.updated'
            && $event->broadcastOn()[0] instanceof PrivateChannel && $event->broadcastOn()[0]->name === 'private-platform.ops');

        // The next stage starts its own crossings
        $this->history($order, OrderStatus::ACCEPTED, OrderStatus::NEW, now()->subMinutes(25));
        $order->forceFill(['status' => OrderStatus::ACCEPTED->value])->saveQuietly();
        app(OrderSlaWatcher::class)->run();
        $this->assertSame(1, OrderSlaEvent::where('stage', 'prepare')->where('state', 'warning')->count());
        Event::assertDispatchedTimes(OpsOrderUpdated::class, 3);

        // The card shows the crossings
        $card = $this->adminJson('GET', '/orders/' . $order->uuid, $this->signIn($this->makeMember(PlatformRole::VIEWER)))->json('data');
        $this->assertSame(['accept:warning', 'accept:late', 'prepare:warning'], array_map(fn ($e) => "{$e['stage']}:{$e['state']}", $card['sla_events']));
    }

    public function test_orders_already_recorded_in_their_band_never_push_a_new_crossing_out_of_the_batch(): void
    {
        Event::fake([OpsOrderUpdated::class]);
        $this->travelTo(now()->startOfMinute());
        // Three older orders in the accept warning band (4 of 5 minutes), recorded by a first run
        $waiting = [$this->orderWithHistory(4), $this->orderWithHistory(4), $this->orderWithHistory(4)];
        app(OrderSlaWatcher::class)->run();
        $this->assertSame(3, OrderSlaEvent::where('state', 'warning')->count());

        // A newer order crosses into warning while the three still wait in the band: a batch of 2 still reaches it
        $order = $this->orderWithHistory(4);
        $this->assertSame(1, app(OrderSlaWatcher::class)->run(2));
        $this->assertSame(1, OrderSlaEvent::where('order_id', $order->id)->where('state', 'warning')->count());
        $this->assertSame(0, app(OrderSlaWatcher::class)->run(2));

        // Late crossings of the waiting ones are still found (their late row is what is missing)
        $this->travel(2)->minutes();
        $this->assertSame(2, app(OrderSlaWatcher::class)->run(2));
        $this->assertSame(2, app(OrderSlaWatcher::class)->run(2));
        $this->assertSame(4, OrderSlaEvent::where('state', 'late')->count());
        $this->assertSame([], array_diff(array_map(fn ($o) => (int) $o->id, $waiting), OrderSlaEvent::where('state', 'late')->pluck('order_id')->map(fn ($id) => (int) $id)->all()));
    }

    public function test_an_order_straight_to_late_records_both_and_broadcasts_once(): void
    {
        Event::fake([OpsOrderUpdated::class]);
        $this->orderWithHistory(70, ['accepted' => 65]); // prepare, 35 of 30 minutes

        app(OrderSlaWatcher::class)->run();

        $this->assertSame(['prepare:warning', 'prepare:late'], OrderSlaEvent::orderBy('id')->get()->map(fn ($e) => "{$e->stage}:{$e->state}")->all());
        Event::assertDispatchedTimes(OpsOrderUpdated::class, 1);
        Event::assertDispatched(OpsOrderUpdated::class, fn (OpsOrderUpdated $event) => $event->sla['state'] === 'late');
    }

    public function test_marketplace_order_changes_go_to_platform_ops_and_pos_orders_do_not(): void
    {
        Event::fake([OpsOrderCreated::class, OpsOrderUpdated::class]);

        $order = $this->checkout($this->customer);
        Event::assertDispatched(OpsOrderCreated::class, fn (OpsOrderCreated $event) => $event->uuid === (string) $order->uuid
            && $event->broadcastWith()['order']['status'] === 'new'
            && !array_key_exists('total', $event->broadcastWith()['order']));

        app(OrderStatusService::class)->transition($order, OrderStatus::ACCEPTED, $this->manager);
        Event::assertDispatched(OpsOrderUpdated::class, fn (OpsOrderUpdated $event) => $event->change === 'status' && $event->status === 'accepted');

        $pos = $this->makeOrder(OrderStatus::ACCEPTED, ['channel' => 'pos_hall']);
        app(OrderStatusService::class)->transition($pos, OrderStatus::PREPARING, null);
        Event::assertDispatchedTimes(OpsOrderUpdated::class, 1);
    }

    public function test_attention_sources(): void
    {
        $unaccepted = $this->orderWithHistory(9);
        $this->orderWithHistory(2); // in time
        $this->orderWithHistory(30, [], ['payment_method' => 'payme', 'payment_status' => 'pending']); // the customer's to pay
        $latePrep = $this->orderWithHistory(70, ['accepted' => 69]);
        $noCourier = $this->orderWithHistory(40, ['accepted' => 39, 'preparing' => 38, 'ready' => 12]);
        $this->orderWithHistory(40, ['accepted' => 39, 'preparing' => 38, 'ready' => 12], ['order_type' => 'pickup']);

        $sources = collect(app()->tagged(AttentionSource::TAG))->filter(fn ($source) => str_starts_with($source::class, 'App\\Services\\Admin\\Orders'));
        $this->assertCount(3, $sources);

        $result = app(UnacceptedOrdersSource::class)->collect(5);
        $this->assertSame(1, $result['count']);
        $item = $result['items'][0]->toArray();
        $this->assertSame(['order_unaccepted', 'critical', '/orders/' . $unaccepted->uuid], [$item['kind'], $item['severity'], $item['href']]);
        $this->assertStringContainsString('9 daqiqadan beri qabul qilinmadi', $item['subtitle']);
        $this->assertSame($this->store->id, $item['store_id']);

        $late = app(LateOrdersSource::class)->collect(5);
        $this->assertSame(1, $late['count']);
        $this->assertSame('/orders/' . $latePrep->uuid, $late['items'][0]->href);
        $this->assertStringContainsString('Tayyorlash 39 daqiqa kechikdi', (string) $late['items'][0]->subtitle);
        $this->assertSame('critical', $late['items'][0]->severity); // late by more than the whole limit again

        $courier = app(NoCourierOrdersSource::class)->collect(5);
        $this->assertSame(1, $courier['count']);
        $this->assertSame('/orders/' . $noCourier->uuid, $courier['items'][0]->href);
        $this->assertSame('critical', $courier['items'][0]->severity);

        $this->assertSame(['count' => 1, 'items' => []], app(UnacceptedOrdersSource::class)->collect(0));
    }
}
