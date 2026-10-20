<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\OrderStatusLog;
use Carbon\CarbonInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * order_status_histories is the single status history; order_status_logs is only read by the migration that merges it.
 */
class OrderStatusHistoryUnificationTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    protected function mergeMigration(): Migration
    {
        return require database_path('migrations/2026_10_18_130100_copy_order_status_logs_into_order_status_histories.php');
    }

    protected function columnMigration(): Migration
    {
        return require database_path('migrations/2026_10_18_130050_add_order_status_log_id_to_order_status_histories_table.php');
    }

    protected function history(Order $order, string $status, ?int $userId, CarbonInterface $at, ?string $notes = null): int
    {
        return DB::table('order_status_histories')->insertGetId([
            'order_id' => $order->id,
            'user_id' => $userId,
            'status' => $status,
            'notes' => $notes,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    protected function log(Order $order, ?string $from, string $to, ?int $userId, CarbonInterface $at): int
    {
        return DB::table('order_status_logs')->insertGetId([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $userId,
            'created_at' => $at,
        ]);
    }

    public function test_checkout_records_its_creation_in_the_status_history_only(): void
    {
        $uuid = $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $this->store->id,
            'type' => 'pickup',
            'branch_id' => $this->branch->id,
            'payment_method' => 'cash',
            'items' => [['variant_id' => $this->variant->uuid, 'quantity' => 1]],
        ])->assertCreated()->json('data.order_uuid');
        $order = Order::where('uuid', $uuid)->sole();

        $creation = OrderStatusHistory::where('order_id', $order->id)->sole();
        $this->assertSame([OrderStatus::NEW, null, $this->customer->id], [$creation->status, $creation->from_status, $creation->user_id]);
        $this->assertSame(0, OrderStatusLog::where('order_id', $order->id)->count());
    }

    public function test_transitions_record_where_they_came_from_in_the_status_history_only(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->setStatus($order, $this->manager, 'accepted')->assertOk();
        $this->setStatus($order, $this->manager, 'canceled')->assertOk();

        $this->assertSame(
            [['new', 'accepted', $this->manager->id], ['accepted', 'canceled', $this->manager->id]],
            OrderStatusHistory::where('order_id', $order->id)->orderBy('id')->get()
                ->map(fn ($history) => [$history->from_status->value, $history->status->value, $history->user_id])->all()
        );
        $this->assertSame(0, OrderStatusLog::count());
    }

    public function test_the_orders_api_embeds_the_history_in_its_old_shape(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);
        $this->setStatus($order, $this->manager, 'accepted')->assertOk();
        DB::table('order_status_histories')->where('order_id', $order->id)->update(['from_status' => 'new', 'order_status_log_id' => 1]);

        $history = $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/orders')->assertOk()->json('data.0.status_histories.0');

        $this->assertEqualsCanonicalizing(['id', 'order_id', 'user_id', 'status', 'notes', 'created_at', 'updated_at'], array_keys($history));
        $this->assertSame('accepted', $history['status']);
    }

    public function test_logs_without_a_history_twin_are_copied_once_and_down_removes_only_the_copies(): void
    {
        $order = $this->makeOrder(OrderStatus::DELIVERED);
        $expired = $this->makeOrder(OrderStatus::CANCELED, ['payment_method' => 'click']);
        $at = now()->subDay()->startOfMinute();

        // Written to both tables: the checkout (its log came after the Telegram calls) and an accept
        $created = $this->history($order, 'new', $this->customer->id, $at, 'Order created via Mini App');
        $this->log($order, null, 'new', $this->customer->id, $at->copy()->addSeconds(4));
        $accepted = $this->history($order, 'accepted', $this->manager->id, $at->copy()->addMinute(), 'Accepted in the Mini App');
        $this->log($order, 'new', 'accepted', $this->manager->id, $at->copy()->addMinute());
        // Two assignments by the same manager, only the first one in the history; a system cancel only in the logs
        $assigned = $this->history($order, 'courier_assigned', $this->manager->id, $at->copy()->addMinutes(3), 'Courier assigned');
        $this->log($order, 'ready', 'courier_assigned', $this->manager->id, $at->copy()->addMinutes(3));
        $reassignLog = $this->log($order, 'courier_assigned', 'courier_assigned', $this->manager->id, $at->copy()->addMinutes(5));
        $cancelLog = $this->log($expired, 'new', 'canceled', null, $at->copy()->addMinutes(31));
        // Only in the history: left as it is
        $delivered = $this->history($order, 'delivered', $this->courier->id, $at->copy()->addMinutes(40), 'Delivered');

        $migration = $this->mergeMigration();
        $migration->up();
        $migration->up(); // a second run changes nothing

        $rows = DB::table('order_status_histories')->get()->keyBy('id');
        $this->assertCount(6, $rows);

        // Twins keep their row and gain the log's from_status
        $this->assertSame(
            [null, 'new', 'ready', null],
            [$rows[$created]->from_status, $rows[$accepted]->from_status, $rows[$assigned]->from_status, $rows[$delivered]->from_status]
        );
        $this->assertSame('Order created via Mini App', $rows[$created]->notes);
        $this->assertNull($rows[$created]->order_status_log_id);

        // Each twin-less log became one history row: its order, transition, actor and time
        $copies = $rows->whereNotNull('order_status_log_id')->keyBy('order_status_log_id');
        $this->assertEqualsCanonicalizing([$reassignLog, $cancelLog], $copies->keys()->all());
        $reassign = $copies[$reassignLog];
        $this->assertSame(
            [$order->id, $this->manager->id, 'courier_assigned', 'courier_assigned', $at->copy()->addMinutes(5)->toDateTimeString()],
            [$reassign->order_id, $reassign->user_id, $reassign->from_status, $reassign->status, $reassign->created_at]
        );
        $cancel = $copies[$cancelLog];
        $this->assertSame([$expired->id, null, 'new', 'canceled'], [$cancel->order_id, $cancel->user_id, $cancel->from_status, $cancel->status]);
        // Readers see it like any other transition
        $this->assertSame(OrderStatus::CANCELED, $expired->statusHistories()->sole()->status);

        $migration->down();

        $this->assertEqualsCanonicalizing([$created, $accepted, $assigned, $delivered], DB::table('order_status_histories')->pluck('id')->all());
        $this->assertSame(5, DB::table('order_status_logs')->count());

        // The column has a migration of its own, so the copy never holds ADD COLUMN's table lock
        $this->columnMigration()->down();
        $this->assertFalse(Schema::hasColumn('order_status_histories', 'order_status_log_id'));
        $migration->up(); // without the column there is nothing to do yet
        $this->assertSame(4, DB::table('order_status_histories')->count());

        $this->columnMigration()->up();
        $migration->up();
        $this->assertSame(6, DB::table('order_status_histories')->count());
    }

    public function test_a_realistic_history_merges_exactly_like_pairing_each_group_in_time_order(): void
    {
        $this->seedRealisticStatusHistory(2500);
        $histories = DB::table('order_status_histories')->get();
        $logs = DB::table('order_status_logs')->get();
        [$expectedFromStatus, $expectedCopies] = $this->pairInMemory($histories, $logs);
        $this->assertGreaterThan(1000, count($expectedCopies), 'the dataset must exercise copies');
        $this->assertGreaterThan(1000, count(array_filter($expectedFromStatus)), 'and twins');

        $migration = $this->mergeMigration();
        $migration->up();

        $after = DB::table('order_status_histories')->orderBy('id')->get();
        $originals = $after->whereNull('order_status_log_id');
        $this->assertSame($histories->count(), $originals->count());
        $this->assertSame($expectedFromStatus, $originals->pluck('from_status', 'id')->all());
        $copies = $after->whereNotNull('order_status_log_id')
            ->map(fn ($row) => [$row->order_status_log_id, $row->order_id, $row->user_id, $row->from_status, $row->status, $row->notes, $row->created_at, $row->updated_at])
            ->sortBy(fn ($row) => $row[0])->values()->all();
        $this->assertSame($expectedCopies, $copies);

        // Idempotent: a second run changes nothing
        $migration->up();
        $this->assertEquals($after, DB::table('order_status_histories')->orderBy('id')->get());

        // down() removes exactly the copies
        $migration->down();
        $this->assertSame($originals->pluck('id')->all(), DB::table('order_status_histories')->orderBy('id')->pluck('id')->all());
    }

    /**
     * Orders that went through the lifecycle while transitions were written to both tables, to one of them only, by
     * the same user more than once (reassignments) and by the system (no user), some within the same second.
     */
    protected function seedRealisticStatusHistory(int $orders): void
    {
        mt_srand(20261018);
        $start = now()->subDays(60)->startOfDay();
        $actors = [$this->customer->id, $this->manager->id, $this->courier->id, $this->owner->id, null];
        $paths = [
            ['new', 'accepted', 'preparing', 'ready', 'courier_assigned', 'courier_assigned', 'on_the_way', 'delivered'],
            ['new', 'accepted', 'preparing', 'ready', 'courier_assigned', 'on_the_way', 'delivered'],
            ['new', 'accepted', 'preparing', 'delivered'],
            ['new', 'canceled'],
            ['new', 'accepted', 'canceled'],
        ];

        $orderRows = [];
        for ($i = 0; $i < $orders; $i++) {
            $orderRows[] = [
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'short_code' => 'M' . $i,
                'store_id' => $this->store->id,
                'user_id' => $this->customer->id,
                'status' => 'delivered',
                'created_at' => $start,
                'updated_at' => $start,
            ];
        }
        foreach (array_chunk($orderRows, 1000) as $chunk) {
            DB::table('orders')->insert($chunk);
        }

        $histories = [];
        $logs = [];
        foreach (DB::table('orders')->where('short_code', 'like', 'M%')->pluck('id') as $orderId) {
            $at = $start->copy()->addMinutes(mt_rand(0, 60 * 24 * 59));
            $from = null;
            foreach ($paths[mt_rand(0, count($paths) - 1)] as $status) {
                $at = $at->copy()->addSeconds(mt_rand(0, 3) === 0 ? 0 : mt_rand(1, 900));
                $userId = $status === 'new' ? $this->customer->id : $actors[mt_rand(0, count($actors) - 1)];
                $where = mt_rand(1, 10);
                if ($where <= 7) { // both tables; the log a few seconds later, or in the same second
                    $histories[] = ['order_id' => $orderId, 'user_id' => $userId, 'status' => $status, 'from_status' => mt_rand(1, 4) === 1 ? $from : null,
                        'notes' => "h {$status}", 'created_at' => $at, 'updated_at' => $at];
                    $logs[] = ['order_id' => $orderId, 'from_status' => $from, 'to_status' => $status, 'user_id' => $userId,
                        'comment' => "l {$status}", 'created_at' => $at->copy()->addSeconds(mt_rand(0, 4))];
                } elseif ($where <= 9) {
                    $logs[] = ['order_id' => $orderId, 'from_status' => $from, 'to_status' => $status, 'user_id' => $userId,
                        'comment' => mt_rand(0, 1) ? "l {$status}" : null, 'created_at' => $at];
                } else {
                    $histories[] = ['order_id' => $orderId, 'user_id' => $userId, 'status' => $status, 'from_status' => null,
                        'notes' => null, 'created_at' => $at, 'updated_at' => $at];
                }
                $from = $status;
            }
        }

        // Inserted shuffled, so ids do not follow time
        shuffle($histories);
        shuffle($logs);
        foreach (array_chunk($histories, 1000) as $chunk) {
            DB::table('order_status_histories')->insert($chunk);
        }
        foreach (array_chunk($logs, 1000) as $chunk) {
            DB::table('order_status_logs')->insert($chunk);
        }
    }

    /**
     * The merge as the first, row-by-row version of the migration did it in PHP.
     *
     * @return array{0: array<int, ?string>, 1: list<array>} from_status of each history row by id, and the copies
     */
    protected function pairInMemory(\Illuminate\Support\Collection $histories, \Illuminate\Support\Collection $logs): array
    {
        $key = fn (object $row, string $status) => "{$row->order_id}|{$status}|{$row->user_id}";
        $inTimeOrder = fn (object $a, object $b) => [$a->created_at, $a->id] <=> [$b->created_at, $b->id];

        $twins = $histories->sort($inTimeOrder)->groupBy(fn ($history) => $key($history, $history->status));
        $fromStatus = $histories->sortBy('id')->pluck('from_status', 'id')->all();
        $copies = [];

        foreach ($logs->sort($inTimeOrder)->groupBy(fn ($log) => $key($log, $log->to_status)) as $group => $groupLogs) {
            $groupTwins = $twins->get($group, collect())->values();
            foreach ($groupLogs->values() as $rank => $log) {
                $twin = $groupTwins->get($rank);
                if (!$twin) {
                    $copies[] = [$log->id, $log->order_id, $log->user_id, $log->from_status, $log->to_status, $log->comment, $log->created_at, $log->created_at];
                } elseif ($fromStatus[$twin->id] === null && $log->from_status !== null) {
                    $fromStatus[$twin->id] = $log->from_status;
                }
            }
        }

        usort($copies, fn ($a, $b) => $a[0] <=> $b[0]);

        return [$fromStatus, $copies];
    }
}
