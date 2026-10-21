<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\StaffAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P2-33 (review A, finding 3): who sees whom in GET /owner/staff, and who gives roles. Anyone tied to a branch sees
 * the staff of their branch and the store-wide ones only; a cashier, waiter, cook or courier the store let see the list
 * (staff.view) sees what their branch manager sees and gives no role.
 */
class StaffListScopeTest extends TestCase
{
    use RefreshDatabase;
    use ManagementFixtures;

    private User $mainCook;
    private User $otherManager;
    private User $otherCook;
    private User $storeWideCourier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
        $this->mainCook = $this->makeStaff(UserRole::COOK, $this->branch);
        $this->otherManager = $this->makeStaff(UserRole::MANAGER, $this->otherBranch);
        $this->otherCook = $this->makeStaff(UserRole::COOK, $this->otherBranch);
        $this->storeWideCourier = $this->makeStaff(UserRole::COURIER);
    }

    /**
     * @return list<int>
     */
    private function listedBy(User $actor): array
    {
        $ids = collect($this->as($actor, 'GET', '/api/v1/owner/staff')->assertOk()->json('data'))->pluck('id')->all();
        sort($ids);

        return $ids;
    }

    /**
     * @param  list<User>  $users
     * @return list<int>
     */
    private function ids(array $users): array
    {
        $ids = array_map(fn (User $user) => (int) $user->id, $users);
        sort($ids);

        return $ids;
    }

    public function test_the_owner_sees_every_staff_member_and_gives_every_role(): void
    {
        $this->assertSame(
            $this->ids([$this->manager, $this->courier, $this->mainCook, $this->otherManager, $this->otherCook, $this->storeWideCourier]),
            $this->listedBy($this->owner),
        );
        $this->assertSame(UserRole::assignableStaffRoles(), app(StaffAccountService::class)->assignableRoles($this->owner));
    }

    public function test_a_branch_manager_sees_their_branch_and_the_store_wide_staff_below_them(): void
    {
        $this->assertSame($this->ids([$this->courier, $this->mainCook, $this->storeWideCourier]), $this->listedBy($this->manager));
        $this->assertSame(
            [UserRole::CASHIER, UserRole::WAITER, UserRole::COOK, UserRole::COURIER],
            app(StaffAccountService::class)->assignableRoles($this->manager),
        );
    }

    public function test_branch_staff_granted_staff_view_see_their_branch_only_and_give_no_role(): void
    {
        foreach ([UserRole::CASHIER, UserRole::WAITER, UserRole::COOK] as $role) {
            app(PermissionService::class)->setOverride($this->store, $role, Permission::STAFF_VIEW, true, $this->owner);
            $actor = $this->makeStaff($role, $this->branch);

            $this->assertSame(
                $this->ids([$this->courier, $this->mainCook, $this->storeWideCourier, $actor]),
                $this->listedBy($actor),
                "{$role->value} sees their branch and the store-wide staff below a manager",
            );
            $this->assertSame([], app(StaffAccountService::class)->assignableRoles($actor), $role->value);

            // Nobody's phone from the other branch, no edit rights, no adding staff
            $rows = $this->as($actor, 'GET', '/api/v1/owner/staff')->json('data');
            $this->assertNotContains($this->otherCook->phone, array_column($rows, 'phone'));
            $this->assertSame([false], array_values(array_unique(array_column($rows, 'can_edit'))));
            $this->as($actor, 'POST', '/api/v1/owner/staff', ['name' => 'X', 'role' => 'cook', 'branch_id' => $this->branch->id])->assertForbidden();

            $actor->delete();
        }
    }

    public function test_a_store_wide_cashier_with_staff_view_sees_the_store_but_no_managers(): void
    {
        app(PermissionService::class)->setOverride($this->store, UserRole::CASHIER, Permission::STAFF_VIEW, true, $this->owner);
        $cashier = $this->makeStaff(UserRole::CASHIER);

        $this->assertSame(
            $this->ids([$this->courier, $this->mainCook, $this->otherCook, $this->storeWideCourier, $cashier]),
            $this->listedBy($cashier),
        );
    }

    public function test_without_staff_view_the_list_is_forbidden(): void
    {
        $this->as($this->makeStaff(UserRole::CASHIER, $this->branch), 'GET', '/api/v1/owner/staff')->assertForbidden();
        $this->as($this->courier, 'GET', '/api/v1/owner/staff')->assertForbidden();
    }

    public function test_the_service_narrows_a_branch_bound_actor_even_without_a_branch_argument(): void
    {
        app(PermissionService::class)->setOverride($this->store, UserRole::CASHIER, Permission::STAFF_VIEW, true, $this->owner);
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->otherBranch);

        $ids = app(StaffAccountService::class)->scopeVisible(User::query(), $cashier, $this->store->id, null)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        $this->assertSame($this->ids([$this->otherCook, $this->storeWideCourier, $cashier]), $ids);
        $this->assertSame([], app(StaffAccountService::class)->assignableRoles(null));
    }
}
