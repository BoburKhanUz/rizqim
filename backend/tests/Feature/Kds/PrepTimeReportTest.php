<?php

namespace Tests\Feature\Kds;

use App\Domain\Pos\Kitchen\PrepTimeReport;
use App\Enums\OrderStatus;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * P6-33 (B6): the prep time report from the lines' real timestamps — per station, dish and hour, median and p90,
 * over-target counts — on fixed times; the business day of Tashkent (05:00 start) compared in UTC; the expo's
 * assembly time and the orders' kitchen time; online lines from their acceptance on their routed station.
 */
class PrepTimeReportTest extends TestCase
{
    use KdsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpKds();
    }

    /**
     * Carbon at 2026-10-22 13:00 UTC (18:00 Tashkent) + $seconds.
     */
    private function at(int $seconds): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 13:00:00', 'UTC')->addSeconds($seconds));
    }

    /**
     * @return array{0: string, 1: OrderItem, 2: OrderItem} the order and its shashlik and tea lines
     */
    private function combo(int $sentAt): array
    {
        $this->at($sentAt);
        $uuid = $this->openCombo();
        [$shashlik, $tea] = Order::where('uuid', $uuid)->firstOrFail()->items()->orderBy('id')->get()->all();

        return [$uuid, $shashlik, $tea];
    }

    private function seedDay(): void
    {
        // A: sent 13:00:00, drink +60, grill +240, assembled +300
        [$a, $aShashlik, $aTea] = $this->combo(0);
        $this->at(60);
        $this->bump($a, $aTea->id)->assertOk();
        $this->at(240);
        $this->bump($a, $aShashlik->id)->assertOk();
        $this->at(300);
        $this->cook('POST', "/staff/orders/{$a}/assembled")->assertOk();

        // B: sent 13:10:00, drink +100 (late), grill +360 (late), assembled +500 (140 s on the expo: late)
        [$b, $bShashlik, $bTea] = $this->combo(600);
        $this->at(700);
        $this->bump($b, $bTea->id)->assertOk();
        $this->at(960);
        $this->bump($b, $bShashlik->id)->assertOk();
        $this->at(1100);
        $this->cook('POST', "/staff/orders/{$b}/assembled")->assertOk();

        // C: one grill line, sent 13:20:00, bumped +120
        $this->at(1200);
        $c = (string) $this->openTakeaway()->assertCreated()->json('data.uuid');
        $this->at(1320);
        $this->bumpAll($c);

        // D: a drink at 04:30 Tashkent (23:30 UTC the day before): the 21st's business day. E: 05:30 Tashkent, +45 s
        foreach (['2026-10-21 23:30:00' => 30, '2026-10-22 00:30:00' => 45] as $sent => $took) {
            $this->at(1400);
            $uuid = (string) $this->tillAs('POST', '/pos/orders', ['channel' => 'pos_takeaway', 'items' => [['variant_id' => $this->tea->uuid, 'quantity' => 1]], 'send' => true])
                ->assertCreated()->json('data.uuid');
            $this->bumpAll($uuid);
            $sentAt = Carbon::parse($sent, 'UTC');
            OrderItem::where('order_id', Order::where('uuid', $uuid)->value('id'))->update(['sent_at' => $sentAt, 'ready_at' => $sentAt->copy()->addSeconds($took)]);
        }
        $this->at(3600);
    }

    public function test_the_report_counts_each_station_dish_and_hour_from_the_real_timestamps(): void
    {
        $this->seedDay();

        $data = $this->owner('GET', "/owner/reports/kitchen-times?branch_id={$this->branch->id}&from=2026-10-22")->assertOk()->json('data');

        $this->assertSame(['from' => '2026-10-22', 'to' => '2026-10-22', 'timezone' => 'Asia/Tashkent', 'day_starts_at' => '05:00'], array_intersect_key($data, array_flip(['from', 'to', 'timezone', 'day_starts_at'])));
        // Lines: 45, 60, 100, 120, 240, 360
        $this->assertSame(['count' => 6, 'median_seconds' => 110, 'p90_seconds' => 300, 'with_target' => 6, 'over_target' => 2, 'over_target_share' => '33.3'], $data['summary']);

        $this->assertSame([
            ['station_id' => $this->grill->id, 'name' => 'Grill', 'kind' => 'grill', 'target_seconds' => 300, 'count' => 3, 'median_seconds' => 240, 'p90_seconds' => 336, 'with_target' => 3, 'over_target' => 1, 'over_target_share' => '33.3'],
            ['station_id' => $this->drinks->id, 'name' => 'Ichimliklar', 'kind' => 'drinks', 'target_seconds' => 90, 'count' => 3, 'median_seconds' => 60, 'p90_seconds' => 92, 'with_target' => 3, 'over_target' => 1, 'over_target_share' => '33.3'],
        ], $data['stations']);

        $this->assertSame(['Choy', 'Shashlik'], array_column($data['products'], 'name'));
        $this->assertSame([$this->tea->product_id, $this->shashlik->product_id], array_column($data['products'], 'product_id'));
        $this->assertSame([3, 3], array_column($data['products'], 'count'));

        $this->assertCount(24, $data['hours']);
        $this->assertSame(['hour' => 18, 'count' => 5, 'median_seconds' => 120, 'p90_seconds' => 312, 'with_target' => 5, 'over_target' => 2, 'over_target_share' => '40.0'], $data['hours'][18]);
        $this->assertSame(1, $data['hours'][5]['count']);
        $this->assertSame(45, $data['hours'][5]['median_seconds']);
        $this->assertSame(0, $data['hours'][4]['count']);
        $this->assertNull($data['hours'][4]['median_seconds']);

        // Orders: A 300 (assembled), B 500 (assembled), C 120, E 45
        $this->assertSame(['count' => 4, 'median_seconds' => 210, 'p90_seconds' => 440, 'with_target' => 0, 'over_target' => 0, 'over_target_share' => null], $data['orders']);
        // The expo: A 60, B 140 against 120 s
        $this->assertSame(['station_id' => $this->expo->id, 'name' => "Yig'ish", 'target_seconds' => 120, 'count' => 2, 'median_seconds' => 100, 'p90_seconds' => 132, 'with_target' => 2, 'over_target' => 1, 'over_target_share' => '50.0'], $data['expo']);

        // The 21st's business day has only D
        $day21 = $this->owner('GET', "/owner/reports/kitchen-times?branch_id={$this->branch->id}&from=2026-10-21&to=2026-10-21")->assertOk()->json('data');
        $this->assertSame(1, $day21['summary']['count']);
        $this->assertSame(30, $day21['summary']['median_seconds']);
        $this->assertNull($day21['expo']['median_seconds']);
    }

    public function test_a_station_filter_narrows_the_lines_and_the_range_is_checked(): void
    {
        $this->seedDay();

        $grill = $this->owner('GET', "/owner/reports/kitchen-times?branch_id={$this->branch->id}&from=2026-10-22&station_id={$this->grill->id}")->assertOk()->json('data');
        $this->assertSame(3, $grill['summary']['count']);
        $this->assertSame([$this->grill->id], array_column($grill['stations'], 'station_id'));
        $this->assertSame(['Shashlik'], array_column($grill['products'], 'name'));
        $this->assertNull($grill['expo']);
        $this->assertSame(4, $grill['orders']['count']);

        $expo = $this->owner('GET', "/owner/reports/kitchen-times?branch_id={$this->branch->id}&from=2026-10-22&station_id={$this->expo->id}")->assertOk()->json('data');
        $this->assertSame(0, $expo['summary']['count']);
        $this->assertSame(2, $expo['expo']['count']);

        $other = KitchenStation::create(['store_id' => $this->store->id, 'branch_id' => $this->makeBranch($this->store, 'Chilonzor')->id, 'name' => 'Boshqa', 'kind' => 'grill']);
        $this->owner('GET', "/owner/reports/kitchen-times?branch_id={$this->branch->id}&station_id={$other->id}")->assertStatus(422)->assertJsonValidationErrors('station_id');
        $this->owner('GET', "/owner/reports/kitchen-times?branch_id={$this->branch->id}&from=2026-10-01&to=2026-11-01")->assertStatus(422)->assertJsonValidationErrors('to');
        $this->owner('GET', "/owner/reports/kitchen-times?branch_id={$this->branch->id}&from=2026-10-02&to=2026-11-01")->assertOk();
        $this->owner('GET', "/owner/reports/kitchen-times?branch_id={$this->branch->id}&from=2026-10-22&to=2026-10-21")->assertStatus(422);

        // Without the kitchen display module: 403
        $this->useModules(['kds' => false]);
        $this->owner('GET', "/owner/reports/kitchen-times?branch_id={$this->branch->id}")->assertStatus(403)->assertJsonPath('data.reason', 'module_disabled');
    }

    public function test_an_online_line_counts_from_its_acceptance_on_the_station_its_dish_routes_to(): void
    {
        $this->route($this->variant, $this->grill);
        $this->at(0);
        $online = $this->orderWithItems(OrderStatus::NEW, ['order_type' => 'pickup']);
        $this->at(120);
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/accept")->assertOk();
        $this->at(320);
        foreach ($online->items()->get() as $item) {
            $this->bump((string) $online->uuid, $item->id)->assertOk();
        }

        $data = $this->owner('GET', "/owner/reports/kitchen-times?branch_id={$this->branch->id}&from=2026-10-22")->assertOk()->json('data');
        // Both lines took 200 s from the acceptance; the cheeseburger on the grill (target 300), the osh on no station
        $this->assertSame(2, $data['summary']['count']);
        $this->assertSame(200, $data['summary']['median_seconds']);
        $this->assertSame(1, $data['summary']['with_target']);
        $this->assertSame([$this->grill->id, null], array_column($data['stations'], 'station_id'));
        $this->assertSame('Sexsiz', $data['stations'][1]['name']);
        $this->assertSame([200, 200], array_column($data['stations'], 'median_seconds'));
    }

    public function test_the_percentiles_interpolate_between_ranks(): void
    {
        $this->assertNull(PrepTimeReport::percentile([], 0.5));
        $this->assertSame(7, PrepTimeReport::percentile([7], 0.9));
        $this->assertSame(15, PrepTimeReport::percentile([10, 20], 0.5));
        $this->assertSame(19, PrepTimeReport::percentile([10, 20], 0.9));
        $this->assertSame(['count' => 3, 'median_seconds' => 20, 'p90_seconds' => 28, 'with_target' => 2, 'over_target' => 1, 'over_target_share' => '50.0'], PrepTimeReport::stats([30, 10, 20], ['with' => 2, 'over' => 1]));
    }
}
