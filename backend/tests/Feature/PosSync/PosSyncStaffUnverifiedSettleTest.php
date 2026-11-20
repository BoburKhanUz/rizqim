<?php

namespace Tests\Feature\PosSync;

use App\Domain\Pos\Billing\BillStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Models\PosSyncOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wave M5 S1 review fixes (two independent reviews of the `staff_unverified` payment):
 *  - the payment settles its open bill through the normal payment path (the bill is paid, the order closes, the shift
 *    closes, the bill cannot be paid twice); unallocated only when there is no open bill to put it on;
 *  - the staff id of the operation is a claim: money is attributed to that person only when they work on this till
 *    and signed in on it at least once; the audit's actor is the device;
 *  - a payment the server already took online (the same Idempotency-Key) is a replay, never a second payment.
 */
class PosSyncStaffUnverifiedSettleTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    /**
     * @return array{order: string, bill: string}
     */
    private function openTable(): array
    {
        $order = (string) Str::uuid();
        $bill = (string) Str::uuid();
        $this->sync([
            $this->op('order.open', ['order' => $order, 'channel' => 'pos_takeaway', 'items' => [$this->line($this->shashlik, '2'), $this->line($this->tea, '1')], 'send' => true]),
            $this->op('bill.create', ['order' => $order, 'bill' => $bill, 'expected_total' => '41000']),
        ])->assertOk()->assertJsonPath('data.results.1.status', 'applied');

        return ['order' => $order, 'bill' => $bill];
    }

    /**
     * @param  array{order: string, bill: string}  $table
     * @return array<string, mixed>
     */
    private function cash(array $table, string $amount = '41000', ?\App\Models\User $staff = null): array
    {
        return $this->op('bill.pay', ['order' => $table['order'], 'bill' => $table['bill'], 'shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => $amount]]], $staff);
    }

    public function test_an_unverified_payment_settles_its_open_bill_and_the_order_and_the_shift_close(): void
    {
        $table = $this->openTable();
        Carbon::setTestNow(now()->addHours(37)->addMinutes(10));
        $pay = $this->cash($table);
        $close = $this->op('order.close', ['order' => $table['order']]);

        $this->sync([$pay, $close])->assertOk()
            ->assertJsonPath('data.results.0.status', 'conflict')
            ->assertJsonPath('data.results.0.reason', 'staff_unverified')
            ->assertJsonPath('data.results.0.refs.flags.0.flag', 'staff_unverified')
            ->assertJsonPath('data.results.0.refs.flags.0.reason', 'staff_not_signed_in')
            // The close that follows it from the same till is applied (flagged), not rejected for the same check
            ->assertJsonPath('data.results.1.status', 'applied')
            ->assertJsonPath('data.results.1.refs.closed', true)
            ->assertJsonPath('data.results.1.refs.flags.0.flag', 'staff_unverified');

        $order = Order::where('external_id', $table['order'])->firstOrFail();
        $bill = OrderBill::where('order_id', $order->id)->sole();
        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame($bill->id, $payment->bill_id);
        $this->assertSame(BillStatus::PAID, $bill->status);
        $this->assertNotNull($order->closed_at);
        $this->assertSame(1, AuditLog::where('action', 'pos.offline_payment_staff_unverified')->count());
        $this->assertSame(1, AuditLog::where('action', 'pos.offline_close_staff_unverified')->count());

        // The manager still sees the payment in "Sinxronlash xatolari"
        $errors = $this->tillAs('GET', '/pos/sync/errors', [], $this->pinSession($this->till, $this->manager, self::MANAGER_PIN))->assertOk()->json('data.errors');
        $this->assertSame('staff_unverified', collect($errors)->firstWhere('op_id', $pay['op_id'])['reason']);
        $this->assertNull(collect($errors)->firstWhere('op_id', $close['op_id']));
        $token = $this->pinSession($this->till, $this->cashier);

        // Paying the same bill again online is refused, the drawer holds the sale once, the cashier closes the shift
        $this->tillAs('POST', "/pos/bills/{$bill->uuid}/payments", ['shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '41000']]], $token)
            ->assertStatus(409);
        $this->assertSame(1, CashMovement::where('shift_id', $this->shift->id)->where('type', 'sale')->count());
        $this->tillAs('POST', "/pos/shifts/{$this->shift->uuid}/close", ['counted_breakdown' => []], $token)->assertSuccessful();
    }

    public function test_only_the_close_of_a_fully_paid_order_passes_a_failed_staff_check(): void
    {
        $unpaid = $this->openTable();
        $partly = $this->openTable();
        Carbon::setTestNow(now()->addHours(37)->addMinutes(10));

        $this->sync([
            // Nothing paid: the close is rejected as before
            $this->op('order.close', ['order' => $unpaid['order']]),
            // Paid in part (unverified): the bill is still open, the close is rejected
            $this->cash($partly, '10000'),
            $this->op('order.close', ['order' => $partly['order']]),
            // Any other operation of that person: rejected
            $this->op('bill.request', ['order' => $partly['order']]),
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'rejected')->assertJsonPath('data.results.0.reason', 'staff_not_signed_in')
            ->assertJsonPath('data.results.1.status', 'conflict')->assertJsonPath('data.results.1.reason', 'staff_unverified')
            ->assertJsonPath('data.results.2.status', 'rejected')->assertJsonPath('data.results.2.reason', 'staff_not_signed_in')
            ->assertJsonPath('data.results.3.status', 'rejected')->assertJsonPath('data.results.3.reason', 'staff_not_signed_in');

        $order = Order::where('external_id', $partly['order'])->firstOrFail();
        $this->assertNull($order->closed_at);
        $this->assertSame(OrderBill::where('order_id', $order->id)->value('id'), Payment::where('order_id', $order->id)->sole()->bill_id);
        $this->assertSame(['staff_unverified', 'underpaid'], collect($order->sync_flags)->pluck('flag')->all());
    }

    public function test_money_is_never_attributed_to_people_who_did_not_sign_in_on_the_till(): void
    {
        $unknownBill = $this->openTable();
        $openBill = $this->openTable();
        $far = $this->makeStaff(UserRole::CASHIER, $this->otherBranch);

        // The till's token alone names the owner and another branch's cashier: on a bill the server does not know
        // (unallocated) and on an open bill (settled)
        $ops = [
            $this->op('bill.pay', ['order' => $unknownBill['order'], 'bill' => (string) Str::uuid(), 'shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '9000']]], $this->owner),
            $this->op('bill.pay', ['order' => $unknownBill['order'], 'bill' => (string) Str::uuid(), 'shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '8000']]], $far),
            $this->cash($openBill, '41000', $this->owner),
        ];
        $this->sync($ops)->assertOk()
            ->assertJsonPath('data.results.0.reason', 'staff_unverified')
            ->assertJsonPath('data.results.1.reason', 'staff_unverified')
            ->assertJsonPath('data.results.2.reason', 'staff_unverified');

        $orders = Order::whereIn('external_id', [$unknownBill['order'], $openBill['order']])->pluck('id');
        $payments = Payment::whereIn('order_id', $orders)->get();
        $this->assertCount(3, $payments);
        $this->assertSame([null, null, null], $payments->pluck('received_by')->all());
        $this->assertNotNull($payments->firstWhere('amount', '41000.00')->bill_id);

        // The drawer counts the cash on the shift's opener; the audit's actor is the device, the id is only a claim
        $this->assertSame([$this->cashier->id], CashMovement::whereIn('payment_id', $payments->pluck('id'))->pluck('user_id')->unique()->values()->all());
        $audits = AuditLog::where('action', 'pos.offline_payment_staff_unverified')->orderBy('id')->get();
        $this->assertSame([null, null, null], $audits->pluck('user_id')->all());
        $this->assertSame([$this->till['device']->id], $audits->pluck('device_id')->unique()->values()->all());
        $this->assertSame([$this->owner->id, $far->id, $this->owner->id], $audits->map(fn (AuditLog $audit) => $audit->payload_json['claimed_staff_id'])->all());
        $this->assertSame(0, AuditLog::whereIn('user_id', [$this->owner->id, $far->id])->whereIn('action', ['pos.payment', 'pos.offline_payment_staff_unverified'])->count());

        // "Sinxronlash xatolari" names nobody who failed the check
        $this->assertSame([null, null, null], PosSyncOperation::whereIn('op_id', array_column($ops, 'op_id'))->pluck('staff_id')->all());
        $errors = (string) json_encode($this->tillAs('GET', '/pos/sync/errors', [], $this->pinSession($this->till, $this->manager, self::MANAGER_PIN))->assertOk()->json('data.errors'), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString((string) $this->owner->name, $errors);
        $this->assertStringNotContainsString((string) $far->name, $errors);
    }

    public function test_a_payment_the_server_took_online_is_a_replay_when_the_session_is_too_old(): void
    {
        [$table, $order, $key] = $this->paidOnline();

        Carbon::setTestNow(now()->addHours(37));
        $this->replayIsNotASecondPayment($table, $order, $key);
    }

    public function test_a_payment_the_server_took_online_is_a_replay_when_the_permission_is_gone(): void
    {
        [$table, $order, $key] = $this->paidOnline();

        // The role changes during the outage (cashier -> waiter: no pos.take_payment)
        $this->cashier->forceFill(['role' => UserRole::WAITER->value])->save();
        $this->replayIsNotASecondPayment($table, $order, $key);
    }

    /**
     * A bill paid online with an Idempotency-Key (the answer is lost on the way back).
     *
     * @return array{0: array{order: string, bill: string}, 1: Order, 2: string}
     */
    private function paidOnline(): array
    {
        $table = $this->openTable();
        $order = Order::where('external_id', $table['order'])->firstOrFail();
        $key = (string) Str::uuid();
        $this->withBearer($this->cashierToken)->withHeader('Idempotency-Key', $key);
        $this->json('POST', '/api/v1/pos/bills/' . OrderBill::where('order_id', $order->id)->value('uuid') . '/payments', [
            'shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '41000']],
        ])->assertSuccessful();
        $this->assertSame(1, Payment::where('order_id', $order->id)->count());

        return [$table, $order, $key];
    }

    /**
     * @param  array{order: string, bill: string}  $table
     */
    private function replayIsNotASecondPayment(array $table, Order $order, string $key): void
    {
        $pay = $this->op('bill.pay', ['order' => $table['order'], 'bill' => $table['bill'], 'idempotency_key' => $key, 'shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '41000']]]);
        // ...and the close queued behind it is not left rejected (it would block the shift)
        $this->sync([$pay, $this->op('order.close', ['order' => $table['order']])])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.refs.replayed', true)
            ->assertJsonPath('data.results.1.status', 'applied')
            ->assertJsonPath('data.results.1.refs.closed', true);

        $this->assertSame(1, Payment::where('order_id', $order->id)->count());
        $this->assertSame(1, CashMovement::where('shift_id', $this->shift->id)->where('type', 'sale')->count());
        $this->assertSame(0, AuditLog::where('action', 'pos.offline_payment_staff_unverified')->count());
        $this->assertSame([], collect($order->refresh()->sync_flags)->where('op_id', $pay['op_id'])->all());
    }
}
