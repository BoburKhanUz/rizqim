<?php

namespace Tests\Feature;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionInvoiceStatus;
use App\Jobs\RefreshPlatformStatsJob;
use App\Services\Admin\Today\PlatformStats;
use App\Services\Admin\Today\PlatformStatsRefresher;
use App\Services\Admin\Today\TodaySummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P4-13: the Bugun aggregate tables and what they count: Asia/Tashkent day and hour boundaries, which orders count
 * (marketplace channels; canceled, rejected, refunded and deleted ones out), commission and subscription revenue,
 * regions, the live part after the last refresh, comparisons, the job and the backfill command.
 */
class AdminTodayStatsTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use AdminTodayFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    private function refresher(): PlatformStatsRefresher
    {
        return app(PlatformStatsRefresher::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        return app(TodaySummary::class)->build();
    }

    public function test_orders_fall_on_the_tashkent_day_and_hour_they_were_created(): void
    {
        $this->at('2026-11-11 10:00');
        // 18:59:59 UTC on the 9th is still the 9th in Tashkent; 19:00 UTC is midnight of the 10th
        $this->orderAt('2026-11-09 23:59:59', attributes: ['total_price' => '100000.00', 'commission_amount' => '10000.00']);
        $this->orderAt('2026-11-10 00:00:00', attributes: ['total_price' => '70000.50', 'commission_amount' => '7000.05']);
        $this->orderAt('2026-11-10 23:59:59', attributes: ['total_price' => '30000.00', 'commission_amount' => '3000.00']);

        $this->refresher()->refreshDay('2026-11-09');
        $this->refresher()->refreshDay('2026-11-10');

        $daily = DB::table('platform_daily_stats')->where('channel', 'telegram')->orderBy('date')->get();
        $this->assertSame(['2026-11-09', '2026-11-10'], $daily->map(fn ($row) => substr($row->date, 0, 10))->all());
        $this->assertSame([1, 2], $daily->pluck('orders_count')->map(fn ($v) => (int) $v)->all());
        $this->assertSame('100000.50', (string) $daily[1]->gmv);
        $this->assertSame('10000.05', (string) $daily[1]->commission);

        $hours = DB::table('platform_hourly_stats')->where('date', '2026-11-10')->orderBy('hour')->pluck('hour')->map(fn ($h) => (int) $h)->all();
        $this->assertSame([0, 23], $hours);
        $this->assertSame([23], DB::table('platform_hourly_stats')->where('date', '2026-11-09')->pluck('hour')->map(fn ($h) => (int) $h)->all());

        // A past day is final through its end (Tashkent midnight = 19:00 UTC)
        $this->assertSame('2026-11-10 19:00:00', substr((string) DB::table('platform_stats_days')->where('date', '2026-11-10')->value('complete_through'), 0, 19));
    }

    public function test_canceled_rejected_refunded_and_deleted_orders_are_not_counted(): void
    {
        $this->at('2026-11-10 20:00');
        $this->orderAt('2026-11-10 09:00', OrderStatus::DELIVERED, ['total_price' => '50000.00', 'commission_amount' => '5000.00']);
        $this->orderAt('2026-11-10 09:10', OrderStatus::PREPARING, ['total_price' => '20000.00', 'commission_amount' => '2000.00']);
        $this->orderAt('2026-11-10 09:20', OrderStatus::CANCELED, ['total_price' => '99000.00', 'commission_amount' => '9900.00']);
        $this->orderAt('2026-11-10 09:30', OrderStatus::REJECTED, ['total_price' => '88000.00', 'commission_amount' => '8800.00']);
        $this->orderAt('2026-11-10 09:40', OrderStatus::REFUNDED, ['total_price' => '77000.00', 'commission_amount' => '7700.00']);
        $this->orderAt('2026-11-10 09:50', OrderStatus::DELIVERED, ['total_price' => '66000.00', 'commission_amount' => '6600.00'])->delete();

        $this->refresher()->refreshDay('2026-11-10');

        $row = DB::table('platform_daily_stats')->where('date', '2026-11-10')->where('channel', 'telegram')->first();
        $this->assertSame(2, (int) $row->orders_count);
        $this->assertSame('70000.00', (string) $row->gmv);
        $this->assertSame('7000.00', (string) $row->commission);
        $this->assertSame(2, (int) $row->canceled_count);
        $this->assertSame(1, (int) $row->refunded_count);

        $kpis = $this->summary()['kpis'];
        $this->assertSame('70000.00', $kpis['gmv']['value']);
        $this->assertSame(2, $kpis['orders']['value']);
        $this->assertSame('7000.00', $kpis['commission']['value']);
        $this->assertSame(2, $kpis['canceled_orders']['value']);
    }

    public function test_online_orders_still_waiting_for_their_payment_are_not_counted(): void
    {
        $this->at('2026-11-10 20:30');
        $this->orderAt('2026-11-10 09:00', OrderStatus::NEW, ['payment_method' => 'payme', 'payment_status' => 'pending', 'total_price' => '90000.00', 'commission_amount' => '9000.00']);
        $paidLater = $this->orderAt('2026-11-10 09:10', OrderStatus::NEW, ['payment_method' => 'click', 'payment_status' => 'pending', 'total_price' => '80000.00', 'commission_amount' => '8000.00']);
        $this->orderAt('2026-11-10 09:20', OrderStatus::NEW, ['payment_method' => 'payme', 'payment_status' => 'paid', 'total_price' => '50000.00', 'commission_amount' => '5000.00']);
        $this->orderAt('2026-11-10 09:30', OrderStatus::NEW, ['payment_method' => 'cash', 'payment_status' => 'pending', 'total_price' => '20000.00', 'commission_amount' => '2000.00']);
        // The current hour, counted live: the same rule
        $this->orderAt('2026-11-10 20:10', OrderStatus::NEW, ['payment_method' => 'uzum', 'payment_status' => 'pending', 'total_price' => '70000.00', 'commission_amount' => '7000.00']);
        $this->orderAt('2026-11-10 20:20', OrderStatus::NEW, ['payment_method' => 'keshbek', 'payment_status' => 'paid', 'total_price' => '10000.00', 'commission_amount' => '1000.00']);

        $this->refresher()->refreshDay('2026-11-10');
        $kpis = $this->summary()['kpis'];
        $this->assertSame(3, $kpis['orders']['value']);
        $this->assertSame('80000.00', $kpis['gmv']['value']);
        $this->assertSame('8000.00', $kpis['commission']['value']);
        $this->assertSame(0, $kpis['canceled_orders']['value']);

        // Paid later: a finished hour shows it from the next refresh on (at most 5 minutes)
        $paidLater->forceFill(['payment_status' => 'paid'])->saveQuietly();
        $this->assertSame(3, $this->summary()['kpis']['orders']['value']);
        $this->refresher()->refreshDay('2026-11-10');
        $this->assertSame(4, $this->summary()['kpis']['orders']['value']);
        $this->assertSame('160000.00', $this->summary()['kpis']['gmv']['value']);
    }

    public function test_only_marketplace_channels_make_the_headline_and_every_channel_is_split(): void
    {
        $this->at('2026-11-10 20:00');
        $this->orderAt('2026-11-10 10:00', attributes: ['channel' => OrderChannel::TELEGRAM->value, 'total_price' => '10000.00', 'commission_amount' => '1000.00']);
        $this->orderAt('2026-11-10 10:00', attributes: ['channel' => OrderChannel::MOBILE->value, 'total_price' => '20000.00', 'commission_amount' => '2000.00']);
        $this->orderAt('2026-11-10 10:00', attributes: ['channel' => OrderChannel::POS_HALL->value, 'total_price' => '300000.00', 'commission_amount' => '0.00', 'order_type' => 'dine_in']);
        $this->orderAt('2026-11-10 10:00', attributes: ['channel' => OrderChannel::POS_TAKEAWAY->value, 'total_price' => '40000.00', 'commission_amount' => '0.00', 'order_type' => 'pickup']);
        $this->orderAt('2026-11-10 10:00', attributes: ['channel' => OrderChannel::AGGREGATOR->value, 'total_price' => '50000.00', 'commission_amount' => '0.00']);

        $summary = $this->summary();
        $this->assertSame('30000.00', $summary['kpis']['gmv']['value']);
        $this->assertSame(2, $summary['kpis']['orders']['value']);
        $this->assertSame('3000.00', $summary['kpis']['commission']['value']);

        $channels = collect($summary['channels'])->keyBy('channel');
        $this->assertCount(count(OrderChannel::cases()), $channels);
        $this->assertSame('300000.00', $channels['pos_hall']['gmv']);
        $this->assertFalse($channels['pos_hall']['marketplace']);
        $this->assertTrue($channels['mobile']['marketplace']);
        // Marketplace channels come first
        $this->assertTrue($summary['channels'][0]['marketplace']);
        $this->assertTrue($summary['channels'][1]['marketplace']);
    }

    public function test_subscription_revenue_is_invoices_paid_that_day(): void
    {
        $this->at('2026-11-10 20:00');
        $this->invoicePaidAt('2026-11-10 00:00', '249000.00');
        $this->invoicePaidAt('2026-11-10 13:15', '149000.50');
        $this->invoicePaidAt('2026-11-09 23:59:59', '449000.00');
        // Not paid: issued, or voided after a payment mark was never made
        $this->invoicePaidAt(null, '59000.00', SubscriptionInvoiceStatus::ISSUED);
        $this->invoicePaidAt('2026-11-10 12:00', '29000.00', SubscriptionInvoiceStatus::VOID);

        $kpi = $this->summary()['kpis']['subscription_revenue'];
        $this->assertSame('398000.50', $kpi['value']);
        $this->assertSame('449000.00', $kpi['yesterday']);

        $this->refresher()->refreshDay('2026-11-10');
        $row = DB::table('platform_daily_stats')->where('date', '2026-11-10')->where('channel', PlatformStats::SUBSCRIPTIONS)->first();
        $this->assertSame(2, (int) $row->invoices_paid_count);
        $this->assertSame('398000.50', (string) $row->subscription_revenue);
        $this->assertSame(0, (int) $row->orders_count);
        $this->assertSame('398000.50', $this->summary()['kpis']['subscription_revenue']['value']);
        // Subscriptions are not a sales channel
        $this->assertNotContains(PlatformStats::SUBSCRIPTIONS, collect($this->summary()['channels'])->pluck('channel')->all());
    }

    public function test_stored_and_live_numbers_agree_and_the_current_hour_is_live(): void
    {
        $this->at('2026-11-10 14:05');
        $this->orderAt('2026-11-10 09:00', attributes: ['total_price' => '10000.00', 'commission_amount' => '1000.00']);
        $this->orderAt('2026-11-10 14:01', attributes: ['total_price' => '20000.00', 'commission_amount' => '2000.00']);

        // No refresh yet: everything is counted live
        $live = $this->summary();
        $this->assertSame('30000.00', $live['kpis']['gmv']['value']);
        $this->assertNull($live['stats_computed_at']);
        $this->assertSame($this->tashkent('2026-11-10 00:00')->toIso8601String(), $live['live_from']);

        (new RefreshPlatformStatsJob(2))->handle($this->refresher());
        $this->assertSame('2026-11-10 09:00:00', substr((string) DB::table('platform_stats_days')->where('date', '2026-11-10')->value('complete_through'), 0, 19));

        // After the refresh: hours before 14:00 from the table, 14:00 on live; a new order shows at once
        $this->at('2026-11-10 14:30');
        $this->orderAt('2026-11-10 14:29', attributes: ['total_price' => '5000.00', 'commission_amount' => '500.00']);
        $stored = $this->summary();
        $this->assertSame('35000.00', $stored['kpis']['gmv']['value']);
        $this->assertSame(3, $stored['kpis']['orders']['value']);
        $this->assertSame('3500.00', $stored['kpis']['commission']['value']);
        $this->assertSame($this->tashkent('2026-11-10 14:00')->toIso8601String(), $stored['live_from']);
        $this->assertNotNull($stored['stats_computed_at']);

        // A stored hour is what the last refresh saw: a later cancellation shows after the next refresh
        DB::table('orders')->where('total_price', '10000.00')->update(['status' => OrderStatus::CANCELED->value]);
        $this->assertSame('35000.00', $this->summary()['kpis']['gmv']['value']);
        $this->refresher()->refreshRecent();
        $this->assertSame('25000.00', $this->summary()['kpis']['gmv']['value']);
    }

    public function test_comparisons_use_the_same_time_of_day_and_whole_days(): void
    {
        $this->at('2026-11-10 14:30');
        $this->orderAt('2026-11-10 10:00', attributes: ['total_price' => '150000.00', 'commission_amount' => '15000.00']);
        // Yesterday: one before 14:30, one in the same hour but later, one in the evening
        $this->orderAt('2026-11-09 13:00', attributes: ['total_price' => '100000.00', 'commission_amount' => '10000.00']);
        $this->orderAt('2026-11-09 14:40', attributes: ['total_price' => '10000.00', 'commission_amount' => '1000.00']);
        $this->orderAt('2026-11-09 21:00', attributes: ['total_price' => '40000.00', 'commission_amount' => '4000.00']);
        // A week ago (same weekday)
        $this->orderAt('2026-11-03 14:29', attributes: ['total_price' => '300000.00', 'commission_amount' => '30000.00']);

        $this->refresher()->refreshRecent(8);
        $gmv = $this->summary()['kpis']['gmv'];
        $this->assertSame('150000.00', $gmv['value']);
        $this->assertSame('100000.00', $gmv['yesterday_same_time']);
        $this->assertSame('150000.00', $gmv['yesterday']);
        $this->assertSame('300000.00', $gmv['last_week_same_time']);
        $this->assertSame('300000.00', $gmv['last_week']);
        $this->assertSame(['direction' => 'up', 'percent' => '50.0'], $gmv['vs_yesterday']);
        $this->assertSame(['direction' => 'down', 'percent' => '50.0'], $gmv['vs_last_week']);

        $orders = $this->summary()['kpis']['orders'];
        $this->assertSame(1, $orders['yesterday_same_time']);
        $this->assertSame(3, $orders['yesterday']);
        $this->assertSame(['direction' => 'same', 'percent' => '0.0'], $orders['vs_yesterday']);

        // Without any refresh the same numbers come out of the live query
        DB::table('platform_stats_days')->delete();
        $this->assertSame($gmv, $this->summary()['kpis']['gmv']);
    }

    public function test_change_against_zero_has_no_percent(): void
    {
        $summary = app(TodaySummary::class);
        $this->assertSame(['direction' => 'up', 'percent' => null], $summary->change(\App\Support\Money::of('10'), \App\Support\Money::zero()));
        $this->assertSame(['direction' => 'same', 'percent' => null], $summary->change(0, 0));
        $this->assertSame(['direction' => 'down', 'percent' => '33.3'], $summary->change(2, 3));
    }

    public function test_regions_show_orders_and_active_stores(): void
    {
        $this->at('2026-11-10 20:00');
        $tashkent = $this->region('Toshkent shahri');
        $samarkand = $this->region('Samarqand viloyati');
        $this->branch->forceFill(['region_id' => $tashkent->id])->save();
        $this->otherBranch->forceFill(['region_id' => $samarkand->id])->save();

        $this->orderAt('2026-11-10 10:00', attributes: ['total_price' => '10000.00']);
        $this->orderAt('2026-11-10 11:00', attributes: ['total_price' => '15000.00']);
        $this->orderAt('2026-11-10 12:00', attributes: ['total_price' => '5000.00', 'branch_id' => $this->otherBranch->id]);
        // A store without a region and a suspended store in Tashkent
        $other = $this->makeStore('Regionless');
        $this->makeBranch($other, 'Somewhere');
        $suspended = $this->makeStore('Suspended');
        $suspended->forceFill(['status' => 'suspended'])->save();
        $this->makeBranch($suspended, 'Closed')->forceFill(['region_id' => $tashkent->id])->save();

        $this->refresher()->refreshDay('2026-11-10');
        $regions = collect($this->summary()['regions'])->keyBy('name');

        $this->assertSame(2, $regions['Toshkent shahri']['orders']);
        $this->assertSame('25000.00', $regions['Toshkent shahri']['gmv']);
        $this->assertSame(1, $regions['Toshkent shahri']['active_stores']);
        $this->assertSame($tashkent->id, $regions['Toshkent shahri']['region_id']);
        $this->assertSame(1, $regions['Samarqand viloyati']['orders']);
        $this->assertSame(1, $regions['Samarqand viloyati']['active_stores']);
        $this->assertNull($regions["Viloyat ko'rsatilmagan"]['region_id']);
        $this->assertSame(1, $regions["Viloyat ko'rsatilmagan"]['active_stores']);
        $this->assertSame('Toshkent shahri', $this->summary()['regions'][0]['name']);
    }

    public function test_refreshing_is_idempotent_and_skips_future_days(): void
    {
        $this->at('2026-11-10 12:00');
        $this->orderAt('2026-11-10 10:00');
        $this->refresher()->refreshDay('2026-11-10');
        $this->refresher()->refreshDay('2026-11-10');

        $this->assertSame(1, DB::table('platform_daily_stats')->where('date', '2026-11-10')->count());
        $this->assertSame(1, DB::table('platform_hourly_stats')->where('date', '2026-11-10')->count());
        $this->assertFalse($this->refresher()->refreshDay('2026-11-11'));
        $this->assertSame(0, DB::table('platform_stats_days')->where('date', '2026-11-11')->count());
    }

    public function test_backfill_command_recomputes_past_days(): void
    {
        $this->at('2026-11-10 12:00');
        $this->orderAt('2026-11-01 10:00', attributes: ['total_price' => '1000.00']);
        $this->orderAt('2026-11-05 10:00', attributes: ['total_price' => '2000.00']);

        $this->artisan('admin:stats-backfill', ['--from' => '2026-11-01', '--to' => '2026-11-05'])
            ->expectsOutputToContain('5 day(s) recomputed')
            ->assertSuccessful();
        $this->assertSame(5, DB::table('platform_stats_days')->count());
        $this->assertSame('2000.00', (string) DB::table('platform_daily_stats')->where('date', '2026-11-05')->value('gmv'));

        $this->artisan('admin:stats-backfill', ['--days' => 2])->expectsOutputToContain('3 day(s) recomputed')->assertSuccessful();
        $this->artisan('admin:stats-backfill', ['--from' => '2026-11-31'])->assertExitCode(2);
        $this->artisan('admin:stats-backfill', ['--from' => '2026-11-06', '--to' => '2026-11-05'])->assertExitCode(2);
    }

    public function test_the_refresh_is_scheduled(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->keyBy('description');

        $this->assertSame('*/5 * * * *', $events['admin-platform-stats']->expression);
        $this->assertSame('20 0 * * *', $events['admin-platform-stats-week']->expression);
        $this->assertSame(PlatformStats::TIMEZONE, $events['admin-platform-stats-week']->timezone);
    }
}
