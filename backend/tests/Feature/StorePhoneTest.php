<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\StoreSettings;
use App\Filament\Pages\Tenancy\EditStoreProfile;
use App\Filament\Superadmin\Resources\GlobalStores\Pages\CreateGlobalStore;
use App\Filament\Superadmin\Resources\GlobalStores\Pages\EditGlobalStore;
use App\Jobs\SendTelegramRequest;
use App\Models\District;
use App\Models\Region;
use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The store's own phone: every form that asks for it saves it (it used to be dropped for want of a column), and the
 * places that show the store's contact details show it.
 */
class StorePhoneTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    private User $admin;
    private Region $region;
    private District $district;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_token' => '123456:store-phone']);
        Queue::fake([SendTelegramRequest::class]);

        $this->setUpLifecycleStore();
        $this->admin = User::create([
            'name' => 'Super Admin',
            'phone' => '+998901110001',
            'role' => UserRole::SUPER_ADMIN->value,
            'password' => bcrypt('password'),
        ]);
        $this->region = Region::create(['name_uz' => 'Toshkent shahri', 'is_active' => true]);
        $this->district = District::create(['region_id' => $this->region->id, 'name_uz' => 'Chilonzor tumani', 'is_active' => true]);
    }

    public function test_a_store_created_from_the_mini_app_keeps_its_phone(): void
    {
        $created = $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/superadmin/stores', [
            'name' => 'Osh Markazi',
            'phone' => '+998712001122',
            'commission_rate' => 10,
            'owner_name' => 'Aziz',
            'branch_region_id' => $this->region->id,
            'branch_district_id' => $this->district->id,
            'branch_address' => 'Chilonzor 1-kvartal',
            'branch_lat' => 41.285,
            'branch_lng' => 69.204,
            'branch_radius' => 5,
        ])->assertOk();

        $this->assertSame('+998712001122', Store::findOrFail($created->json('data.store_id'))->phone);

        $listed = collect($this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/superadmin/stores')->assertOk()->json('data'))
            ->firstWhere('id', $created->json('data.store_id'));
        $this->assertSame('+998712001122', $listed['phone']);
    }

    public function test_a_store_created_with_the_panel_wizard_keeps_its_phone(): void
    {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('superadmin');

        Livewire::test(CreateGlobalStore::class)
            ->fillForm([
                'name' => 'Wizard Cafe',
                'phone' => '+998712003344',
                'commission_rate' => 10,
                'payment_account_type' => 'platform_account',
                'owner_name' => 'Wizard Owner',
                'owner_phone' => '+998901110002',
                'owner_password' => 'secret123',
                'branch_region_id' => $this->region->id,
                'branch_district_id' => $this->district->id,
                'branch_address' => 'Bunyodkor 1',
                'branch_radius' => 5,
                'branch_lat' => 41.3,
                'branch_lng' => 69.2,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('+998712003344', Store::where('name', 'Wizard Cafe')->sole()->phone);

        // The superadmin store form edits it afterwards
        Livewire::test(EditGlobalStore::class, ['record' => Store::where('name', 'Wizard Cafe')->sole()->getRouteKey()])
            ->assertSet('data.phone', '+998712003344')
            ->fillForm(['phone' => '+998712009999', 'active_payment_methods' => ['cash', 'click', 'payme']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('+998712009999', Store::where('name', 'Wizard Cafe')->sole()->phone);
    }

    public function test_the_owner_edits_it_on_the_settings_and_profile_pages(): void
    {
        $this->actingAs($this->owner);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($this->store->fresh());

        Livewire::test(StoreSettings::class)
            ->set('data.phone', '+998712005566')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('+998712005566', $this->store->fresh()->phone);

        Livewire::test(EditStoreProfile::class)
            ->assertSet('data.phone', '+998712005566')
            ->fillForm(['phone' => '+998712007788'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('+998712007788', $this->store->fresh()->phone);
    }

    public function test_the_owner_settings_api_reads_and_updates_it_and_keeps_it_when_left_out(): void
    {
        $this->store->update(['phone' => '+998712001100']);
        $settings = ['is_open' => true, 'payment_settings' => ['delivery_fee' => 0]];

        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/settings')
            ->assertOk()
            ->assertJsonPath('data.phone', '+998712001100');

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/settings', $settings + ['phone' => '+998712002200'])->assertOk();
        $this->assertSame('+998712002200', $this->store->fresh()->phone);

        // A client that does not send the phone (the settings screen before it had the field) keeps it
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/settings', $settings)->assertOk();
        $this->assertSame('+998712002200', $this->store->fresh()->phone);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/settings', $settings + ['phone' => null])->assertOk();
        $this->assertNull($this->store->fresh()->phone);
    }

    public function test_customers_see_it_in_the_store_list_and_the_catalog(): void
    {
        $this->store->update(['phone' => '+998712004455']);

        $stores = collect($this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/stores')->assertOk()->json('data'))->keyBy('uuid');
        $this->assertSame('+998712004455', $stores[$this->store->uuid]['phone']);

        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/catalog?store_id=' . $this->store->uuid)
            ->assertOk()
            ->assertJsonPath('data.store.phone', '+998712004455');
    }
}
