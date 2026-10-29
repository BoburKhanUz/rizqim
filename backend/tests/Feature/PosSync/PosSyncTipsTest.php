<?php

namespace Tests\Feature\PosSync;

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
        $sale['ops'][2]['payload']['tenders'] = [['tender' => 'cash', 'amount' => '41000', 'tendered_amount' => '50000', 'tip_amount' => '4000']];
        $sale['ops'][2]['payload']['tip_staff_id'] = $this->waiter->id;
        $this->sync($sale['ops'])->assertOk()->assertJsonPath('data.results.2.status', 'applied');

        $payment = Payment::sole();
        $this->assertSame(['41000.00', '5000.00'], [$payment->amount, $payment->change_amount]);
        $tip = PosTip::sole();
        $this->assertSame(['4000.00', $payment->id, $this->waiter->id, 'cash'], [$tip->amount, $tip->payment_id, $tip->staff_id, $tip->method->value]);
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
