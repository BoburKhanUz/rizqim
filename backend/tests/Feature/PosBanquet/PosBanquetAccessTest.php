<?php

namespace Tests\Feature\PosBanquet;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\BanquetEvent;
use App\Models\BanquetExtra;
use App\Models\BanquetPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-51/P6-52: who may do what (banquets.manage writes, the cashier reads), the `banquet` module gate on new flows
 * only, tenant and branch isolation, the owner's setup (packages, extras, hall rent, settings) audited, and the
 * bootstrap section.
 */
class PosBanquetAccessTest extends TestCase
{
    use RefreshDatabase;
    use PosBanquetFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBanquets();
    }

    public function test_the_cashier_reads_but_does_not_change_events(): void
    {
        $uuid = $this->held();

        $this->api($this->cashier, 'GET', "/api/v1/pos/banquets/{$uuid}")->assertOk()->assertJsonPath('data.uuid', $uuid);
        $this->api($this->cashier, 'GET', '/api/v1/pos/banquets/calendar?from=2027-03-01&to=2027-03-31')->assertOk();
        $this->hold([], $this->cashier)->assertForbidden();
        $this->api($this->cashier, 'PATCH', "/api/v1/pos/banquets/{$uuid}", ['guests_guaranteed' => 10])->assertForbidden();
        $this->api($this->cashier, 'POST', "/api/v1/pos/banquets/{$uuid}/freeze")->assertForbidden();

        $waiter = $this->makeStaff(UserRole::WAITER, $this->branch);
        $this->api($waiter, 'GET', "/api/v1/pos/banquets/{$uuid}")->assertForbidden();
    }

    public function test_a_pin_session_at_the_till_works_on_its_branch(): void
    {
        $session = $this->terminalSession($this->manager, $this->branch);
        $uuid = (string) $this->apiToken($session['token'], 'POST', '/api/v1/pos/banquets', $this->eventPayload(['branch_id' => null]))
            ->assertCreated()->json('data.uuid');
        $this->assertSame($this->branch->id, (int) BanquetEvent::where('uuid', $uuid)->value('branch_id'));

        // The owner's setup is the web panel's, never a terminal session's
        $this->apiToken($session['token'], 'GET', '/api/v1/owner/pos/banquets/packages')->assertForbidden();
    }

    public function test_the_module_gates_new_holds_and_setup_only(): void
    {
        $uuid = $this->held();
        $this->banquetModule(false);

        $this->hold(['event_date' => '2027-03-21'])->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $this->api($this->owner, 'POST', '/api/v1/owner/pos/banquets/packages', ['name' => 'X', 'per_person_price' => '1000'])
            ->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        // The existing event goes on
        $this->api($this->manager, 'GET', "/api/v1/pos/banquets/{$uuid}")->assertOk();
        $this->api($this->manager, 'PATCH', "/api/v1/pos/banquets/{$uuid}", ['guests_guaranteed' => 210])->assertOk();
        $this->api($this->owner, 'GET', '/api/v1/owner/pos/banquets/packages')->assertOk();
    }

    public function test_the_toyxona_profile_has_the_module_by_default(): void
    {
        $this->banquetModule(null);
        $this->hold()->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');

        $this->branch->forceFill(['pos_settings' => ['profile' => 'toyxona']])->save();
        app()->forgetScopedInstances();
        $this->hold()->assertCreated();
    }

    public function test_another_store_and_another_branch_do_not_see_the_event(): void
    {
        $uuid = $this->held();

        $this->api($this->foreignOwner, 'GET', "/api/v1/pos/banquets/{$uuid}")->assertNotFound();
        $this->api($this->foreignOwner, 'PATCH', "/api/v1/pos/banquets/{$uuid}", ['guests_guaranteed' => 1])->assertNotFound();
        $otherManager = $this->makeStaff(UserRole::MANAGER, $this->otherBranch);
        $this->api($otherManager, 'GET', "/api/v1/pos/banquets/{$uuid}")->assertNotFound();

        // Another store's package and hall
        $foreignPackage = BanquetPackage::create(['store_id' => $this->foreignStore->id, 'name' => 'Begona', 'per_person_price' => '1']);
        $this->hold(['event_date' => '2027-03-22', 'banquet_package_id' => $foreignPackage->id])->assertStatus(422)->assertJsonPath('data.reason', 'package_not_found');
        $foreignHall = $this->makeHall($this->foreignBranch, 'Begona zal');
        $this->hold(['event_date' => '2027-03-22', 'hall_ids' => [$foreignHall]])->assertStatus(422)->assertJsonPath('data.reason', 'hall_not_found');
        $this->banquetModule(true, $this->foreignBranch);
        $this->api($this->foreignOwner, 'PATCH', "/api/v1/owner/pos/banquets/packages/{$this->package->id}", ['name' => 'X'])->assertNotFound();
        $this->api($this->foreignOwner, 'PUT', "/api/v1/owner/pos/banquets/halls/{$this->hallA}/rates", ['rates' => ['evening' => '1']])->assertNotFound();
    }

    public function test_the_owner_sets_up_packages_extras_rent_and_settings_audited(): void
    {
        $package = $this->api($this->owner, 'POST', '/api/v1/owner/pos/banquets/packages', [
            'name' => 'Premium',
            'per_person_price' => '250000',
            'minimum_guests' => 150,
            'included' => ['Osh', ' ', 'Shashlik'],
            'items' => [
                ['product_variant_id' => $this->variant->id, 'quantity_per_person' => '0.25', 'unit' => 'kg'],
                ['name' => 'Non', 'quantity_per_person' => '1', 'unit' => 'piece'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.included', ['Osh', 'Shashlik'])
            ->assertJsonPath('data.items.0.name', 'Cheeseburger')
            ->assertJsonPath('data.items.0.quantity_per_person', '0.250')
            ->json('data');

        $this->api($this->owner, 'PATCH', "/api/v1/owner/pos/banquets/packages/{$package['id']}", ['per_person_price' => '260000'])->assertOk()
            ->assertJsonPath('data.per_person_price', '260000.00')
            ->assertJsonPath('data.items.1.name', 'Non');
        $this->api($this->owner, 'POST', '/api/v1/owner/pos/banquets/packages', [
            'name' => 'Begona taom', 'per_person_price' => '1', 'items' => [['product_id' => 999999, 'quantity_per_person' => '1']],
        ])->assertStatus(422);

        $extra = $this->api($this->owner, 'POST', '/api/v1/owner/pos/banquets/extras', ['name' => "Qo'shimcha soat", 'price' => '1000000', 'pricing' => 'per_event'])
            ->assertCreated()->json('data');
        $this->api($this->owner, 'POST', '/api/v1/owner/pos/banquets/extras', ['name' => 'X', 'price' => '1', 'pricing' => 'per_hour'])->assertStatus(422);

        $this->api($this->owner, 'PUT', "/api/v1/owner/pos/banquets/halls/{$this->hallA}/rates", ['rates' => ['morning' => '2000000', 'day' => null]])->assertOk()
            ->assertJsonPath('data.rates.morning', '2000000.00')
            ->assertJsonPath('data.rates.day', null)
            ->assertJsonPath('data.rates.evening', '5000000.00');
        $halls = $this->api($this->owner, 'GET', "/api/v1/owner/pos/banquets/halls?branch_id={$this->branch->id}")->assertOk()->json('data.halls');
        $this->assertContains($this->hallA, array_column($halls, 'hall_id'));

        $this->api($this->owner, 'PUT', '/api/v1/owner/pos/banquets/settings', [
            'slots' => ['evening' => ['start' => '17:00', 'end' => '23:30']],
            'hold_hours' => 48,
            'guest_warning_thresholds' => [300, 200],
            'advance_mxik' => '10305001001000000',
            'cancellation_mxik' => '123',
        ])->assertStatus(422);
        $this->api($this->owner, 'PUT', '/api/v1/owner/pos/banquets/settings', [
            'slots' => ['evening' => ['start' => '17:00', 'end' => '23:30']],
            'hold_hours' => 48,
            'guest_warning_thresholds' => [300, 200],
            'advance_mxik' => '10305001001000000',
        ])->assertOk()
            ->assertJsonPath('data.slots.evening.start', '17:00')
            ->assertJsonPath('data.slots.day.start', '12:00')
            ->assertJsonPath('data.guest_warning_thresholds', [200, 300])
            ->assertJsonPath('data.hold_hours', 48);
        $this->hold()->assertCreated()->assertJsonPath('data.start_time', '17:00');

        foreach (['banquet.package_created', 'banquet.package_updated', 'banquet.extra_created', 'banquet.hall_rates_updated', 'banquet.settings_updated'] as $action) {
            $this->assertTrue(AuditLog::where('action', $action)->where('store_id', $this->store->id)->exists(), $action);
        }

        // Deleting: an unused extra goes, a used package is archived
        $this->api($this->owner, 'DELETE', "/api/v1/owner/pos/banquets/extras/{$extra['id']}")->assertOk()->assertJsonPath('data.deleted', true);
        $this->api($this->owner, 'DELETE', "/api/v1/owner/pos/banquets/packages/{$this->package->id}")->assertOk()->assertJsonPath('data.deleted', false);
        $this->assertFalse($this->package->fresh()->is_active);
        $this->assertSame(0, BanquetExtra::where('store_id', $this->store->id)->where('name', "Qo'shimcha soat")->count());
        $catalog = $this->api($this->manager, 'GET', '/api/v1/pos/banquets/catalog')->assertOk()->json('data');
        $this->assertNotContains('Standart', array_column($catalog['packages'], 'name'));
        $this->assertContains('Premium', array_column($catalog['packages'], 'name'));
    }

    public function test_the_bootstrap_carries_the_banquet_section_when_the_module_is_on(): void
    {
        $session = $this->terminalSession($this->cashier, $this->branch);
        $this->apiToken($session['token'], 'GET', '/api/v1/pos/bootstrap')->assertOk()
            ->assertJsonPath('data.banquet.slots.evening.start', '18:00')
            ->assertJsonPath('data.banquet.hold_hours', 72);

        $this->banquetModule(false);
        $this->apiToken($session['token'], 'GET', '/api/v1/pos/bootstrap')->assertOk()->assertJsonPath('data.banquet', null);
    }
}
