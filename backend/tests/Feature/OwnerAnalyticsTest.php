<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * GET /api/v1/owner/analytics: server-side sales aggregates for the owner's store (a branch manager's branch).
 * "Now" is 2026-10-20 12:00 in Tashkent (07:00 UTC); periods are Tashkent calendar days.
 */
class OwnerAnalyticsTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-20 07:00:00', 'UTC'));
        Cache::flush();
        $this->setUpLifecycleStore();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function analytics(?User $user, array $query = []): TestResponse
    {
        $request = $user ? $this->actingAs($user, 'sanctum') : $this;

        return $request->getJson('/api/v1/owner/analytics' . ($query ? '?' . http_build_query($query) : ''));
    }

    /** An order created at a UTC time, with one item of $quantity at $unit. */
    private function sale(OrderStatus $status, string $createdAtUtc, array $attributes = [], string $name = 'Cheeseburger', int $quantity = 1, int|string $unit = 50000): Order
    {
        $order = $this->makeOrder($status, array_merge([
            'created_at' => $createdAtUtc,
            'updated_at' => $createdAtUtc,
            'total_price' => $unit * $quantity,
        ], $attributes));
        OrderItem::create([
            'order_id' => $order->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => $name,
            'quantity' => $quantity,
            'unit_price' => $unit,
            'total_price' => $unit * $quantity,
        ]);

        return $order;
    }

    private function history(Order $order, string $status, string $atUtc): void
    {
        DB::table('order_status_histories')->insert(['order_id' => $order->id, 'status' => $status, 'created_at' => $atUtc, 'updated_at' => $atUtc]);
    }

    // --- Figures ------------------------------------------------------------------------------------------------

    public function test_today_aggregates_the_stores_orders(): void
    {
        $courierTwo = $this->makeStaff(UserRole::COURIER, $this->branch);

        $this->sale(OrderStatus::DELIVERED, '2026-10-20 03:00:00', ['courier_id' => $this->courier->id], 'Cheeseburger', 2, 40000);
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 04:00:00', ['courier_id' => $this->courier->id, 'payment_method' => 'click'], 'Cola', 1, 10000);
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 05:00:00', ['courier_id' => $courierTwo->id, 'order_type' => 'pickup'], 'Cheeseburger', 1, 40000);
        $this->sale(OrderStatus::CANCELED, '2026-10-20 05:30:00', [], 'Cola', 1, 10000);
        $this->sale(OrderStatus::REJECTED, '2026-10-20 05:40:00');
        $this->sale(OrderStatus::REFUNDED, '2026-10-20 05:50:00');
        $this->sale(OrderStatus::PREPARING, '2026-10-20 06:00:00');

        $data = $this->analytics($this->owner)->assertOk()->assertJsonPath('success', true)->json('data');

        $this->assertSame(['key' => 'today', 'from' => '2026-10-20', 'to' => '2026-10-20', 'days' => 1, 'timezone' => 'Asia/Tashkent', 'basis' => 'created_at'], $data['period']);
        $this->assertEquals([
            'orders_count' => 7,
            'delivered_count' => 3,
            'canceled_count' => 2,
            'refunded_count' => 1,
            'active_count' => 1,
            'revenue' => 130000.0,
            'average_check' => 43333.33,
        ], $data['summary']);
        $this->assertSame(['canceled' => 1, 'delivered' => 3, 'preparing' => 1, 'refunded' => 1, 'rejected' => 1], $data['by_status']);

        $this->assertEquals([
            ['method' => 'cash', 'orders' => 2, 'revenue' => 120000.0],
            ['method' => 'click', 'orders' => 1, 'revenue' => 10000.0],
        ], $data['by_payment_method']);
        $this->assertEquals([
            ['type' => 'delivery', 'orders' => 2, 'revenue' => 90000.0],
            ['type' => 'pickup', 'orders' => 1, 'revenue' => 40000.0],
        ], $data['by_order_type']);
        // Only delivered orders count towards the rankings
        $this->assertEquals([
            ['name' => 'Cheeseburger', 'quantity' => 3, 'revenue' => 120000.0],
            ['name' => 'Cola', 'quantity' => 1, 'revenue' => 10000.0],
        ], $data['top_products']);
        $this->assertSame([
            ['id' => $this->courier->id, 'name' => $this->courier->name, 'delivered_count' => 2],
            ['id' => $courierTwo->id, 'name' => $courierTwo->name, 'delivered_count' => 1],
        ], $data['couriers']);
    }

    public function test_money_is_summed_exactly_to_the_tiyin(): void
    {
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 03:00:00', ['total_price' => '0.10']);
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 03:10:00', ['total_price' => '0.20']);
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 03:20:00', ['total_price' => '100.15']);

        $summary = $this->analytics($this->owner)->assertOk()->json('data.summary');

        $this->assertEquals(100.45, $summary['revenue']);
        // 100.45 / 3 = 33.4833…, half-up to the tiyin
        $this->assertEquals(33.48, $summary['average_check']);
    }

    public function test_an_empty_period_is_all_zeros(): void
    {
        $data = $this->analytics($this->owner, ['period' => 'week'])->assertOk()->json('data');

        $this->assertSame(0, $data['summary']['orders_count']);
        $this->assertEquals(0.0, $data['summary']['revenue']);
        $this->assertEquals(0.0, $data['summary']['average_check']);
        $this->assertSame([], $data['by_payment_method']);
        $this->assertSame([], $data['top_products']);
        $this->assertSame([], $data['couriers']);
        $this->assertSame(['average_prep_seconds' => null, 'average_delivery_seconds' => null], $data['times']);
        $this->assertCount(7, $data['daily']);
    }

    public function test_average_prep_and_delivery_times_come_from_the_status_history(): void
    {
        $a = $this->sale(OrderStatus::DELIVERED, '2026-10-20 03:00:00');
        $this->history($a, 'accepted', '2026-10-20 03:00:10');
        $this->history($a, 'preparing', '2026-10-20 03:01:10');  // prep 60 s
        $this->history($a, 'on_the_way', '2026-10-20 03:11:10');
        $this->history($a, 'delivered', '2026-10-20 03:31:10');  // 20 min

        $b = $this->sale(OrderStatus::DELIVERED, '2026-10-20 04:00:00');
        $this->history($b, 'accepted', '2026-10-20 04:00:00');
        $this->history($b, 'preparing', '2026-10-20 04:03:00');  // prep 180 s
        $this->history($b, 'delivery', '2026-10-20 04:10:00');   // the legacy name of on_the_way
        $this->history($b, 'delivered', '2026-10-20 04:50:00');  // 40 min

        // A pickup order's handover is not a delivery time; a repeated status does not skew the figures
        $c = $this->sale(OrderStatus::DELIVERED, '2026-10-20 05:00:00', ['order_type' => 'pickup']);
        $this->history($c, 'accepted', '2026-10-20 05:00:00');
        $this->history($c, 'preparing', '2026-10-20 05:02:00');  // prep 120 s
        $this->history($c, 'delivered', '2026-10-20 05:30:00');

        // Outside the period: ignored
        $old = $this->sale(OrderStatus::DELIVERED, '2026-10-01 05:00:00');
        $this->history($old, 'accepted', '2026-10-01 05:00:00');
        $this->history($old, 'preparing', '2026-10-01 06:00:00');

        $this->assertSame(
            ['average_prep_seconds' => 120, 'average_delivery_seconds' => 1800],
            $this->analytics($this->owner)->assertOk()->json('data.times'),
        );
    }

    // --- Periods --------------------------------------------------------------------------------------------------

    public function test_a_day_is_a_tashkent_calendar_day(): void
    {
        // Tashkent is UTC+5: 2026-10-19 19:00 UTC is 00:00 on the 20th
        $this->sale(OrderStatus::DELIVERED, '2026-10-19 18:59:59'); // 23:59:59 on the 19th
        $this->sale(OrderStatus::DELIVERED, '2026-10-19 19:00:00'); // 00:00:00 on the 20th
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 18:59:59'); // 23:59:59 on the 20th
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 19:00:00'); // 00:00:00 on the 21st

        $this->analytics($this->owner, ['period' => 'today'])->assertOk()->assertJsonPath('data.summary.delivered_count', 2);
        $this->analytics($this->owner, ['period' => 'week'])->assertOk()->assertJsonPath('data.summary.delivered_count', 3)
            ->assertJsonPath('data.period.from', '2026-10-14')->assertJsonPath('data.period.to', '2026-10-20');

        $this->analytics($this->owner, ['period' => 'custom', 'from' => '2026-10-19', 'to' => '2026-10-19'])->assertOk()
            ->assertJsonPath('data.summary.delivered_count', 1);
    }

    public function test_period_lengths_and_the_daily_series(): void
    {
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 03:00:00', [], 'Cheeseburger', 1, 20000);
        $this->sale(OrderStatus::CANCELED, '2026-10-20 03:30:00');
        $this->sale(OrderStatus::DELIVERED, '2026-10-18 03:00:00', [], 'Cheeseburger', 1, 30000);
        $this->sale(OrderStatus::DELIVERED, '2026-09-25 03:00:00', [], 'Cheeseburger', 1, 70000);   // 25 days ago
        $this->sale(OrderStatus::DELIVERED, '2026-03-01 03:00:00', [], 'Cheeseburger', 1, 90000);   // ~8 months ago
        $this->sale(OrderStatus::DELIVERED, '2025-09-01 03:00:00', [], 'Cheeseburger', 1, 5000);    // over a year ago

        foreach ([['today', 1, 20000.0], ['week', 7, 50000.0], ['month', 30, 120000.0], ['year', 365, 210000.0]] as [$period, $days, $revenue]) {
            $data = $this->analytics($this->owner, ['period' => $period])->assertOk()->json('data');

            $this->assertSame($days, $data['period']['days'], $period);
            $this->assertCount($days, $data['daily'], $period);
            $this->assertEquals($revenue, $data['summary']['revenue'], $period);
            // The series adds up to the summary
            $this->assertEquals($revenue, array_sum(array_column($data['daily'], 'revenue')), $period);
            $this->assertSame($data['summary']['orders_count'], array_sum(array_column($data['daily'], 'orders')), $period);
        }

        $week = $this->analytics($this->owner, ['period' => 'week'])->json('data.daily');
        $this->assertEquals(['date' => '2026-10-20', 'orders' => 2, 'delivered' => 1, 'revenue' => 20000.0], end($week));
        $this->assertEquals(['date' => '2026-10-19', 'orders' => 0, 'delivered' => 0, 'revenue' => 0.0], $week[5]);
        $this->assertEquals(['date' => '2026-10-18', 'orders' => 1, 'delivered' => 1, 'revenue' => 30000.0], $week[4]);
        $this->assertSame('2026-10-14', $week[0]['date']);
    }

    public function test_the_period_defaults_to_today_and_dates_alone_mean_custom(): void
    {
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 03:00:00');
        $this->sale(OrderStatus::DELIVERED, '2026-10-17 03:00:00');

        $this->analytics($this->owner)->assertOk()->assertJsonPath('data.period.key', 'today')->assertJsonPath('data.summary.delivered_count', 1);
        $this->analytics($this->owner, ['from' => '2026-10-16', 'to' => '2026-10-18'])->assertOk()
            ->assertJsonPath('data.period.key', 'custom')->assertJsonPath('data.summary.delivered_count', 1);
    }

    public function test_a_custom_period_is_validated(): void
    {
        $cases = [
            'no dates' => [['period' => 'custom'], ['from', 'to']],
            'only from' => [['from' => '2026-10-01'], null],
            'bad format' => [['period' => 'custom', 'from' => '01.10.2026', 'to' => '2026-10-05'], ['from']],
            'reversed' => [['period' => 'custom', 'from' => '2026-10-05', 'to' => '2026-10-01'], ['to']],
            'over 93 days' => [['period' => 'custom', 'from' => '2026-06-01', 'to' => '2026-09-02'], ['to']],
            'unknown period' => [['period' => 'decade'], ['period']],
        ];

        foreach ($cases as [$query, $fields]) {
            $response = $this->analytics($this->owner, $query)->assertStatus(422);
            foreach ($fields ?? [] as $field) {
                $response->assertJsonValidationErrors($field);
            }
        }

        // Exactly 93 days is fine
        $this->analytics($this->owner, ['period' => 'custom', 'from' => '2026-06-01', 'to' => '2026-09-01'])->assertOk()
            ->assertJsonPath('data.period.days', 93);
    }

    // --- Tenancy and roles ----------------------------------------------------------------------------------------

    public function test_a_branch_manager_sees_only_their_branch_and_the_owner_the_whole_store(): void
    {
        $storeWideManager = $this->makeStaff(UserRole::MANAGER);
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 03:00:00', [], 'Cheeseburger', 1, 10000);
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 03:10:00', ['branch_id' => $this->otherBranch->id], 'Cheeseburger', 1, 20000);

        $this->analytics($this->manager)->assertOk()
            ->assertJsonPath('data.summary.revenue', 10000)->assertJsonPath('data.summary.delivered_count', 1);
        foreach ([$this->owner, $storeWideManager] as $user) {
            $this->analytics($user)->assertOk()
                ->assertJsonPath('data.summary.revenue', 30000)->assertJsonPath('data.summary.delivered_count', 2);
        }
    }

    public function test_another_stores_orders_never_leak_in(): void
    {
        $other = $this->makeStore('Other Store');
        $otherBranch = $this->makeBranch($other, 'Foreign Branch');
        $otherCourier = $this->makeStaff(UserRole::COURIER, $otherBranch, $other);
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 03:00:00', [], 'Cheeseburger', 1, 10000);
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 03:00:00', ['store_id' => $other->id, 'branch_id' => $otherBranch->id, 'courier_id' => $otherCourier->id], 'Foreign Wrap', 5, 99000);

        $data = $this->analytics($this->owner)->assertOk()->json('data');

        $this->assertEquals(10000.0, $data['summary']['revenue']);
        $this->assertSame(['Cheeseburger'], array_column($data['top_products'], 'name'));
        $this->assertSame([], $data['couriers']);

        $ownerOfOther = $this->makeStaff(UserRole::OWNER, null, $other);
        $this->analytics($ownerOfOther)->assertOk()->assertJsonPath('data.summary.revenue', 495000)
            ->assertJsonPath('data.top_products.0.name', 'Foreign Wrap');
    }

    public function test_deleted_orders_are_not_counted(): void
    {
        $order = $this->sale(OrderStatus::DELIVERED, '2026-10-20 03:00:00');
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 03:10:00');
        $order->delete();

        $this->analytics($this->owner)->assertOk()->assertJsonPath('data.summary.delivered_count', 1)->assertJsonPath('data.top_products.0.quantity', 1);
    }

    public function test_only_owners_and_managers_may_read_it(): void
    {
        $this->analytics(null)->assertUnauthorized();
        $this->analytics($this->customer)->assertForbidden();
        $this->analytics($this->courier)->assertForbidden();
        $this->analytics($this->makeStaff(UserRole::SUPER_ADMIN, null, $this->store))->assertForbidden();
        $this->analytics($this->owner)->assertOk();
        $this->analytics($this->manager)->assertOk();
    }

    // --- Cost -----------------------------------------------------------------------------------------------------

    public function test_the_number_of_queries_does_not_grow_with_the_number_of_orders(): void
    {
        $count = function (): int {
            Cache::flush();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->analytics($this->owner, ['period' => 'month'])->assertOk();

            return count(DB::getQueryLog());
        };

        $this->sale(OrderStatus::DELIVERED, '2026-10-20 03:00:00', ['courier_id' => $this->courier->id]);
        $few = $count();

        for ($i = 0; $i < 30; $i++) {
            $order = $this->sale(OrderStatus::DELIVERED, '2026-10-19 03:00:00', ['courier_id' => $this->courier->id], 'Item ' . $i);
            $this->history($order, 'accepted', '2026-10-19 03:00:00');
        }
        $many = $count();

        $this->assertSame($few, $many);
        // The auth lookups plus the six aggregate queries
        $this->assertLessThanOrEqual(12, $many);
    }

    public function test_the_figures_are_cached_for_a_minute_per_store_and_period(): void
    {
        $this->sale(OrderStatus::DELIVERED, '2026-10-20 03:00:00', [], 'Cheeseburger', 1, 10000);
        $this->analytics($this->owner)->assertOk()->assertJsonPath('data.summary.revenue', 10000);

        $this->sale(OrderStatus::DELIVERED, '2026-10-20 03:10:00', [], 'Cheeseburger', 1, 5000);

        // Within the minute: the same figures, for the same store and period
        Carbon::setTestNow(Carbon::now()->addSeconds(30));
        $this->analytics($this->owner)->assertOk()->assertJsonPath('data.summary.revenue', 10000);
        // Another period, another branch, another store are computed on their own
        $this->analytics($this->owner, ['period' => 'week'])->assertOk()->assertJsonPath('data.summary.revenue', 15000);
        $this->analytics($this->manager)->assertOk()->assertJsonPath('data.summary.revenue', 15000);

        Carbon::setTestNow(Carbon::now()->addSeconds(31));
        $this->analytics($this->owner)->assertOk()->assertJsonPath('data.summary.revenue', 15000);
    }
}
