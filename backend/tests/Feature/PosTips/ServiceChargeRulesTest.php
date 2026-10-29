<?php

namespace Tests\Feature\PosTips;

use App\Domain\Pos\Billing\ServiceChargeRules;
use App\Domain\Pos\Orders\PosChargesCalculator;
use App\Models\Order;
use App\Models\ServiceFeeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-22 (C1): service charge variants — the percent by order type (hall, table service, takeaway), the large-group
 * rule, the restaurant cover per guest as the place fee line, the bill's notice (worked out or the owner's) — set by
 * the owner with the `service_charge_rules` module, applied by PosChargesCalculator; a stolovaya stays at 0%.
 */
class ServiceChargeRulesTest extends TestCase
{
    use PosTipsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTips('kafe');
        ServiceFeeSetting::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'service_charge_percent' => '10.00']);
    }

    private function rules(array $data): \Illuminate\Testing\TestResponse
    {
        return $this->owner('PUT', '/owner/pos/service-charge-rules', ['branch_id' => $this->branch->id] + $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function charges(array $attributes, array $lines = [['Osh', '1', '100000']]): array
    {
        $order = $this->posOrder($lines, $attributes);

        return app(PosChargesCalculator::class)->recalculate(Order::findOrFail($order->id))->toArray();
    }

    public function test_the_percent_by_order_type_and_the_large_group_rule(): void
    {
        $this->rules(['by_order_type' => ['hall' => '15', 'table_service' => '12.5', 'takeaway' => '0'], 'large_group_min_guests' => 8, 'large_group_percent' => '18'])
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.rows.0.rules.by_order_type', ['hall' => '15.00', 'table_service' => '12.50', 'takeaway' => '0.00'])
            ->assertJsonPath('data.rows.0.notice_preview', "Xizmat haqi: zalda 15%, stolga olib kelishda 12,5%. 8 va undan ko'p mehmonli stolga xizmat haqi 18%.");

        $hall = $this->charges(['guests_count' => 4]);
        $this->assertSame(['15.00', '15000.00', 'order_type', '115000.00'], [$hall['service_charge_percent'], $hall['service_charge_amount'], $hall['service_charge_rule'], $hall['total']]);

        $group = $this->charges(['guests_count' => 8]);
        $this->assertSame(['18.00', '18000.00', 'large_group'], [$group['service_charge_percent'], $group['service_charge_amount'], $group['service_charge_rule']]);

        $plate = $this->charges(['channel' => 'pos_takeaway', 'order_type' => 'pickup', 'table_label' => 'A7', 'guests_count' => 2]);
        $this->assertSame(['12.50', '12500.00', 'order_type'], [$plate['service_charge_percent'], $plate['service_charge_amount'], $plate['service_charge_rule']]);

        // Takeaway takes none, and the large-group rule is for eat-in orders only
        $takeaway = $this->charges(['channel' => 'pos_takeaway', 'order_type' => 'pickup', 'guests_count' => 10]);
        $this->assertSame(['0.00', '0.00', 'none'], [$takeaway['service_charge_percent'], $takeaway['service_charge_amount'], $takeaway['service_charge_rule']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.service_charge_rules_changed']);
    }

    public function test_the_cover_per_guest_and_the_notice_on_the_bill(): void
    {
        $this->rules(['cover_amount' => '5000'])->assertOk()
            ->assertJsonPath('data.rows.0.notice_preview', "Xizmat haqi 10%. Kuver: 5 000 so'm har bir mehmon uchun.");

        $charges = $this->charges(['guests_count' => 3]);
        $this->assertSame(['15000.00', 'cover', '10000.00', 'base', '125000.00'], [$charges['place_fee_amount'], $charges['place_fee_kind'], $charges['service_charge_amount'], $charges['service_charge_rule'], $charges['total']]);

        // The bill carries the notice; the owner's own text replaces the worked-out one
        $this->openShift();
        $order = $this->posOrder([['Osh', '1', '100000']], ['guests_count' => 2]);
        app(PosChargesCalculator::class)->recalculate($order);
        $this->bill($order->refresh())->assertCreated()
            ->assertJsonPath('data.bills.0.place_fee_amount', '10000.00')
            ->assertJsonPath('data.bills.0.service_charge_notice', "Xizmat haqi 10%. Kuver: 5 000 so'm har bir mehmon uchun.");

        $this->rules(['cover_amount' => '5000', 'notice' => "Xizmat haqi 10% va kuver hisobga qo'shiladi."])->assertOk()->assertJsonPath('data.rows.0.notice', "Xizmat haqi 10% va kuver hisobga qo'shiladi.");
        $this->assertSame("Xizmat haqi 10% va kuver hisobga qo'shiladi.", $this->charges(['guests_count' => 1])['service_charge_notice']);

        // Takeaway: no cover
        $this->assertSame(['0.00', 'place_fee'], array_values(array_intersect_key($this->charges(['channel' => 'pos_takeaway', 'order_type' => 'pickup', 'guests_count' => 3]), array_flip(['place_fee_amount', 'place_fee_kind']))));
    }

    public function test_without_the_module_the_rules_neither_change_nor_apply(): void
    {
        $this->rules(['by_order_type' => ['hall' => '20']])->assertOk();
        $this->profile($this->branch, 'kafe', ['service_charge_rules' => false]);

        $this->rules(['by_order_type' => ['hall' => '25']])->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $charges = $this->charges(['guests_count' => 2]);
        $this->assertSame(['10.00', '10000.00', 'base'], [$charges['service_charge_percent'], $charges['service_charge_amount'], $charges['service_charge_rule']]);
        $this->owner('GET', "/owner/pos/service-charge-rules?branch_id={$this->branch->id}")->assertOk()->assertJsonPath('data.enabled', false);
    }

    public function test_a_stolovaya_stays_at_zero_whatever_the_rules_say(): void
    {
        $this->rules(['by_order_type' => ['hall' => '15'], 'large_group_min_guests' => 6, 'large_group_percent' => '20'])->assertOk();
        $this->profile($this->branch, 'stolovaya', ['service_charge_rules' => true]);

        $charges = $this->charges(['guests_count' => 10]);
        $this->assertSame(['0.00', '0.00', 'none', null], [$charges['service_charge_percent'], $charges['service_charge_amount'], $charges['service_charge_rule'], $charges['service_charge_notice']]);
    }

    public function test_validation_and_the_rules_value_object(): void
    {
        $this->rules(['by_order_type' => ['vip' => '10']])->assertStatus(422)->assertJsonValidationErrors('by_order_type');
        $this->rules(['by_order_type' => ['hall' => '31']])->assertStatus(422)->assertJsonValidationErrors('by_order_type.hall');
        $this->rules(['large_group_min_guests' => 8])->assertStatus(422)->assertJsonValidationErrors('large_group_percent');
        $this->rules(['hall_id' => 999999])->assertStatus(422)->assertJsonValidationErrors('hall_id');

        // A stored value that no longer fits reads as no rule
        $rules = ServiceChargeRules::fromArray(['by_order_type' => ['hall' => '99', 'takeaway' => 'x', 'table_service' => '5'], 'large_group_min_guests' => 1, 'large_group_percent' => '10', 'cover_amount' => '-3']);
        $this->assertSame(['by_order_type' => ['table_service' => '5.00'], 'large_group_min_guests' => null, 'large_group_percent' => null, 'cover_amount' => null], $rules->toArray());
        $this->assertSame('12,5%', ServiceChargeRules::percentText('12.50'));

        // The service fee list shows the rules too
        $this->rules(['cover_amount' => '3000'])->assertOk();
        $this->owner('GET', '/owner/service-fees')->assertOk()->assertJsonPath('data.settings.0.service_charge_rules.cover_amount', '3000.00');
    }
}
