<?php

namespace Tests\Feature\PosStaffPay;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Models\PosRefund;
use App\Models\StaffPaySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Owner decision 30.10.2026: a table handed to another waiter mid-meal. The bill carries the waiter at payment time
 * (order_bills.waiter_id); the branch's staff pay `sales_attribution` decides whose sales its bills are — `at_payment`
 * (default): bills paid before the handover stay with the first waiter; `current_waiter`: every bill goes to the last
 * one. A refund follows the person of the refunded bill. The setting is read and changed through the owner API,
 * validated and audited.
 */
class StaffPaySalesAttributionTest extends TestCase
{
    use RefreshDatabase;
    use StaffPayFixtures;

    protected User $waiter2;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-20 14:00:00', 'UTC')); // 19:00 in Tashkent
        $this->setUpStaffPay();
        $this->waiter2 = $this->makeStaff(UserRole::WAITER, $this->branch);
        // Every waiter: 10% of own sales and 50% of the service charge of own bills
        $this->as($this->owner, 'POST', '/owner/staff-pay/rules', ['branch_id' => $this->branch->id, 'role' => 'waiter', 'effective_from' => '2026-11-01', 'components' => [
            ['type' => 'sales_percent', 'percent' => '10'],
            ['type' => 'service_charge_percent', 'percent' => '50'],
        ]])->assertCreated();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Table 5 of the first waiter: 400 000 of dishes and a 10% service charge, split in two equal bills; the first is
     * paid, then the manager hands the table to waiter 2, then the second is paid.
     *
     * @return array{0: Order, 1: OrderBill, 2: OrderBill}
     */
    private function tableHandedOverMidMeal(): array
    {
        $this->openShift();
        $order = $this->posOrder([['Lag\'mon', '2', '300000'], ['Choy', '2', '100000']], [
            'waiter_id' => $this->waiter->id, 'service_charge_amount' => '40000', 'total_price' => '440000',
        ]);
        $order->forceFill(['service_charge_amount' => '40000', 'total_price' => '440000'])->save();

        $bills = $this->bill($order, 'equal', ['parts' => 2])->assertCreated()->json('data.bills');
        $this->assertCount(2, $bills);
        [$first, $second] = $bills;

        $this->pay($first['uuid'], [['tender' => 'cash', 'amount' => $first['total']]])->assertOk();
        $this->assertSame($this->waiter->id, OrderBill::where('uuid', $first['uuid'])->value('waiter_id'));

        $this->posAs($this->manager, 'POST', "/orders/{$order->uuid}/transfer", [
            'version' => (int) $order->refresh()->version, 'waiter_id' => $this->waiter2->id, 'reason' => 'Smena tugadi',
        ])->assertOk();

        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->pay($second['uuid'], [['tender' => 'card', 'amount' => $second['total'], 'card_type' => 'uzcard', 'transaction_id' => 'RRN-2']])->assertOk();

        return [$order->refresh(), OrderBill::where('uuid', $first['uuid'])->firstOrFail(), OrderBill::where('uuid', $second['uuid'])->firstOrFail()];
    }

    /**
     * @return array<int, array<string, mixed>> the statement's lines by user id
     */
    private function statementLines(): array
    {
        $statement = $this->as($this->owner, 'POST', '/owner/staff-pay/statements', ['branch_id' => $this->branch->id, 'date' => '2026-11-20'])
            ->assertCreated()->json('data');

        return collect($statement['lines'])->keyBy('user_id')->all();
    }

    private function attribution(string $value): void
    {
        $this->as($this->owner, 'PUT', '/owner/staff-pay/settings', ['branch_id' => $this->branch->id, 'sales_attribution' => $value])
            ->assertOk()->assertJsonPath('data.sales_attribution', $value);
    }

    public function test_the_bill_keeps_the_waiter_it_was_paid_under(): void
    {
        [$order, $first, $second] = $this->tableHandedOverMidMeal();

        $this->assertSame($this->waiter2->id, (int) $order->waiter_id);
        $this->assertSame('paid', $first->status->value);
        $this->assertSame('paid', $second->status->value);
        $this->assertSame($this->waiter->id, $first->waiter_id);
        $this->assertSame($this->waiter2->id, $second->waiter_id);
    }

    public function test_at_payment_by_default_the_bills_paid_before_the_handover_stay_with_the_first_waiter(): void
    {
        $this->tableHandedOverMidMeal();

        $lines = $this->statementLines();

        // Each bill: 200 000 of sales and 20 000 of service charge → 20 000 + 10 000
        foreach ([$this->waiter, $this->waiter2] as $person) {
            $line = $lines[$person->id] ?? $this->fail("No line for {$person->name}");
            $this->assertSame('200000.00', $line['metrics']['sales']);
            $this->assertSame('20000.00', $line['metrics']['service_charge']);
            $this->assertSame('30000.00', $line['gross']);
        }
    }

