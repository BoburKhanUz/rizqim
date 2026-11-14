<?php

namespace Tests\Feature\PosBanquetOps;

use App\Domain\Pos\Banquet\Ops\OshCalculator;
use App\Enums\UserRole;
use App\Models\BanquetOpsSetting;
use App\Models\Branch;
use Brick\Math\BigDecimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-54 (E4): the production sheet (package items × the planned guests with the reserve, portions and kg by station),
 * the osh calculator's ratios and kazans, the shopping list summed over a date range, no customer data, the module
 * gate, the kitchen's access, the owner's settings and tenant isolation.
 */
class BanquetProductionTest extends TestCase
{
    use PosBanquetOpsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBanquetOps();
        BanquetOpsSetting::where('store_id', $this->store->id)->update(['reserve_percent' => '10', 'portion_weights' => json_encode([['product_id' => $this->osh->id, 'grams' => 350]])]);
    }

    public function test_the_sheet_counts_the_package_items_for_the_planned_guests_with_the_reserve(): void
    {
        $event = $this->pricedEvent(200, 150);

        $sheet = $this->pos('GET', "/banquets/{$event->uuid}/production-sheet")->assertOk()->json('data');

        $this->assertSame(['guaranteed' => 200, 'minimum' => 150, 'actual' => null, 'base' => 200, 'reserve_percent' => '10.00', 'planned' => 220], $sheet['guests']);
        $this->assertSame(['Osh sexi', 'Umumiy'], array_column($sheet['stations'], 'name'));
        $this->assertSame([['product_id' => $this->osh->id, 'name' => "To'y oshi", 'unit' => 'portion', 'quantity_per_person' => '1.000', 'quantity' => '220.000', 'kg' => '77.000']], $sheet['stations'][0]['dishes']);
        $this->assertSame([
            ['product_id' => $this->salad->id, 'name' => 'Achchiq-chuchuk', 'unit' => 'kg', 'quantity_per_person' => '0.200', 'quantity' => '44.000', 'kg' => '44.000'],
            ['product_id' => null, 'name' => 'Non', 'unit' => 'piece', 'quantity_per_person' => '1.000', 'quantity' => '220.000', 'kg' => null],
        ], $sheet['stations'][1]['dishes']);

        // The osh item: 220 portions, 5 persons per kg → 44 kg; one 50 kg kazan
        $this->assertSame('44.0', $sheet['osh']['osh_kg']);
        $this->assertSame(['44.000', '44.000', '11.000'], array_column($sheet['osh']['ingredients'], 'quantity'));
        $this->assertSame([['size_kg' => 50, 'count' => 1]], $sheet['osh']['kazans']);
        $this->assertSame("Aziz va Malika to'yi", $sheet['event']['title']);
        $this->assertSame(['Oq zal'], $sheet['event']['halls']);
        $this->assertSame('18:00–23:00', $sheet['event']['local_time']);
        // The kitchen's paper: no customer
        $this->assertStringNotContainsString('Karimov', (string) json_encode($sheet));
        $this->assertStringNotContainsString('998901234567', (string) json_encode($sheet));

        // More guests came than guaranteed: the kitchen cooks for them
        $event->forceFill(['guests_actual' => 260])->save();
        $this->assertSame(286, $this->pos('GET', "/banquets/{$event->uuid}/production-sheet")->json('data.guests.planned'));
    }

    public function test_the_frozen_snapshot_keeps_the_items_after_the_package_changes(): void
    {
        $event = $this->pricedEvent(100, 100);
        $this->package->items()->delete();

        $sheet = $this->pos('GET', "/banquets/{$event->uuid}/production-sheet")->assertOk()->json('data');
        $this->assertCount(3, array_merge(...array_column($sheet['stations'], 'dishes')));
    }

    public function test_osh_calculator_ratios_and_kazans(): void
    {
        $settings = OshCalculator::normalize(['persons_per_kg' => '5.5', 'ingredients' => [
            ['key' => 'rice', 'name' => 'Guruch', 'unit' => 'kg', 'per_kg' => '1'],
            ['key' => 'carrot', 'name' => 'Sabzi', 'unit' => 'kg', 'per_kg' => '1.2'],
        ], 'kazans' => [50, 10, 20]]);
        $this->assertSame([10, 20, 50], $settings['kazans']);

        $this->assertSame('2.0', OshCalculator::calculate(11, $settings)['osh_kg']);
        $result = OshCalculator::calculate(12, $settings);
        $this->assertSame('2.5', $result['osh_kg']); // 12 / 5.5 = 2.18 → up to the next half kg
        $this->assertSame(['2.500', '3.000'], array_column($result['ingredients'], 'quantity'));
        $this->assertSame([['size_kg' => 10, 'count' => 1]], $result['kazans']);

        // 715 persons → 130 kg: two 50 kg kazans and the 30 kg left in a third
        $big = OshCalculator::calculate(715, $settings);
        $this->assertSame('130.0', $big['osh_kg']);
        $this->assertSame([['size_kg' => 50, 'count' => 3]], $big['kazans']);
        $this->assertSame('150.0', $big['kazan_capacity_kg']);
        $this->assertSame([['size_kg' => 50, 'count' => 1], ['size_kg' => 20, 'count' => 1]], OshCalculator::kazans(BigDecimal::of('70'), [10, 20, 50]));

        // The defaults: 1 kg rice, meat and carrot per kg of osh, 5 persons per kg
        $defaults = OshCalculator::calculate(100, OshCalculator::normalize(null));
        $this->assertSame('20.0', $defaults['osh_kg']);
        $this->assertSame(['20.000', '20.000', '20.000'], array_slice(array_column($defaults['ingredients'], 'quantity'), 0, 3));

        $this->pos('GET', '/banquets/osh-calculator?persons=220')->assertOk()->assertJsonPath('data.osh_kg', '44.0')->assertJsonPath('data.ingredients.2.quantity', '11.000');
        $this->pos('GET', '/banquets/osh-calculator?persons=0')->assertUnprocessable();
    }

    public function test_an_osh_event_without_an_osh_item_runs_the_calculator_for_the_guests(): void
    {
        BanquetOpsSetting::where('store_id', $this->store->id)->update(['osh' => json_encode(['persons_per_kg' => '5', 'product_ids' => [], 'kazans' => [20]])]);
        $event = $this->pricedEvent(100, 0, '2027-01-20', ['event_type' => 'osh', 'slot' => 'morning']);

        $osh = $this->pos('GET', "/banquets/{$event->uuid}/production-sheet")->assertOk()->json('data.osh');
        $this->assertSame(110, $osh['persons']);
        $this->assertSame('22.0', $osh['osh_kg']);
        $this->assertSame([['size_kg' => 20, 'count' => 2]], $osh['kazans']);
    }

    public function test_the_shopping_list_sums_the_events_of_the_dates(): void
    {
        $this->pricedEvent(200, 150, '2027-01-20');
        $this->pricedEvent(100, 100, '2027-01-21');
        $this->pricedEvent(300, 300, '2027-01-21', ['status' => 'cancelled']);
        $this->pricedEvent(500, 500, '2027-01-25');

        $list = $this->pos('GET', '/banquets/shopping-list?from=2027-01-20&to=2027-01-22')->assertOk()->json('data');

        $this->assertSame([220, 110], array_column($list['events'], 'planned_guests'));
        $byName = array_column($list['dishes'], null, 'name');
        $this->assertSame('330.000', $byName["To'y oshi"]['quantity']);
        $this->assertSame('115.500', $byName["To'y oshi"]['kg']);
        $this->assertSame('66.000', $byName['Achchiq-chuchuk']['quantity']);
        $this->assertSame('330.000', $byName['Non']['quantity']);
        // Osh: 44 kg + 22 kg
        $this->assertSame(330, $list['osh']['persons']);
        $this->assertSame('66.0', $list['osh']['osh_kg']);
        $this->assertSame(['66.000', '66.000', '16.500'], array_column($list['osh']['ingredients'], 'quantity'));
        $this->assertStringNotContainsString('Karimov', (string) json_encode($list));

        $this->pos('GET', '/banquets/shopping-list?from=2027-01-20&to=2027-04-20')->assertUnprocessable();

        $export = $this->pos('GET', '/banquets/shopping-list/export?from=2027-01-20&to=2027-01-22')->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $export->headers->get('Content-Type'));
        $this->pos('GET', '/banquets/' . $list['events'][0]['uuid'] . '/production-sheet/export')->assertOk();
    }

    public function test_access_module_gate_and_tenant_isolation(): void
    {
        $event = $this->pricedEvent();

        // The cook reads the kitchen's papers; a waiter does not
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $this->posAs($cook, 'GET', "/banquets/{$event->uuid}/production-sheet")->assertOk();
        $this->posAs($this->waiter, 'GET', "/banquets/{$event->uuid}/production-sheet")->assertForbidden();

        // Another store's event: 404
        $foreignStore = $this->makeStore('Boshqa');
        $foreignBranch = $this->makeBranch($foreignStore, 'Boshqa filial');
        $foreignEvent = $this->pricedEvent(100, 100, '2027-01-20', [], $foreignBranch, false);
        $this->posAs($this->manager, 'GET', "/banquets/{$foreignEvent->uuid}/production-sheet")->assertNotFound();
        $this->posAs($this->manager, 'GET', "/banquets/{$foreignEvent->uuid}/contract")->assertNotFound();

        // The module off: the papers are a new flow
        $settings = is_array($this->branch->fresh()->pos_settings) ? $this->branch->fresh()->pos_settings : [];
        $settings['modules'] = ['banquet' => false];
        Branch::whereKey($this->branch->id)->update(['pos_settings' => json_encode($settings)]);
        app()->forgetScopedInstances();
        $this->pos('GET', "/banquets/{$event->uuid}/production-sheet")->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
    }

    public function test_the_owner_edits_the_settings_with_the_version_and_an_audit(): void
    {
        $settings = $this->panel($this->owner, 'GET', '/owner/pos/banquets/ops-settings')->assertOk()->json('data');
        $this->assertSame('10.00', $settings['reserve_percent']);
        $this->assertTrue($settings['contract_template_is_default']);

        $saved = $this->panel($this->owner, 'PUT', '/owner/pos/banquets/ops-settings', [
            'version' => $settings['version'],
            'reserve_percent' => '5',
            'osh' => ['persons_per_kg' => '6', 'ingredients' => [['key' => 'rice', 'name' => 'Guruch', 'unit' => 'kg', 'per_kg' => '1']], 'kazans' => [30], 'product_ids' => [$this->osh->id, 999999]],
            'portion_weights' => [['product_id' => $this->salad->id, 'grams' => 150]],
            'contract_template' => 'Shartnoma {{contract_no}}: {{customer_name}}',
        ])->assertOk()->json('data');
        $this->assertSame('5.00', $saved['reserve_percent']);
        $this->assertSame('6.0', $saved['osh']['persons_per_kg']);
        $this->assertSame([$this->osh->id], $saved['osh']['product_ids']);
        $this->assertSame([['product_id' => $this->salad->id, 'grams' => 150]], $saved['portion_weights']);
        $this->assertFalse($saved['contract_template_is_default']);
        $this->assertSame($settings['version'] + 1, $saved['version']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'banquet.ops_settings_updated']);

        // An old version: 409
        $this->panel($this->owner, 'PUT', '/owner/pos/banquets/ops-settings', ['version' => $settings['version'], 'reserve_percent' => '1'])->assertConflict()->assertJsonPath('data.reason', 'version_conflict');
        $this->panel($this->owner, 'PUT', '/owner/pos/banquets/ops-settings', ['package_mxik' => '123'])->assertUnprocessable();
        // A PIN session (the till) does not reach the owner's settings
        $this->withBearer($this->managerToken)->getJson('/api/v1/owner/pos/banquets/ops-settings')->assertForbidden();
    }
}
