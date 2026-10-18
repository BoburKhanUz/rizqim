<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\StoreSettings;
use App\Filament\Superadmin\Resources\GlobalStores\Pages\EditGlobalStore;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Values come from config(), never env(): once `php artisan config:cache` has run, env() returns null outside config/.
 */
class TelegramBotLinkConfigTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_username' => 'rizqim_live_bot']);
        $this->setUpLifecycleStore();
    }

    public function test_employee_invite_link_uses_the_configured_bot(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/employees/invite', [
            'role' => 'courier',
            'name' => 'Yangi Kuryer',
            'branch_id' => $this->branch->id,
        ])->assertOk();

        $token = User::where('name', 'Yangi Kuryer')->value('telegram_invite_token');
        $response->assertJsonPath('data.invite_link', "https://t.me/rizqim_live_bot?start=invite_{$token}");
    }

    public function test_store_settings_page_links_the_configured_bot(): void
    {
        $this->actingAs($this->owner);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->store);

        Livewire::test(StoreSettings::class)
            ->assertSee("https://t.me/rizqim_live_bot?start=store_{$this->store->uuid}", false);
    }

    public function test_superadmin_store_form_links_the_configured_bot(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password'),
            'role' => UserRole::SUPER_ADMIN->value,
        ]);
        $this->actingAs($admin);
        Filament::setCurrentPanel('superadmin');

        Livewire::test(EditGlobalStore::class, ['record' => $this->store->getRouteKey()])
            ->assertSee("https://t.me/rizqim_live_bot?start=store_{$this->store->uuid}", false);
    }

    public function test_setting_falls_back_to_config_and_a_stored_row_wins(): void
    {
        Cache::forget('setting_sms_driver');
        config(['settings.sms_driver' => 'eskiz']);

        $this->assertSame('eskiz', setting('sms_driver', 'log'));

        Setting::create(['key' => 'sms_driver', 'value' => 'log']);
        $this->assertSame('log', setting('sms_driver', 'eskiz'));

        Cache::forget('setting_eskiz_from');
        config(['settings.eskiz_from' => null]);
        $this->assertSame('4546', setting('eskiz_from', '4546'));
    }
}
