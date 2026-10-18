<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Superadmin\Resources\GlobalUsers\Pages\ManageGlobalUsers;
use App\Http\Middleware\EnsureStaffHasStore;
use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AccessStorelessStaffTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;
    protected Store $otherStore;
    protected User $superAdmin;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create(['name' => 'Own Store', 'slug' => 'own-store', 'commission_rate' => 10, 'status' => 'active']);
        $this->otherStore = Store::create(['name' => 'Other Store', 'slug' => 'other-store', 'commission_rate' => 10, 'status' => 'active']);
        $this->superAdmin = User::create(['name' => 'Platform Admin', 'email' => 'root@rizqim.uz', 'password' => Hash::make('a-strong-password'), 'role' => UserRole::SUPER_ADMIN]);
        $this->customer = User::create(['name' => 'Customer', 'telegram_id' => '5000001', 'role' => UserRole::CUSTOMER]);
    }

    private function staff(UserRole $role, ?Store $store, string $telegramId): User
    {
        return User::create(['name' => ucfirst($role->value), 'telegram_id' => $telegramId, 'store_id' => $store?->id, 'role' => $role]);
    }

    public function test_storeless_owner_cannot_delete_global_accounts(): void
    {
        $storelessOwner = $this->staff(UserRole::OWNER, null, '5000002');

        $this->actingAs($storelessOwner, 'sanctum')->deleteJson("/api/v1/owner/employees/{$this->superAdmin->id}")->assertForbidden();
        $this->actingAs($storelessOwner, 'sanctum')->deleteJson("/api/v1/owner/employees/{$this->customer->id}")->assertForbidden();
        $this->actingAs($storelessOwner, 'sanctum')->getJson('/api/v1/owner/employees')->assertForbidden();

        // The controller refuses too, even without the route middleware
        $this->withoutMiddleware(EnsureStaffHasStore::class);
        $this->actingAs($storelessOwner, 'sanctum')->deleteJson("/api/v1/owner/employees/{$this->superAdmin->id}")->assertForbidden();
        $this->actingAs($storelessOwner, 'sanctum')
            ->postJson("/api/v1/owner/employees/{$this->customer->id}/transfer", ['branch_id' => Branch::create(['store_id' => $this->store->id, 'name' => 'B'])->id])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $this->superAdmin->id]);
        $this->assertDatabaseHas('users', ['id' => $this->customer->id]);
    }

    public function test_owner_can_only_remove_managers_and_couriers_of_own_store(): void
    {
        $owner = $this->staff(UserRole::OWNER, $this->store, '5000003');
        $coOwner = $this->staff(UserRole::OWNER, $this->store, '5000004');
        $foreignCourier = $this->staff(UserRole::COURIER, $this->otherStore, '5000005');
        $courier = $this->staff(UserRole::COURIER, $this->store, '5000006');

        foreach ([$owner, $coOwner, $foreignCourier, $this->customer, $this->superAdmin] as $target) {
            $this->actingAs($owner, 'sanctum')->deleteJson("/api/v1/owner/employees/{$target->id}")->assertNotFound();
            $this->assertDatabaseHas('users', ['id' => $target->id]);
        }

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/owner/employees')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $courier->id);

        $this->actingAs($owner, 'sanctum')->deleteJson("/api/v1/owner/employees/{$courier->id}")->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $courier->id]);
    }

    public function test_storeless_staff_are_rejected_on_store_scoped_routes(): void
    {
        foreach ([UserRole::OWNER, UserRole::MANAGER, UserRole::COURIER] as $i => $role) {
            $storeless = $this->staff($role, null, '50001' . $i);

            foreach (['/api/v1/orders', '/api/v1/couriers', '/api/v1/complaints', '/api/v1/owner/branch-variants', '/api/v1/user'] as $uri) {
                $this->actingAs($storeless, 'sanctum')->getJson($uri)->assertForbidden();
            }
        }

        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/user')->assertOk();
        $this->actingAs($this->superAdmin, 'sanctum')->getJson('/api/v1/superadmin/dashboard')->assertOk();
    }

    public function test_storeless_staff_cannot_enter_the_admin_panel_or_list_global_users(): void
    {
        $storelessOwner = $this->staff(UserRole::OWNER, null, '5000020');

        $this->assertFalse($storelessOwner->canAccessPanel(Filament::getPanel('admin')));

        $this->actingAs($storelessOwner);
        $this->assertSame(0, EmployeeResource::getEloquentQuery()->count());
    }

    public function test_superadmin_user_form_requires_a_store_for_staff_roles(): void
    {
        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel(Filament::getPanel('superadmin'));

        Livewire::test(ManageGlobalUsers::class)
            ->callAction('create', data: ['name' => 'No Store Owner', 'role' => UserRole::OWNER->value, 'password' => 'secret-password'])
            ->assertHasActionErrors(['store_id' => 'required']);
        $this->assertDatabaseMissing('users', ['name' => 'No Store Owner']);

        Livewire::test(ManageGlobalUsers::class)
            ->callAction('create', data: ['name' => 'Store Owner', 'role' => UserRole::OWNER->value, 'store_id' => $this->store->id, 'password' => 'secret-password'])
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('users', ['name' => 'Store Owner', 'store_id' => $this->store->id, 'role' => 'owner']);

        Livewire::test(ManageGlobalUsers::class)
            ->callAction('create', data: ['name' => 'Plain Customer', 'role' => UserRole::CUSTOMER->value, 'store_id' => $this->store->id, 'password' => 'secret-password'])
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('users', ['name' => 'Plain Customer', 'store_id' => null, 'role' => 'customer']);
    }

    public function test_production_check_reports_storeless_staff_count(): void
    {
        $this->staff(UserRole::OWNER, null, '5000030');
        $this->staff(UserRole::COURIER, null, '5000031');
        config(['app.env' => 'production', 'app.debug' => false, 'telegram.allow_mock_auth' => false, 'services.telegram.webhook_secret' => 'valid_secret-1',
            'services.telegram.webapp_url' => 'https://app.rizqim.example/', 'app.key' => 'base64:' . base64_encode(str_repeat('k', 32))]);

        $exitCode = Artisan::call('app:check-production');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('2 ta xodim hisobi', Artisan::output());
    }
}
