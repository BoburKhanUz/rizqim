<?php

namespace Tests\Feature;

use App\Domain\Pos\Setup\PosBootstrap;
use App\Domain\Pos\Setup\PosBootstrapContributor;
use App\Enums\DeviceType;
use App\Enums\KitchenStationKind;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * P3-13: GET /pos/bootstrap for a terminal PIN session and for a normal staff sign-in: the branch, its settings,
 * stations, the printers the terminal may use, staff, menu_version, isolation.
 */
class PosBootstrapTest extends TestCase
{
    use RefreshDatabase;
    use PosSetupFixtures;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
        $this->cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_terminal_session_gets_its_branch_stations_printers_and_staff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 21:30:00', 'UTC')); // Friday 02:30 in Tashkent
        [$token, $terminal] = $this->terminalSession($this->cashier);

        $osh = $this->makeStation($this->branch, 'Osh', KitchenStationKind::OSH, ['sort_order' => 2]);
        $mangal = $this->makeStation($this->branch, 'Mangal', KitchenStationKind::MANGAL, ['sort_order' => 1]);
        $this->makeStation($this->branch, 'Eski', KitchenStationKind::OTHER, ['is_active' => false]);
        $this->makeStation($this->otherBranch, 'Boshqa filial');
        $this->route($this->branch, $osh, categoryId: $this->product->category_id);
        $this->route($this->branch, $mangal, productId: $this->product->id);
        $this->as($this->owner, 'PUT', "/api/v1/owner/branches/{$this->branch->id}/pos-settings", [
            'business_day_starts_at' => '04:00',
            'pos_settings' => ['mode' => 'table', 'printing_device_id' => $terminal->uuid, 'queue_numbers' => true],
        ])->assertOk();

        $response = $this->bearerJson('GET', '/api/v1/pos/bootstrap', $token)->assertOk();
        $data = $response->json('data');

        $this->assertSame('2026-10-22T21:30:00+00:00', $data['server_time']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $data['menu_version']);
        $this->assertSame(['id' => $this->store->id, 'name' => 'Lifecycle Burger', 'timezone' => 'Asia/Tashkent'], $data['store']);

        $this->assertSame($this->branch->id, $data['branch']['id']);
        $this->assertSame('Asia/Tashkent', $data['branch']['timezone']);
        $this->assertSame('04:00', $data['branch']['business_day_starts_at']);
        $this->assertSame('2026-10-22', $data['branch']['business_date']); // 02:30 Friday: Thursday's business day
        $this->assertSame('table', $data['branch']['pos_settings']['mode']);
        $this->assertSame($terminal->uuid, $data['branch']['pos_settings']['printing_device_id']);
        $this->assertTrue($data['branch']['pos_settings']['queue_numbers']);
        $this->assertSame(['open_room_hours', 'void_amount', 'discount_percent', 'unaccepted_online_minutes'], array_keys($data['branch']['pos_settings']['alerts']));

        $this->assertSame(['uuid' => $terminal->uuid, 'type' => 'pos', 'name' => 'Kassa', 'is_printing_device' => true], $data['device']);
        $this->assertSame($this->cashier->id, $data['user']['id']);
        $this->assertSame('cashier', $data['user']['role']);
        $this->assertContains('pos.take_payment', $data['user']['permissions']);
        $this->assertNotContains('settings.manage', $data['user']['permissions']);

        // Active stations of this branch only, in screen order; the routing for offline use
        $this->assertSame(['Mangal', 'Osh'], array_column($data['stations'], 'name'));
        $this->assertSame([['category_id' => $this->product->category_id, 'station_id' => $osh->id]], $data['station_routes']['categories']);
        $this->assertSame([['product_id' => $this->product->id, 'station_id' => $mangal->id]], $data['station_routes']['products']);

