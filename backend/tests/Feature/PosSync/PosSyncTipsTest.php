<?php

namespace Tests\Feature\PosSync;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Models\PosTip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-22: a tip the till took offline with a tender (`tip_amount`) lands with the payment, whatever the module says
 * at sync (the money was taken), never in the payment; a tendered amount that does not cover the tip loses only the
 * change.
 */
class PosSyncTipsTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    public function test_an_offline_tip_lands_with_its_payment(): void
    {
        $sale = $this->walkInSale();
        $sale['ops'][0]['payload']['waiter_id'] = $this->waiter->id;
        $sale['ops'][2]['payload']['tenders'] = [['tender' => 'cash', 'amount' => '41000', 'tendered_amount' => '50000', 'tip_amount' => '4000']];
        $sale['ops'][2]['payload']['tip_staff_id'] = $this->waiter->id;
        $this->sync($sale['ops'])->assertOk()->assertJsonPath('data.results.2.status', 'applied');

        $payment = Payment::sole();
        $this->assertSame(['41000.00', '5000.00'], [$payment->amount, $payment->change_amount]);
        $tip = PosTip::sole();
        $this->assertSame(['4000.00', $payment->id, $this->waiter->id, 'cash'], [$tip->amount, $tip->payment_id, $tip->staff_id, $tip->method->value]);
    }

    /**
     * Review P6 wave 2 (S5): offline there is no manager's approval — a pick other than the order's waiter, or one
     * outside the tip roles, falls back to the order's waiter (the money was taken: never refused).
     */
    public function test_an_offline_tip_to_someone_else_or_outside_the_tip_roles_goes_to_the_orders_waiter(): void
    {
        $waiter2 = $this->makeStaff(UserRole::WAITER, $this->branch);
        foreach (['K1-001' => $waiter2, 'K1-002' => $this->cashier] as $number => $picked) {
            $sale = $this->walkInSale($number);
            $sale['ops'][0]['payload']['waiter_id'] = $this->waiter->id;
            $sale['ops'][2]['payload']['tenders'] = [['tender' => 'cash', 'amount' => '41000', 'tendered_amount' => '50000', 'tip_amount' => '4000']];
            $sale['ops'][2]['payload']['tip_staff_id'] = $picked->id;
            $this->sync($sale['ops'])->assertOk()->assertJsonPath('data.results.2.status', 'applied');
        }

        $this->assertSame([$this->waiter->id, $this->waiter->id], PosTip::orderBy('id')->pluck('staff_id')->map(fn ($id) => (int) $id)->all());
    }

    /**
     * Review P6 wave 2 (C10): a payment the till took offline is the sales of the waiter the till showed on the
     * order (`waiter_id` in bill.pay), even if the table was handed to someone else before it synced; the tip too.
     */
    public function test_an_offline_payment_keeps_the_waiter_the_till_saw(): void
    {
        $waiter2 = $this->makeStaff(UserRole::WAITER, $this->branch);
        $sale = $this->walkInSale();
        $sale['ops'][0]['payload']['waiter_id'] = $this->waiter->id;
        $sale['ops'][2]['payload']['tenders'] = [['tender' => 'cash', 'amount' => '41000', 'tendered_amount' => '50000', 'tip_amount' => '4000']];
        $sale['ops'][2]['payload']['waiter_id'] = $this->waiter->id;
        $this->sync(array_slice($sale['ops'], 0, 2))->assertOk();
        // Meanwhile on the server the table went to the second waiter
        Order::where('external_id', $sale['order'])->update(['waiter_id' => $waiter2->id]);

        $this->sync(array_slice($sale['ops'], 2))->assertOk()->assertJsonPath('data.results.0.status', 'applied');
        $this->assertSame($this->waiter->id, (int) OrderBill::sole()->waiter_id);
        $this->assertSame($this->waiter->id, (int) PosTip::sole()->staff_id);

        // Without the till's view (an older till) or with a stranger in it: the order's waiter now
        $other = $this->walkInSale('K1-002');
        $other['ops'][0]['payload']['waiter_id'] = $this->waiter->id;
        $other['ops'][2]['payload']['waiter_id'] = 999999;
        $this->sync(array_slice($other['ops'], 0, 2))->assertOk();
        Order::where('external_id', $other['order'])->update(['waiter_id' => $waiter2->id]);
        $this->sync(array_slice($other['ops'], 2))->assertOk();
        $this->assertSame($waiter2->id, (int) OrderBill::whereKeyNot(OrderBill::min('id'))->sole()->waiter_id);
    }

    public function test_a_tendered_amount_short_of_the_tip_keeps_the_money_without_the_change(): void
    {
        $sale = $this->walkInSale();
        $sale['ops'][2]['payload']['tenders'] = [['tender' => 'cash', 'amount' => '41000', 'tendered_amount' => '42000', 'tip_amount' => '3000']];
        $this->sync($sale['ops'])->assertOk()->assertJsonPath('data.results.2.status', 'applied');

        $this->assertNull(Payment::sole()->change_amount);
        $this->assertSame('3000.00', PosTip::sole()->amount);
    }
}
