<?php

namespace Tests\Feature\Kds;

use App\Enums\OrderStatus;
use App\Events\Broadcast\KitchenOrderAssembledBroadcast;
use App\Events\Broadcast\QueueNumberBroadcast;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\TestCase;

/**
 * P6-33 (B6, module `kds_expo`): an order cooked on several stations waits on the expo (assembly) station after its
 * last bump; the expo bump makes it ready and calls its number; a recall undoes an assembly; an order of one station,
 * a branch without the module or without an expo station keep the "every line ready → ready" path. The tickets carry
 * the time target of each line and whether it is late.
 */
class KdsExpoTest extends TestCase
{
    use KdsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpKds();
    }

    public function test_an_order_of_two_stations_waits_on_the_expo_and_its_bump_makes_it_ready(): void
    {
        Event::fake([QueueNumberBroadcast::class, KitchenOrderAssembledBroadcast::class]);
        $uuid = $this->openCombo();
        $order = Order::where('uuid', $uuid)->firstOrFail();
        [$shashlik, $tea] = $order->items()->orderBy('id')->get()->all();

        // The ticket: the expo view with each station's progress, the lines' targets
        $ticket = $this->ticketOf($uuid);
        $this->assertNotNull($ticket);
        $this->assertSame($this->expo->id, $ticket['assembly']['station_id']);
        $this->assertTrue($ticket['assembly']['required']);
        $this->assertFalse($ticket['assembly']['waiting']);
        $this->assertNull($ticket['assembly']['assembled_at']);
        $this->assertSame([
            ['station_id' => $this->grill->id, 'name' => 'Grill', 'kind' => 'grill', 'items_total' => 1, 'items_ready' => 0, 'done' => false],
            ['station_id' => $this->drinks->id, 'name' => 'Ichimliklar', 'kind' => 'drinks', 'items_total' => 1, 'items_ready' => 0, 'done' => false],
        ], $ticket['assembly']['stations']);
        $this->assertSame([300, 90], array_column($ticket['items'], 'target_seconds'));
        $this->assertSame([false, false], array_column($ticket['items'], 'late'));

        // Too early: a station is still cooking
        $this->cook('POST', "/staff/orders/{$uuid}/assembled")->assertStatus(422)->assertJsonPath('data.reason', 'stations_not_ready');

        // The grill is done (partial view), then the drinks: the order is not ready yet, it waits on the expo
        $this->bump($uuid, $shashlik->id)->assertOk()->assertJsonPath('data.assembly.stations.0.done', true)->assertJsonPath('data.assembly.stations.1.done', false);
        $this->bump($uuid, $tea->id)->assertOk()->assertJsonPath('data.assembly.waiting', true);
        $order->refresh();
        $this->assertSame(OrderStatus::PREPARING, $order->status);
        $this->assertSame('preparing', $order->pickup_status);
        Event::assertNotDispatched(QueueNumberBroadcast::class, fn (QueueNumberBroadcast $event) => $event->broadcastAs() === 'queue.ready');
        $this->tillAs('GET', '/pos/queue')->assertOk()->assertJsonPath('data.preparing.0.order_uuid', $uuid)->assertJsonCount(0, 'data.ready');

        // The expo bump: assembled, ready, the number called (the board and the customer display)
        Carbon::setTestNow(now()->addSeconds(40));
        $this->cook('POST', "/staff/orders/{$uuid}/assembled")->assertOk()
            ->assertJsonPath('message', "Buyurtma yig'ildi.")
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.pickup_status', 'ready')
            ->assertJsonPath('data.assembly.waiting', false)
            ->assertJsonPath('data.assembly.assembled_at', now()->toJSON());
        $order->refresh();
        $this->assertSame(OrderStatus::READY, $order->status);
        $this->assertSame('ready', $order->pickup_status);
        $this->assertSame($this->cook->id, (int) $order->getAttribute('assembled_by'));
        Event::assertDispatched(QueueNumberBroadcast::class, fn (QueueNumberBroadcast $event) => $event->broadcastAs() === 'queue.ready');
        Event::assertDispatched(KitchenOrderAssembledBroadcast::class, fn (KitchenOrderAssembledBroadcast $event) => $event->assembled
            && $event->broadcastAs() === 'kitchen.order_assembled' && $event->broadcastWith()['queue_number'] === 1);
        $this->tillAs('GET', '/pos/queue')->assertOk()->assertJsonPath('data.ready.0.order_uuid', $uuid);
        $this->assertDatabaseHas('audit_logs', ['action' => 'kds.order_assembled']);

        // Again: unchanged
        $this->cook('POST', "/staff/orders/{$uuid}/assembled")->assertOk()->assertJsonPath('message', "O'zgarish yo'q.");

        // A line recalled: the assembly is undone, the order and its number are preparing again
        $this->cook('POST', "/staff/orders/{$uuid}/items/{$tea->id}/recall")->assertOk();
        $order->refresh();
        $this->assertNull($order->getAttribute('assembled_at'));
        $this->assertSame(OrderStatus::PREPARING, $order->status);
        $this->assertSame('preparing', $order->pickup_status);
        Event::assertDispatched(KitchenOrderAssembledBroadcast::class, fn (KitchenOrderAssembledBroadcast $event) => !$event->assembled);

        // Bumped again: back on the expo, assembled again
        $this->bump($uuid, $tea->id)->assertOk()->assertJsonPath('data.assembly.waiting', true);
        $this->assertSame(OrderStatus::PREPARING, $order->refresh()->status);
        $this->cook('POST', "/staff/orders/{$uuid}/assembled")->assertOk()->assertJsonPath('data.status', 'ready');

        // Handed over at the counter as before
        $this->tillAs('POST', "/pos/orders/{$uuid}/handed")->assertOk()->assertJsonPath('data.status', 'handed');
    }

    public function test_an_order_of_one_station_and_a_branch_without_the_module_or_an_expo_keep_the_old_path(): void
    {
        // One station: the last bump makes it ready, the expo has nothing to do
        $single = (string) $this->openTakeaway()->assertCreated()->json('data.uuid');
        $this->assertNull($this->ticketOf($single)['assembly']);
        $this->bumpAll($single);
        $this->assertSame(OrderStatus::READY, Order::where('uuid', $single)->firstOrFail()->status);
        $this->cook('POST', "/staff/orders/{$single}/assembled")->assertStatus(422)->assertJsonPath('data.reason', 'assembly_not_required');

        // The expo station switched off: two stations, no assembly
        $this->expo->forceFill(['is_active' => false])->save();
        $noExpo = $this->openCombo();
        $this->assertNull($this->ticketOf($noExpo)['assembly']);
        $this->bumpAll($noExpo);
        $this->assertSame('ready', Order::where('uuid', $noExpo)->firstOrFail()->pickup_status);
        $this->expo->forceFill(['is_active' => true])->save();

        // The module switched off: no assembly, and the expo bump is refused
        $this->useModules(['kds_expo' => false]);
        $off = $this->openCombo();
        $this->assertNull($this->ticketOf($off)['assembly']);
        $this->bumpAll($off);
        $this->assertSame(OrderStatus::READY, Order::where('uuid', $off)->firstOrFail()->status);
        $this->cook('POST', "/staff/orders/{$off}/assembled")->assertStatus(403)->assertJsonPath('data.reason', 'module_disabled');
    }

    public function test_a_counter_order_paid_first_is_called_once_the_expo_assembled_it(): void
    {
        Event::fake([QueueNumberBroadcast::class]);
        $response = $this->tillAs('POST', '/pos/orders', [
            'channel' => 'pos_takeaway',
            'items' => [['variant_id' => $this->shashlik->uuid, 'quantity' => 1], ['variant_id' => $this->tea->uuid, 'quantity' => 1]],
            'send' => true,
        ])->assertCreated();
        $uuid = (string) $response->json('data.uuid');
        $bill = (string) $this->tillAs('POST', "/pos/orders/{$uuid}/bills", ['version' => $response->json('data.version'), 'mode' => 'single'])->assertSuccessful()->json('data.bills.0.uuid');
        $this->tillAs('POST', "/pos/bills/{$bill}/payments", ['shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '23000']]])->assertSuccessful();
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(OrderStatus::DELIVERED, $order->status);

        $this->bumpAll($uuid);
        $this->assertSame('preparing', $order->refresh()->pickup_status);
        $this->assertTrue($this->ticketOf($uuid)['assembly']['waiting']);

        $this->cook('POST', "/staff/orders/{$uuid}/assembled")->assertOk()->assertJsonPath('data.status', 'delivered')->assertJsonPath('data.pickup_status', 'ready');
        $this->assertSame(OrderStatus::DELIVERED, $order->refresh()->status);
        Event::assertDispatched(QueueNumberBroadcast::class, fn (QueueNumberBroadcast $event) => $event->broadcastAs() === 'queue.ready');
        // The payment and the bill were never touched
        $this->assertNotNull($order->closed_at);
    }

    public function test_a_telegram_pickup_order_routed_by_its_dishes_waits_on_the_expo_and_the_customer_hears_ready(): void
    {
        $telegram = new FakeTelegramMessenger();
        $this->app->instance(TelegramMessenger::class, $telegram);
        $this->customer->forceFill(['telegram_id' => 7001])->save();
        // The cheeseburger goes to the grill; the osh line has no dish: the default printer, a second "station"
        $this->route($this->variant, $this->grill);
        $online = $this->orderWithItems(OrderStatus::NEW, ['order_type' => 'pickup']);
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/accept")->assertOk();

        $ticket = $this->ticketOf((string) $online->uuid);
        $this->assertSame([$this->grill->id, null], array_column($ticket['assembly']['stations'], 'station_id'));
        // An online line carries a target only when one applies (else its P2-11 shape)
        $this->assertSame(300, $ticket['items'][0]['target_seconds']);
        $this->assertArrayNotHasKey('target_seconds', $ticket['items'][1]);

        foreach ($online->items()->get() as $item) {
            $this->bump((string) $online->uuid, $item->id)->assertOk();
        }
        $this->assertSame(OrderStatus::PREPARING, $online->refresh()->status);
        $this->assertCount(0, array_filter($telegram->sent, fn (array $message) => str_contains($message['text'], 'Buyurtmangiz tayyor, raqam')));

        $this->cook('POST', "/staff/orders/{$online->uuid}/assembled")->assertOk()->assertJsonPath('data.status', 'ready');
        $this->assertSame(OrderStatus::READY, $online->refresh()->status);
        $this->assertCount(1, array_filter($telegram->sent, fn (array $message) => $message['chat'] === '7001' && str_contains($message['text'], 'Buyurtmangiz tayyor, raqam')));
    }

    public function test_a_line_is_late_once_its_target_passed_and_a_dish_target_beats_the_station(): void
    {
        $uuid = $this->openCombo();
        $order = Order::where('uuid', $uuid)->firstOrFail();
        [$shashlik, $tea] = $order->items()->orderBy('id')->get()->all();

        // 91 s after sending: the drink (90 s) is late, the shashlik (300 s) is not
        Carbon::setTestNow(now()->addSeconds(91));
        $ticket = $this->ticketOf($uuid);
        $this->assertSame([false, true], array_column($ticket['items'], 'late'));

        // The drink bumped now stays late (its ready time is past the target); the shashlik's own dish target, 60 s
        $this->bump($uuid, $tea->id)->assertOk();
        $this->owner('PUT', '/owner/kds/targets/products', ['branch_id' => $this->branch->id, 'items' => [['product_id' => $this->shashlik->product_id, 'target_seconds' => 60]]])
            ->assertOk()->assertJsonPath('data.products.0.target_seconds', 60);
        $ticket = $this->ticketOf($uuid);
        $this->assertSame([60, 90], array_column($ticket['items'], 'target_seconds'));
        $this->assertSame([true, true], array_column($ticket['items'], 'late'));

        // Without a target a line is never late
        $this->owner('PUT', '/owner/kds/targets/products', ['branch_id' => $this->branch->id, 'items' => [['product_id' => $this->shashlik->product_id, 'target_seconds' => null]]])->assertOk()->assertJsonCount(0, 'data.products');
        $this->owner('PATCH', "/owner/kitchen-stations/{$this->grill->id}", ['target_seconds' => null])->assertOk()->assertJsonPath('data.target_seconds', null);
        $ticket = $this->ticketOf($uuid);
        $this->assertSame([null, 90], array_column($ticket['items'], 'target_seconds'));
        $this->assertSame([false, true], array_column($ticket['items'], 'late'));
    }

    public function test_the_owner_sets_station_kinds_and_targets_and_needs_the_module_for_a_target(): void
    {
        $this->owner('GET', "/owner/kds/targets?branch_id={$this->branch->id}")->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.limits', ['min' => 10, 'max' => 7200])
            ->assertJsonPath('data.stations.0.kind', 'grill')
            ->assertJsonPath('data.stations.0.target_seconds', 300)
            ->assertJsonPath('data.stations.2.kind_label', "Yig'ish")
            ->assertJsonPath('data.stations.2.default_target_seconds', 120);

        $id = (int) $this->owner('POST', '/owner/kitchen-stations', ['branch_id' => $this->branch->id, 'name' => 'Barista', 'kind' => 'barista', 'target_seconds' => 150])
            ->assertCreated()->assertJsonPath('data.kind_label', 'Barista')->assertJsonPath('data.target_seconds', 150)->json('data.id');
        $this->owner('PATCH', "/owner/kitchen-stations/{$id}", ['target_seconds' => 5])->assertStatus(422)->assertJsonValidationErrors('target_seconds');
        $this->owner('PATCH', "/owner/kitchen-stations/{$id}", ['kind' => 'pizza'])->assertStatus(422)->assertJsonValidationErrors('kind');
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.station_created']);

        // The expo station cooks nothing: no routes to it, a station with routes does not become one
        $this->owner('PUT', '/owner/kitchen-station-routes', ['branch_id' => $this->branch->id, 'station_id' => $this->expo->id, 'product_ids' => [$this->tea->product_id]])
            ->assertStatus(422)->assertJsonValidationErrors('station_id');
        $this->owner('PATCH', "/owner/kitchen-stations/{$this->grill->id}", ['kind' => 'expo'])->assertStatus(422)->assertJsonPath('data.reason', 'station_has_routes');

        // A dish of another store is refused; the change is audited
        $this->owner('PUT', '/owner/kds/targets/products', ['branch_id' => $this->branch->id, 'items' => [['product_id' => 999999, 'target_seconds' => 60]]])->assertStatus(422);
        $this->owner('PUT', '/owner/kds/targets/products', ['branch_id' => $this->branch->id, 'items' => [['product_id' => $this->tea->product_id, 'target_seconds' => 45]]])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'kds.product_targets_updated']);

        // Without the module: setting a target is refused, clearing one is not
        $this->useModules(['kds_expo' => false]);
        $this->owner('GET', "/owner/kds/targets?branch_id={$this->branch->id}")->assertOk()->assertJsonPath('data.enabled', false);
        $this->owner('PATCH', "/owner/kitchen-stations/{$id}", ['target_seconds' => 200])->assertStatus(403)->assertJsonPath('data.reason', 'module_disabled');
        $this->owner('PATCH', "/owner/kitchen-stations/{$id}", ['target_seconds' => null])->assertOk();
        $this->owner('PUT', '/owner/kds/targets/products', ['branch_id' => $this->branch->id, 'items' => [['product_id' => $this->tea->product_id, 'target_seconds' => 50]]])->assertStatus(403);
        $this->owner('PUT', '/owner/kds/targets/products', ['branch_id' => $this->branch->id, 'items' => [['product_id' => $this->tea->product_id, 'target_seconds' => null]]])
            ->assertOk()->assertJsonCount(0, 'data.products');

        // A cook or a terminal session does not set targets
        $this->cook('GET', "/owner/kds/targets?branch_id={$this->branch->id}")->assertStatus(403);
    }

    public function test_the_fast_food_template_seeds_the_universal_stations_with_their_targets(): void
    {
        \App\Models\KitchenStation::query()->where('branch_id', $this->branch->id)->delete();
        $this->owner('POST', '/owner/pos/format/template', ['branch_id' => $this->branch->id, 'stations' => true, 'menu' => false])->assertOk()
            ->assertJsonPath('data.stations_created', ['Grill', 'Fritur', 'Ichimliklar', "Yig'ish"]);
        $stations = \App\Models\KitchenStation::query()->where('branch_id', $this->branch->id)->orderBy('sort_order')->get();
        $this->assertSame(['grill', 'fryer', 'drinks', 'expo'], $stations->map(fn ($station) => $station->kind->value)->all());
        $this->assertSame([300, 240, 90, 120], $stations->pluck('target_seconds')->all());
    }

    public function test_the_choyxona_kitchen_display_is_unchanged(): void
    {
        // A choyxona branch: its stations have no targets and no expo; a takeaway of two stations is ready at its last bump
        $this->useProfile($this->branch, 'choyxona');
        $this->expo->delete();
        $this->grill->forceFill(['kind' => 'mangal', 'target_seconds' => null])->save();
        $this->drinks->forceFill(['kind' => 'tea', 'target_seconds' => null])->save();

        $uuid = $this->openCombo();
        $ticket = $this->ticketOf($uuid);
        $this->assertNull($ticket['assembly']);
        $this->assertSame([null, null], array_column($ticket['items'], 'target_seconds'));
        $this->assertSame([false, false], array_column($ticket['items'], 'late'));
        $this->bumpAll($uuid);
        $this->assertSame(OrderStatus::READY, Order::where('uuid', $uuid)->firstOrFail()->status);
        $this->assertSame(0, OrderItem::query()->whereIn('kitchen_status', ['pending', 'sent'])->count());
        $this->cook('POST', "/staff/orders/{$uuid}/assembled")->assertStatus(403)->assertJsonPath('data.reason', 'module_disabled');
    }
}
