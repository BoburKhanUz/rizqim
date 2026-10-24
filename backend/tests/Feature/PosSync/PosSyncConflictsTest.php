<?php

namespace Tests\Feature\PosSync;

use App\Models\CashMovement;
use App\Models\FiscalReceipt;
use App\Models\MenuBatch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PosSyncOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3-31: the conflict rules of docs/POS_SPEC.md §5.3 on POST /pos/sync — the server is the source of truth, but a
 * sale that happened and money taken are never rejected — and what they do to the shift's Z (§5.4).
 */
class PosSyncConflictsTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    public function test_an_offline_price_is_kept_and_the_order_flagged(): void
    {
        // The price went up on the server during the outage; the guest paid the old one
        $this->shashlik->forceFill(['price' => '20000'])->save();
        $order = (string) Str::uuid();

        $this->sync([$this->op('order.open', [
            'order' => $order, 'local_number' => 'K1-001', 'channel' => 'pos_takeaway',
            'items' => [$this->line($this->shashlik, '2', '18000')], 'menu_taken_at' => now()->subHours(2)->toJSON(),
        ])])->assertOk()->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.refs.flags.0.flag', 'offline_price')
            ->assertJsonPath('data.results.0.refs.flags.0.current_price', '20000.00');

        $model = Order::where('external_id', $order)->firstOrFail();
        $this->assertSame('36000.00', (string) $model->total_price);
        $this->assertSame('18000.00', (string) OrderItem::where('order_id', $model->id)->value('unit_price'));
        $this->assertSame('offline_price', $model->sync_flags[0]['flag']);
    }

    public function test_an_offline_price_from_a_menu_older_than_a_day_is_a_conflict_for_the_manager(): void
    {
        $this->shashlik->forceFill(['price' => '20000'])->save();

        $this->sync([$this->op('order.open', [
            'order' => (string) Str::uuid(), 'local_number' => 'K1-002', 'channel' => 'pos_takeaway',
            'items' => [$this->line($this->shashlik, '1', '18000')], 'menu_taken_at' => now()->subHours(25)->toJSON(),
        ])])->assertOk()->assertJsonPath('data.results.0.status', 'conflict')
            ->assertJsonPath('data.results.0.reason', 'stale_menu');
    }

    public function test_an_oversold_kazan_keeps_the_sale_never_goes_below_zero_and_records_the_shortfall(): void
    {
        $this->kazan->forceFill(['quantity_left' => '1'])->save();
        $order = (string) Str::uuid();

        $this->sync([$this->op('order.open', [
            'order' => $order, 'local_number' => 'K1-003', 'channel' => 'pos_takeaway', 'items' => [$this->line($this->plov, '3')],
        ])])->assertOk()->assertJsonPath('data.results.0.status', 'conflict')
            ->assertJsonPath('data.results.0.reason', 'oversold')
            ->assertJsonPath('data.results.0.refs.flags.0.quantity', '2.000');

        $kazan = MenuBatch::findOrFail($this->kazan->id);
        $this->assertSame('0.000', (string) $kazan->quantity_left);
        $this->assertSame('2.000', (string) $kazan->oversold_quantity);
        $this->assertNotNull($kazan->sold_out_at);
        $this->assertSame('360000.00', (string) Order::where('external_id', $order)->value('total_price'));
        $this->assertSame('1.000', (string) OrderItem::where('client_line_id', Order::where('external_id', $order)->firstOrFail()->items()->value('client_line_id'))->value('batch_quantity'));
    }

    public function test_an_order_cancelled_on_the_server_rejects_the_round_and_keeps_the_payment_with_a_refund_flag(): void
    {
        $sale = $this->walkInSale();
        $this->sync([$sale['ops'][0]])->assertOk();
        $order = Order::where('external_id', $sale['order'])->firstOrFail();

        // Meanwhile a manager cancels it on the server
        $approval = (string) $this->tillAs('POST', '/pos/approvals', ['permission' => 'orders.cancel', 'user_id' => $this->manager->id, 'pin' => self::MANAGER_PIN])->assertCreated()->json('data.token');
        $this->tillAs('POST', "/pos/orders/{$order->uuid}/cancel", ['version' => $order->version, 'reason' => 'Mijoz ketdi', 'approval_token' => $approval])->assertOk();

        $response = $this->sync([
            $this->op('order.round', ['order' => $sale['order'], 'items' => [$this->line($this->tea, '1')]]),
            $sale['ops'][1],
            $sale['ops'][2],
        ])->assertOk();

        // In seq order: the bill (2) and the payment (3) the till queued before, then the new round (5)
        $response->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.reason', 'order_closed')
            ->assertJsonPath('data.results.1.status', 'conflict')
            ->assertJsonPath('data.results.1.reason', 'refund_needed')
            ->assertJsonPath('data.results.2.status', 'rejected')
            ->assertJsonPath('data.results.2.reason', 'order_closed');

        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertNull($payment->bill_id);
        $this->assertSame('41000.00', (string) $payment->amount);
        $this->assertNotNull($payment->offline_paid_at);
        $this->assertSame(1, CashMovement::where('shift_id', $this->shift->id)->where('type', 'sale')->count());
        $this->assertSame('refund_needed', collect($order->refresh()->sync_flags)->last()['flag']);
        $this->assertSame(0, FiscalReceipt::count());

        // "Sinxronlash xatolari", newest first: the round, the payment, the bill
        $errors = $this->tillAs('GET', '/pos/sync/errors')->assertOk();
        $this->assertSame(3, $errors->json('data.meta.total'));
        $this->assertSame(['order.round', 'bill.pay', 'bill.create'], array_column($errors->json('data.errors'), 'type'));
        $this->assertSame(['rejected', 'conflict', 'rejected'], array_column($errors->json('data.errors'), 'status'));
    }

    public function test_a_line_sent_from_another_device_meanwhile_rejects_the_offline_void(): void
    {
        $order = (string) Str::uuid();
        $line = (string) Str::uuid();
        $this->sync([$this->op('order.open', ['order' => $order, 'local_number' => 'K1-004', 'channel' => 'pos_hall', 'guests_count' => 2, 'items' => [$this->line($this->tea, '1', null, $line)]])])->assertOk();
        $model = Order::where('external_id', $order)->firstOrFail();
        $this->tillAs('POST', "/pos/orders/{$model->uuid}/send", ['version' => $model->version])->assertOk();

        $this->sync([$this->op('order.void', ['order' => $order, 'line' => $line])])->assertOk()
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.reason', 'line_sent');

        $this->assertSame('sent', OrderItem::where('client_line_id', $line)->firstOrFail()->kitchen_status->value);
    }

    public function test_an_unsent_line_voided_offline_gives_its_kazan_portion_back(): void
    {
        $order = (string) Str::uuid();
        $line = (string) Str::uuid();

        $this->sync([
            $this->op('order.open', ['order' => $order, 'local_number' => 'K1-009', 'channel' => 'pos_takeaway', 'items' => [$this->line($this->plov, '2', null, $line)]]),
            $this->op('order.void', ['order' => $order, 'line' => $line]),
        ])->assertOk()->assertJsonPath('data.results.1.status', 'applied');

        $this->assertSame('void', OrderItem::where('client_line_id', $line)->firstOrFail()->kitchen_status->value);
        $this->assertSame('10.000', (string) MenuBatch::whereKey($this->kazan->id)->value('quantity_left'));
    }

    public function test_a_room_taken_on_the_server_meanwhile_opens_the_order_without_it_and_flags_it(): void
    {
        // A waiter phone (still online) seated another party in the room
        $this->tillAs('POST', '/pos/orders', ['channel' => 'pos_hall', 'table_id' => $this->room->id, 'guests_count' => 2])->assertCreated();
        $order = (string) Str::uuid();

        $this->sync([$this->op('order.open', ['order' => $order, 'local_number' => 'K1-006', 'channel' => 'pos_hall', 'table_id' => $this->room->id, 'guests_count' => 4, 'items' => [$this->line($this->tea, '2')]])])
            ->assertOk()->assertJsonPath('data.results.0.status', 'conflict')
            ->assertJsonPath('data.results.0.reason', 'table_occupied');

        $model = Order::where('external_id', $order)->firstOrFail();
        $this->assertNull($model->table_id);
        $this->assertSame('1-xona', $model->table_ref);
    }

    public function test_a_bill_paid_less_than_the_server_total_stays_open_as_a_conflict(): void
    {
        $sale = $this->walkInSale('K1-001', '30000');

        $response = $this->sync($sale['ops'])->assertOk();

        $response->assertJsonPath('data.results.2.status', 'conflict')
            ->assertJsonPath('data.results.2.reason', 'underpaid')
            ->assertJsonPath('data.results.2.refs.flags.0.amount', '11000.00')
            ->assertJsonPath('data.results.3.status', 'applied')
            ->assertJsonPath('data.results.3.refs.closed', false);
        $order = Order::where('external_id', $sale['order'])->firstOrFail();
        $this->assertNull($order->closed_at);
        $this->assertSame(0, FiscalReceipt::count());
        $this->assertSame('30000.00', (string) Payment::where('order_id', $order->id)->value('amount'));
    }

    public function test_a_device_clock_more_than_five_minutes_off_is_a_warning(): void
    {
        $sale = $this->walkInSale();

        $response = $this->sync($sale['ops'], ['sent_at' => now()->subMinutes(12)->toJSON()])->assertOk();

        $response->assertJsonPath('data.clock_warning', true)
            ->assertJsonPath('data.clock_skew_seconds', 720)
            ->assertJsonPath('data.device.clock_warning', true)
            ->assertJsonPath('data.results.0.status', 'applied');
        $this->assertSame(720, PosSyncOperation::where('seq', 1)->value('clock_skew_seconds'));
    }

    public function test_a_manager_resolves_a_sync_error_and_the_shift_closes(): void
    {
        $order = (string) Str::uuid();
        // A round for an order the server never got: rejected
        $this->sync([$this->op('order.round', ['order' => $order, 'items' => [$this->line($this->tea, '1')]])])->assertOk()
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.reason', 'order_not_found');
        $opId = (string) PosSyncOperation::value('op_id');

        $this->tillAs('POST', "/pos/shifts/{$this->shift->uuid}/close", ['counted_breakdown' => ['100000' => 1]])
            ->assertStatus(409)->assertJsonPath('data.reason', 'shift_has_sync_errors')
            ->assertJsonPath('data.sync_errors.0', $opId);

        // The cashier cannot settle it; the manager can
        $this->tillAs('POST', "/pos/sync/errors/{$opId}/resolve", ['resolution' => 'dismissed'])->assertForbidden();
        $managerToken = $this->pinSession($this->till, $this->manager, self::MANAGER_PIN);
        $this->tillAs('POST', "/pos/sync/errors/{$opId}/resolve", ['resolution' => 'resolved', 'note' => "Qo'lda kiritildi"], $managerToken)->assertOk()
            ->assertJsonPath('data.resolution', 'resolved')
            ->assertJsonPath('data.resolved_by.id', $this->manager->id);
        $this->tillAs('GET', '/pos/sync/errors', [], $managerToken)->assertOk()->assertJsonPath('data.meta.total', 0);
        $this->tillAs('GET', '/pos/sync/errors?all=1', [], $managerToken)->assertOk()->assertJsonPath('data.meta.total', 1);

        $this->tillAs('POST', "/pos/shifts/{$this->shift->uuid}/close", ['counted_breakdown' => ['100000' => 1]], $managerToken)->assertOk();
    }

    public function test_the_shift_does_not_close_while_the_till_reports_operations_waiting(): void
    {
        $sale = $this->walkInSale();
        // Two of four sent; the device says four were in its queue
        $this->sync(array_slice($sale['ops'], 0, 2), ['queue' => ['size' => 4, 'oldest_at' => $sale['ops'][0]['occurred_at']]])->assertOk()
            ->assertJsonPath('data.device.pending_ops', 2);

        $this->tillAs('POST', "/pos/shifts/{$this->shift->uuid}/close", ['counted_breakdown' => ['100000' => 1]])
            ->assertStatus(409)->assertJsonPath('data.reason', 'shift_has_unsynced_ops')
            ->assertJsonPath('data.pending_ops', 2);

        $this->sync(array_slice($sale['ops'], 2), ['queue' => ['size' => 2]])->assertOk()->assertJsonPath('data.device.pending_ops', 0);
        $this->tillAs('POST', "/pos/shifts/{$this->shift->uuid}/close", ['counted_breakdown' => ['100000' => 1, '20000' => 2, '1000' => 1]])->assertOk();
    }

    public function test_the_status_endpoint_reports_the_queue_and_the_errors_of_this_till(): void
    {
        $this->sync([$this->op('order.round', ['order' => (string) Str::uuid(), 'items' => [$this->line($this->tea, '1')]])], ['queue' => ['size' => 3]])->assertOk();

        $this->withBearer($this->till['token']);
        $status = $this->json('GET', '/api/v1/pos/sync/status')->assertOk();
        $status->assertJsonPath('data.device.last_seq', 1)
            ->assertJsonPath('data.device.pending_ops', 2)
            ->assertJsonPath('data.errors_count', 1)
            ->assertJsonPath('data.errors.0.reason', 'order_not_found')
            ->assertJsonPath('data.errors.0.type_label', "Bosqich qo'shildi");

        // The queue drained (nothing left to send): the till says so
        $this->withBearer($this->till['token']);
        $this->json('GET', '/api/v1/pos/sync/status?queue_size=0')->assertOk()->assertJsonPath('data.device.pending_ops', 0);
    }
}
