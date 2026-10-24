<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Pos\Shifts\ShiftStatus;
use App\Enums\UserRole;
use App\Events\Broadcast\ShiftChangedBroadcast;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P3-18: shifts: opening on a terminal (one per terminal, the business day around 05:00 and an earlier 04:00 start),
 * queue numbers per business day, cash in and out with categories (bazaar, wages), the X report, the Z close (counted
 * banknotes, difference, z_number, refused with open orders or pending receipts unless forced by a manager), the
 * real-time hint and tenant isolation.
 */
class PosShiftsTest extends TestCase
{
    use PosBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosBilling();
    }

    public function test_a_shift_opens_on_a_terminal_once(): void
    {
        Event::fake([ShiftChangedBroadcast::class]);

        $response = $this->pos('POST', '/shifts', ['opening_cash' => '250000'])->assertCreated();
        $response->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.opening_cash', '250000.00')
            ->assertJsonPath('data.device.uuid', $this->till['device']->uuid)
            ->assertJsonPath('data.opened_by.id', $this->cashier->id);
        $this->assertDatabaseHas('cash_movements', ['type' => 'opening', 'amount' => '250000.00']);
        Event::assertDispatched(ShiftChangedBroadcast::class, fn ($event) => $event->change === 'opened' && $event->branchId === $this->branch->id);

        $this->pos('POST', '/shifts', ['opening_cash' => '0'])->assertStatus(409)->assertJsonPath('data.reason', 'shift_already_open');
        $this->posAs($this->manager, 'POST', '/shifts', ['opening_cash' => '0'])->assertStatus(422)->assertJsonPath('data.reason', 'terminal_required');

        $this->withBearer($this->cashierToken)->getJson('/api/v1/pos/shifts/current')->assertOk()
            ->assertJsonPath('data.shift.uuid', $response->json('data.uuid'))
            ->assertJsonPath('data.report.cash.expected', '250000.00');
    }

    public function test_the_business_day_starts_at_five_in_the_stores_time_zone(): void
    {
        // 23:30 UTC = 04:30 in Tashkent: still the day before
        $this->travelTo(Carbon::parse('2026-10-22 23:30:00', 'UTC'));
        $this->assertSame('2026-10-22', $this->openShift()->business_date->toDateString());
        PosShift::query()->update(['status' => 'closed']);

        // 00:30 UTC = 05:30: the new day
        $this->travelTo(Carbon::parse('2026-10-23 00:30:00', 'UTC'));
        $this->assertSame('2026-10-23', $this->openShift()->business_date->toDateString());
        PosShift::query()->update(['status' => 'closed']);

        // A branch that opens early (04:00): 04:30 already belongs to the new day
        $this->branch->forceFill(['business_day_starts_at' => '04:00'])->save();
        $this->travelTo(Carbon::parse('2026-10-22 23:30:00', 'UTC'));
        $this->assertSame('2026-10-23', $this->openShift()->business_date->toDateString());
    }

    public function test_queue_numbers_restart_every_business_day_and_orders_join_the_tills_shift(): void
    {
        $this->travelTo(Carbon::parse('2026-10-22 18:00:00', 'UTC')); // 23:00
        $shift = $this->openShift();
        $first = $this->posOrder();
        $this->travelTo(Carbon::parse('2026-10-22 21:00:00', 'UTC')); // 02:00, same business day
        $second = $this->posOrder();
        $online = $this->posOrder([['Osh', '1', '1000']], ['channel' => 'telegram']);
        $this->travelTo(Carbon::parse('2026-10-23 01:00:00', 'UTC')); // 06:00, the next day
        $third = $this->posOrder();
        $otherBranch = $this->posOrder([['Osh', '1', '1000']], ['branch_id' => $this->otherBranch->id, 'device_id' => null]);

        $this->assertSame([1, 2, 1, 1], [$first->queue_number, $second->queue_number, $third->queue_number, $otherBranch->queue_number]);
        $this->assertSame(['2026-10-22', '2026-10-22', '2026-10-23'], [$first->business_date->toDateString(), $second->business_date->toDateString(), $third->business_date->toDateString()]);
        $this->assertSame($shift->id, $first->shift_id);
        $this->assertNull($otherBranch->shift_id);
        $this->assertNull($online->queue_number);
        $this->assertNull($online->business_date);
    }

    public function test_cash_in_and_out_with_categories_wages_and_the_drawer_limit(): void
    {
        $shift = $this->openShift('100000');
        $managerToken = null;

        // P3-37: a cashier puts money in and takes it out alone by default...
        $this->pos('POST', "/shifts/{$shift->uuid}/cash-movements", ['type' => 'cash_in', 'category' => 'change_fund', 'amount' => '10000'])
            ->assertCreated()->assertJsonPath('data.movement.approved_by', null);
        $this->pos('POST', "/shifts/{$shift->uuid}/cash-movements", ['type' => 'cash_out', 'category' => 'collection', 'amount' => '10000'])
            ->assertCreated()->assertJsonPath('data.movement.approved_by', null);

        // ...unless the owner took the drawer away from cashiers in this store: then a manager's PIN
        app(\App\Services\Auth\PermissionService::class)->setOverride($this->store, \App\Enums\UserRole::CASHIER, \App\Enums\Permission::POS_CASH_IN_OUT, false);
        $this->pos('POST', "/shifts/{$shift->uuid}/cash-movements", ['type' => 'purchase', 'category' => 'bazaar', 'amount' => '60000', 'note' => 'Guruch, sabzi'])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required');

        $this->pos('POST', "/shifts/{$shift->uuid}/cash-movements", ['type' => 'purchase', 'category' => 'bazaar', 'amount' => '60000', 'note' => 'Guruch, sabzi', 'approval_token' => $this->approval('pos.cash_in_out')])
            ->assertCreated()->assertJsonPath('data.movement.approved_by', $this->manager->id);

        $as = fn (array $data) => $this->posAs($this->manager, 'POST', "/shifts/{$shift->uuid}/cash-movements", $data);
        $as(['type' => 'wage', 'category' => 'daily_wage', 'amount' => '20000'])->assertStatus(422)->assertJsonPath('data.reason', 'employee_required');
        $as(['type' => 'wage', 'category' => 'daily_wage', 'amount' => '20000', 'employee_id' => $this->waiter->id])->assertCreated()
            ->assertJsonPath('data.movement.employee_id', $this->waiter->id);
        $as(['type' => 'cash_out', 'category' => 'other', 'amount' => '1000'])->assertStatus(422)->assertJsonPath('data.reason', 'movement_note_required');
        $as(['type' => 'cash_out', 'category' => 'fun', 'amount' => '1000'])->assertStatus(422)->assertJsonPath('data.reason', 'movement_category_invalid');
        $as(['type' => 'cash_out', 'category' => 'collection', 'amount' => '30000'])->assertStatus(409)->assertJsonPath('data.reason', 'not_enough_cash');
        $as(['type' => 'cash_in', 'category' => 'change_fund', 'amount' => '50000'])->assertCreated();

        $report = $this->withBearer($this->cashierToken)->getJson("/api/v1/pos/shifts/{$shift->uuid}/x-report")->assertOk()->json('data.report.cash');
        $this->assertSame(['100000.00', '60000.00', '20000.00', '60000.00', '10000.00', '70000.00'], [$report['opening'], $report['purchases'], $report['wages'], $report['cash_in'], $report['cash_out'], $report['expected']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.cash_movement', 'user_id' => $this->manager->id]);
    }

    public function test_the_x_report_adds_up_tenders_channels_waiters_discounts_voids_refunds_and_credit(): void
    {
        $shift = $this->openShift('50000');
        $order = $this->posOrder([['Osh', '1', '200000'], ['Choy', '1', '10000']], ['discount' => '10000', 'service_charge_amount' => '20000', 'waiter_id' => $this->waiter->id]);
        $this->line($order, 'Somsa', '1', '15000')->forceFill(['voided_at' => now(), 'kitchen_status' => 'void'])->save();
        $this->advances->deposits[$order->id] = '50000';
        $bill = $this->bill($order)->json('data.bills.0.uuid');
        $this->pay($bill, [
            ['tender' => 'prepayment', 'amount' => '50000'],
            ['tender' => 'card', 'amount' => '100000', 'card_type' => 'uzcard', 'transaction_id' => '77'],
            ['tender' => 'cash', 'amount' => '70000'],
        ])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $cash = \App\Models\Payment::where('provider', 'cash')->value('uuid');
        $this->posAs($this->manager, 'POST', '/refunds', ['payment_uuid' => $cash, 'amount' => '5000', 'reason' => 'Sovuq choy', 'shift_uuid' => $shift->uuid])->assertCreated();

        $report = $this->posAs($this->manager, 'GET', "/shifts/{$shift->uuid}/x-report")->assertOk()->json('data.report');

        $this->assertSame(['cash' => '70000.00', 'card' => '100000.00', 'qr' => '0.00', 'prepayment' => '50000.00', 'account' => '0.00'], array_column($report['by_tender'], 'amount', 'tender'));
        $this->assertSame([['card_type' => 'uzcard', 'count' => 1, 'amount' => '100000.00']], $report['cards']);
        $this->assertSame([['channel' => 'pos_hall', 'orders' => 1, 'amount' => '220000.00']], $report['by_channel']);
        $this->assertSame($this->waiter->id, $report['by_waiter'][0]['waiter_id']);
        $this->assertSame('20000.00', $report['by_waiter'][0]['service_charge']);
        $this->assertSame(['count' => 1, 'amount' => '10000.00'], $report['discounts']);
        $this->assertSame(['count' => 1, 'amount' => '15000.00'], $report['voids']);
        $this->assertSame('5000.00', $report['refunds']['amount']);
        $this->assertSame('50000.00', $report['deposits']['applied']['amount']);
        $this->assertSame('220000.00', $report['sales']['received']);
        $this->assertSame('215000.00', $report['sales']['net']);
        $this->assertSame('115000.00', $report['cash']['expected']); // 50 000 + 70 000 - 5 000
    }

    public function test_the_z_close_counts_banknotes_and_numbers_the_reports(): void
    {
        Event::fake([ShiftChangedBroadcast::class]);
        $shift = $this->openShift('100000');
        $order = $this->posOrder([['Osh', '1', '150000']]);
        $this->pay($this->bill($order)->json('data.bills.0.uuid'), [['tender' => 'cash', 'amount' => '150000']])->assertOk();

        $closed = $this->pos('POST', "/shifts/{$shift->uuid}/close", ['counted_breakdown' => ['100000' => 2, '50000' => 1, '1000' => 3]])->assertOk();

        $closed->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.expected_cash', '250000.00')
            ->assertJsonPath('data.counted_cash', '253000.00')
            ->assertJsonPath('data.difference', '3000.00')
            ->assertJsonPath('data.z_number', 1)
            ->assertJsonPath('data.counted_breakdown', ['100000' => 2, '50000' => 1, '1000' => 3])
            ->assertJsonPath('data.summary.sales.received', '150000.00');
        Event::assertDispatched(ShiftChangedBroadcast::class, fn ($event) => $event->change === 'closed' && $event->zNumber === 1);
        $this->pos('POST', "/shifts/{$shift->uuid}/close", ['counted_breakdown' => []])->assertStatus(409)->assertJsonPath('data.reason', 'shift_closed');
        $this->pos('POST', "/shifts/{$shift->uuid}/close", ['counted_breakdown' => ['3000' => 1]])->assertStatus(422);

        $next = $this->openShift('0');
        $this->pos('POST', "/shifts/{$next->uuid}/close", ['counted_breakdown' => []])->assertOk()->assertJsonPath('data.z_number', 2);
        $this->posAs($this->owner, 'GET', '/shifts')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_shift_with_open_orders_or_pending_receipts_closes_only_when_a_manager_forces_it(): void
    {
        $shift = $this->openShift('0');
        $open = $this->posOrder([['Osh', '1', '100000']]);

        $this->pos('POST', "/shifts/{$shift->uuid}/close", ['counted_breakdown' => []])->assertStatus(409)
            ->assertJsonPath('data.reason', 'shift_has_open_orders')
            ->assertJsonPath('data.open_orders', [$open->uuid]);

        // Paid but the receipt is pending: the order is still open, and so is its receipt
        $this->fiscal->mode = 'pending';
        $bill = $this->bill($open)->json('data.bills.0.uuid');
        $this->pay($bill, [['tender' => 'cash', 'amount' => '100000']])->assertOk();
        $response = $this->pos('POST', "/shifts/{$shift->uuid}/close", ['counted_breakdown' => ['100000' => 1]])->assertStatus(409);
        $this->assertSame([$bill], $response->json('data.pending_bills'));

        // A cashier cannot force; a manager's approval with a reason can
        $this->pos('POST', "/shifts/{$shift->uuid}/close", ['counted_breakdown' => ['100000' => 1], 'force' => true])->assertStatus(422)->assertJsonPath('data.reason', 'force_reason_required');
        $this->pos('POST', "/shifts/{$shift->uuid}/close", ['counted_breakdown' => ['100000' => 1], 'force' => true, 'reason' => 'Provayder ishlamayapti'])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required');
        $this->pos('POST', "/shifts/{$shift->uuid}/close", ['counted_breakdown' => ['100000' => 1], 'force' => true, 'reason' => 'Provayder ishlamayapti', 'approval_token' => $this->approval('orders.cancel')])
            ->assertOk()
            ->assertJsonPath('data.force_closed', true)
            ->assertJsonPath('data.close_note', 'Provayder ishlamayapti')
            ->assertJsonPath('data.summary.open_orders', [$open->uuid]);

        $this->assertNull(Order::find($open->id)->shift_id); // waits for the next shift
        $this->assertSame(ShiftStatus::CLOSED, $shift->refresh()->status);
        $this->assertSame($this->manager->id, (int) $shift->forced_by);
    }

    public function test_a_receipt_issued_meanwhile_no_longer_blocks_the_close(): void
    {
        $shift = $this->openShift('0');
        $order = $this->posOrder([['Osh', '1', '100000']]);
        $this->fiscal->mode = 'pending';
        $bill = $this->bill($order)->json('data.bills.0.uuid');
        $this->pay($bill, [['tender' => 'cash', 'amount' => '100000']])->assertOk();

        // The provider confirmed it: the close asks again, the bill is paid, the order closed
        $this->fiscal->mode = 'issued';
        $this->fiscal->statuses = [];
        $this->pos('POST', "/shifts/{$shift->uuid}/close", ['counted_breakdown' => ['100000' => 1]])->assertOk()->assertJsonPath('data.difference', '0.00');
        $this->assertNotNull(Order::find($order->id)->closed_at);
    }

    public function test_shifts_of_another_branch_or_store_are_not_found(): void
    {
        $shift = $this->openShift();

        $otherManager = $this->makeStaff(UserRole::MANAGER, $this->otherBranch);
        $this->posAs($otherManager, 'GET', "/shifts/{$shift->uuid}")->assertNotFound();
        $this->posAs($otherManager, 'POST', "/shifts/{$shift->uuid}/cash-movements", ['type' => 'cash_in', 'category' => 'owner', 'amount' => '1'])->assertNotFound();
        $this->posAs($otherManager, 'GET', '/shifts')->assertOk()->assertJsonCount(0, 'data');
        $this->posAs($otherManager, 'GET', '/shifts?branch_id=' . $this->branch->id)->assertForbidden();

        $foreign = $this->makeStore('Boshqa');
        $foreignOwner = $this->makeStaff(UserRole::OWNER, null, $foreign);
        $this->posAs($foreignOwner, 'GET', "/shifts/{$shift->uuid}/x-report")->assertNotFound();

        $this->posAs($this->waiter, 'GET', "/shifts/{$shift->uuid}")->assertForbidden();
        $this->posAs($this->owner, 'GET', "/shifts/{$shift->uuid}")->assertOk();
    }

    public function test_the_bootstrap_carries_the_terminals_open_shift(): void
    {
        $this->withBearer($this->cashierToken)->getJson('/api/v1/pos/bootstrap')->assertOk()->assertJsonPath('data.shift', null);

        $shift = $this->openShift();
        $this->withBearer($this->cashierToken)->getJson('/api/v1/pos/bootstrap')->assertOk()->assertJsonPath('data.shift.uuid', $shift->uuid);
    }
}
