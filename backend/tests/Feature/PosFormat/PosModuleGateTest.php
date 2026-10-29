<?php

namespace Tests\Feature\PosFormat;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModuleDisabled;
use App\Domain\Pos\Setup\PosModules;
use App\Domain\Pos\Setup\PosSettings;
use App\Domain\Pos\Setup\FormatProfile;
use App\Enums\KitchenStationKind;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Hall;
use App\Models\KitchenStation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Feature\BillingV2\BillingV2Fixtures;
use Tests\Feature\PosBilling\PosBillingFixtures;
use Tests\TestCase;

/**
 * P6-11: `pos.module:<key>` and the module checks on the existing choyxona setup endpoints (rooms, room fee, choy-non,
 * bookings, nasiya, service charge, KDS pairing): 403 module_disabled when the branch's format switched the module
 * off, plan_feature_missing first when the plan lacks it; payments, bills, shifts and refunds never ask. And the
 * profile's sample stations and menu (B10), never over what exists.
 */
class PosModuleGateTest extends TestCase
{
    use BillingV2Fixtures;
    use PosBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosBilling();
    }

    public function test_switched_off_modules_answer_module_disabled_on_their_setup_endpoints(): void
    {
        $off = ['rooms' => false, 'room_fee' => false, 'guest_items' => false, 'bookings' => false, 'customer_accounts' => false, 'service_charge' => false, 'kds' => false];
        $this->switch($this->branch, $off);
        $hall = Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Zal']);

        $disabled = fn (TestResponse $response, string $module) => $response->assertForbidden()
            ->assertJsonPath('code', 'module_disabled')->assertJsonPath('module', $module)
            ->assertJsonPath('data.reason', 'module_disabled')->assertJsonPath('data.module', $module);

        // The till (its branch from the terminal) and the owner naming the branch
        $disabled($this->pos('POST', '/bookings', []), 'bookings')->assertJsonPath('data.profile', 'choyxona');
        $disabled($this->pos('POST', '/customer-accounts', ['name' => 'Aziz aka']), 'customer_accounts');
        $disabled($this->as($this->owner, 'POST', '/api/v1/owner/room-tariffs', ['branch_id' => $this->branch->id]), 'room_fee');
        $disabled($this->as($this->owner, 'POST', '/api/v1/owner/auto-guest-items', ['branch_id' => $this->branch->id]), 'guest_items');
        $disabled($this->as($this->owner, 'POST', '/api/v1/owner/dining-tables', ['hall_id' => $hall->id, 'name' => 'Xona 1', 'kind' => 'room']), 'rooms');
        $disabled($this->as($this->owner, 'POST', '/api/v1/owner/dining-tables', ['hall_id' => $hall->id, 'name' => 'Tapchan 1', 'kind' => 'tapchan']), 'rooms');
        $disabled($this->as($this->owner, 'PUT', '/api/v1/owner/service-fees', ['branch_id' => $this->branch->id, 'service_charge_percent' => '10', 'place_fee_amount' => '0', 'place_fee_mode' => 'per_table']), 'service_charge');
        $disabled($this->as($this->owner, 'POST', '/api/v1/owner/devices/pairing-codes', ['type' => 'kds', 'branch_id' => $this->branch->id]), 'kds');

        // What stays: an ordinary table, a 0% service charge with a place fee, a POS terminal, reading everything
        $this->as($this->owner, 'POST', '/api/v1/owner/dining-tables', ['hall_id' => $hall->id, 'name' => 'Stol 1', 'kind' => 'table'])->assertCreated();
        $this->as($this->owner, 'PUT', '/api/v1/owner/service-fees', ['branch_id' => $this->branch->id, 'service_charge_percent' => '0', 'place_fee_amount' => '5000', 'place_fee_mode' => 'per_table'])->assertOk();
        $this->as($this->owner, 'POST', '/api/v1/owner/devices/pairing-codes', ['type' => 'pos', 'branch_id' => $this->branch->id])->assertCreated();
        $this->as($this->owner, 'GET', '/api/v1/owner/room-tariffs?branch_id=' . $this->branch->id)->assertOk();
        $this->as($this->owner, 'GET', '/api/v1/owner/auto-guest-items?branch_id=' . $this->branch->id)->assertOk();
        $this->pos('GET', '/bookings')->assertOk();
        $this->pos('GET', '/customer-accounts')->assertOk();

        // The other branch keeps its modules: its requests go past the gate (to the controller's own validation)
        $this->as($this->owner, 'POST', '/api/v1/owner/room-tariffs', ['branch_id' => $this->otherBranch->id])->assertUnprocessable();
        $this->as($this->owner, 'POST', '/api/v1/owner/devices/pairing-codes', ['type' => 'kds', 'branch_id' => $this->otherBranch->id])->assertCreated();
        // A store-wide request: on while at least one branch has it
        $this->as($this->owner, 'PUT', '/api/v1/owner/service-fees', ['service_charge_percent' => '10', 'place_fee_amount' => '0', 'place_fee_mode' => 'per_table'])->assertOk();
        $this->switch($this->otherBranch, $off);
        $disabled($this->as($this->owner, 'PUT', '/api/v1/owner/service-fees', ['service_charge_percent' => '10', 'place_fee_amount' => '0', 'place_fee_mode' => 'per_table']), 'service_charge')
            ->assertJsonPath('data.profile', null); // two branches: no one profile to name
        $disabled($this->as($this->owner, 'POST', '/api/v1/owner/devices/pairing-codes', ['type' => 'kds']), 'kds');

        // Switched back on: the same requests pass the gate again
        $this->switch($this->branch, array_map(fn () => null, $off));
        $this->pos('POST', '/bookings', [])->assertUnprocessable();
        $this->as($this->owner, 'POST', '/api/v1/owner/dining-tables', ['hall_id' => $hall->id, 'name' => 'Xona 1', 'kind' => 'room'])->assertCreated();
    }

    public function test_the_plan_answers_before_the_format(): void
    {
        $this->billing()->assignPlan($this->store, $this->v2('start'));
        $this->switch($this->branch, ['bookings' => false]);

        // Start lacks bookings: the plan's answer, which says where to get it
        $this->pos('POST', '/bookings', [])->assertForbidden()->assertJsonPath('code', 'plan_feature_missing')->assertJsonPath('required_plan', 'asosiy');
        // A module every plan has, switched off: the format's
        $this->switch($this->branch, ['rooms' => false]);
        $hall = Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Zal']);
        $this->as($this->owner, 'POST', '/api/v1/owner/dining-tables', ['hall_id' => $hall->id, 'name' => 'Xona 1', 'kind' => 'room'])
            ->assertForbidden()->assertJsonPath('code', 'module_disabled');
    }

    public function test_the_middleware_on_a_route_of_its_own(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'pos.module:queue_numbers'])->post('/api/v1/test/p6/queue', fn () => response()->json(['success' => true]));
        Route::middleware(['api', 'auth:sanctum', 'pos.module:queue_numbers,customer_display'])->post('/api/v1/test/p6/both', fn () => response()->json(['success' => true]));
        Route::middleware(['api', 'auth:device', 'pos.module:queue_numbers'])->post('/api/v1/test/p6/device', fn () => response()->json(['success' => true]));

        // choyxona: no queue numbers
        $this->withBearer($this->cashierToken)->postJson('/api/v1/test/p6/queue')->assertForbidden()->assertJsonPath('module', 'queue_numbers');
        $this->withBearer($this->till['token'])->postJson('/api/v1/test/p6/device')->assertForbidden()->assertJsonPath('code', 'module_disabled');

        $this->switch($this->branch, ['queue_numbers' => true]);
        $this->withBearer($this->cashierToken)->postJson('/api/v1/test/p6/queue')->assertOk();
        $this->withBearer($this->till['token'])->postJson('/api/v1/test/p6/device')->assertOk();
        // Several keys: all of them
        $this->withBearer($this->cashierToken)->postJson('/api/v1/test/p6/both')->assertForbidden()->assertJsonPath('module', 'customer_display');
        $this->switch($this->branch, ['queue_numbers' => true, 'customer_display' => true]);
        $this->withBearer($this->cashierToken)->postJson('/api/v1/test/p6/both')->assertOk();

        // The owner names the branch with branch_id; another store's branch id counts as none
        $this->as($this->owner, 'POST', '/api/v1/test/p6/queue', ['branch_id' => $this->otherBranch->id])->assertForbidden();
        $this->as($this->owner, 'POST', '/api/v1/test/p6/queue', ['branch_id' => $this->branch->id])->assertOk();
        $this->as($this->owner, 'POST', '/api/v1/test/p6/queue')->assertOk(); // on for one branch of the store
    }

    public function test_payments_bills_shifts_and_refunds_never_ask_for_a_module(): void
    {
        $never = [
            'POST api/v1/pos/orders/{uuid}/bills', 'POST api/v1/pos/bills/{uuid}/payments', 'POST api/v1/pos/bills/{uuid}/print',
            'POST api/v1/pos/bills/{uuid}/fiscal-retry', 'GET api/v1/pos/fiscal-receipts/{uuid}', 'POST api/v1/pos/orders/{uuid}/close',
            'POST api/v1/pos/refunds', 'POST api/v1/pos/shifts', 'POST api/v1/pos/shifts/{uuid}/close',
            'POST api/v1/pos/shifts/{uuid}/cash-movements', 'POST api/v1/pos/bookings/{uuid}/deposits',
            'POST api/v1/pos/customer-accounts/{uuid}/repayments', 'POST api/v1/pos/sync',
        ];
        $found = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                $key = "{$method} {$route->uri()}";
                if (!in_array($key, $never, true)) {
                    continue;
                }
                $found[] = $key;
                foreach ($route->gatherMiddleware() as $middleware) {
                    $this->assertFalse(is_string($middleware) && str_starts_with($middleware, 'pos.module:'), "{$key} must never ask for a module");
                }
            }
        }
        sort($found);
        sort($never);
        $this->assertSame($never, $found);
    }

    public function test_the_enabled_api_and_the_store_wide_assertion(): void
    {
        $modules = app(PosModules::class);
        $this->assertTrue($modules->enabled($this->branch, PosModule::ROOMS));
        $this->assertFalse($modules->enabled($this->branch, PosModule::DAILY_MENU));

        $this->branch->update(['pos_settings' => PosSettings::withFormat(null, FormatProfile::STOLOVAYA, ['service_charge' => true, 'rooms' => true])]);
        $branch = $this->branch->fresh();
        $this->assertSame(['rooms' => true], PosSettings::format($branch->pos_settings)['modules']); // the lock is never stored on
        $this->assertFalse($modules->enabled($branch, PosModule::SERVICE_CHARGE));
        $this->assertTrue($modules->enabled($branch, PosModule::DAILY_MENU));
        $this->assertTrue($modules->serviceChargeLocked((int) $branch->id));
        $this->assertFalse($modules->serviceChargeLocked((int) $this->otherBranch->id));

        $this->expectException(PosModuleDisabled::class);
        $modules->assertEnabled($branch, PosModule::KDS);
    }

    public function test_applying_the_template_adds_stations_and_dishes_once_and_never_over_existing_ones(): void
    {
        $this->as($this->owner, 'PUT', '/api/v1/owner/pos/format', ['branch_id' => $this->branch->id, 'profile' => 'kabobxona'])->assertOk();
        KitchenStation::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Shashlik', 'kind' => KitchenStationKind::MANGAL->value]);
        $category = Category::create(['name' => 'Mangal', 'slug' => 'mangal-x', 'is_active' => true]);
        Product::create(['store_id' => $this->store->id, 'category_id' => $category->id, 'name' => 'Qiyma kabob', 'slug' => 'qiyma-x', 'status' => 'active']);

        $preview = $this->as($this->owner, 'GET', '/api/v1/owner/pos/format?branch_id=' . $this->branch->id)->json('data.template');
        $this->assertSame([true, false, false], array_column($preview['stations'], 'exists'));
        $this->assertTrue(collect($preview['dishes'])->firstWhere('name', 'Qiyma kabob')['exists']);

        $applied = $this->as($this->owner, 'POST', '/api/v1/owner/pos/format/template', ['branch_id' => $this->branch->id])->assertOk()->json('data');
        $this->assertSame(['Oshxona', 'Choy'], $applied['stations_created']);
        $this->assertSame(['Mangal'], $applied['stations_skipped']); // a mangal station is there already
        $this->assertSame(['Qiyma kabob'], $applied['dishes_skipped']);
        $this->assertContains('Kabob assorti', $applied['dishes_created']);
        $this->assertNotContains('Mangal', $applied['categories_created']); // the existing category is reused
        $this->assertSame('Shashlik', KitchenStation::where('branch_id', $this->branch->id)->where('kind', 'mangal')->sole()->name);

        $assorti = Product::where('store_id', $this->store->id)->where('name', 'Kabob assorti')->with('variants')->sole();
        $this->assertSame('inactive', $assorti->status);
        $this->assertSame($category->id, Product::where('store_id', $this->store->id)->where('name', "Qo'y go'shti kabob")->value('category_id'));
        $this->assertSame('0.00', (string) $assorti->variants->first()->price);
        $this->assertSame('active', Product::where('name', 'Qiyma kabob')->sole()->status); // untouched

        // Again: nothing new
        $again = $this->as($this->owner, 'POST', '/api/v1/owner/pos/format/template', ['branch_id' => $this->branch->id])->assertOk();
        $again->assertJsonPath('data.stations_created', [])->assertJsonPath('data.dishes_created', [])
            ->assertJsonPath('message', "Hammasi allaqachon bor: hech narsa qo'shilmadi.");
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.pos_template_applied', 'branch_id' => $this->branch->id]);

        // Only the stations of another profile; a manager may not
        $this->as($this->owner, 'POST', '/api/v1/owner/pos/format/template', ['branch_id' => $this->otherBranch->id, 'profile' => 'fast_food', 'menu' => false])->assertOk()
            ->assertJsonPath('data.stations_created', ['Grill', 'Fritur', 'Ichimliklar', "Yig'ish"])->assertJsonPath('data.dishes_created', []);
        $this->as($this->manager, 'POST', '/api/v1/owner/pos/format/template', ['branch_id' => $this->branch->id])->assertForbidden();
    }

    /**
     * The branch's module switches (null: back to the profile's default), as the owner saves them.
     *
     * @param  array<string, bool|null>  $modules
     */
    private function switch(Branch $branch, array $modules): void
    {
        $this->as($this->owner, 'PUT', '/api/v1/owner/pos/format', ['branch_id' => $branch->id, 'modules' => $modules])->assertOk();
        $this->flushHeaders();
        app()->forgetScopedInstances();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function as(User $user, string $method, string $uri, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->actingAs($user, 'sanctum')->json($method, $uri, $data);
    }
}