        // Staff of this branch and the store-wide ones: no customers, no other branch, no PIN data
        $names = array_column($data['staff'], 'name');
        $this->assertContains($this->owner->name, $names);
        $this->assertContains($this->cashier->name, $names);
        $this->assertContains($this->manager->name, $names);
        $this->assertNotContains($this->courier->name, $names);
        $this->assertNotContains($this->customer->name, $names);
        $this->assertNotContains($this->foreignOwner->name, $names);
        $this->assertSame(['id', 'name', 'photo_url', 'role'], collect($data['staff'][0])->keys()->sort()->values()->all());

        // The hooks of the hall and shift modules
        $this->assertSame([], $data['halls']);
        $this->assertSame([], $data['tables']);
        $this->assertNull($data['shift']);
    }

    public function test_the_printers_are_the_ones_this_terminal_can_use(): void
    {
        [$token, $terminal] = $this->terminalSession($this->cashier);
        $other = $this->pairTerminal($this->owner, $this->branch)['device'];
        $station = $this->makeStation($this->branch, 'Mangal');

        $this->makePrinter($this->branch, 'LAN kassa');
        $this->makePrinter($this->branch, 'Mangal LAN', ['purpose' => 'kitchen', 'station_id' => $station->id, 'paper_width' => 58]);
        $this->makePrinter($this->branch, 'Bluetooth', ['connection' => 'bluetooth', 'address' => 'AA:BB:CC:DD:EE:FF', 'port' => null]);
        $this->makePrinter($this->branch, 'Mening USB', ['connection' => 'usb', 'address' => null, 'port' => null, 'device_id' => $terminal->id]);
        $this->makePrinter($this->branch, 'Boshqa USB', ['connection' => 'usb', 'address' => null, 'port' => null, 'device_id' => $other->id]);
        $this->makePrinter($this->branch, 'Ichki', ['connection' => 'builtin', 'address' => null, 'port' => null, 'device_id' => $other->id]);
        $this->makePrinter($this->branch, 'Eski LAN', ['is_active' => false]);
        $this->makePrinter($this->otherBranch, 'Boshqa filial LAN');
        $this->makePrinter($this->foreignBranch, 'Begona LAN');

        $printers = collect($this->bearerJson('GET', '/api/v1/pos/bootstrap', $token)->assertOk()->json('data.printers'));

        $this->assertSame(['LAN kassa', 'Mangal LAN', 'Bluetooth', 'Mening USB'], $printers->pluck('name')->all());
        $mine = $printers->firstWhere('name', 'Mening USB');
        $this->assertSame($terminal->uuid, $mine['device_id']);
        $this->assertNull($mine['address']);
        $kitchen = $printers->firstWhere('name', 'Mangal LAN');
        $this->assertEquals(['purpose' => 'kitchen', 'connection' => 'lan', 'address' => '192.168.1.50', 'port' => 9100, 'paper_width' => 58, 'station_id' => $station->id, 'print_as_image' => false, 'encoding' => null, 'is_active' => true], collect($kitchen)->only(['purpose', 'connection', 'address', 'port', 'paper_width', 'station_id', 'print_as_image', 'encoding', 'is_active'])->all());
        $this->assertArrayNotHasKey('store_id', $kitchen);

        // The other terminal sees its own USB and built-in printers instead
        $otherSession = $this->staffSessionOn($other, $this->cashier);
        $this->assertSame(['LAN kassa', 'Mangal LAN', 'Bluetooth', 'Boshqa USB', 'Ichki'], array_column($this->bearerJson('GET', '/api/v1/pos/bootstrap', $otherSession)->json('data.printers'), 'name'));
    }

    private function staffSessionOn(Device $terminal, User $user): string
    {
        // pairTerminal() does not keep the device's own token: open the PIN session through the service
        $session = app(\App\Services\Auth\StaffPinService::class)->startSession($terminal, $user->id, null, '5926');

        return $session['token'];
    }

    public function test_a_normal_staff_sign_in_works_its_own_branch_without_a_terminal(): void
    {
        $this->makeStation($this->branch, 'Osh', KitchenStationKind::OSH);
        $terminal = $this->pairTerminal($this->owner, $this->branch)['device'];
        $this->makePrinter($this->branch, 'LAN kassa');
        $this->makePrinter($this->branch, 'USB', ['connection' => 'usb', 'address' => null, 'port' => null, 'device_id' => $terminal->id]);
        $this->as($this->owner, 'PUT', "/api/v1/owner/branches/{$this->branch->id}/pos-settings", ['pos_settings' => ['printing_device_id' => $terminal->uuid]])->assertOk();

        $response = $this->as($this->manager, 'GET', '/api/v1/pos/bootstrap')->assertOk();

        $this->assertNull($response->json('data.device'));
        $this->assertSame($this->branch->id, $response->json('data.branch.id'));
        $this->assertSame($this->manager->id, $response->json('data.user.id'));
        $this->assertSame(['Osh'], array_column($response->json('data.stations'), 'name'));
        $this->assertSame(['LAN kassa'], array_column($response->json('data.printers'), 'name')); // a USB printer needs its own terminal
        $this->assertSame($terminal->uuid, $response->json('data.branch.pos_settings.printing_device_id'));
        $this->assertSame('05:00', $response->json('data.branch.business_day_starts_at'));
    }

    public function test_the_owner_names_the_branch(): void
    {
        $this->as($this->owner, 'GET', '/api/v1/pos/bootstrap')->assertStatus(422)->assertJsonPath('data.errors.branch_id.0', 'Filialni tanlang.');
        $this->as($this->owner, 'GET', "/api/v1/pos/bootstrap?branch_id={$this->otherBranch->id}")->assertOk()->assertJsonPath('data.branch.id', $this->otherBranch->id);
        $this->as($this->owner, 'GET', '/api/v1/pos/bootstrap?branch_id=abc')->assertStatus(422);
        $this->as($this->owner, 'GET', '/api/v1/pos/bootstrap?branch_id=99999999999999999999')->assertStatus(422);
    }

    public function test_a_store_wide_terminal_names_the_branch_too(): void
    {
        [$token] = $this->terminalSession($this->cashier, $this->branch);
        $this->makeStation($this->otherBranch, 'Boshqa');

        // The PIN session acts in the terminal's branch: asking for another one is refused
        $this->bearerJson('GET', "/api/v1/pos/bootstrap?branch_id={$this->otherBranch->id}", $token)->assertForbidden();
        $this->bearerJson('GET', "/api/v1/pos/bootstrap?branch_id={$this->branch->id}", $token)->assertOk();

        $storeWide = $this->makeStaff(UserRole::MANAGER, null);
        $this->givePin($storeWide, '5926');
        $terminal = $this->pairTerminal($this->owner, null)['device'];
        $session = app(\App\Services\Auth\StaffPinService::class)->startSession($terminal, $storeWide->id, null, '5926')['token'];

        $this->bearerJson('GET', '/api/v1/pos/bootstrap', $session)->assertStatus(422)->assertJsonPath('data.errors.branch_id.0', 'Filialni tanlang.');
        $this->bearerJson('GET', "/api/v1/pos/bootstrap?branch_id={$this->otherBranch->id}", $session)->assertOk()->assertJsonPath('data.stations.0.name', 'Boshqa');
    }

    public function test_branches_stores_and_roles_are_isolated(): void
    {
        $this->as($this->owner, 'GET', "/api/v1/pos/bootstrap?branch_id={$this->foreignBranch->id}")->assertNotFound();
        $this->as($this->foreignOwner, 'GET', "/api/v1/pos/bootstrap?branch_id={$this->branch->id}")->assertNotFound();
        $this->as($this->manager, 'GET', "/api/v1/pos/bootstrap?branch_id={$this->otherBranch->id}")->assertForbidden();
        $this->as($this->manager, 'GET', "/api/v1/pos/bootstrap?branch_id={$this->foreignBranch->id}")->assertNotFound();
        $this->as($this->courier, 'GET', '/api/v1/pos/bootstrap')->assertForbidden();
        $this->as($this->customer, 'GET', '/api/v1/pos/bootstrap')->assertForbidden();
        $this->as(null, 'GET', '/api/v1/pos/bootstrap')->assertUnauthorized();

        // Another store's stations and printers never appear
        $this->makeStation($this->foreignBranch, 'Begona');
        $this->makePrinter($this->foreignBranch, 'Begona LAN');
        $response = $this->as($this->manager, 'GET', '/api/v1/pos/bootstrap')->assertOk();
        $this->assertSame([], $response->json('data.stations'));
        $this->assertSame([], $response->json('data.printers'));
    }

    public function test_a_revoked_terminal_session_gets_nothing(): void
    {
        [$token, $terminal] = $this->terminalSession($this->cashier);
        $this->bearerJson('GET', '/api/v1/pos/bootstrap', $token)->assertOk();

        $terminal->forceFill(['revoked_at' => now()])->save();

        $this->assertContains($this->bearerJson('GET', '/api/v1/pos/bootstrap', $token)->status(), [401, 403]);
    }

    public function test_a_kds_terminal_session_reads_it_as_well_and_a_gone_printing_till_is_dropped(): void
    {
        [$token, $kds] = $this->terminalSession($this->makeStaff(UserRole::COOK, $this->branch), $this->branch, DeviceType::KDS);
        $till = $this->pairTerminal($this->owner, $this->branch)['device'];
        $this->as($this->owner, 'PUT', "/api/v1/owner/branches/{$this->branch->id}/pos-settings", ['pos_settings' => ['printing_device_id' => $till->uuid]])->assertOk();

        $response = $this->bearerJson('GET', '/api/v1/pos/bootstrap', $token)->assertOk();
        $this->assertSame('kds', $response->json('data.device.type'));
        $this->assertFalse($response->json('data.device.is_printing_device'));
        $this->assertSame($till->uuid, $response->json('data.branch.pos_settings.printing_device_id'));

        $till->forceFill(['revoked_at' => now()])->save();
        $this->assertNull($this->bearerJson('GET', '/api/v1/pos/bootstrap', $token)->json('data.branch.pos_settings.printing_device_id'));
        $this->assertSame($kds->uuid, $response->json('data.device.uuid'));
    }

    public function test_menu_version_follows_the_menu_but_not_availability(): void
    {
        $version = fn () => $this->as($this->manager, 'GET', '/api/v1/pos/bootstrap')->json('data.menu_version');
        $first = $version();
        $this->assertSame($first, $version());

        // Sales move the stock and the stop-list moves availability: neither is the menu
        $this->variant->decrement('stock', 2);
        $this->as($this->manager, 'POST', '/api/v1/owner/stop-list', ['branch_id' => $this->branch->id, 'type' => 'variant', 'id' => $this->variant->id])->assertCreated();
        $this->assertSame($first, $version());

        // A price, a new dish or a removed one is the menu
        Carbon::setTestNow(now()->addMinute());
        $this->variant->update(['price' => 51000]);
        $second = $version();
        $this->assertNotSame($first, $second);

        $extra = $this->makeProduct('Yangi taom');
        $this->assertNotSame($second, $version());
        $third = $version();
        $extra->delete();
        $this->assertNotSame($third, $version());

        // Another store's menu does not move it
        $before = $version();
        \App\Models\Product::create(['store_id' => $this->foreignStore->id, 'category_id' => $this->product->category_id, 'name' => 'Begona', 'slug' => 'begona-x', 'status' => 'active']);
        $this->assertSame($before, $version());
    }

    public function test_halls_tables_and_the_terminals_open_shift_are_included(): void
    {
        [$token, $terminal] = $this->terminalSession($this->cashier);
        $other = $this->pairTerminal($this->owner, $this->branch)['device'];
        $main = \App\Models\Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Asosiy zal', 'sort_order' => 1]);
        $summer = \App\Models\Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Yozgi hovli', 'is_active' => false]);
        $elsewhere = \App\Models\Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->otherBranch->id, 'name' => 'Boshqa filial zali']);
        $room = \App\Models\DiningTable::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $main->id, 'name' => 'Xona 1', 'kind' => 'room', 'seats' => 12, 'layout_x' => 1, 'layout_y' => 2, 'layout_w' => 3, 'layout_h' => 4, 'min_spend' => '500000', 'min_spend_mode' => 'per_table']);
        \App\Models\DiningTable::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $main->id, 'name' => 'Yopiq', 'is_active' => false]);
        \App\Models\DiningTable::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $summer->id, 'name' => 'Hovli 1']);
        \App\Models\DiningTable::create(['store_id' => $this->store->id, 'branch_id' => $this->otherBranch->id, 'hall_id' => $elsewhere->id, 'name' => 'Boshqa 1']);
        $shift = fn ($device, string $status) => \App\Models\PosShift::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'device_id' => $device->id, 'business_date' => '2026-10-22',
            'status' => $status, 'opened_by' => $this->cashier->id, 'opened_at' => now(), 'opening_cash' => '300000',
        ]);
        $shift($other, 'open');

        $data = $this->bearerJson('GET', '/api/v1/pos/bootstrap', $token)->assertOk()->json('data');

        $this->assertSame([['id' => $main->id, 'name' => 'Asosiy zal', 'sort_order' => 1]], $data['halls']);
        $this->assertCount(1, $data['tables']);
        $this->assertSame(['id' => $room->id, 'hall_id' => $main->id, 'name' => 'Xona 1', 'kind' => 'room', 'seats' => 12], collect($data['tables'][0])->only(['id', 'hall_id', 'name', 'kind', 'seats'])->all());
        $this->assertEquals(['x' => 1, 'y' => 2, 'w' => 3, 'h' => 4], $data['tables'][0]['layout']);
        $this->assertSame('500000.00', $data['tables'][0]['min_spend']);
        $this->assertSame('per_table', $data['tables'][0]['min_spend_mode']);
        $this->assertNull($data['shift']); // the shift open on ANOTHER terminal is not this till's

        $mine = $shift($terminal, 'open');
        $current = $this->bearerJson('GET', '/api/v1/pos/bootstrap', $token)->json('data.shift');
        $this->assertSame($mine->uuid, $current['uuid']);
        $this->assertSame('open', $current['status']);
        $this->assertSame('2026-10-22', $current['business_date']);
        $this->assertSame('300000.00', $current['opening_cash']);
        $this->assertSame($terminal->uuid, $current['device_id']);

        // A normal sign-in has no terminal, so no till shift; the halls are the branch's all the same
        $response = $this->as($this->manager, 'GET', '/api/v1/pos/bootstrap')->assertOk();
        $this->assertNull($response->json('data.shift'));
        $this->assertSame(['Asosiy zal'], array_column($response->json('data.halls'), 'name'));
        $this->assertSame(['Boshqa filial zali'], array_column($this->as($this->owner, 'GET', "/api/v1/pos/bootstrap?branch_id={$this->otherBranch->id}")->json('data.halls'), 'name'));
    }

    public function test_other_modules_add_their_sections_through_the_contributor_hook(): void
    {
        $this->app->bind('test.hall-contributor', fn () => new class implements PosBootstrapContributor {
            public function contribute(User $user, Branch $branch, ?Device $device): array
            {
                return ['halls' => [['id' => 7, 'branch_id' => $branch->id]], 'shift' => ['status' => 'open']];
            }
        });
        $this->app->tag('test.hall-contributor', PosBootstrap::CONTRIBUTORS);

        $response = $this->as($this->manager, 'GET', '/api/v1/pos/bootstrap')->assertOk();

        $this->assertSame([['id' => 7, 'branch_id' => $this->branch->id]], $response->json('data.halls'));
        $this->assertSame(['status' => 'open'], $response->json('data.shift'));
        $this->assertSame([], $response->json('data.tables'));
    }
}
