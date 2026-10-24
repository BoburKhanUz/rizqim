<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * The permission layer: the default role map, store overrides, branch scoping, the Gate and the API around it.
 */
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;
    use AccessFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    /**
     * The documented matrix (docs/AUTH.md). Changing a default is a product decision: update both.
     */
    public function test_the_default_matrix(): void
    {
        $all = Permission::values();
        $expected = [
            'owner' => array_values(array_diff($all, ['orders.refund'])),
            'manager' => ['orders.view', 'orders.manage', 'orders.cancel', 'orders.discount', 'orders.void_item', 'orders.assign_courier',
                'kitchen.manage', 'tables.manage', 'menu.view', 'stoplist.manage', 'staff.view', 'staff.pin', 'devices.view',
                'devices.manage', 'reports.view', 'pos.open_shift', 'pos.close_shift', 'pos.cash_in_out', 'pos.take_payment', 'pos.reprint_bill',
                'batches.manage', // P1-21
                'branches.manage', // P2-12
                'bookings.manage', 'customer_accounts.manage', // P3-17
                'pos.refund'], // P3-18
            'cashier' => ['orders.view', 'orders.manage', 'tables.manage', 'menu.view', 'pos.open_shift', 'pos.close_shift', 'pos.take_payment',
                'bookings.manage', // P3-17
                'pos.reprint_bill', 'pos.cash_in_out'], // P3-37
            'waiter' => ['orders.view', 'orders.manage', 'tables.manage', 'menu.view'],
            'cook' => ['orders.view', 'kitchen.manage', 'menu.view', 'batches.manage'], // P1-21: batches
            'courier' => ['deliveries.manage'],
            'customer' => [],
        ];

        foreach ($expected as $role => $permissions) {
            $actual = array_map(fn (Permission $permission) => $permission->value, RolePermissions::for(UserRole::from($role)));
            $this->assertEqualsCanonicalizing($permissions, $actual, "Default permissions of {$role}");
        }

        $this->assertSame(Permission::cases(), RolePermissions::for(UserRole::SUPER_ADMIN));
    }

    public function test_the_gate_answers_permission_abilities_with_store_and_branch_scope(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);
        $secondBranchOrder = $this->makeOrder(OrderStatus::NEW, ['branch_id' => $this->otherBranch->id]);
        $foreignStore = $this->makeStore('Foreign Grill');
        $foreignOrder = $this->makeOrder(OrderStatus::NEW, ['store_id' => $foreignStore->id, 'branch_id' => $this->makeBranch($foreignStore, 'F')->id]);
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $storeWideWaiter = $this->makeStaff(UserRole::WAITER);

        $this->assertTrue($this->owner->can('orders.cancel', $secondBranchOrder));
        $this->assertFalse($this->owner->can('orders.cancel', $foreignOrder));

        $this->assertTrue($this->manager->can('orders.cancel', $order));
        $this->assertFalse($this->manager->can('orders.cancel', $secondBranchOrder));

        $this->assertTrue($cashier->can('orders.manage', $order));
        $this->assertFalse($cashier->can('orders.cancel', $order));
        $this->assertFalse($cashier->can('orders.manage', $secondBranchOrder));
        $this->assertTrue($storeWideWaiter->can('orders.manage', $secondBranchOrder));

        $this->assertFalse($this->courier->can('orders.view'));
        $this->assertFalse($this->customer->can('orders.view', $order));
        $this->assertTrue(Gate::forUser($this->owner)->allows('settings.manage', $this->store));
        $this->assertFalse(Gate::forUser($this->owner)->allows('orders.refund'));

        // Policies are still policies
        $this->assertTrue($this->manager->can('view', $order));
        $this->assertFalse($this->manager->can('view', $secondBranchOrder));
    }

    public function test_store_overrides_grant_and_take_away_for_that_store_only(): void
    {
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $foreignStore = $this->makeStore('Foreign Grill');
        $foreignCashier = $this->makeStaff(UserRole::CASHIER, null, $foreignStore);
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/permissions', [
            'role' => 'cashier', 'permission' => 'orders.discount', 'allowed' => true,
        ])->assertOk()->assertJsonPath('data.allowed', true);

        $this->assertTrue($cashier->fresh()->can('orders.discount', $order));
        $this->assertFalse(app(PermissionService::class)->has($foreignCashier, Permission::ORDERS_DISCOUNT));

        // Back to the default
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/permissions', [
            'role' => 'cashier', 'permission' => 'orders.discount', 'allowed' => null,
        ])->assertOk()->assertJsonPath('data.allowed', false);
        $this->assertDatabaseCount('role_permission_overrides', 0);
        $this->assertFalse($cashier->fresh()->can('orders.discount', $order));
    }

    public function test_taking_cancel_away_from_managers_stops_them_cancelling(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW);

        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/permissions', [
            'role' => 'manager', 'permission' => 'orders.cancel', 'allowed' => false,
        ])->assertOk();

        $this->setStatus($order, $this->manager, 'canceled')->assertForbidden();
        // Running orders is still theirs
        $this->setStatus($order, $this->manager, 'accepted')->assertOk();
        // The owner always can
        $this->setStatus($order->fresh(), $this->owner, 'canceled')->assertOk();
    }

    public function test_owner_permissions_and_owner_only_rights_cannot_be_overridden(): void
    {
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/permissions', [
            'role' => 'owner', 'permission' => 'settings.manage', 'allowed' => false,
        ])->assertUnprocessable();

        foreach (['orders.refund', 'finance.manage', 'settings.manage', 'staff.manage', 'audit.view', 'permissions.manage'] as $permission) {
            $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/permissions', [
                'role' => 'manager', 'permission' => $permission, 'allowed' => true,
            ])->assertUnprocessable();
        }

        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/permissions', [
            'role' => 'manager', 'permission' => 'nope.nope', 'allowed' => true,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('role_permission_overrides', 0);
    }

    public function test_only_the_owner_edits_permissions(): void
    {
        $payload = ['role' => 'waiter', 'permission' => 'orders.discount', 'allowed' => true];

        $this->actingAs($this->manager, 'sanctum')->putJson('/api/v1/owner/permissions', $payload)->assertForbidden();
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/permissions')->assertForbidden();
        $this->actingAs($this->customer, 'sanctum')->putJson('/api/v1/owner/permissions', $payload)->assertForbidden();

        $matrix = $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/permissions')->assertOk()->json('data');
        $this->assertTrue($matrix['matrix']['manager']['orders.cancel']);
        $this->assertFalse($matrix['matrix']['waiter']['orders.cancel']);
        $this->assertCount(count(Permission::cases()), $matrix['permissions']);
    }

    public function test_a_superadmin_has_no_store_matrix_and_gets_a_clean_refusal(): void
    {
        $admin = User::create(['name' => 'Platform Admin', 'phone' => '+998900000001', 'password' => bcrypt('password'), 'role' => UserRole::SUPER_ADMIN->value]);

        $this->actingAs($admin, 'sanctum')->putJson('/api/v1/owner/permissions', ['role' => 'waiter', 'permission' => 'orders.discount', 'allowed' => true])
            ->assertForbidden()
            ->assertJsonPath('data.reason', 'store_required');
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/owner/permissions')
            ->assertForbidden()
            ->assertJsonPath('data.reason', 'store_required');
        $this->assertDatabaseCount('role_permission_overrides', 0);
    }

    public function test_sales_analytics_follow_the_reports_permission(): void
    {
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/analytics')->assertOk();

        app(PermissionService::class)->setOverride($this->store, UserRole::MANAGER, Permission::REPORTS_VIEW, false, $this->owner);
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/analytics')->assertForbidden();
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/analytics')->assertOk();

        // Reports without the order list: the figures are still theirs
        app(PermissionService::class)->setOverride($this->store, UserRole::MANAGER, Permission::REPORTS_VIEW, null, $this->owner);
        app(PermissionService::class)->setOverride($this->store, UserRole::MANAGER, Permission::ORDERS_VIEW, false, $this->owner);
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/analytics')->assertOk();

        // The role check stays: a cashier given reports.view still has no analytics screen
        app(PermissionService::class)->setOverride($this->store, UserRole::CASHIER, Permission::REPORTS_VIEW, true, $this->owner);
        $this->actingAs($this->makeStaff(UserRole::CASHIER, $this->branch), 'sanctum')->getJson('/api/v1/owner/analytics')->assertForbidden();
    }

    public function test_me_permissions(): void
    {
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/me/permissions')
            ->assertOk()
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonPath('data.branch_id', $this->branch->id)
            ->assertJsonPath('data.branch_scoped', true)
            ->assertJsonPath('data.device_uuid', null);

        $this->assertSame([], $this->actingAs($this->customer->fresh(), 'sanctum')->getJson('/api/v1/me/permissions')->json('data.permissions'));
        $this->assertFalse($this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/me/permissions')->json('data.branch_scoped'));
        $this->withBearer(null)->getJson('/api/v1/me/permissions')->assertUnauthorized();
    }

    public function test_new_staff_roles_need_a_store(): void
    {
        $storeless = User::create(['name' => 'Lost cook', 'role' => UserRole::COOK, 'telegram_id' => '9090']);

        $this->actingAs($storeless, 'sanctum')->getJson('/api/v1/me/permissions')->assertForbidden();
        $this->assertSame([], app(PermissionService::class)->permissionsFor($storeless));
    }

    public function test_the_owner_changes_staff_roles_within_the_store(): void
    {
        $waiter = $this->makeStaff(UserRole::WAITER, $this->branch);
        $foreignStore = $this->makeStore('Foreign Grill');
        $foreignWaiter = $this->makeStaff(UserRole::WAITER, null, $foreignStore);

        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/staff/{$waiter->id}/role", ['role' => 'cashier'])
            ->assertOk()->assertJsonPath('data.role', 'cashier');
        $this->assertSame(UserRole::CASHIER, $waiter->fresh()->role);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.role_changed', 'model_id' => $waiter->id, 'store_id' => $this->store->id]);

        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/staff/{$foreignWaiter->id}/role", ['role' => 'manager'])->assertNotFound();
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/staff/{$this->owner->id}/role", ['role' => 'manager'])->assertNotFound();
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/staff/{$waiter->id}/role", ['role' => 'owner'])->assertUnprocessable();
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/staff/{$waiter->id}/role", ['role' => 'super_admin'])->assertUnprocessable();
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/staff/{$waiter->id}/role", ['role' => 'cook', 'branch_id' => $foreignStore->id + 1000])->assertUnprocessable();
        $this->actingAs($this->manager, 'sanctum')->putJson("/api/v1/owner/staff/{$waiter->id}/role", ['role' => 'manager'])->assertForbidden();
        $this->assertSame(UserRole::WAITER, $foreignWaiter->fresh()->role);
    }

    public function test_the_employee_api_knows_the_new_roles(): void
    {
        \Illuminate\Support\Facades\Http::fake();
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);

        $ids = array_column($this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/employees')->assertOk()->json('data'), 'id');
        $this->assertContains($cook->id, $ids);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/employees/invite', [
            'role' => 'waiter', 'name' => 'New Waiter', 'branch_id' => $this->branch->id,
        ])->assertOk();
        $this->assertDatabaseHas('users', ['name' => 'New Waiter', 'role' => 'waiter', 'store_id' => $this->store->id]);
    }

    public function test_an_invite_token_only_shows_in_the_owners_employee_list(): void
    {
        $invited = $this->makeStaff(UserRole::COURIER, $this->branch, null, ['telegram_invite_token' => 'emp_secret_token']);
        $order = $this->makeOrder(OrderStatus::COURIER_ASSIGNED, ['courier_id' => $invited->id]);

        $employees = collect($this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/employees')->json('data'));
        $this->assertSame('emp_secret_token', $employees->firstWhere('id', $invited->id)['telegram_invite_token']);

        $this->assertStringNotContainsString('emp_secret_token', $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/orders')->getContent());
        $this->assertStringNotContainsString('emp_secret_token', $this->actingAs($invited, 'sanctum')->getJson('/api/v1/user')->getContent());
        $this->assertArrayNotHasKey('telegram_invite_token', $invited->toArray());
        $this->assertNotNull($order->id);
    }
}
