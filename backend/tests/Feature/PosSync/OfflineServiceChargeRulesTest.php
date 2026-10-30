<?php

namespace Tests\Feature\PosSync;

use App\Domain\Pos\Orders\PosChargesCalculator;
use App\Models\Order;
use App\Models\ServiceFeeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\PosTips\PosTipsFixtures;
use Tests\TestCase;

/**
 * P6-22 (C1) for the limited mode: the bootstrap's `offline` section carries each fee row's service charge rules, the
 * bill notice and the stolovaya lock, so the till prices an offline order as PosChargesCalculator does. The fixed
 * cases here are the ones pos_data's offline pricing parity tests (offline_pricing_rules_test.dart) mirror.
 */
class OfflineServiceChargeRulesTest extends TestCase
{
    use PosTipsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTips('kafe');
        ServiceFeeSetting::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'service_charge_percent' => '10.00']);
    }

    /**
     * @return array<string, mixed>
     */
    private function charges(array $attributes, array $lines = [['Osh', '1', '100000']]): array
    {
        $order = $this->posOrder($lines, $attributes);

        return app(PosChargesCalculator::class)->recalculate(Order::findOrFail($order->id))->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function branchFee(string $channel): array
    {
        $fees = $this->pos('GET', '/bootstrap')->assertOk()->json('data.offline.fees');
        foreach ($fees as $fee) {
            if ($fee['hall_id'] === null && $fee['channel'] === $channel) {
                return $fee;
            }
        }
        $this->fail("No branch fee row for {$channel}");
    }

    public function test_the_rules_and_the_notice_reach_the_till_and_price_as_the_server(): void
    {
        $this->owner('PUT', '/owner/pos/service-charge-rules', ['branch_id' => $this->branch->id, 'by_order_type' => ['hall' => '15', 'table_service' => '12.5', 'takeaway' => '0'], 'large_group_min_guests' => 8, 'large_group_percent' => '18', 'cover_amount' => '5000'])->assertOk();

        $hall = $this->branchFee('pos_hall');
        $this->assertSame('10.00', $hall['service_charge_percent']);
        $this->assertSame(['by_order_type' => ['hall' => '15.00', 'table_service' => '12.50', 'takeaway' => '0.00'], 'large_group_min_guests' => 8, 'large_group_percent' => '18.00', 'cover_amount' => '5000.00'], $hall['rules']);
        $this->assertSame("Xizmat haqi: zalda 15%, stolga olib kelishda 12,5%. 8 va undan ko'p mehmonli stolga xizmat haqi 18%. Kuver: 5 000 so'm har bir mehmon uchun.", $hall['service_charge_notice']);
        $this->assertSame('0.00', $this->branchFee('pos_takeaway')['service_charge_percent']);
        $this->assertFalse($this->pos('GET', '/bootstrap')->json('data.offline.service_charge_locked'));

        // The fixed cases of the Dart parity test: 100 000 of food
        $case = fn (array $a) => array_values(array_intersect_key($this->charges($a), array_flip(['service_charge_percent', 'service_charge_amount', 'place_fee_amount', 'total', 'service_charge_rule', 'place_fee_kind'])));
        $this->assertSame(['15.00', '15000.00', '20000.00', '135000.00', 'order_type', 'cover'], $case(['guests_count' => 4]));
        $this->assertSame(['18.00', '18000.00', '40000.00', '158000.00', 'large_group', 'cover'], $case(['guests_count' => 8]));
        $this->assertSame(['12.50', '12500.00', '10000.00', '122500.00', 'order_type', 'cover'], $case(['channel' => 'pos_takeaway', 'order_type' => 'pickup', 'table_label' => 'A7', 'guests_count' => 2]));
        $this->assertSame(['0.00', '0.00', '0.00', '100000.00', 'none', 'place_fee'], $case(['channel' => 'pos_takeaway', 'order_type' => 'pickup', 'guests_count' => 10]));
    }

    public function test_without_the_module_no_rules_and_a_stolovaya_is_locked(): void
    {
        $this->owner('PUT', '/owner/pos/service-charge-rules', ['branch_id' => $this->branch->id, 'by_order_type' => ['hall' => '20'], 'cover_amount' => '3000'])->assertOk();

        $this->profile($this->branch, 'kafe', ['service_charge_rules' => false]);
        $fee = $this->branchFee('pos_hall');
        $this->assertNull($fee['rules']);
        // Review C8: without the rules only the owner's own text is a notice
        $this->assertNull($fee['service_charge_notice']);

        // Stolovaya: the row reads 0%, the rules come with the lock flag (the till keeps every percent at 0)
        $this->profile($this->branch, 'stolovaya', ['service_charge_rules' => true]);
        $fee = $this->branchFee('pos_hall');
        $this->assertSame('0.00', $fee['service_charge_percent']);
        $this->assertSame('20.00', $fee['rules']['by_order_type']['hall']);
        $this->assertTrue($this->pos('GET', '/bootstrap')->json('data.offline.service_charge_locked'));
        $this->assertSame(['0.00', '0.00', '6000.00'], array_values(array_intersect_key($this->charges(['guests_count' => 2]), array_flip(['service_charge_percent', 'service_charge_amount', 'place_fee_amount']))));
    }
}
