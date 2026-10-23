<?php

namespace Tests\Feature\PosSync;

use App\Domain\Fiscal\FiscalReceiptType;
use App\Models\CashMovement;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PosSyncOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3-31: POST /pos/sync on the real POS services: a walk-in sale and a room round made offline reach the server in
 * order, once, with the device's prices and local number, and the sale receipt is issued at sync time keeping when the
 * money was taken (docs/POS_SPEC.md §5.2).
 */
class PosSyncFlowTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    public function test_a_walk_in_sale_made_offline_is_applied_in_order_and_receipted_at_sync(): void
    {
        $sale = $this->walkInSale();
        $paidAt = $sale['ops'][2]['occurred_at'];

        $response = $this->sync($sale['ops'], ['queue' => ['size' => 4, 'oldest_at' => $sale['ops'][0]['occurred_at']]])->assertOk();
        $response->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.1.status', 'applied')
            ->assertJsonPath('data.results.2.status', 'applied')
            ->assertJsonPath('data.results.3.status', 'applied')
            ->assertJsonPath('data.clock_warning', false)
            ->assertJsonPath('data.device.last_seq', 4)
            ->assertJsonPath('data.device.pending_ops', 0);

        $order = Order::where('external_id', $sale['order'])->firstOrFail();
        $this->assertSame('K1-001', $order->local_number);
        $this->assertSame('delivered', $order->status->value);
        $this->assertNotNull($order->closed_at);
        $this->assertNotNull($order->offline_created_at);
        $this->assertNotNull($order->synced_at);
        $this->assertSame((int) $this->till['device']->id, (int) $order->device_id);
        $this->assertSame('41000.00', (string) $order->total_price);
        $response->assertJsonPath('data.results.0.refs.order_uuid', $order->uuid)
            ->assertJsonPath('data.results.0.refs.local_number', 'K1-001');

        // Each line keeps the device's id and price, and went to the kitchen
        $lines = OrderItem::where('order_id', $order->id)->orderBy('id')->get();
        $this->assertCount(2, $lines);
        $this->assertSame($sale['ops'][0]['payload']['items'][0]['line_id'], $lines[0]->client_line_id);
        $this->assertSame('offline_snapshot', $lines[0]->price_source);
        $this->assertNotNull($lines[0]->sent_at);
        $this->assertSame((int) $lines[0]->id, $response->json('data.results.0.refs.items.' . $lines[0]->client_line_id));

        // The money: one cash payment in the till's shift, with the device's time
        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame('41000.00', (string) $payment->amount);
        $this->assertSame('9000.00', (string) $payment->change_amount);
        $this->assertSame((int) $this->shift->id, (int) $payment->shift_id);
        $this->assertSame(\Illuminate\Support\Carbon::parse($paidAt)->toJSON(), $payment->offline_paid_at?->toJSON());
        $this->assertSame(1, CashMovement::where('shift_id', $this->shift->id)->where('type', 'sale')->count());

        // The sale receipt, issued now, remembering when the sale happened
        $bill = OrderBill::where('order_id', $order->id)->sole();
        $this->assertSame('paid', $bill->status->value);
        $receipt = FiscalReceipt::where('subject_type', OrderBill::FISCAL_SUBJECT)->where('subject_id', $bill->id)->sole();
        $this->assertSame(FiscalReceiptType::SALE, $receipt->type);
        $this->assertSame('issued', $receipt->status->value);
        $this->assertSame(\Illuminate\Support\Carbon::parse($paidAt)->toJSON(), $receipt->sold_at?->toJSON());
        $this->assertSame('41000.00', (string) $receipt->total);

        // The journal
        $this->assertSame(4, PosSyncOperation::where('device_id', $this->till['device']->id)->where('status', 'applied')->count());
        $this->assertSame($sale['order'], PosSyncOperation::where('type', 'order.open')->value('client_ref'));
    }

    public function test_an_unreachable_provider_leaves_the_offline_sale_fiskal_kutmoqda_until_the_receipt_is_issued(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $settings = (array) $this->store->fiscal_settings;
        $this->store->forceFill(['fiscal_settings' => ['sandbox' => ['fail_attempts' => 1]] + $settings])->save();
        $sale = $this->walkInSale();

        $this->sync($sale['ops'])->assertOk()
            ->assertJsonPath('data.results.2.status', 'applied')
            ->assertJsonPath('data.results.3.refs.closed', false);

        $order = Order::where('external_id', $sale['order'])->firstOrFail();
        $bill = OrderBill::where('order_id', $order->id)->sole();
        $this->assertSame('pending', $bill->getAttribute('fiscal_status'));
        $this->assertNull($order->closed_at);
        $receipt = FiscalReceipt::where('subject_id', $bill->id)->where('subject_type', OrderBill::FISCAL_SUBJECT)->sole();
        $this->assertSame('pending', $receipt->status->value);
        $this->assertNotNull($receipt->sold_at);
        // The money is in the drawer, the receipt is queued: the Z waits for it
        $this->tillAs('POST', "/pos/shifts/{$this->shift->uuid}/close", ['counted_breakdown' => ['100000' => 1]])
            ->assertStatus(409)->assertJsonPath('data.pending_bills.0', $bill->uuid);

        // The provider answers on the retry: the bill is paid and the order closes
        app(\App\Domain\Fiscal\FiscalService::class)->attempt((int) $receipt->id, force: true);
        $this->assertSame('issued', $receipt->refresh()->status->value);
        $this->assertSame('paid', $bill->refresh()->status->value);
        $this->assertNotNull($order->refresh()->closed_at);
    }

    public function test_the_same_batch_twice_is_applied_once(): void
    {
        $sale = $this->walkInSale();

        $this->sync($sale['ops'])->assertOk()->assertJsonPath('data.results.0.duplicate', false);
        $again = $this->sync($sale['ops'])->assertOk();

        $again->assertJsonPath('data.results.0.duplicate', true)
            ->assertJsonPath('data.results.3.duplicate', true)
            ->assertJsonPath('data.results.2.status', 'applied');
        $this->assertSame(1, Order::where('external_id', $sale['order'])->count());
        $this->assertSame(1, Payment::count());
        $this->assertSame(1, OrderBill::count());
        $this->assertSame(1, FiscalReceipt::where('type', 'sale')->count());
        $this->assertSame(4, PosSyncOperation::count());
    }

    public function test_a_lost_answer_resent_as_part_of_a_longer_batch_only_applies_the_new_operations(): void
    {
        $sale = $this->walkInSale();
        $this->sync(array_slice($sale['ops'], 0, 2))->assertOk();

        // The device never got the answer: it sends the first two again, with the rest
        $response = $this->sync($sale['ops'])->assertOk();

        $response->assertJsonPath('data.results.0.duplicate', true)
            ->assertJsonPath('data.results.1.duplicate', true)
            ->assertJsonPath('data.results.2.duplicate', false)
            ->assertJsonPath('data.results.2.status', 'applied');
        $this->assertSame(1, OrderBill::count());
        $this->assertSame('delivered', Order::where('external_id', $sale['order'])->firstOrFail()->status->value);
    }

    public function test_operations_are_applied_in_seq_order_whatever_order_they_come_in(): void
    {
        $sale = $this->walkInSale();

        $response = $this->sync(array_reverse($sale['ops']))->assertOk();

        $this->assertSame([1, 2, 3, 4], array_column($response->json('data.results'), 'seq'));
        $this->assertSame(['applied', 'applied', 'applied', 'applied'], array_column($response->json('data.results'), 'status'));
    }

    public function test_a_seq_given_to_another_operation_is_refused(): void
    {
        $sale = $this->walkInSale();
        $this->sync([$sale['ops'][0]])->assertOk();

        $clash = $sale['ops'][1];
        $clash['seq'] = 1;
        $this->sync([$clash])->assertStatus(409)->assertJsonPath('data.reason', 'seq_reused');
    }

    public function test_the_same_order_opened_twice_with_different_operation_ids_is_one_order(): void
    {
        $order = (string) Str::uuid();
        $open = fn () => $this->op('order.open', ['order' => $order, 'local_number' => 'K1-005', 'channel' => 'pos_takeaway', 'items' => [$this->line($this->tea, '1')]]);

        $this->sync([$open(), $open()])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.1.status', 'applied');

        $this->assertSame(1, Order::where('external_id', $order)->count());
        $this->assertSame(1, OrderItem::count());
    }

    public function test_a_room_order_opened_before_the_outage_takes_offline_rounds_and_payment(): void
    {
        $opened = $this->tillAs('POST', '/pos/orders', ['channel' => 'pos_hall', 'table_id' => $this->room->id, 'guests_count' => 4])->assertCreated();
        $uuid = (string) $opened->json('data.uuid');
        $version = (int) $opened->json('data.version');
        $line = (string) Str::uuid();
        $bill = (string) Str::uuid();

        $response = $this->sync([
            $this->op('order.round', ['order' => $uuid, 'base_version' => $version, 'items' => [$this->line($this->plov, '1.5', null, $line), $this->line($this->tea, '2')], 'send' => true]),
            $this->op('order.served', ['order' => $uuid, 'line' => $line]),
            $this->op('bill.create', ['order' => $uuid, 'bill' => $bill, 'expected_total' => '539000']),
            $this->op('bill.pay', ['order' => $uuid, 'bill' => $bill, 'tenders' => [
                ['tender' => 'cash', 'amount' => '239000'],
                ['tender' => 'card', 'amount' => '300000', 'card_type' => 'uzcard', 'transaction_id' => 'RRN-1'],
            ]]),
        ])->assertOk();

        // 1.5 kg plov 180 000 + 2 tea 10 000 = 190 000; 10% service charge 19 000; room fee 300 000: 509 000
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(['applied', 'applied'], [$response->json('data.results.0.status'), $response->json('data.results.1.status')]);
        $this->assertSame('509000.00', (string) $order->total_price);
        $this->assertSame('served', OrderItem::where('client_line_id', $line)->firstOrFail()->kitchen_status->value);
        $this->assertSame('8.500', (string) \App\Models\MenuBatch::whereKey($this->kazan->id)->value('quantity_left'));

        // The device thought 539 000 (an older room fee): the server billed 509 000, the cash was cut to fit and the
        // 30 000 taken too much is a conflict for the manager
        $response->assertJsonPath('data.results.2.refs.flags.0.flag', 'amount_mismatch')
            ->assertJsonPath('data.results.3.status', 'conflict')
            ->assertJsonPath('data.results.3.reason', 'overpaid')
            ->assertJsonPath('data.results.3.refs.flags.0.amount', '30000.00');
        $this->assertSame('209000.00', (string) Payment::where('order_id', $order->id)->where('provider', 'cash')->value('amount'));
        $this->assertSame('300000.00', (string) Payment::where('order_id', $order->id)->where('provider', 'card')->value('amount'));
        $this->assertSame('delivered', $order->status->value);
    }

    public function test_an_online_request_the_server_did_before_the_answer_was_lost_is_not_applied_again(): void
    {
        $opened = $this->tillAs('POST', '/pos/orders', ['channel' => 'pos_hall', 'table_id' => $this->room->id, 'guests_count' => 2])->assertCreated();
        $uuid = (string) $opened->json('data.uuid');
        $line = $this->line($this->tea, '1');

        // The round went through online, its answer never reached the till
        $this->withBearer($this->cashierToken);
        $this->withHeader('Idempotency-Key', 'round-key-1')->json('POST', "/api/v1/pos/orders/{$uuid}/rounds", [
            'version' => (int) $opened->json('data.version'),
            'items' => [['variant_id' => $this->tea->uuid, 'quantity' => '1']],
        ])->assertOk();
        $this->flushHeaders();

        // The till queued the same round with the same key
        $this->sync([$this->op('order.round', ['order' => $uuid, 'items' => [$line], 'idempotency_key' => 'round-key-1'])])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.refs.replayed', true);

        $this->assertSame(1, OrderItem::whereHas('order', fn ($query) => $query->where('uuid', $uuid))->where('product_variant_id', $this->tea->id)->count());
    }

    public function test_the_order_json_shows_the_local_number_and_flags(): void
    {
        $sale = $this->walkInSale();
        $this->sync($sale['ops'])->assertOk();
        $uuid = Order::where('external_id', $sale['order'])->value('uuid');

        $this->tillAs('GET', "/pos/orders/{$uuid}")->assertOk()
            ->assertJsonPath('data.offline.local_number', 'K1-001')
            ->assertJsonPath('data.offline.flags', []);
    }

    public function test_bootstrap_gives_the_till_its_offline_section(): void
    {
        $data = $this->tillAs('GET', '/pos/bootstrap')->assertOk()->json('data.offline');

        $this->assertSame('K1', $data['device_prefix']);
        $this->assertSame(1, $data['next_local_number']);
        $this->assertSame('weekday', $data['day_type']);
        $this->assertSame(500, $data['limits']['max_queued_orders']);
        $this->assertSame([24, 40], $data['limits']['fiscal_warn_hours']);
        $hallFee = collect($data['fees'])->first(fn (array $fee) => $fee['hall_id'] === $this->hall->id && $fee['channel'] === 'pos_hall');
        $this->assertSame('10.00', $hallFee['service_charge_percent']);
        $place = collect($data['places'])->firstWhere('table_id', $this->room->id);
        $this->assertSame('300000.00', $place['room_fee']);

        // After an offline order the next number follows it; a second till gets K2
        $this->sync($this->walkInSale('K1-007')['ops'])->assertOk();
        $this->assertSame(8, $this->tillAs('GET', '/pos/bootstrap')->assertOk()->json('data.offline.next_local_number'));
        $second = $this->pairTerminal($this->owner, $this->branch);
        $token = $this->pinSession($second, $this->cashier);
        $this->assertSame('K2', $this->tillAs('GET', '/pos/bootstrap', [], $token)->assertOk()->json('data.offline.device_prefix'));
    }
}
