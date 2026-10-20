<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\SendOrderReport;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Report exports are heavy worker jobs: they run on a queue of their own, are rate limited, cover a bounded period
 * and are written a chunk of orders at a time.
 */
class ReportExportTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_token' => '123456:report-token']);
        $this->setUpLifecycleStore();
        $this->owner->update(['telegram_id' => '5003']);
    }

    private function export(array $payload)
    {
        return $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/reports/export', $payload);
    }

    public function test_reports_go_to_their_own_queue(): void
    {
        Queue::fake([SendOrderReport::class]);

        $this->export(['timeframe' => 'weekly'])->assertOk();

        Queue::assertPushedOn('reports', SendOrderReport::class);
    }

    public function test_a_custom_period_needs_both_dates_in_order_and_at_most_93_days(): void
    {
        Queue::fake([SendOrderReport::class]);

        $this->export(['timeframe' => 'custom'])->assertStatus(422)
            ->assertJsonPath('errors.start_date.0', 'Hisobot davrining boshlanish sanasini kiriting.')
            ->assertJsonPath('errors.end_date.0', 'Hisobot davrining tugash sanasini kiriting.');
        $this->export(['timeframe' => 'custom', 'start_date' => '2026-07-01', 'end_date' => '2026-06-30'])->assertStatus(422)
            ->assertJsonPath('errors.end_date.0', "Tugash sanasi boshlanish sanasidan oldin bo'lishi mumkin emas.");
        $this->export(['timeframe' => 'custom', 'start_date' => '2026-07-01', 'end_date' => '2026-10-02'])->assertStatus(422)
            ->assertJsonPath('errors.end_date.0', "Hisobot davri ko'pi bilan 93 kun bo'lishi mumkin.");
        Queue::assertNothingPushed();
        $this->travel(61)->seconds(); // past the per-minute limit

        // 1 July to 1 October: 93 days, both ends included
        $this->export(['timeframe' => 'custom', 'start_date' => '2026-07-01', 'end_date' => '2026-10-01'])->assertOk();
        Queue::assertPushed(SendOrderReport::class, fn (SendOrderReport $job) => $job->startDate === '2026-07-01' && $job->endDate === '2026-10-01');
    }

    public function test_exports_are_limited_to_3_a_minute_and_20_a_day_per_user(): void
    {
        Queue::fake([SendOrderReport::class]);

        foreach (range(1, 3) as $_) {
            $this->export(['timeframe' => 'today'])->assertOk();
        }
        $this->export(['timeframe' => 'today'])->assertStatus(429);
        // Another user has a bucket of their own
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/reports/export', ['timeframe' => 'today'])->assertOk();

        for ($sent = 3; $sent < 20; $sent++) {
            if ($sent % 3 === 0) {
                $this->travel(61)->seconds();
            }
            $this->export(['timeframe' => 'today'])->assertOk();
        }
        $this->travel(61)->seconds();
        $this->export(['timeframe' => 'today'])->assertStatus(429);
        Queue::assertPushed(SendOrderReport::class, 21);
    }

    public function test_the_report_is_written_a_chunk_of_orders_at_a_time_and_covers_exactly_the_period(): void
    {
        $sent = null;
        Http::fake(function (HttpRequest $request) use (&$sent) {
            $sent = $request->body();

            return Http::response(['ok' => true, 'result' => true]);
        });

        $orders = collect(range(1, 450))->map(fn (int $i) => $this->makeOrder(
            $i % 3 === 0 ? OrderStatus::CANCELED : OrderStatus::DELIVERED,
            ['total_price' => 10000 + $i, 'short_code' => "R{$i}"]
        ));
        $old = $this->makeOrder(OrderStatus::DELIVERED, ['short_code' => 'OLD1']);
        DB::table('orders')->where('id', $old->id)->update(['updated_at' => now()->subDays(40)]);

        $chunks = 0;
        DB::listen(function ($query) use (&$chunks) {
            if (str_contains($query->sql, 'from "orders"') && str_contains($query->sql, 'limit 200')) {
                $chunks++;
            }
        });

        $this->export(['timeframe' => 'monthly'])->assertOk();

        $this->assertSame(3, $chunks, '450 orders in chunks of 200');
        foreach ($orders as $order) {
            $this->assertStringContainsString("#{$order->short_code}", $sent);
        }
        $this->assertStringNotContainsString('#OLD1', $sent);
        // Newest first
        $this->assertLessThan(strpos($sent, "#{$orders->first()->short_code},"), strpos($sent, "#{$orders->last()->short_code},"));

        $delivered = $orders->filter(fn (Order $order) => $order->status === OrderStatus::DELIVERED);
        $revenue = number_format($delivered->sum(fn (Order $order) => (float) $order->total_price), 0, '.', ' ');
        $this->assertStringContainsString("Jami tushum:* {$revenue} UZS", $sent);
        $this->assertStringContainsString("Muvaffaqiyatli:* {$delivered->count()} ta", $sent);
        $this->assertStringContainsString('Bekor qilingan:* 150 ta', $sent);
    }
}
