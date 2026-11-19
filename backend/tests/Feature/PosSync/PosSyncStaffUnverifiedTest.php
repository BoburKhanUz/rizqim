<?php

namespace Tests\Feature\PosSync;

use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosSyncOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wave M5 S1 (docs/scale/M5_DESIGN.md 1.6 bug 4): a payment taken offline is never rejected because the staff check
 * failed (a PIN session older than 36 h before the payment, a permission taken away meanwhile, a staff id the store
 * does not know). The money is recorded through the unallocated path with the conflict flag `staff_unverified`, an
 * audit row and a line in "Sinxronlash xatolari"; every other operation is rejected as before. Also: the server's
 * `last_seq` for the till is what it really holds, after a batch that stopped half-way too.
 */
class PosSyncStaffUnverifiedTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    /**
     * An order opened and billed while the cashier's PIN session was fresh.
     *
     * @return array{order: string, bill: string}
     */
    private function openTable(): array
    {
        $order = (string) Str::uuid();
        $bill = (string) Str::uuid();
        $this->sync([
            $this->op('order.open', ['order' => $order, 'channel' => 'pos_takeaway', 'items' => [$this->line($this->shashlik, '2'), $this->line($this->tea, '1')], 'send' => true]),
            $this->op('bill.create', ['order' => $order, 'bill' => $bill, 'expected_total' => '41000']),
        ])->assertOk()->assertJsonPath('data.results.0.status', 'applied')->assertJsonPath('data.results.1.status', 'applied');

        return ['order' => $order, 'bill' => $bill];
    }

    public function test_a_payment_37_hours_after_the_pin_login_is_recorded_as_staff_unverified(): void
    {
        $table = $this->openTable();

        // The outage goes on: the payment is taken 37 h after the cashier typed the PIN (the window is 36 h)
        Carbon::setTestNow(now()->addHours(37)->addMinutes(10));
        $pay = $this->op('bill.pay', ['order' => $table['order'], 'bill' => $table['bill'], 'shift_uuid' => $this->shift->uuid, 'tenders' => [
            ['tender' => 'cash', 'amount' => '41000', 'tendered_amount' => '50000'],
        ]]);
        $close = $this->op('order.close', ['order' => $table['order']]);

        $this->sync([$pay, $close])->assertOk()
            ->assertJsonPath('data.results.0.status', 'conflict')
            ->assertJsonPath('data.results.0.reason', 'staff_unverified')
            // A non-money operation of the same staff member is still rejected
            ->assertJsonPath('data.results.1.status', 'rejected')
            ->assertJsonPath('data.results.1.reason', 'staff_not_signed_in');

        // The money is in the books: on the order, in the till's shift and drawer, attributed to the named cashier
        $order = Order::where('external_id', $table['order'])->firstOrFail();
        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame(['41000.00', 'paid', 'cash', $this->cashier->id, $this->shift->id, null], [
            $payment->amount, $payment->status->value, $payment->provider, $payment->received_by, $payment->shift_id, $payment->bill_id,
        ]);
        $this->assertSame(1, CashMovement::where('shift_id', $this->shift->id)->where('payment_id', $payment->id)->count());

        $flag = collect($order->sync_flags)->firstWhere('flag', 'staff_unverified');
        $this->assertSame(['41000.00', 'staff_not_signed_in', $pay['op_id']], [$flag['amount'], $flag['reason'], $flag['op_id']]);

        $audit = AuditLog::where('action', 'pos.offline_payment_staff_unverified')->sole();
        $this->assertSame([$this->store->id, $this->till['device']->id, $order->id], [$audit->store_id, $audit->device_id, $audit->model_id]);
        $this->assertSame(['41000.00', 'staff_not_signed_in', [$payment->uuid]], [$audit->payload_json['amount'], $audit->payload_json['reason'], $audit->payload_json['payments']]);

        // The manager sees it in "Sinxronlash xatolari"; the same operation sent again changes nothing
        $this->pinSession($this->till, $this->cashier);
        $errors = $this->tillAs('GET', '/pos/sync/errors', [], $this->pinSession($this->till, $this->manager, self::MANAGER_PIN))->assertOk()->json('data.errors');
        $row = collect($errors)->firstWhere('op_id', $pay['op_id']);
        $this->assertSame(['conflict', 'staff_unverified', $this->cashier->id], [$row['status'], $row['reason'], $row['staff']['id']]);
        $this->assertStringContainsString('41000.00', (string) $row['message']);

        $this->sync([$pay])->assertOk()->assertJsonPath('data.results.0.duplicate', true);
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
    }

    public function test_a_payment_by_staff_without_the_permission_is_recorded_and_other_operations_are_rejected(): void
    {
        $table = $this->openTable();
        // The waiter is signed in on the till but may not take money
        $this->pinSession($this->till, $this->waiter, '7391');

        $pay = $this->op('bill.pay', ['order' => $table['order'], 'bill' => $table['bill'], 'shift_uuid' => $this->shift->uuid, 'tenders' => [
            ['tender' => 'card', 'amount' => '41000', 'card_type' => 'uzcard'],
        ]], $this->waiter);
        $this->sync([$pay, $this->op('order.close', ['order' => $table['order']], $this->waiter)])->assertOk()
            ->assertJsonPath('data.results.0.status', 'conflict')
            ->assertJsonPath('data.results.0.reason', 'staff_unverified')
            ->assertJsonPath('data.results.0.refs.flags.0.reason', 'permission_denied')
            ->assertJsonPath('data.results.1.status', 'rejected')
            ->assertJsonPath('data.results.1.reason', 'permission_denied');

        $payment = Payment::where('order_id', Order::where('external_id', $table['order'])->value('id'))->sole();
        $this->assertSame(['41000.00', 'card', $this->waiter->id], [$payment->amount, $payment->provider, $payment->received_by]);
        $this->assertSame(1, AuditLog::where('action', 'pos.offline_payment_staff_unverified')->count());
    }

    public function test_a_payment_naming_nobody_of_the_store_is_recorded_without_a_receiver(): void
    {
        $table = $this->openTable();
        $stranger = User::create(['name' => 'Begona Mijoz', 'phone' => '+998901112233', 'password' => bcrypt('x')]);

        $pay = $this->op('bill.pay', ['order' => $table['order'], 'bill' => $table['bill'], 'shift_uuid' => $this->shift->uuid, 'tenders' => [
            ['tender' => 'cash', 'amount' => '41000'],
        ]], $stranger);
        $this->sync([$pay])->assertOk()
            ->assertJsonPath('data.results.0.status', 'conflict')
            ->assertJsonPath('data.results.0.reason', 'staff_unverified')
            ->assertJsonPath('data.results.0.refs.flags.0.reason', 'staff_invalid');

        $order = Order::where('external_id', $table['order'])->firstOrFail();
        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame(['41000.00', null, $this->shift->id], [$payment->amount, $payment->received_by, $payment->shift_id]);
        // The cash is in the drawer's count all the same; the row never links a user outside the store
        $this->assertSame(1, CashMovement::where('shift_id', $this->shift->id)->where('payment_id', $payment->id)->count());
        $this->assertNull(PosSyncOperation::where('op_id', $pay['op_id'])->sole()->staff_id);
        $this->assertNull(AuditLog::where('action', 'pos.offline_payment_staff_unverified')->sole()->user_id);
        $this->assertStringNotContainsString('Begona', (string) json_encode($this->tillAs('GET', '/pos/sync/errors')->assertOk()->json('data.errors')));

        // A non-money operation naming them: rejected as before
        $this->sync([$this->op('order.close', ['order' => $table['order']], $stranger)])->assertOk()
            ->assertJsonPath('data.results.0.status', 'rejected')->assertJsonPath('data.results.0.reason', 'staff_invalid');
    }

    public function test_an_unverified_payment_on_a_cancelled_order_also_asks_for_the_refund(): void
    {
        $table = $this->openTable();
        $order = Order::where('external_id', $table['order'])->firstOrFail();
        $order->forceFill(['status' => \App\Enums\OrderStatus::CANCELED])->save();

        Carbon::setTestNow(now()->addHours(37)->addMinutes(10));
        $pay = $this->op('bill.pay', ['order' => $table['order'], 'bill' => $table['bill'], 'shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '41000']]]);
        $this->sync([$pay])->assertOk()->assertJsonPath('data.results.0.status', 'conflict')->assertJsonPath('data.results.0.reason', 'staff_unverified');

        $this->assertSame(['staff_unverified', 'refund_needed'], collect($order->refresh()->sync_flags)->where('op_id', $pay['op_id'])->pluck('flag')->all());
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
    }

    public function test_the_status_tells_the_last_seq_the_server_really_holds(): void
    {
        $sale = $this->walkInSale();
        $this->sync($sale['ops'])->assertOk()->assertJsonPath('data.device.last_seq', 4);

        // A batch that is refused half-way (an op id that belongs to another till): what was applied before it counts
        $second = $this->pairTerminal($this->owner, $this->branch);
        $this->seq = 0;
        $foreign = $this->op('order.open', ['order' => (string) Str::uuid(), 'channel' => 'pos_takeaway']);
        $this->pinSession($second, $this->cashier);
        $this->sync([$foreign], [], $second['token'])->assertOk();

        $this->seq = 4;
        $fifth = $this->op('order.open', ['order' => (string) Str::uuid(), 'channel' => 'pos_takeaway']);
        $sixth = ['op_id' => $foreign['op_id']] + $this->op('order.open', ['order' => (string) Str::uuid(), 'channel' => 'pos_takeaway']);
        $this->pinSession($this->till, $this->cashier);
        $this->sync([$fifth, $sixth])->assertStatus(409)->assertJsonPath('data.reason', 'op_id_conflict');
        $this->assertSame(5, (int) PosSyncOperation::where('device_id', $this->till['device']->id)->max('seq'));

        $this->withBearer($this->till['token']);
        $this->json('GET', '/api/v1/pos/sync/status')->assertOk()->assertJsonPath('data.device.last_seq', 5);
    }
}
