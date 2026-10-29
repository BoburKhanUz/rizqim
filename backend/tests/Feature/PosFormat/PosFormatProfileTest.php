<?php

namespace Tests\Feature\PosFormat;

use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\OrderChannel;
use App\Enums\StoreFormat;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Hall;
use App\Models\ServiceFeeSetting;
use App\Services\Admin\Stores\StoreProvisioner;
use App\Services\Order\DiningFeeCalculator;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Feature\BillingV2\BillingV2Fixtures;
use Tests\Feature\PosBilling\PosBillingFixtures;
use Tests\TestCase;

/**
 * P6-11: the branch's format profile — the bootstrap's `format` for every profile, the owner's GET/PUT
 * /owner/pos/format (overrides, plan intersection, the stolovaya lock, audit, access), existing branches as choyxona
 * and the application format → profile mapping.
 */
class PosFormatProfileTest extends TestCase
{
    use BillingV2Fixtures;
    use PosBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosBilling();
    }

    public function test_an_existing_branch_is_a_choyxona_and_the_bootstrap_carries_its_format(): void
    {
        $this->assertNull($this->branch->fresh()->pos_settings);

        $format = $this->pos('GET', '/bootstrap')->assertOk()->json('data.format');
        $this->assertSame('choyxona', $format['profile']);
        $this->assertSame('hybrid', $format['mode']);
        $this->assertSame('hall_map', $format['first_screen']);
        $this->assertSame(['service_charge_zero' => false], $format['locks']);
        // The phase 3 flow: every choyxona module (a store never on a v2 plan keeps every feature)
        $this->assertSame(['quick_counter', 'batches', 'terminal_card_flow', 'rooms', 'room_fee', 'guest_items', 'bookings', 'customer_accounts', 'service_charge', 'kds'], $format['modules']);
    }

    public function test_the_migration_backfills_choyxona_without_touching_other_settings(): void
    {
        Branch::whereKey($this->branch->id)->update(['pos_settings' => json_encode(['mode' => 'table', 'queue_numbers' => true])]);
        Branch::whereKey($this->otherBranch->id)->update(['pos_settings' => json_encode(['profile' => 'kafe'])]);

        $migration = require database_path('migrations/2026_11_10_100100_backfill_branch_pos_profile.php');
        $migration->up();

        $this->assertEquals(['mode' => 'table', 'queue_numbers' => true, 'profile' => 'choyxona', 'modules' => []], $this->branch->fresh()->pos_settings);
        $this->assertSame('kafe', $this->otherBranch->fresh()->pos_settings['profile']);

        $migration->down();
        $this->assertEquals(['mode' => 'table', 'queue_numbers' => true], $this->branch->fresh()->pos_settings);
    }

    public function test_the_bootstrap_format_of_every_profile(): void
    {
        $expected = [
            'choyxona' => ['hybrid', 'hall_map'],
            'osh_markazi' => ['counter', 'quick_counter'],
            'lagmonxona' => ['hybrid', 'quick_counter'],
            'somsaxona' => ['counter', 'quick_counter'],
            'tamaddixona' => ['counter', 'quick_counter'],
            'stolovaya' => ['counter', 'quick_counter'],
            'kafe' => ['hybrid', 'quick_counter'],
            'restoran' => ['table', 'hall_map'],
            'kabobxona' => ['table', 'hall_map'],
            'baliqxona' => ['table', 'hall_map'],
            'fast_food' => ['counter', 'quick_counter'],
            'qahvaxona' => ['counter', 'quick_counter'],
            'toyxona' => ['table', 'booking_calendar'],
            'dark_kitchen' => ['counter', 'online_orders'],
        ];
        $this->assertSame(FormatProfile::values(), array_keys($expected));

        foreach ($expected as $profile => [$mode, $screen]) {
            $this->format(['profile' => $profile])->assertOk()->assertJsonPath('data.profile', $profile);
            $format = $this->pos('GET', '/bootstrap')->assertOk()->json('data.format');

            $this->assertSame($profile, $format['profile'], $profile);
            $this->assertSame($mode, $format['mode'], $profile);
            $this->assertSame($screen, $format['first_screen'], $profile);
            $this->assertSame($profile === 'stolovaya', $format['locks']['service_charge_zero'], $profile);
            $modules = array_values(array_filter(PosModule::values(), fn (string $key) => in_array($key, array_map(fn (PosModule $m) => $m->value, FormatProfile::from($profile)->modules()), true)));
            $this->assertSame($modules, $format['modules'], $profile);
            $this->assertContains('terminal_card_flow', $format['modules']);
        }

        // The mode the owner sets moves the first screen with it
        $this->format(['profile' => 'osh_markazi', 'mode' => 'table'])->assertOk()->assertJsonPath('data.first_screen', 'hall_map');
        $this->format(['profile' => 'choyxona', 'mode' => 'counter'])->assertOk()->assertJsonPath('data.first_screen', 'quick_counter');
        // The P3-13 queue numbers flag follows the module
        $this->format(['profile' => 'fast_food'])->assertOk();
        $this->assertTrue($this->branch->fresh()->pos_settings['queue_numbers']);
        $this->pos('GET', '/bootstrap')->assertJsonPath('data.branch.pos_settings.queue_numbers', true);
    }

    public function test_effective_modules_are_the_profile_with_overrides_intersected_with_the_plan(): void
    {
        $this->format(['profile' => 'osh_markazi'])->assertOk();
        $this->format(['modules' => ['customer_display' => true, 'queue_calling' => false, 'kds' => true]])->assertOk()
            ->assertJsonPath('data.modules', ['quick_counter', 'queue_numbers', 'customer_display', 'batches', 'batch_writeoff', 'terminal_card_flow', 'service_charge', 'kds']);
        $this->assertEquals(['customer_display' => true, 'kds' => true, 'queue_calling' => false], $this->branch->fresh()->pos_settings['modules']);

        // null goes back to the profile's default; a switch equal to the default is not stored
        $this->format(['modules' => ['queue_calling' => null, 'batches' => true]])->assertOk();
        $this->assertEquals(['customer_display' => true, 'kds' => true], $this->branch->fresh()->pos_settings['modules']);

        // On Start the plan takes away what it lacks: the owner's switch stays, the module is off
        $this->billing()->assignPlan($this->store, $this->v2('start'));
        app(\App\Services\Finance\Billing\Entitlements::class)->forget();
        $response = $this->format([], 'GET')->assertOk();
        $this->assertSame(['quick_counter', 'queue_numbers', 'queue_calling', 'customer_display', 'batches', 'batch_writeoff', 'terminal_card_flow'], $response->json('data.modules'));
        $kds = collect($response->json('data.module_rows'))->firstWhere('key', 'kds');
        $this->assertSame(['configured' => true, 'plan_allows' => false, 'required_plan' => 'asosiy', 'locked' => 'plan', 'enabled' => false], array_intersect_key($kds, array_flip(['configured', 'plan_allows', 'required_plan', 'locked', 'enabled'])));
        $this->pos('GET', '/bootstrap')->assertJsonPath('data.format.modules', $response->json('data.modules'));

        // Switching on what the plan lacks is refused with the plan that has it
        $this->format(['modules' => ['bookings' => true]])->assertForbidden()
            ->assertJsonPath('code', 'plan_feature_missing')->assertJsonPath('required_plan', 'asosiy');
        // Switching it off is always fine
        $this->format(['modules' => ['kds' => false]])->assertOk();
    }

    public function test_a_new_profile_starts_from_its_own_set_and_the_change_is_audited(): void
    {
        $this->format(['modules' => ['kds' => false, 'phone_mode' => true]])->assertOk();
        $this->format(['profile' => 'somsaxona'])->assertOk()
            ->assertJsonPath('data.mode', 'counter')
            ->assertJsonPath('message', "Format o'zgartirildi: Somsaxona.");
        $this->assertSame([], $this->branch->fresh()->pos_settings['modules']);

        $log = AuditLog::where('action', 'settings.pos_format_changed')->latest('id')->firstOrFail();
        $this->assertSame((int) $this->branch->id, (int) $log->branch_id);
        $this->assertSame((int) $this->owner->id, (int) $log->user_id);
        $this->assertEquals(['profile' => 'choyxona', 'mode' => 'hybrid', 'modules' => ['kds' => false, 'phone_mode' => true]], $log->before);
        $this->assertEquals(['profile' => 'somsaxona', 'mode' => 'counter', 'modules' => []], $log->after);
        $this->assertEquals(['profile_changed' => true, 'profile_from' => 'choyxona', 'profile_to' => 'somsaxona'], $log->payload_json);

        // A profile with switches in the same request keeps them; the mode the owner names wins
        $this->format(['profile' => 'kafe', 'mode' => 'counter', 'modules' => ['kds' => false]])->assertOk()
            ->assertJsonPath('data.mode', 'counter');
        $this->assertSame(['kds' => false], $this->branch->fresh()->pos_settings['modules']);
    }

    public function test_the_pos_settings_endpoint_keeps_the_format_and_does_not_take_it(): void
    {
        $this->format(['profile' => 'qahvaxona', 'modules' => ['kds' => false]])->assertOk();

        $this->as($this->owner, 'PUT', "/api/v1/owner/branches/{$this->branch->id}/pos-settings", ['pos_settings' => ['screen_lock_minutes' => 9]])->assertOk();
        $stored = $this->branch->fresh()->pos_settings;
        $this->assertSame('qahvaxona', $stored['profile']);
        $this->assertSame(['kds' => false], $stored['modules']);
        $this->assertSame(9, $stored['screen_lock_minutes']);

        $this->as($this->owner, 'PUT', "/api/v1/owner/branches/{$this->branch->id}/pos-settings", ['pos_settings' => ['profile' => 'kafe']])
            ->assertUnprocessable()->assertJsonValidationErrors('pos_settings.profile');
    }

    public function test_the_stolovaya_takes_no_service_charge(): void
    {
        $this->format(['profile' => 'stolovaya'])->assertOk()->assertJsonPath('data.locks.service_charge_zero', true);
        $row = collect($this->format([], 'GET')->json('data.module_rows'))->firstWhere('key', 'service_charge');
        $this->assertSame('law', $row['locked']);
        $this->assertFalse($row['enabled']);

        $this->format(['modules' => ['service_charge' => true]])->assertUnprocessable()
            ->assertJsonPath('code', 'service_charge_locked')->assertJsonPath('data.module', 'service_charge');

        // The branch's own service charge: refused
        $this->as($this->owner, 'PUT', '/api/v1/owner/service-fees', ['branch_id' => $this->branch->id, 'service_charge_percent' => '10', 'place_fee_amount' => '0', 'place_fee_mode' => 'per_table'])
            ->assertUnprocessable()->assertJsonPath('code', 'service_charge_locked');
        $hall = Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Liniya']);
        $this->as($this->owner, 'PUT', '/api/v1/owner/service-fees', ['hall_id' => $hall->id, 'service_charge_percent' => '5', 'place_fee_amount' => '0', 'place_fee_mode' => 'per_table'])
            ->assertUnprocessable()->assertJsonPath('code', 'service_charge_locked');
        // 0% is fine
        $this->as($this->owner, 'PUT', '/api/v1/owner/service-fees', ['branch_id' => $this->branch->id, 'service_charge_percent' => '0', 'place_fee_amount' => '1000', 'place_fee_mode' => 'per_table'])->assertOk();

        // A store-wide 12% (the choyxona branch charges it) reads as 0% in the stolovaya; its place fee stays
        $this->as($this->owner, 'PUT', '/api/v1/owner/service-fees', ['service_charge_percent' => '12', 'place_fee_amount' => '0', 'place_fee_mode' => 'per_table'])->assertOk();
        ServiceFeeSetting::create(['store_id' => $this->store->id, 'branch_id' => null, 'hall_id' => $hall->id, 'service_charge_percent' => '15', 'place_fee_amount' => '0', 'place_fee_mode' => 'per_table']);
        $fees = app(DiningFeeCalculator::class);
        $this->assertSame('12000.00', $fees->calculate((int) $this->store->id, (int) $this->otherBranch->id, OrderChannel::POS_HALL, Money::of('100000'), 2)['service_charge']->toString());
        $stolovaya = $fees->calculate((int) $this->store->id, (int) $this->branch->id, OrderChannel::POS_HALL, Money::of('100000'), 2);
        $this->assertSame('0.00', $stolovaya['service_charge']->toString());
        $this->assertSame('1000.00', $stolovaya['place_fee']->toString());
        $inHall = $fees->settingsForHall((int) $this->store->id, (int) $this->branch->id, (int) $hall->id);
        $this->assertSame('0.00', $inHall?->service_charge_percent);
        $this->assertSame('15.00', ServiceFeeSetting::where('hall_id', $hall->id)->value('service_charge_percent')); // never saved over
    }

    public function test_access_to_the_format_endpoints(): void
    {
        // settings.manage: the owner (a manager does not hold it, and it cannot be granted)
        $this->as($this->manager, 'GET', '/api/v1/owner/pos/format')->assertForbidden();
        $this->as($this->manager, 'PUT', '/api/v1/owner/pos/format', ['profile' => 'kafe'])->assertForbidden();

        // A cashier, a terminal PIN session and another store: no
        $this->as($this->cashier, 'GET', '/api/v1/owner/pos/format')->assertForbidden();
        $this->withBearer($this->cashierToken)->getJson('/api/v1/owner/pos/format')->assertForbidden();
        $this->as($this->owner, 'GET', '/api/v1/owner/pos/format?branch_id=' . $this->makeBranch($this->makeStore('Begona'), 'B')->id)->assertNotFound();
        // The owner names the branch
        $this->as($this->owner, 'GET', '/api/v1/owner/pos/format')->assertUnprocessable();

        // Validation: a profile, a mode and module keys that exist
        $this->format(['profile' => 'pizzeria'])->assertUnprocessable()->assertJsonValidationErrors('profile');
        $this->format(['mode' => 'drive'])->assertUnprocessable()->assertJsonValidationErrors('mode');
        $this->format(['modules' => ['teleport' => true]])->assertUnprocessable()->assertJsonValidationErrors('modules.teleport');
        $this->format(['modules' => ['kds' => 'maybe']])->assertUnprocessable();
    }

    public function test_the_owner_page_lists_profiles_and_the_template(): void
    {
        $data = $this->format([], 'GET')->assertOk()->json('data');

        $this->assertSame(FormatProfile::values(), array_column($data['profiles'], 'key'));
        $osh = collect($data['profiles'])->firstWhere('key', 'osh_markazi');
        $this->assertSame(['counter', 'quick_counter', 'start', ['Osh tarqatish']], [$osh['mode'], $osh['first_screen'], $osh['recommended_plan'], $osh['stations']]);
        $this->assertTrue(collect($data['profiles'])->firstWhere('key', 'stolovaya')['locks_service_charge']);
        $this->assertSame(PosModule::values(), array_column($data['module_rows'], 'key'));
        $this->assertSame('choyxona', $data['template']['profile']);
        $this->assertSame(['Osh va qozon', 'Mangal', 'Salat va bar', 'Choy'], array_column($data['template']['stations'], 'name'));
    }

    public function test_an_application_format_picks_the_profile_of_a_new_store(): void
    {
        $mapping = [
            'restaurant' => 'restoran', 'cafe' => 'kafe', 'fast_food' => 'fast_food', 'canteen' => 'stolovaya',
            'coffee_shop' => 'qahvaxona', 'bakery' => 'somsaxona', 'teahouse' => 'choyxona', 'catering' => 'toyxona', 'other' => 'kafe',
        ];
        $this->assertSame(StoreFormat::values(), array_keys($mapping));
        foreach ($mapping as $format => $profile) {
            $this->assertSame($profile, FormatProfile::fromStoreFormat($format)->value, $format);
        }
        $this->assertSame(FormatProfile::CHOYXONA, FormatProfile::fromStoreFormat(null));
        $this->assertSame(['profile' => 'stolovaya', 'modules' => [], 'mode' => 'counter'], StoreProvisioner::posSettingsFor(StoreFormat::CANTEEN));
        $this->assertNull(StoreProvisioner::posSettingsFor(null));

        \Illuminate\Support\Facades\Http::fake();
        $region = \App\Models\Region::forceCreate(['name_uz' => 'Toshkent shahri', 'is_active' => true]);
        $district = \App\Models\District::forceCreate(['region_id' => $region->id, 'name_uz' => 'Chilonzor tumani', 'is_active' => true]);
        $created = app(StoreProvisioner::class)->create([
            'name' => 'Yangi osh markazi', 'owner_name' => 'Aziz', 'owner_phone' => '+998901112233',
            'branch_region_id' => (int) $region->id, 'branch_district_id' => (int) $district->id,
            'branch_address' => 'Chilonzor', 'branch_lat' => 41.3, 'branch_lng' => 69.2, 'format' => 'fast_food',
        ]);
        $this->assertSame('fast_food', app(PosModules::class)->profile($created['branch']->fresh())->value);
        $this->assertSame('counter', $created['branch']->fresh()->pos_settings['mode']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function as(\App\Models\User $user, string $method, string $uri, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->actingAs($user, 'sanctum')->json($method, $uri, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function format(array $data, string $method = 'PUT'): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $uri = '/api/v1/owner/pos/format' . ($method === 'GET' ? '?branch_id=' . $this->branch->id : '');

        return $this->actingAs($this->owner, 'sanctum')->json($method, $uri, $method === 'GET' ? [] : ['branch_id' => $this->branch->id, ...$data]);
    }
}
