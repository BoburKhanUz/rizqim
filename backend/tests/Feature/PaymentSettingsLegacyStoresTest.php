<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\SendTelegramRequest;
use App\Models\District;
use App\Models\Region;
use App\Models\Store;
use App\Models\User;
use App\Services\Admin\Stores\StoreProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Stores made by the first creation wizard hold providers as bare booleans (`cash: true`) and no delivery/pickup
 * switches; editing them must not switch anything off, and new stores start in the nested form.
 */
class PaymentSettingsLegacyStoresTest extends TestCase
{
    use RefreshDatabase;

    private const WIZARD_SETTINGS = ['cash' => true, 'click' => true, 'payme' => true, 'uzum' => true, 'paynet' => true];

    protected Store $store;
    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::forceCreate([
            'name' => 'Wizard Cafe',
            'status' => 'active',
            'is_active' => true,
            'payment_account_type' => 'own_account',
            'min_order_amount' => 0,
            'open_time' => '09:00:00',
            'close_time' => '22:00:00',
        ]);
        $this->legacySettings(self::WIZARD_SETTINGS);

        $this->owner = User::create([
            'name' => 'Owner',
            'phone' => '+998901110001',
            'role' => UserRole::OWNER->value,
            'store_id' => $this->store->id,
            'password' => bcrypt('password'),
        ]);
    }

    private function legacySettings(array $settings, ?Store $store = null): void
    {
        DB::table('stores')->where('id', ($store ?? $this->store)->id)->update(['payment_settings' => json_encode($settings)]);
    }

    private function assertEverythingStillOn(): void
    {
        $store = $this->store->fresh();
        $settings = $store->payment_settings;

        foreach (['cash', 'click', 'payme'] as $method) {
            $this->assertTrue($settings[$method]['enabled'], "{$method} was switched off");
        }
        $this->assertTrue($settings['delivery_enabled']);
        $this->assertTrue($settings['pickup_enabled']);
        $this->assertTrue((bool) $store->accept_cash);
        $this->assertTrue((bool) $store->accept_click);
        $this->assertTrue((bool) $store->accept_payme);
    }

    public function test_a_partial_save_merges_into_a_legacy_value_instead_of_replacing_it(): void
    {
        $this->store->fresh()->update(['payment_settings' => ['click' => ['merchant_id' => 'm-2']]]);

        $settings = $this->store->fresh()->payment_settings;
        $this->assertEquals(['enabled' => true, 'merchant_id' => 'm-2'], $settings['click']);
        $this->assertEquals(['enabled' => true], $settings['cash']);
    }

    public function test_the_data_migration_rewrites_legacy_rows_once_and_keeps_their_values(): void
    {
        $nested = Store::forceCreate(['name' => 'Nested Cafe', 'payment_settings' => ['cash' => ['enabled' => false], 'delivery_fee' => 5000]]);
        $bare = Store::forceCreate(['name' => 'Bare Cafe']);
        $this->legacySettings(['cash' => false, 'click' => true, 'payme' => ['enabled' => true, 'merchant_id' => 'pm'], 'delivery_fee' => 10000]);
        $migration = require database_path('migrations/2026_10_18_140000_normalize_legacy_store_payment_settings.php');

        $migration->up();
        $migration->up();

        $this->assertEquals([
            'cash' => ['enabled' => false],
            'click' => ['enabled' => true],
            'payme' => ['enabled' => true, 'merchant_id' => 'pm'],
            'delivery_fee' => 10000,
        ], $this->store->fresh()->payment_settings);
        $this->assertEquals(['cash' => ['enabled' => false], 'delivery_fee' => 5000], $nested->fresh()->payment_settings);
        $this->assertNull($bare->fresh()->payment_settings);
    }

    /**
     * @return array{0: User, 1: Region, 2: District}
     */
    private function creationSetup(): array
    {
        config(['services.telegram.bot_token' => '123456:create-store', 'services.telegram.bot_username' => 'rizqim_test_bot']);
        Queue::fake([SendTelegramRequest::class]);
        Http::fake();
        $admin = User::create([
            'name' => 'Super Admin',
            'phone' => '+998901110009',
            'role' => UserRole::SUPER_ADMIN->value,
            'telegram_id' => '777001',
            'password' => bcrypt('password'),
        ]);
        $region = Region::create(['name_uz' => 'Toshkent', 'is_active' => true]);
        $district = District::create(['region_id' => $region->id, 'name_uz' => 'Chilonzor', 'is_active' => true]);

        return [$admin, $region, $district];
    }

    public function test_a_store_created_through_the_provisioner_starts_nested_and_its_telegram_notice_is_queued_unchanged(): void
    {
        [, $region, $district] = $this->creationSetup();

        app(StoreProvisioner::class)->create([
            'name' => 'Wizard Two',
            'commission_rate' => 10,
            'payment_account_type' => 'platform_account',
            'owner_name' => 'Wizard Owner',
            'owner_phone' => '+998901110003',
            'owner_password' => 'secret123',
            'branch_region_id' => $region->id,
            'branch_district_id' => $district->id,
            'branch_address' => 'Bunyodkor 1',
            'branch_radius' => 5,
            'branch_lat' => 41.3,
            'branch_lng' => 69.2,
        ]);

        $wizardStore = Store::where('name', 'Wizard Two')->sole();
        $this->assertEquals(Store::initialPaymentSettings(['cash', 'click', 'payme']), $wizardStore->payment_settings);
        $token = User::where('store_id', $wizardStore->id)->where('role', UserRole::OWNER->value)->value('telegram_invite_token');
        Queue::assertPushed(SendTelegramRequest::class, fn (SendTelegramRequest $job) => $job->params === [
            'chat_id' => '777001',
            'text' => "🏢 <b>Yangi do'kon qo'shildi!</b>\n\nNomi: <b>Wizard Two</b>\nKomissiya: <b>10%</b>\n"
                . "Egasi: <b>Wizard Owner</b> (+998901110003)\nFilial: <b>Bunyodkor 1</b>\n\n"
                . "🔗 <b>Egasi uchun taklifnoma havolasi:</b>\nhttps://t.me/rizqim_test_bot?start=invite_{$token}",
            'parse_mode' => 'HTML',
        ]);
        Queue::assertPushed(SendTelegramRequest::class, 1);
        Http::assertNothingSent();
    }
}
