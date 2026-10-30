<?php

namespace Tests\Feature\PosTips;

use App\Domain\Pos\Orders\PosChargesCalculator;
use App\Domain\Pos\Orders\PosOrderService;
use App\Models\Order;
use App\Models\ServiceFeeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6 wave 2 review C4 and C8: the table plate changes the order type the service charge rules speak of, so setting
 * or clearing it recalculates the charges; the bill notice is the rules' (the `service_charge_rules` module on) or the
 * owner's own text, never a sentence made up for a branch without the rules (choyxona prints no notice line).
 */
class ServiceChargeOrderTypeFollowUpTest extends TestCase
{
    use PosTipsFixtures;
    use RefreshDatabase;

    public function test_setting_and_clearing_the_table_plate_recalculates_the_service_charge(): void
    {
        $this->setUpTips('kafe');
        ServiceFeeSetting::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'service_charge_percent' => '10.00', 'service_charge_channels' => ['pos_hall', 'pos_takeaway']]);
        $this->owner('PUT', '/owner/pos/service-charge-rules', ['branch_id' => $this->branch->id, 'by_order_type' => ['takeaway' => '0', 'table_service' => '12.5']])->assertOk();

        $order = $this->posOrder([['Osh', '1', '100000']], ['channel' => 'pos_takeaway', 'order_type' => 'pickup', 'guests_count' => 2]);
        app(PosChargesCalculator::class)->recalculate($order);
        $order->refresh();
        $this->assertSame(['0.00', '100000.00'], [(string) $order->service_charge_amount, (string) $order->total_price]);

        // Carried to table A7: table service, 12.5%
        $order = app(PosOrderService::class)->setTableLabel($order, (int) $order->version, $this->cashier, 'A7');
        $order = Order::findOrFail($order->id);
        $this->assertSame(['12500.00', '112500.00'], [(string) $order->service_charge_amount, (string) $order->total_price]);

        $this->openShift();
        $this->bill($order)->assertCreated()->assertJsonPath('data.bills.0.total', '112500.00')->assertJsonPath('data.bills.0.service_charge_amount', '12500.00');

        // A plate cleared: takeaway again, the charge goes (on an order with no bill yet)
        $other = $this->posOrder([['Osh', '1', '100000']], ['channel' => 'pos_takeaway', 'order_type' => 'pickup', 'guests_count' => 2, 'table_label' => 'B2']);
        app(PosChargesCalculator::class)->recalculate($other);
        $this->assertSame('12500.00', (string) $other->refresh()->service_charge_amount);
        $other = app(PosOrderService::class)->setTableLabel($other, (int) $other->version, $this->cashier, null);
        $other = Order::findOrFail($other->id);
        $this->assertSame(['0.00', '100000.00'], [(string) $other->service_charge_amount, (string) $other->total_price]);
    }

    public function test_a_choyxona_bill_has_no_made_up_notice_but_the_owners_own_text_shows(): void
    {
        $this->setUpTips('choyxona');
        $fee = ServiceFeeSetting::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'service_charge_percent' => '10.00']);
        $this->openShift();

        $order = $this->posOrder([['Osh', '1', '100000']]);
        $charges = app(PosChargesCalculator::class)->recalculate(Order::findOrFail($order->id))->toArray();
        $this->assertSame(['10000.00', null], [$charges['service_charge_amount'], $charges['service_charge_notice']]);
        $this->bill($order)->assertCreated()->assertJsonPath('data.bills.0.service_charge_amount', '10000.00')
            ->assertJsonPath('data.bills.0.service_charge_notice', null);
        foreach ($this->pos('GET', '/bootstrap')->assertOk()->json('data.offline.fees') as $row) {
            $this->assertNull($row['service_charge_notice']);
        }

        // The owner's own text is printed whatever the module
        $fee->forceFill(['service_charge_notice' => "Xizmat haqi 10% hisobga qo'shiladi."])->save();
        $second = $this->posOrder([['Osh', '1', '100000']]);
        app(PosChargesCalculator::class)->recalculate(Order::findOrFail($second->id));
        $this->bill($second)->assertCreated()->assertJsonPath('data.bills.0.service_charge_notice', "Xizmat haqi 10% hisobga qo'shiladi.");
        $this->assertSame("Xizmat haqi 10% hisobga qo'shiladi.", $this->pos('GET', '/bootstrap')->json('data.offline.fees.0.service_charge_notice'));
    }
}
