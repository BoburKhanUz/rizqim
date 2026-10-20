<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\StaffPermissions;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\Devices\DeviceResource;
use App\Filament\Resources\Devices\Pages\ManageDevices;
use App\Filament\Resources\Employees\Pages\ManageEmployees;
use App\Models\AuditLog;
use App\Models\DevicePairingCode;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Enums\Permission;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Xodimlar va ruxsatlar" in the store panel: roles, PINs, devices, pairing codes, permissions and the audit log,
 * each limited to the tenant store (and a branch manager's branch).
 */
class StaffAccessPanelTest extends TestCase
{
    use RefreshDatabase;
    use AccessFixtures;

    private Store $foreignStore;
    private User $foreignOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->foreignStore = $this->makeStore('Foreign Grill');
        $this->foreignOwner = $this->makeStaff(UserRole::OWNER, null, $this->foreignStore);
    }

    private function signIn(User $user, ?Store $tenant = null): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($tenant ?? $this->store);
    }

    public function test_the_owner_assigns_new_roles_and_sets_pins(): void
    {
        \Illuminate\Support\Facades\Http::fake();
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $foreignCook = $this->makeStaff(UserRole::COOK, null, $this->foreignStore);
        $this->signIn($this->owner);

        Livewire::test(ManageEmployees::class)
            ->assertCanSeeTableRecords([$this->manager, $this->courier, $cashier])
            ->assertCanNotSeeTableRecords([$foreignCook, $this->owner])
            ->callAction('create', data: ['name' => 'Oshpaz Ali', 'role' => UserRole::COOK->value, 'branch_id' => $this->branch->id])
            ->assertHasNoActionErrors()
            ->callTableAction('setPin', $cashier, data: ['pin' => '1234', 'pin_confirmation' => '1234'])
            ->assertHasTableActionErrors(['pin']);
        Livewire::test(ManageEmployees::class)
            ->callTableAction('setPin', $cashier, data: ['pin' => '4831', 'pin_confirmation' => '4831'])
            ->assertHasNoTableActionErrors();
        Livewire::test(ManageEmployees::class)
            ->callAction('myPin', data: ['pin' => '5926', 'pin_confirmation' => '5926'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('users', ['name' => 'Oshpaz Ali', 'role' => 'cook', 'store_id' => $this->store->id]);
        $this->assertTrue(Hash::check('4831', $cashier->fresh()->pin_hash));
        $this->assertTrue(Hash::check('5926', $this->owner->fresh()->pin_hash));

        Livewire::test(ManageEmployees::class)->callTableAction('resetPin', $cashier->fresh());
        $this->assertNull($cashier->fresh()->pin_hash);
        $this->assertSame(2, AuditLog::whereIn('action', ['staff.pin_set', 'staff.pin_reset'])->where('model_id', $cashier->id)->count());
    }

    public function test_a_branch_manager_manages_floor_staff_of_their_branch(): void
    {
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $secondBranchWaiter = $this->makeStaff(UserRole::WAITER, $this->otherBranch);
        $this->signIn($this->manager);

        Livewire::test(ManageEmployees::class)
            ->assertCanSeeTableRecords([$this->courier, $cashier])
            ->assertCanNotSeeTableRecords([$secondBranchWaiter, $this->manager])
            ->assertTableActionVisible('setPin', $cashier)
            ->callTableAction('setPin', $cashier, data: ['pin' => '4831', 'pin_confirmation' => '4831']);

        $this->assertNotNull($cashier->fresh()->pin_hash);
    }

    public function test_devices_are_listed_paired_and_revoked_within_the_tenant(): void
    {
        ['device' => $ours] = $this->pairTerminal($this->owner, $this->branch);
        ['device' => $theirs] = $this->pairTerminal($this->foreignOwner);
        $this->signIn($this->owner);

        Livewire::test(ManageDevices::class)
            ->assertCanSeeTableRecords([$ours])
            ->assertCanNotSeeTableRecords([$theirs])
            ->callAction('pairingCode', data: ['type' => 'kds', 'branch_id' => $this->branch->id, 'name' => 'Oshxona'])
            ->assertHasNoActionErrors()
            ->callTableAction('rename', $ours, data: ['name' => 'Kassa 2'])
            ->callTableAction('revoke', $ours);

        $this->assertSame(1, DevicePairingCode::where('store_id', $this->store->id)->where('type', 'kds')->count());
        $this->assertSame('Kassa 2', $ours->fresh()->name);
        $this->assertNotNull($ours->fresh()->revoked_at);
        $this->assertNull($theirs->fresh()->revoked_at);

        // A cashier has no device screen
        $this->signIn($this->makeStaff(UserRole::CASHIER, $this->branch));
        $this->assertFalse(DeviceResource::canViewAny());
    }

    public function test_the_audit_log_is_the_owners_and_shows_only_this_store(): void
    {
        $ours = AuditLog::create(['action' => 'order.cancelled', 'store_id' => $this->store->id]);
        $theirs = AuditLog::create(['action' => 'order.cancelled', 'store_id' => $this->foreignStore->id]);

        $this->signIn($this->owner);
        Livewire::test(ListAuditLogs::class)->assertCanSeeTableRecords([$ours])->assertCanNotSeeTableRecords([$theirs]);

        $this->signIn($this->manager);
        $this->assertFalse(AuditLogResource::canViewAny());
    }

    public function test_the_owner_toggles_permissions_and_managers_only_look(): void
    {
        $this->signIn($this->owner);
        Livewire::test(StaffPermissions::class)
            ->assertSee('Ofitsiant')
            ->call('toggle', 'waiter', 'orders.discount')
            ->call('toggle', 'owner', 'orders.view')
            ->call('toggle', 'manager', 'finance.manage');

        $waiter = $this->makeStaff(UserRole::WAITER, $this->branch);
        $service = app(PermissionService::class);
        $this->assertTrue($service->has($waiter, Permission::ORDERS_DISCOUNT));
        $this->assertTrue($service->has($this->owner, Permission::ORDERS_VIEW));
        $this->assertFalse($service->has($this->manager, Permission::FINANCE_MANAGE));
        $this->assertSame(1, AuditLog::where('action', 'permissions.changed')->count());

        // Toggling back returns to the default (no override row left)
        Livewire::test(StaffPermissions::class)->call('toggle', 'waiter', 'orders.discount');
        $this->assertDatabaseCount('role_permission_overrides', 0);

        $this->signIn($this->manager);
        Livewire::test(StaffPermissions::class)->call('toggle', 'waiter', 'orders.discount')->assertForbidden();
        $this->assertDatabaseCount('role_permission_overrides', 0);
    }

    public function test_another_stores_owner_cannot_open_this_tenant(): void
    {
        $this->actingAs($this->foreignOwner)
            ->get("/admin/{$this->store->uuid}/devices")
            ->assertNotFound();
    }
}
