<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserHasRole;
use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::forceCreate([
            'name' => 'RBAC Store',
            'slug' => 'rbac-store',
            'commission_rate' => 10,
            'status' => 'active',
            'min_payout_reserve' => 100000,
        ]);
        // Balances are not mass assignable (they only change through the ledger)
        $this->store->forceFill(['wallet_balance' => 500000])->save();

        $this->branch = Branch::create(['store_id' => $this->store->id, 'name' => 'Asosiy filial']);
    }

    private function member(UserRole $role): User
    {
        return User::create([
            'name' => ucfirst($role->value) . ' User',
            'telegram_id' => (string) random_int(100000000, 999999999),
            'store_id' => in_array($role, [UserRole::SUPER_ADMIN, UserRole::CUSTOMER], true) ? null : $this->store->id,
            'branch_id' => in_array($role, [UserRole::MANAGER, UserRole::COURIER], true) ? $this->branch->id : null,
            'role' => $role,
        ]);
    }

    private function payoutPayload(): array
    {
        return ['amount' => 50000, 'card_number' => '8600123456789012'];
    }

    public function test_courier_and_manager_cannot_request_payouts_or_see_finance(): void
    {
        foreach ([UserRole::COURIER, UserRole::MANAGER] as $role) {
            $user = $this->member($role);

            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/owner/payouts', $this->payoutPayload())
                ->assertForbidden();

            $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/owner/finance')
                ->assertForbidden();

            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/owner/finance/top-up', ['amount' => 50000, 'provider' => 'click'])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('payout_requests', 0);
        $this->assertDatabaseCount('store_topup_requests', 0);
    }

    public function test_role_middleware_guards_sensitive_routes_independently_of_controllers(): void
    {
        $expected = [
            // P2-30: by permission (an owner may grant finance.view / menu.view to managers)
            ['GET', 'api/v1/owner/finance', 'permission:finance.view'],
            ['POST', 'api/v1/owner/payouts', 'role:owner'],
            ['POST', 'api/v1/owner/finance/top-up', 'role:owner'],
            ['GET', 'api/v1/owner/settings', 'role:owner'],
            ['POST', 'api/v1/owner/settings', 'role:owner'],
            ['POST', 'api/v1/owner/settings/upload', 'role:owner'],
            ['POST', 'api/v1/owner/employees/invite', 'role:owner'],
            ['POST', 'api/v1/owner/products', 'permission:menu.manage'],
            ['GET', 'api/v1/owner/products', 'permission:menu.view'],
            ['POST', 'api/v1/owner/branch-variants', 'role:owner,manager'],
            ['POST', 'api/v1/owner/categories', 'role:super_admin'],
            ['GET', 'api/v1/superadmin/dashboard', 'role:super_admin'],
            ['POST', 'api/v1/superadmin/stores', 'role:super_admin'],
        ];

        foreach ($expected as [$method, $uri, $middleware]) {
            $route = app('router')->getRoutes()->match(Request::create($uri, $method));
            $this->assertContains($middleware, $route->gatherMiddleware(), "{$method} {$uri} must use {$middleware}");
        }
    }

    public function test_owner_passes_the_finance_role_check(): void
    {
        $owner = $this->member(UserRole::OWNER);

        $this->actingAs($owner, 'sanctum')->getJson('/api/v1/owner/finance')->assertOk();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/owner/payouts', $this->payoutPayload())
            ->assertOk();
    }

    public function test_other_roles_cannot_use_owner_only_endpoints(): void
    {
        $endpoints = [
            ['getJson', '/api/v1/owner/settings', []],
            ['postJson', '/api/v1/owner/settings', ['is_open' => false, 'payment_settings' => ['delivery_fee' => 77777]]],
            ['getJson', '/api/v1/owner/employees', []],
            // P2-30: GET /owner/products is menu.view (a manager reads the menu); writing it stays the owner's
            ['postJson', '/api/v1/owner/products', ['name' => 'X', 'category_id' => 1, 'variants' => '[]']],
        ];

        foreach ([UserRole::MANAGER, UserRole::COURIER, UserRole::CUSTOMER, UserRole::SUPER_ADMIN] as $role) {
            $user = $this->member($role);

            foreach ($endpoints as [$method, $uri, $payload]) {
                $this->actingAs($user, 'sanctum')->{$method}($uri, $payload)->assertForbidden();
            }
        }

        $this->assertNotEquals(77777, $this->store->fresh()->payment_settings['delivery_fee'] ?? null);
    }

    public function test_settings_controller_enforces_owner_role_without_route_middleware(): void
    {
        $this->withoutMiddleware(EnsureUserHasRole::class);
        $manager = $this->member(UserRole::MANAGER);

        $this->actingAs($manager, 'sanctum')->getJson('/api/v1/owner/settings')->assertForbidden();
        $this->actingAs($manager, 'sanctum')
            ->postJson('/api/v1/owner/settings', ['is_open' => false, 'payment_settings' => ['delivery_fee' => 99999]])
            ->assertForbidden();

        $this->assertNotEquals(99999, $this->store->fresh()->payment_settings['delivery_fee'] ?? null);
    }

    public function test_managers_may_manage_branch_availability_but_couriers_may_not(): void
    {
        $this->actingAs($this->member(UserRole::MANAGER), 'sanctum')
            ->getJson('/api/v1/owner/branch-variants')
            ->assertOk();

        $this->actingAs($this->member(UserRole::COURIER), 'sanctum')
            ->getJson('/api/v1/owner/branch-variants')
            ->assertForbidden();
    }

    public function test_only_super_admin_can_use_superadmin_endpoints(): void
    {
        foreach ([UserRole::CUSTOMER, UserRole::OWNER, UserRole::MANAGER, UserRole::COURIER] as $role) {
            $this->actingAs($this->member($role), 'sanctum')
                ->getJson('/api/v1/superadmin/dashboard')
                ->assertForbidden();
        }

        $this->actingAs($this->member(UserRole::SUPER_ADMIN), 'sanctum')
            ->getJson('/api/v1/superadmin/dashboard')
            ->assertOk();
    }

    public function test_filament_panels_are_limited_to_intended_staff_roles(): void
    {
        $adminPanel = Filament::getPanel('admin');
        $superadminPanel = Filament::getPanel('superadmin');

        $expected = [
            'super_admin' => [true, true],
            'owner' => [true, false],
            'manager' => [true, false],
            'courier' => [false, false],
            'customer' => [false, false],
        ];

        foreach ($expected as $role => [$admin, $superadmin]) {
            $user = $this->member(UserRole::from($role));
            $this->assertSame($admin, $user->canAccessPanel($adminPanel), "{$role} admin panel");
            $this->assertSame($superadmin, $user->canAccessPanel($superadminPanel), "{$role} superadmin panel");
        }
    }

    public function test_only_super_admin_can_create_global_categories(): void
    {
        $this->actingAs($this->member(UserRole::OWNER), 'sanctum')
            ->postJson('/api/v1/owner/categories', ['name' => 'Fake'])
            ->assertForbidden();

        $this->actingAs($this->member(UserRole::OWNER), 'sanctum')
            ->getJson('/api/v1/owner/categories')
            ->assertOk();
    }
}
