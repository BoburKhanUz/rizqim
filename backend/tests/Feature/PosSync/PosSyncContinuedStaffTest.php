<?php

namespace Tests\Feature\PosSync;

use App\Domain\Pos\Sync\ContinuedStaff;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosSyncOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wave M5 S6-a (docs/scale/M5_DESIGN.md §2 "App restart during an outage", owner answer 8.1-3): operations a till
 * queued for a staff member who went on without a PIN carry `staff_continued: true` (and, after an app restart,
 * `staff_resumed: true`). The server records the mark ("PIN'siz davom etilgan": the operation row, a `staff_continued`
 * flag on the order, the X/Z report's `continued`, `staff_continued` in "Sinxronlash xatolari"); its own staff check
 * does not change.
 */
class PosSyncContinuedStaffTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    private const MARK = ['staff_continued' => true, 'staff_resumed' => true];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    /**
     * A walk-in opened while the PIN session was alive; the bill, the payment and the close queued after a restart
     * (PIN-less: marked).
     *
     * @return array{order: string, bill: string, ops: list<array<string, mixed>>}
     */
    private function finishedWithoutPin(string $cash = '41000'): array
    {
        $order = (string) Str::uuid();
        $bill = (string) Str::uuid();

        return ['order' => $order, 'bill' => $bill, 'ops' => [
            $this->op('order.open', ['order' => $order, 'channel' => 'pos_takeaway', 'items' => [$this->line($this->shashlik, '2'), $this->line($this->tea, '1')], 'send' => true], null, 30),
            $this->op('bill.create', ['order' => $order, 'bill' => $bill, 'expected_total' => '41000'] + self::MARK, null, 20),
            $this->op('bill.pay', ['order' => $order, 'bill' => $bill, 'shift_uuid' => $this->shift->uuid, 'tenders' => [
                ['tender' => 'cash', 'amount' => $cash, 'tendered_amount' => '50000'],
            ]] + self::MARK, null, 19),
            $this->op('order.close', ['order' => $order] + self::MARK, null, 19),
        ]];
    }

    public function test_the_bootstrap_says_the_server_records_the_mark(): void
    {
        $this->tillAs('GET', '/pos/bootstrap')->assertOk()->assertJsonPath('data.offline.staff_resume', true);
    }

    public function test_marked_operations_are_applied_as_usual_and_recorded_on_the_rows_the_order_and_the_shift_report(): void
    {
        $sale = $this->finishedWithoutPin();
        $this->sync($sale['ops'])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.1.status', 'applied')
            ->assertJsonPath('data.results.2.status', 'applied')
            ->assertJsonPath('data.results.3.status', 'applied');

        // The rows keep the mark as sent
        foreach (array_slice($sale['ops'], 1) as $op) {
            $row = PosSyncOperation::where('op_id', $op['op_id'])->sole();
            $this->assertSame([true, true, $this->cashier->id], [$row->payload['staff_continued'], $row->payload['staff_resumed'], $row->staff_id]);
        }
        $this->assertArrayNotHasKey('staff_continued', PosSyncOperation::where('op_id', $sale['ops'][0]['op_id'])->sole()->payload);

        // The order says so once, and once more with the payment it took
        $order = Order::where('external_id', $sale['order'])->firstOrFail();
        $payment = Payment::where('order_id', $order->id)->sole();
        $flags = array_values(array_filter((array) $order->sync_flags, fn (array $flag) => $flag['flag'] === ContinuedStaff::FLAG));
        $this->assertCount(2, $flags);
        $this->assertSame([true, $this->cashier->id, $sale['ops'][1]['op_id']], [$flags[0]['resumed'], $flags[0]['staff_id'], $flags[0]['op_id']]);
        $this->assertArrayNotHasKey('payments', $flags[0]);
        $this->assertSame([[$payment->uuid], $sale['ops'][2]['op_id']], [$flags[1]['payments'], $flags[1]['op_id']]);
        $this->assertTrue($order->closed_at !== null);

        // The order screen lists it among the offline flags
        $shown = $this->tillAs('GET', "/pos/orders/{$order->uuid}")->assertOk()->json('data.offline.flags');
        $this->assertContains(ContinuedStaff::FLAG, array_column($shown, 'flag'));

        // The X report counts the payment taken PIN-less
        $report = $this->tillAs('GET', "/pos/shifts/{$this->shift->uuid}/x-report")->assertOk()->json('data.report');
        $this->assertSame(['count' => 1, 'amount' => '41000.00'], $report['continued']);
    }

    public function test_an_unmarked_sale_is_not_counted(): void
    {
        $this->sync($this->walkInSale()['ops'])->assertOk()->assertJsonPath('data.results.2.status', 'applied');
        $this->assertSame([], array_filter((array) Order::sole()->sync_flags, fn (array $flag) => $flag['flag'] === ContinuedStaff::FLAG));

        $report = $this->tillAs('GET', "/pos/shifts/{$this->shift->uuid}/x-report")->assertOk()->json('data.report');
        $this->assertSame(['count' => 0, 'amount' => '0.00'], $report['continued']);
    }

    public function test_the_manager_sees_the_mark_in_the_sync_errors(): void
    {
        // Overpaid by 9 000 (a conflict the manager looks at), queued PIN-less
        $sale = $this->finishedWithoutPin('50000');
        $this->sync($sale['ops'])->assertOk()->assertJsonPath('data.results.2.status', 'conflict');

        $errors = $this->tillAs('GET', '/pos/sync/errors', [], $this->pinSession($this->till, $this->manager, self::MANAGER_PIN))->assertOk()->json('data.errors');
        $row = collect($errors)->firstWhere('op_id', $sale['ops'][2]['op_id']);
        $this->assertSame(['conflict', 'overpaid', true], [$row['status'], $row['reason'], $row['staff_continued']]);

        // The X report counts the money taken (the drawer holds what the till took)
        $report = $this->tillAs('GET', "/pos/shifts/{$this->shift->uuid}/x-report", [], $this->pinSession($this->till, $this->manager, self::MANAGER_PIN))->assertOk()->json('data.report');
        $this->assertSame(1, $report['continued']['count']);
    }

    public function test_the_mark_widens_nothing_the_server_checks(): void
    {
        // 37 h after the cashier's PIN login on this till: the marked bill is rejected as any other, the marked payment
        // is recorded `staff_unverified` as any other (S1)
        $order = (string) Str::uuid();
        $bill = (string) Str::uuid();
        $this->sync([
            $this->op('order.open', ['order' => $order, 'channel' => 'pos_takeaway', 'items' => [$this->line($this->tea, '1')], 'send' => true]),
        ])->assertOk()->assertJsonPath('data.results.0.status', 'applied');

        Carbon::setTestNow(now()->addHours(37));
        $this->sync([
            $this->op('bill.create', ['order' => $order, 'bill' => $bill, 'expected_total' => '5000'] + self::MARK),
            $this->op('bill.pay', ['order' => $order, 'shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '5000']]] + self::MARK),
        ])->assertOk()
            ->assertJsonPath('data.results.0.status', 'rejected')
            ->assertJsonPath('data.results.0.reason', 'staff_not_signed_in')
            ->assertJsonPath('data.results.1.status', 'conflict')
            ->assertJsonPath('data.results.1.reason', 'staff_unverified');

        // A marked discount is refused as before (M5 S2)
        $this->sync([
            $this->op('order.discount', ['order' => $order, 'kind' => 'percent', 'value' => '5'] + self::MARK),
        ])->assertOk()->assertJsonPath('data.results.0.status', 'rejected');
    }
}
