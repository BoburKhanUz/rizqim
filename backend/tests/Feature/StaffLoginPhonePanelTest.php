<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Employees\Pages\ManageEmployees;
use App\Filament\Superadmin\Resources\GlobalStores\Pages\CreateGlobalStore;
use App\Filament\Superadmin\Resources\GlobalUsers\Pages\ManageGlobalUsers;
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
 * P1-36: where a staff member's login phone comes from. The phone the store owner (or a manager, for the staff below
 * them) saves in the employee form, and the owner's phone the super admin enters, is vouched for and signs in by SMS;
 * a phone typed anywhere else stays a contact detail until proven with a code.
 */
class StaffLoginPhonePanelTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_token' => '123456:login-phone']);
        Queue::fake([SendTelegramRequest::class]);

        $this->setUpLifecycleStore();
        $this->admin = User::create(['name' => 'Super Admin', 'role' => UserRole::SUPER_ADMIN->value, 'email' => 'root@example.uz', 'password' => bcrypt('password')]);
    }

    private function signIn(User $user, ?Store $tenant = null): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($tenant ?? $this->store);
    }

    public function test_the_phone_saved_with_a_new_employee_is_their_login_phone(): void
    {
        $this->signIn($this->owner);

        Livewire::test(ManageEmployees::class)
            ->callAction('create', data: ['name' => 'App Courier', 'role' => UserRole::COURIER->value, 'branch_id' => $this->branch->id, 'phone' => '+998901230001'])
            ->assertHasNoActionErrors();

        $courier = User::where('name', 'App Courier')->sole();
        $this->assertSame('+998901230001', $courier->phone);
        $this->assertNotNull($courier->phone_verified_at);
    }

    public function test_editing_the_phone_vouches_for_the_new_one(): void
    {
        $this->courier->update(['phone' => '+998901230002']);
        $this->assertNull($this->courier->fresh()->phone_verified_at);
        $this->signIn($this->owner);

        Livewire::test(ManageEmployees::class)
            ->callTableAction('edit', $this->courier, data: ['phone' => '+998901230003'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('+998901230003', $this->courier->fresh()->phone);
        $this->assertNotNull($this->courier->fresh()->phone_verified_at);
    }

    public function test_a_manager_sets_the_phone_of_their_couriers(): void
    {
        $this->signIn($this->manager);

        Livewire::test(ManageEmployees::class)
            ->callTableAction('edit', $this->courier, data: ['phone' => '+998901230004'])
            ->assertHasNoTableActionErrors();

        $this->assertNotNull($this->courier->fresh()->phone_verified_at);
    }

    public function test_a_phone_is_one_staff_account_per_store_and_a_proper_number(): void
    {
        $this->signIn($this->owner);
        $this->owner->update(['phone' => '+998901230005']);

        Livewire::test(ManageEmployees::class)
            ->callAction('create', data: ['name' => 'Twin', 'role' => UserRole::COURIER->value, 'branch_id' => $this->branch->id, 'phone' => '+998901230005'])
            ->assertHasActionErrors(['phone' => 'unique']);
        Livewire::test(ManageEmployees::class)
            ->callAction('create', data: ['name' => 'Typo', 'role' => UserRole::COURIER->value, 'branch_id' => $this->branch->id, 'phone' => '90 123 00 05'])
            ->assertHasActionErrors(['phone' => 'regex']);
        $this->assertDatabaseMissing('users', ['name' => 'Twin']);
        $this->assertDatabaseMissing('users', ['name' => 'Typo']);

        // The same person may work in another store
        $elsewhere = $this->makeStore('Elsewhere');
        $elsewhereBranch = $this->makeBranch($elsewhere, 'Elsewhere Branch');
        $this->signIn($this->makeStaff(UserRole::OWNER, null, $elsewhere), $elsewhere);
        Livewire::test(ManageEmployees::class)
            ->callAction('create', data: ['name' => 'Moonlighter', 'role' => UserRole::COURIER->value, 'branch_id' => $elsewhereBranch->id, 'phone' => '+998901230005'])
            ->assertHasNoActionErrors();
        $this->assertNotNull(User::where('name', 'Moonlighter')->sole()->phone_verified_at);
    }

    public function test_the_owner_phone_of_the_store_wizard_is_the_owner_login_phone(): void
    {
        // The owner-to-be already orders in the Mini App with the same phone: that customer account does not block it
        $customer = $this->makeCustomer(0, ['phone' => '+998901230006']);
        $customer->forceFill(['phone_verified_at' => now()])->save();
        $region = Region::create(['name_uz' => 'Toshkent shahri', 'is_active' => true]);
        $district = District::create(['region_id' => $region->id, 'name_uz' => 'Chilonzor tumani', 'is_active' => true]);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('superadmin');

        $form = [
            'name' => 'Wizard Cafe',
            'commission_rate' => 10,
            'payment_account_type' => 'platform_account',
            'owner_name' => 'Wizard Owner',
            'owner_password' => 'secret123',
            'branch_region_id' => $region->id,
            'branch_district_id' => $district->id,
            'branch_address' => 'Bunyodkor 1',
            'branch_radius' => 5,
            'branch_lat' => 41.3,
            'branch_lng' => 69.2,
        ];

        Livewire::test(CreateGlobalStore::class)
            ->fillForm($form + ['owner_phone' => '90 123 00 06'])
            ->call('create')
            ->assertHasFormErrors(['owner_phone' => 'regex']);
        // Another staff account holding it does block it
        Livewire::test(CreateGlobalStore::class)
            ->fillForm($form + ['owner_phone' => $this->manager->phone])
            ->call('create')
            ->assertHasFormErrors(['owner_phone' => 'unique']);

        Livewire::test(CreateGlobalStore::class)
            ->fillForm($form + ['owner_phone' => '+998901230006'])
            ->call('create')
            ->assertHasNoFormErrors();

        $owner = User::where('name', 'Wizard Owner')->sole();
        $this->assertSame(UserRole::OWNER, $owner->role);
        $this->assertSame('+998901230006', $owner->phone);
        $this->assertNotNull($owner->phone_verified_at);
        $this->assertSame(UserRole::CUSTOMER, $customer->fresh()->role);
    }

    public function test_the_super_admin_vouches_for_an_existing_staff_phone(): void
    {
        $this->owner->update(['phone' => '+998901230007']);
        $this->customer->update(['phone' => '+998901230008']);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('superadmin');

        Livewire::test(ManageGlobalUsers::class)
            ->assertTableActionVisible('vouchLoginPhone', $this->owner)
            // Customers prove their phone with a code only
            ->assertTableActionHidden('vouchLoginPhone', $this->customer->fresh())
            ->callTableAction('vouchLoginPhone', $this->owner);

        $this->assertNotNull($this->owner->fresh()->phone_verified_at);
        $this->assertNull($this->customer->fresh()->phone_verified_at);
    }

    public function test_the_mini_app_store_creation_vouches_for_a_proper_owner_phone_only(): void
    {
        $region = Region::create(['name_uz' => 'Toshkent shahri', 'is_active' => true]);
        $district = District::create(['region_id' => $region->id, 'name_uz' => 'Chilonzor tumani', 'is_active' => true]);
        $store = fn (string $name, string $phone) => $this->actingAs($this->admin, 'sanctum')->postJson('/api/v1/superadmin/stores', [
            'name' => $name,
            'commission_rate' => 10,
            'owner_name' => "{$name} Owner",
            'owner_phone' => $phone,
            'branch_region_id' => $region->id,
            'branch_district_id' => $district->id,
            'branch_address' => 'Chilonzor 1-kvartal',
            'branch_lat' => 41.285,
            'branch_lng' => 69.204,
            'branch_radius' => 5,
        ])->assertOk();

        $store('Osh Markazi', '+998901230009');
        $store('Somsa Uyi', '90 123 00 10');

        $this->assertNotNull(User::where('name', 'Osh Markazi Owner')->sole()->phone_verified_at);
        $this->assertNull(User::where('name', 'Somsa Uyi Owner')->sole()->phone_verified_at);
    }
}