    public function test_current_waiter_moves_every_bill_to_the_last_waiter(): void
    {
        $this->tableHandedOverMidMeal();
        $this->attribution('current_waiter');

        $lines = $this->statementLines();

        $this->assertArrayNotHasKey($this->waiter->id, $lines);
        $this->assertSame('400000.00', $lines[$this->waiter2->id]['metrics']['sales']);
        $this->assertSame('40000.00', $lines[$this->waiter2->id]['metrics']['service_charge']);
        $this->assertSame('60000.00', $lines[$this->waiter2->id]['gross']);
        $this->assertSame(2, $lines[$this->waiter2->id]['metrics']['bills']);
    }

    public function test_a_refund_follows_the_person_of_the_refunded_bill(): void
    {
        [$order, $first] = $this->tableHandedOverMidMeal();
        // Half of the first bill (220 000) given back: 100 000 of sales and 10 000 of service charge
        PosRefund::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'order_id' => $order->id, 'bill_id' => $first->id,
            'payment_id' => Payment::where('bill_id', $first->id)->value('id'),
            'shift_id' => $first->shift_id, 'amount' => '110000', 'method' => 'cash', 'reason' => 'Mijoz qaytardi',
        ]);

        $lines = $this->statementLines();
        $this->assertSame('100000.00', $lines[$this->waiter->id]['metrics']['sales']);
        $this->assertSame('10000.00', $lines[$this->waiter->id]['metrics']['service_charge']);
        $this->assertSame('200000.00', $lines[$this->waiter2->id]['metrics']['sales']);

        $this->attribution('current_waiter');
        $this->as($this->owner, 'DELETE', '/owner/staff-pay/statements/' . \App\Models\StaffPayStatement::query()->value('uuid'))->assertOk();
        $lines = $this->statementLines();
        $this->assertSame('300000.00', $lines[$this->waiter2->id]['metrics']['sales']);
        $this->assertSame('30000.00', $lines[$this->waiter2->id]['metrics']['service_charge']);
    }

    public function test_the_setting_is_read_changed_validated_and_audited(): void
    {
        $page = $this->as($this->owner, 'GET', '/owner/staff-pay/rules?branch_id=' . $this->branch->id)->assertOk()
            ->assertJsonPath('data.sales_attribution', 'at_payment')
            ->assertJsonPath('data.sales_attribution_label', "To'lov paytidagi ofitsiant")
            ->json('data');
        $this->assertSame(['at_payment', 'current_waiter'], array_column($page['sales_attributions'], 'value'));

        $this->as($this->owner, 'PUT', '/owner/staff-pay/settings', ['branch_id' => $this->branch->id])->assertStatus(422);
        $this->as($this->owner, 'PUT', '/owner/staff-pay/settings', ['branch_id' => $this->branch->id, 'sales_attribution' => 'first_waiter'])->assertStatus(422);
        $this->as($this->manager, 'PUT', '/owner/staff-pay/settings', ['branch_id' => $this->branch->id, 'sales_attribution' => 'current_waiter'])->assertForbidden();

        $this->as($this->owner, 'PUT', '/owner/staff-pay/settings', ['branch_id' => $this->branch->id, 'sales_attribution' => 'current_waiter'])
            ->assertOk()
            ->assertJsonPath('data.sales_attribution', 'current_waiter')
            ->assertJsonPath('data.sales_attribution_label', 'Stolning oxirgi ofitsianti')
            ->assertJsonPath('data.period', 'month');
        // The period alone keeps the attribution
        $this->as($this->owner, 'PUT', '/owner/staff-pay/settings', ['branch_id' => $this->branch->id, 'period' => 'week'])
            ->assertOk()->assertJsonPath('data.sales_attribution', 'current_waiter')->assertJsonPath('data.period', 'week');

        $setting = StaffPaySetting::where('branch_id', $this->branch->id)->firstOrFail();
        $this->assertSame(['week', 'current_waiter'], [$setting->period, $setting->sales_attribution]);
        $log = AuditLog::where('action', 'staff_pay.settings_changed')->orderBy('id')->firstOrFail();
        $this->assertSame('at_payment', $log->before['sales_attribution'] ?? null);
        $this->assertSame('current_waiter', $log->after['sales_attribution'] ?? null);
    }
}
