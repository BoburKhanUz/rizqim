<?php

namespace Tests\Feature\PosSync;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-16 (D5 a): a card the till took offline keeps the card's last four digits when it sent four digits; anything else
 * is dropped, never a reason to refuse money already taken.
 */
class PosSyncCardLast4Test extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    public function test_the_last_four_digits_of_an_offline_card_payment_are_kept_when_they_are_four_digits(): void
    {
        $sale = $this->walkInSale();
        $sale['ops'][2]['payload']['tenders'] = [['tender' => 'card', 'amount' => '41000', 'card_type' => 'humo', 'transaction_id' => '000777', 'card_last4' => '9901']];
        $this->sync($sale['ops'])->assertOk()->assertJsonPath('data.results.2.status', 'applied');
        $this->assertSame('9901', Payment::sole()->card_last4);
    }

    public function test_a_malformed_last_four_is_dropped_and_the_payment_still_lands(): void
    {
        $sale = $this->walkInSale();
        $sale['ops'][2]['payload']['tenders'] = [['tender' => 'card', 'amount' => '41000', 'card_type' => 'humo', 'transaction_id' => '000778', 'card_last4' => '99x']];
        $this->sync($sale['ops'])->assertOk()->assertJsonPath('data.results.2.status', 'applied');
        $payment = Payment::sole();
        $this->assertNull($payment->card_last4);
        $this->assertSame('000778', $payment->transaction_id);
    }
}
