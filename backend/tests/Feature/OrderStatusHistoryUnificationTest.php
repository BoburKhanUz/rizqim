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

        $this->assertFalse(Schema::hasColumn('order_status_histories', 'order_status_log_id'));
        $this->assertEqualsCanonicalizing([$created, $accepted, $assigned, $delivered], DB::table('order_status_histories')->pluck('id')->all());
        $this->assertSame(5, DB::table('order_status_logs')->count());

        $migration->up();
        $this->assertSame(6, DB::table('order_status_histories')->count());
    }
}
