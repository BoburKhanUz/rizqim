<?php

namespace Tests\Feature\PosTakeaway;

use App\Domain\Pos\Takeaway\ScheduledPickup;
use App\Models\KitchenStation;
use App\Models\KitchenStationRoute;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * P6-39 (found by the fast food end-to-end test, e2e/pos/test/lunch_rush_fast_food_test.dart): a Telegram pickup for a
 * later time on a fast food kitchen (stations with time targets, the expo).
 *  - Its kitchen clock starts at its kitchen time, not at its acceptance long before: the kitchen display does not
 *    flag it late the moment it shows, and the prep time report counts the cooking only.
 *  - Its lines name their station (routed by the dish), so a station's screen shows them; the ticket says the pickup.
 *  - One cooked and handed over before the minute job came round keeps its number handed (never back to preparing).
 *
 * The clock: Thursday 22.10.2026 18:00 in Tashkent (13:00 UTC).
 */
class PosScheduledPickupKitchenTest extends TestCase
{
    use PosTakeawayFixtures;
    use RefreshDatabase;

    private KitchenStation $grill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosTakeaway();
        $this->bootstrapQueue();
        $this->grill = KitchenStation::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Grill', 'kind' => 'grill',
            'sort_order' => 1, 'show_on_kds' => true, 'target_seconds' => 60,
        ]);
        KitchenStationRoute::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'station_id' => $this->grill->id,
            'product_id' => $this->shashlik->product_id, 'category_id' => null,
        ]);
    }

    /**
     * A Telegram pickup at 14:30 UTC (the kitchen gets it at 14:15), accepted at the till at 13:00.
     */
    private function telegramForLater(): Order
    {
        $this->order([['variant_id' => $this->shashlik->uuid, 'quantity' => 1]], ['pickup_at' => '2026-10-22T14:30:00Z'])->assertCreated();
        $online = Order::where('channel', 'telegram')->latest('id')->firstOrFail();
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/accept")->assertOk();

        return $online->refresh();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ticketOf(Order $order): ?array
    {
        foreach ($this->kitchenTickets() as $ticket) {
            if ($ticket['uuid'] === $order->uuid) {
                return $ticket;
            }
        }

        return null;
    }

    public function test_a_telegram_order_for_later_is_not_late_when_it_reaches_the_kitchen_and_shows_on_its_station(): void
    {
        $online = $this->telegramForLater();
        $this->assertSame('2026-10-22T14:15:00+00:00', $online->kitchen_fire_at?->utc()->toIso8601String());

        // 14:15:10, before the minute job: the kitchen display already shows it — 75 minutes after its acceptance, but
        // 10 seconds after its kitchen time: not late (the grill's target is 60 s)
        Carbon::setTestNow('2026-10-22 14:15:10');
        $ticket = $this->ticketOf($online);
        $this->assertNotNull($ticket);
        $line = $ticket['items'][0];
        $this->assertSame(60, $line['target_seconds']);
        $this->assertFalse($line['late']);
        $this->assertSame($this->grill->id, $line['station_id'], 'the line names its station');
        $this->assertSame(10, $ticket['waiting_seconds']);
        $this->assertSame('2026-10-22T14:30:00.000000Z', $ticket['pickup']['pickup_at']);
        $this->assertSame(1, app(ScheduledPickup::class)->fireDue());

        // Bumped at 14:16, a minute after its kitchen time: not late (60 s is the target), the report counts 60 s, not 76
        // minutes
        Carbon::setTestNow('2026-10-22 14:16:00');
        $item = OrderItem::where('order_id', $online->id)->firstOrFail();
        $this->withBearer(null);
        $this->actingAs($this->cook, 'sanctum')->postJson("/api/v1/staff/orders/{$online->uuid}/items/{$item->id}/ready")
            ->assertOk()->assertJsonPath('data.items.0.late', false);
        $this->app['auth']->forgetGuards();

        $report = $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/v1/owner/reports/kitchen-times?branch_id={$this->branch->id}&from=2026-10-22&to=2026-10-22")
            ->assertOk();
        $report->assertJsonPath('data.summary.count', 1)
            ->assertJsonPath('data.summary.median_seconds', 60)
            ->assertJsonPath('data.summary.over_target', 0)
            ->assertJsonPath('data.stations.0.station_id', $this->grill->id);
    }

    public function test_an_order_for_later_cooked_before_the_minute_job_keeps_its_number_handed(): void
    {
        $online = $this->telegramForLater();

        // The kitchen shows it from 14:15; the cook bumps it, it is ready and handed before the job runs at 14:15:50
        Carbon::setTestNow('2026-10-22 14:15:20');
        $item = OrderItem::where('order_id', $online->id)->firstOrFail();
        $this->withBearer(null);
        $this->actingAs($this->cook, 'sanctum')->postJson("/api/v1/staff/orders/{$online->uuid}/items/{$item->id}/ready")->assertOk();
        $this->app['auth']->forgetGuards();
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/handed")->assertOk();
        $this->assertSame('handed', $online->refresh()->pickup_status);

        Carbon::setTestNow('2026-10-22 14:15:50');
        $this->assertSame(1, app(ScheduledPickup::class)->fireDue());
        $online->refresh();
        $this->assertNotNull($online->kitchen_fired_at);
        $this->assertSame('handed', $online->pickup_status, 'never back to "Tayyorlanmoqda" on the board');
        $this->assertSame(0, app(ScheduledPickup::class)->fireDue());
    }
}
