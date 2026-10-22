<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\KitchenStation;
use App\Models\KitchenStationRoute;
use App\Models\Printer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3-13: kitchen stations, the routing of dishes to them and printers: the management API of the partner web panel
 * (settings.manage, not from a terminal PIN session, branch and store isolation).
 */
class PosStationsPrintersApiTest extends TestCase
{
    use RefreshDatabase;
    use PosSetupFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
    }

    // --- stations ----------------------------------------------------------------------------------------------

    public function test_the_owner_adds_lists_edits_and_deletes_stations(): void
    {
        $id = $this->as($this->owner, 'POST', '/api/v1/owner/kitchen-stations', ['branch_id' => $this->branch->id, 'name' => 'Osh va qozon', 'kind' => 'osh', 'sort_order' => 1])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Osh va qozon')
            ->assertJsonPath('data.kind', 'osh')
            ->assertJsonPath('data.kind_label', 'Osh va qozon')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.show_on_kds', true)
            ->json('data.id');
        $this->as($this->owner, 'POST', '/api/v1/owner/kitchen-stations', ['branch_id' => $this->branch->id, 'name' => 'Mangal', 'kind' => 'mangal', 'sort_order' => 2, 'show_on_kds' => false])->assertCreated();
        $this->as($this->owner, 'POST', '/api/v1/owner/kitchen-stations', ['branch_id' => $this->otherBranch->id, 'name' => 'Osh va qozon'])->assertCreated();

        // The same name twice in one branch is refused; another branch may reuse it
        $this->as($this->owner, 'POST', '/api/v1/owner/kitchen-stations', ['branch_id' => $this->branch->id, 'name' => 'Osh va qozon'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->as($this->owner, 'POST', '/api/v1/owner/kitchen-stations', ['branch_id' => $this->branch->id, 'name' => 'X', 'kind' => 'pizza'])->assertStatus(422)->assertJsonValidationErrors('kind');

        $list = $this->as($this->owner, 'GET', "/api/v1/owner/kitchen-stations?branch_id={$this->branch->id}")->assertOk();
        $this->assertSame(['Osh va qozon', 'Mangal'], array_column($list->json('data.stations'), 'name'));
        $this->assertSame(['osh', 'mangal', 'salad_bar', 'tea', 'kitchen', 'other'], array_column($list->json('data.kinds'), 'value'));
        $this->assertCount(3, $this->as($this->owner, 'GET', '/api/v1/owner/kitchen-stations')->json('data.stations'));

        $this->as($this->owner, 'PATCH', "/api/v1/owner/kitchen-stations/{$id}", ['name' => 'Osh', 'is_active' => false])
            ->assertOk()->assertJsonPath('data.name', 'Osh')->assertJsonPath('data.is_active', false);
        $this->assertSame(1, AuditLog::where('action', 'pos.station_updated')->count());

        $this->as($this->owner, 'DELETE', "/api/v1/owner/kitchen-stations/{$id}")->assertOk();
        $this->assertNull(KitchenStation::find($id));
    }

    public function test_deleting_a_station_removes_its_routes_and_keeps_its_printers(): void
    {
        $station = $this->makeStation($this->branch, 'Mangal');
        $this->route($this->branch, $station, productId: $this->product->id);
        $printer = $this->makePrinter($this->branch, 'Mangal printeri', ['purpose' => 'kitchen', 'station_id' => $station->id]);

        $this->as($this->owner, 'DELETE', "/api/v1/owner/kitchen-stations/{$station->id}")->assertOk();

        $this->assertSame(0, KitchenStationRoute::count());
        $this->assertNull($printer->fresh()->station_id);
    }

    public function test_a_station_orders_were_sent_to_cannot_be_deleted(): void
    {
        $station = $this->makeStation($this->branch, 'Osh');
        $line = $this->lineOf($this->branch, $this->variant);

        if (!\Illuminate\Support\Facades\Schema::hasColumn('order_items', 'station_id')) {
            $this->markTestSkipped('order_items.station_id belongs to P3-12 and is not in the schema yet.');
        }
        \Illuminate\Support\Facades\DB::table('order_items')->where('id', $line->id)->update(['station_id' => $station->id]);

        $this->as($this->owner, 'DELETE', "/api/v1/owner/kitchen-stations/{$station->id}")
            ->assertStatus(422)->assertJsonPath('data.reason', 'station_in_use');
        $this->assertNotNull(KitchenStation::find($station->id));
    }

    public function test_station_endpoints_need_settings_manage_and_respect_branches_and_stores(): void
    {
        $station = $this->makeStation($this->branch, 'Osh');
        $foreign = $this->makeStation($this->foreignBranch, 'Foreign osh');
        $manager = $this->manager; // a branch manager: no settings.manage by default
        $cook = $this->makeCook();

        foreach ([$manager, $cook] as $user) {
            $this->as($user, 'GET', '/api/v1/owner/kitchen-stations')->assertForbidden();
            $this->as($user, 'POST', '/api/v1/owner/kitchen-stations', ['branch_id' => $this->branch->id, 'name' => 'X'])->assertForbidden();
        }
        $this->as($this->customer, 'GET', '/api/v1/owner/kitchen-stations')->assertForbidden();
        $this->as(null, 'GET', '/api/v1/owner/kitchen-stations')->assertUnauthorized();

        // Another store: not found, not listed, branch not accepted
        $this->as($this->owner, 'PATCH', "/api/v1/owner/kitchen-stations/{$foreign->id}", ['name' => 'Hack'])->assertNotFound();
        $this->as($this->owner, 'DELETE', "/api/v1/owner/kitchen-stations/{$foreign->id}")->assertNotFound();
        $this->as($this->owner, 'POST', '/api/v1/owner/kitchen-stations', ['branch_id' => $this->foreignBranch->id, 'name' => 'Hack'])->assertNotFound();
        $this->as($this->owner, 'GET', "/api/v1/owner/kitchen-stations?branch_id={$this->foreignBranch->id}")->assertNotFound();
        $this->assertSame(['Osh'], array_column($this->as($this->owner, 'GET', '/api/v1/owner/kitchen-stations')->json('data.stations'), 'name'));
        $this->as($this->foreignOwner, 'PATCH', "/api/v1/owner/kitchen-stations/{$station->id}", ['name' => 'Hack'])->assertNotFound();
        $this->as($this->owner, 'GET', '/api/v1/owner/kitchen-stations?branch_id=99999999999999999999')->assertStatus(422);
    }

    public function test_a_terminal_pin_session_cannot_change_settings(): void
    {
        [$token] = $this->terminalSession($this->owner);

        $this->bearerJson('GET', '/api/v1/owner/kitchen-stations', $token)->assertForbidden()->assertJsonPath('data.reason', 'staff_session_not_allowed');
        $this->bearerJson('POST', '/api/v1/owner/printers', $token, ['name' => 'X', 'connection' => 'lan', 'address' => '10.0.0.1'])->assertForbidden();
        $this->bearerJson('PUT', "/api/v1/owner/branches/{$this->branch->id}/pos-settings", $token, ['pos_settings' => ['mode' => 'table']])->assertForbidden();
    }

    // --- routes ------------------------------------------------------------------------------------------------

    public function test_routes_are_assigned_in_bulk_moved_and_removed(): void
    {
        $osh = $this->makeStation($this->branch, 'Osh', \App\Enums\KitchenStationKind::OSH);
        $mangal = $this->makeStation($this->branch, 'Mangal', \App\Enums\KitchenStationKind::MANGAL);
        $drinks = Category::create(['name' => 'Ichimliklar', 'slug' => 'ichimlik-' . Str::random(5), 'is_active' => true]);
        $kebab = $this->makeProduct('Kabob');

        $response = $this->as($this->owner, 'PUT', '/api/v1/owner/kitchen-station-routes', [
            'branch_id' => $this->branch->id,
            'station_id' => $osh->id,
            'category_ids' => [$this->product->category_id, $drinks->id],
            'product_ids' => [$kebab->id],
        ])->assertOk();
        $this->assertCount(3, $response->json('data'));
        $this->assertSame(['Kabob'], array_values(array_filter(array_column($response->json('data'), 'product_name'))));

        // The kebab moves to the mangal; nothing is duplicated
        $this->as($this->owner, 'PUT', '/api/v1/owner/kitchen-station-routes', ['branch_id' => $this->branch->id, 'station_id' => $mangal->id, 'product_ids' => [$kebab->id]])->assertOk();
        $this->assertSame(3, KitchenStationRoute::where('branch_id', $this->branch->id)->count());
        $this->assertSame($mangal->id, KitchenStationRoute::where('product_id', $kebab->id)->value('station_id'));
        $this->assertSame(2, AuditLog::where('action', 'pos.station_routes_assigned')->count());

        // station_id null frees the listed dishes
        $this->as($this->owner, 'PUT', '/api/v1/owner/kitchen-station-routes', ['branch_id' => $this->branch->id, 'station_id' => null, 'category_ids' => [$drinks->id], 'product_ids' => [$kebab->id]])->assertOk()->assertJsonCount(1, 'data');

        $route = KitchenStationRoute::firstOrFail();
        $this->as($this->owner, 'GET', "/api/v1/owner/kitchen-station-routes?branch_id={$this->branch->id}")->assertOk()->assertJsonPath('data.0.category_name', 'Burgers');
        $this->as($this->owner, 'DELETE', "/api/v1/owner/kitchen-station-routes/{$route->id}")->assertOk();
        $this->assertSame(0, KitchenStationRoute::count());
    }

    public function test_route_assignment_is_validated_and_isolated(): void
    {
        $osh = $this->makeStation($this->branch, 'Osh');
        $otherBranchStation = $this->makeStation($this->otherBranch, 'Boshqa filial');
        $foreignStation = $this->makeStation($this->foreignBranch, 'Begona');
        $foreignProduct = \App\Models\Product::create(['store_id' => $this->foreignStore->id, 'category_id' => $this->product->category_id, 'name' => 'Begona', 'slug' => 'begona-' . Str::random(5), 'status' => 'active']);
        $assign = fn (array $payload, $user = null) => $this->as($user ?? $this->owner, 'PUT', '/api/v1/owner/kitchen-station-routes', ['branch_id' => $this->branch->id] + $payload);

        $assign(['station_id' => $osh->id])->assertStatus(422); // nothing to route
        $assign(['category_ids' => [$this->product->category_id]])->assertStatus(422)->assertJsonValidationErrors('station_id'); // station_id must be present
        $assign(['station_id' => $otherBranchStation->id, 'product_ids' => [$this->product->id]])->assertStatus(422)->assertJsonValidationErrors('station_id');
        $assign(['station_id' => $foreignStation->id, 'product_ids' => [$this->product->id]])->assertStatus(422)->assertJsonValidationErrors('station_id');
        $assign(['station_id' => $osh->id, 'product_ids' => [$foreignProduct->id]])->assertStatus(422)->assertJsonValidationErrors('product_ids');
        $assign(['station_id' => $osh->id, 'category_ids' => [999999]])->assertStatus(422)->assertJsonValidationErrors('category_ids');
        $assign(['station_id' => $osh->id, 'product_ids' => [$this->product->id, $this->product->id]])->assertStatus(422);
        $this->as($this->owner, 'PUT', '/api/v1/owner/kitchen-station-routes', ['branch_id' => $this->foreignBranch->id, 'station_id' => $foreignStation->id, 'product_ids' => [$foreignProduct->id]])->assertNotFound();
        $this->assertSame(0, KitchenStationRoute::count());

        // Another store's route: not found
        $route = $this->route($this->foreignBranch, $foreignStation, productId: $foreignProduct->id);
        $this->as($this->owner, 'DELETE', "/api/v1/owner/kitchen-station-routes/{$route->id}")->assertNotFound();
        $this->assertSame(1, KitchenStationRoute::count());
    }

    // --- printers ----------------------------------------------------------------------------------------------

    public function test_a_lan_printer_is_added_with_defaults_and_edited(): void
    {
        $station = $this->makeStation($this->branch, 'Mangal');

        $id = $this->as($this->owner, 'POST', '/api/v1/owner/printers', [
            'branch_id' => $this->branch->id, 'name' => 'Mangal printeri', 'purpose' => 'kitchen', 'connection' => 'lan',
            'address' => '192.168.1.60', 'station_id' => $station->id, 'paper_width' => 58, 'encoding' => 'cp866', 'print_as_image' => true,
        ])->assertCreated()
            ->assertJsonPath('data.port', 9100)
            ->assertJsonPath('data.paper_width', 58)
            ->assertJsonPath('data.station_id', $station->id)
            ->assertJsonPath('data.device_id', null)
            ->assertJsonPath('data.encoding', 'cp866')
            ->assertJsonPath('data.print_as_image', true)
            ->json('data.id');

        $this->as($this->owner, 'PATCH', "/api/v1/owner/printers/{$id}", ['port' => 9101, 'is_active' => false, 'name' => 'Mangal 2'])
            ->assertOk()->assertJsonPath('data.port', 9101)->assertJsonPath('data.is_active', false)->assertJsonPath('data.address', '192.168.1.60');
        $this->assertSame(1, AuditLog::where('action', 'pos.printer_updated')->count());

        $this->as($this->owner, 'GET', "/api/v1/owner/printers?branch_id={$this->branch->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->as($this->owner, 'DELETE', "/api/v1/owner/printers/{$id}")->assertOk();
        $this->assertSame(0, Printer::count());
    }

    public function test_connection_rules_are_checked(): void
    {
        $terminal = $this->pairTerminal($this->owner, $this->branch)['device'];
        $post = fn (array $payload) => $this->as($this->owner, 'POST', '/api/v1/owner/printers', ['branch_id' => $this->branch->id, 'purpose' => 'receipt'] + $payload);

        $post(['name' => 'A', 'connection' => 'lan'])->assertStatus(422)->assertJsonValidationErrors('address');
        $post(['name' => 'A', 'connection' => 'bluetooth'])->assertStatus(422)->assertJsonValidationErrors('address');
        $post(['name' => 'A', 'connection' => 'usb'])->assertStatus(422)->assertJsonValidationErrors('device_id');
        $post(['name' => 'A', 'connection' => 'lan', 'address' => 'bad address!'])->assertStatus(422)->assertJsonValidationErrors('address');
        $post(['name' => 'A', 'connection' => 'lan', 'address' => '10.0.0.5', 'paper_width' => 72])->assertStatus(422)->assertJsonValidationErrors('paper_width');
        $post(['name' => 'A', 'connection' => 'lan', 'address' => '10.0.0.5', 'port' => 70000])->assertStatus(422)->assertJsonValidationErrors('port');
        $post(['name' => 'A', 'connection' => 'fax', 'address' => '10.0.0.5'])->assertStatus(422)->assertJsonValidationErrors('connection');
        // A receipt printer has no station
        $station = $this->makeStation($this->branch, 'Osh');
        $post(['name' => 'A', 'connection' => 'lan', 'address' => '10.0.0.5', 'station_id' => $station->id])->assertStatus(422)->assertJsonValidationErrors('station_id');
        // Not a terminal of this store, a KDS, another branch's terminal
        $post(['name' => 'A', 'connection' => 'usb', 'device_id' => (string) Str::uuid()])->assertStatus(422)->assertJsonValidationErrors('device_id');
        $kds = $this->pairTerminal($this->owner, $this->branch, \App\Enums\DeviceType::KDS)['device'];
        $post(['name' => 'A', 'connection' => 'usb', 'device_id' => $kds->uuid])->assertStatus(422)->assertJsonValidationErrors('device_id');
        $elsewhere = $this->pairTerminal($this->owner, $this->otherBranch)['device'];
        $post(['name' => 'A', 'connection' => 'usb', 'device_id' => $elsewhere->uuid])->assertStatus(422)->assertJsonValidationErrors('device_id');
        $this->assertSame(0, Printer::count());

        // A USB printer belongs to its terminal: the address is dropped; the terminal is shown by uuid
        $response = $post(['name' => 'USB kassa', 'connection' => 'usb', 'device_id' => $terminal->uuid, 'address' => '1.2.3.4'])->assertCreated()
            ->assertJsonPath('data.device_id', $terminal->uuid)
            ->assertJsonPath('data.address', null)
            ->assertJsonPath('data.port', null);
        $this->assertSame($terminal->id, Printer::find($response->json('data.id'))->device_id);

        // Switching it to LAN needs an address and drops the terminal
        $id = $response->json('data.id');
        $this->as($this->owner, 'PATCH', "/api/v1/owner/printers/{$id}", ['connection' => 'lan'])->assertStatus(422)->assertJsonValidationErrors('address');
        $this->as($this->owner, 'PATCH', "/api/v1/owner/printers/{$id}", ['connection' => 'lan', 'address' => '10.0.0.9'])->assertOk()->assertJsonPath('data.device_id', null)->assertJsonPath('data.port', 9100);
        // Renaming a USB printer whose terminal was revoked since still works (the terminal is only checked when set)
        $usb = $this->makePrinter($this->branch, 'Eski USB', ['connection' => 'usb', 'address' => null, 'port' => null, 'device_id' => $terminal->id]);
        $terminal->forceFill(['revoked_at' => now()])->save();
        $this->as($this->owner, 'PATCH', "/api/v1/owner/printers/{$usb->id}", ['name' => 'Yangi nom'])->assertOk()->assertJsonPath('data.name', 'Yangi nom');
    }

    public function test_printer_names_are_unique_per_branch_and_isolated_per_store(): void
    {
        $this->makePrinter($this->branch, 'Kassa');
        $foreign = $this->makePrinter($this->foreignBranch, 'Begona');
        $payload = ['name' => 'Kassa', 'connection' => 'lan', 'address' => '10.0.0.7'];

        $this->as($this->owner, 'POST', '/api/v1/owner/printers', ['branch_id' => $this->branch->id] + $payload)->assertStatus(422)->assertJsonValidationErrors('name');
        $this->as($this->owner, 'POST', '/api/v1/owner/printers', ['branch_id' => $this->otherBranch->id] + $payload)->assertCreated();
        $this->as($this->owner, 'POST', '/api/v1/owner/printers', ['branch_id' => $this->foreignBranch->id] + $payload)->assertNotFound();

        $this->as($this->owner, 'PATCH', "/api/v1/owner/printers/{$foreign->id}", ['name' => 'Hack'])->assertNotFound();
        $this->as($this->owner, 'DELETE', "/api/v1/owner/printers/{$foreign->id}")->assertNotFound();
        $this->assertSame(['Kassa', 'Kassa'], array_column($this->as($this->owner, 'GET', '/api/v1/owner/printers')->json('data'), 'name'));
        $this->as($this->manager, 'GET', '/api/v1/owner/printers')->assertForbidden();

        // A kitchen printer cannot be tied to another branch's station
        $otherStation = $this->makeStation($this->otherBranch, 'Boshqa');
        $this->as($this->owner, 'POST', '/api/v1/owner/printers', ['branch_id' => $this->branch->id, 'name' => 'Oshxona', 'purpose' => 'kitchen', 'station_id' => $otherStation->id] + $payload)
            ->assertStatus(422)->assertJsonValidationErrors('station_id');
    }
}
