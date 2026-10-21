<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P2-12: the staff list, adding staff and editing name, branch and login phone from the partner web panel, with the
 * panel employee screen's rules (StaffAccountService).
 */
class ManagementStaffApiTest extends TestCase
{
    use RefreshDatabase;
    use ManagementFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
    }

    public function test_the_owner_lists_and_adds_staff_with_a_login_phone(): void
    {
        $list = $this->as($this->owner, 'GET', '/api/v1/owner/staff')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([$this->manager->id, $this->courier->id], array_column($list, 'id'));
        $this->assertNotContains($this->owner->id, array_column($list, 'id'));

        $created = $this->as($this->owner, 'POST', '/api/v1/owner/staff', [
            'name' => 'Aziz', 'role' => 'cashier', 'branch_id' => $this->otherBranch->id, 'phone' => '+998901234567',
        ])->assertCreated()
            ->assertJsonPath('data.staff.name', 'Aziz')
            ->assertJsonPath('data.staff.role', 'cashier')
            ->assertJsonPath('data.staff.role_label', 'Kassir')
            ->assertJsonPath('data.staff.branch.id', $this->otherBranch->id)
            ->assertJsonPath('data.staff.login_phone_enabled', true)
            ->assertJsonPath('data.staff.invite_pending', true)
            ->assertJsonPath('data.staff.can_edit', true)
            ->json('data');

        $this->assertStringContainsString('?start=invite_', $created['invite_link']);
        $user = User::findOrFail($created['staff']['id']);
        $this->assertSame($this->store->id, (int) $user->store_id);
        $this->assertSame(User::PHONE_VIA_VOUCHED, $user->phone_verified_via);
        $this->assertSame(1, AuditLog::where('action', 'staff.login_phone_set')->where('model_id', $user->id)->count());

        // Rename and change the phone: the new one is vouched, the change audited
        $this->as($this->owner, 'PATCH', "/api/v1/owner/staff/{$user->id}", ['name' => 'Aziz B.', 'phone' => '+998907654321'])
            ->assertOk()->assertJsonPath('data.name', 'Aziz B.')->assertJsonPath('data.phone', '+998907654321');
        $this->assertSame(1, AuditLog::where('action', 'staff.login_phone_changed')->where('model_id', $user->id)->count());
    }

    public function test_a_branch_manager_adds_and_edits_staff_below_them_in_their_branch(): void
    {
        $storeWideCourier = $this->makeStaff(UserRole::COURIER);
        $otherBranchCook = $this->makeStaff(UserRole::COOK, $this->otherBranch);
        $otherManager = $this->makeStaff(UserRole::MANAGER, $this->branch);

        $ids = array_column($this->as($this->manager, 'GET', '/api/v1/owner/staff')->assertOk()->json('data'), 'id');
        $this->assertEqualsCanonicalizing([$this->courier->id, $storeWideCourier->id], $ids);

        $this->as($this->manager, 'POST', '/api/v1/owner/staff', ['name' => 'Kuryer', 'role' => 'courier', 'branch_id' => $this->branch->id])
            ->assertCreated()->assertJsonPath('data.staff.branch.id', $this->branch->id);
        $this->as($this->manager, 'POST', '/api/v1/owner/staff', ['name' => 'Boss', 'role' => 'manager', 'branch_id' => $this->branch->id])
            ->assertStatus(422)->assertJsonPath('errors.role.0', 'Bu rolni bera olmaysiz.');
        $this->as($this->manager, 'POST', '/api/v1/owner/staff', ['name' => 'Kuryer', 'role' => 'courier', 'branch_id' => $this->otherBranch->id])
            ->assertStatus(422)->assertJsonValidationErrors('branch_id');

        $this->as($this->manager, 'PATCH', "/api/v1/owner/staff/{$this->courier->id}", ['name' => 'Tez kuryer'])->assertOk();
        $this->as($this->manager, 'PATCH', "/api/v1/owner/staff/{$this->courier->id}", ['branch_id' => $this->otherBranch->id])->assertStatus(422);
        foreach ([$otherBranchCook, $otherManager, $storeWideCourier] as $staff) {
            $this->as($this->manager, 'PATCH', "/api/v1/owner/staff/{$staff->id}", ['name' => 'Mine'])->assertForbidden();
        }
        $this->as($this->manager, 'PATCH', "/api/v1/owner/staff/{$this->owner->id}", ['name' => 'Mine'])->assertNotFound();

        $this->assertSame('Tez kuryer', $this->courier->refresh()->name);
        $this->assertNotSame('Mine', $otherBranchCook->refresh()->name);
    }

    public function test_a_phone_the_employee_proved_by_sms_is_theirs(): void
    {
        $this->courier->forceFill(['phone' => '+998901110000', 'phone_verified_at' => now(), 'phone_verified_via' => User::PHONE_VIA_SMS])->save();

        foreach ([['phone' => '+998902220000'], ['phone' => null]] as $change) {
            $this->as($this->owner, 'PATCH', "/api/v1/owner/staff/{$this->courier->id}", $change)
                ->assertStatus(422)->assertJsonValidationErrors('phone');
        }
        // Other fields still save; the phone stays the employee's own
        $this->as($this->owner, 'PATCH', "/api/v1/owner/staff/{$this->courier->id}", ['name' => 'Renamed'])->assertOk();
        $this->assertSame(User::PHONE_VIA_SMS, $this->courier->refresh()->phone_verified_via);

        // One staff account per phone in a store; a bad format is refused
        $this->as($this->owner, 'POST', '/api/v1/owner/staff', ['name' => 'Twin', 'role' => 'cook', 'branch_id' => $this->branch->id, 'phone' => '+998901110000'])
            ->assertStatus(422)->assertJsonPath('errors.phone.0', "Bu raqam do'koningizning boshqa xodimiga biriktirilgan.");
        $this->as($this->owner, 'POST', '/api/v1/owner/staff', ['name' => 'Bad', 'role' => 'cook', 'branch_id' => $this->branch->id, 'phone' => '90 111 22 33'])
            ->assertStatus(422)->assertJsonPath('errors.phone.0', "Raqam +998XXXXXXXXX ko'rinishida bo'lsin.");
    }

    public function test_who_may_reach_the_staff_endpoints(): void
    {
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);

        foreach ([$cashier, $this->courier, $this->customer] as $user) {
            $this->as($user, 'GET', '/api/v1/owner/staff')->assertForbidden();
            $this->as($user, 'POST', '/api/v1/owner/staff', ['name' => 'X', 'role' => 'cook', 'branch_id' => $this->branch->id])->assertForbidden();
        }
        $this->bearerJson('GET', '/api/v1/owner/staff', $this->pinSession($this->owner))
            ->assertForbidden()->assertJsonPath('data.reason', 'staff_session_not_allowed');

        app(PermissionService::class)->setOverride($this->store, UserRole::MANAGER, \App\Enums\Permission::STAFF_VIEW, false);
        $this->as($this->manager, 'GET', '/api/v1/owner/staff')->assertForbidden();
    }

    public function test_another_stores_staff_and_branches_are_out_of_reach(): void
    {
        $foreignCourier = $this->makeStaff(UserRole::COURIER, $this->foreignBranch, $this->foreignStore);

        $this->assertNotContains($foreignCourier->id, array_column($this->as($this->owner, 'GET', '/api/v1/owner/staff')->json('data'), 'id'));
        $this->as($this->owner, 'PATCH', "/api/v1/owner/staff/{$foreignCourier->id}", ['name' => 'Mine'])->assertNotFound();
        $this->as($this->owner, 'POST', '/api/v1/owner/staff', ['name' => 'X', 'role' => 'cook', 'branch_id' => $this->foreignBranch->id])
            ->assertStatus(422)->assertJsonPath('errors.branch_id.0', 'Filial topilmadi.');

        $this->assertNotSame('Mine', $foreignCourier->refresh()->name);
    }
}
