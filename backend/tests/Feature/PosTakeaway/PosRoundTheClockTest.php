<?php

namespace Tests\Feature\PosTakeaway;

use App\Domain\Pos\Setup\PosSettings;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * P6-34 (module round_the_clock): a fast food branch open all night with its day starting at 05:00 — one shift from
 * Thursday evening into Friday morning: the orders at 23:59, 00:00 and 04:59 Tashkent are Thursday's, 05:00 starts
 * Friday's with queue number 1 again in the middle of the shift; the Z is the shift's (Thursday's) business day; the
 * business-day report and the bootstrap's business day say the same.
 */
class PosRoundTheClockTest extends TestCase
{
    use PosTakeawayFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosTakeaway();
        $this->branch->forceFill(['pos_settings' => array_merge($this->branch->pos_settings ?? [], ['modules' => ['round_the_clock' => true]])])->save();
        $this->bootstrapQueue();
    }

    public function test_the_business_day_turns_at_five_not_at_midnight(): void
    {
        $cases = [
            '2026-10-22 18:59:00' => '2026-10-22', // 23:59 Thursday
            '2026-10-22 19:00:00' => '2026-10-22', // 00:00 Friday, still Thursday's
            '2026-10-22 23:59:00' => '2026-10-22', // 04:59
            '2026-10-23 00:00:00' => '2026-10-23', // 05:00: Friday's day starts
        ];
        foreach ($cases as $utc => $date) {
            $this->assertSame($date, PosSettings::businessDate(Carbon::parse($utc, 'UTC'), 'Asia/Tashkent', '05:00'), $utc);
        }
    }

    public function test_one_shift_across_midnight_numbers_by_business_day_and_closes_on_its_own_day(): void
    {
        $numbers = [];
        foreach (['2026-10-22 18:59:00', '2026-10-22 19:00:00', '2026-10-22 23:59:00', '2026-10-23 00:00:00', '2026-10-23 00:01:00'] as $utc) {
            Carbon::setTestNow(Carbon::parse($utc, 'UTC'));
            $opened = $this->openTakeaway()->assertCreated();
            $order = Order::where('uuid', $opened->json('data.uuid'))->firstOrFail();
            $numbers[] = [$order->business_date?->toDateString(), (int) $order->queue_number, (int) $order->shift_id];
            $this->payAll((string) $order->uuid, (int) $opened->json('data.version'));
        }

        // Thursday 1, 2, 3; Friday starts at 1 again, all in the same shift
        $this->assertSame([
            ['2026-10-22', 1, $this->shift->id],
            ['2026-10-22', 2, $this->shift->id],
            ['2026-10-22', 3, $this->shift->id],
            ['2026-10-23', 1, $this->shift->id],
            ['2026-10-23', 2, $this->shift->id],
        ], $numbers);

        // The bootstrap at 05:01 knows the new day and when the next one starts
        $this->tillAs('GET', '/pos/bootstrap')->assertOk()
            ->assertJsonPath('data.takeaway.business_day.business_date', '2026-10-23')
            ->assertJsonPath('data.takeaway.business_day.started_at', '2026-10-23T00:00:00.000000Z')
            ->assertJsonPath('data.takeaway.business_day.next_starts_at', '2026-10-24T00:00:00.000000Z')
            ->assertJsonPath('data.takeaway.business_day.round_the_clock', true)
            ->assertJsonPath('data.queue.business_date', '2026-10-23');

        // The Z at 08:00 Friday is the shift's: Thursday's
        Carbon::setTestNow(Carbon::parse('2026-10-23 03:00:00', 'UTC'));
        $this->withBearer(null);
        $this->cashierToken = $this->pinSession($this->till, $this->cashier);
        $this->tillAs('POST', "/pos/shifts/{$this->shift->uuid}/close", ['counted_breakdown' => ['100000' => 1, '50000' => 3, '10000' => 3, '5000' => 1]])
            ->assertOk()->assertJsonPath('data.business_date', '2026-10-22');

        // The report by business day: Thursday 3 orders (2 after midnight), Friday 2; the shifts on their opening day
        $this->withBearer(null);
        $report = $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/branches/{$this->branch->id}/business-days?from=2026-10-22&to=2026-10-23")->assertOk()->json('data');
        $this->assertSame('2026-10-23', $report['business_day']['business_date']);
        [$thursday, $friday] = $report['days'];
        // 36 000 of shashlik and a 1 000 bag each
        $this->assertSame(['2026-10-22', 3, 2, '111000.00', '3000.00'], [$thursday['business_date'], $thursday['orders'], $thursday['after_midnight_orders'], $thursday['revenue'], $thursday['packaging_amount']]);
        $this->assertSame(['2026-10-23', 2, 0, '74000.00', '2000.00'], [$friday['business_date'], $friday['orders'], $friday['after_midnight_orders'], $friday['revenue'], $friday['packaging_amount']]);
        $this->assertSame('2026-10-22T18:59:00.000000Z', $thursday['first_order_at']);
        $this->assertSame('2026-10-22T23:59:00.000000Z', $thursday['last_order_at']);
        $this->assertSame([$this->shift->uuid, $this->shift2->uuid], collect($thursday['shifts'])->sortBy('opened_at')->pluck('uuid')->values()->all());
        $this->assertSame('closed', collect($thursday['shifts'])->firstWhere('uuid', $this->shift->uuid)['status']);
        $this->assertSame([], $friday['shifts']);
    }

    public function test_the_report_needs_the_module_and_at_most_31_days(): void
    {
        $this->withBearer(null);
        $url = "/api/v1/owner/branches/{$this->branch->id}/business-days";
        $this->actingAs($this->owner, 'sanctum')->getJson("{$url}?from=2026-09-01&to=2026-10-22")->assertStatus(422)->assertJsonValidationErrors('to');
        $this->actingAs($this->owner, 'sanctum')->getJson("{$url}?from=2026-10-23&to=2026-10-22")->assertStatus(422)->assertJsonValidationErrors('from');
        $this->actingAs($this->owner, 'sanctum')->getJson($url)->assertOk()->assertJsonCount(7, 'data.days')->assertJsonPath('data.days.6.business_date', '2026-10-22');

        $this->branch->forceFill(['pos_settings' => array_merge($this->branch->pos_settings ?? [], ['modules' => []])])->save();
        $this->actingAs($this->owner, 'sanctum')->getJson($url)->assertStatus(403)->assertJsonPath('data.reason', 'module_disabled');
    }
}
