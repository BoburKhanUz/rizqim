<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\District;
use App\Models\Region;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * P2-12: branches and their working hours for the partner web panel: the owner creates and deletes, owners and
 * managers edit (a branch manager their own branch), staff list them.
 */
class ManagementBranchesApiTest extends TestCase
{
    use RefreshDatabase;
    use ManagementFixtures;

    private Region $region;
    private District $district;
    private District $otherRegionDistrict;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
        $this->region = Region::create(['name_uz' => 'Toshkent shahri', 'is_active' => true]);
        $this->district = District::create(['region_id' => $this->region->id, 'name_uz' => 'Chilonzor tumani', 'is_active' => true]);
        $other = Region::create(['name_uz' => 'Samarqand viloyati', 'is_active' => true]);
        $this->otherRegionDistrict = District::create(['region_id' => $other->id, 'name_uz' => 'Urgut tumani', 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Yunusobod filiali',
            'phone' => '+998901112233',
            'address' => 'Amir Temur 108',
            'region_id' => $this->region->id,
            'district_id' => $this->district->id,
            'latitude' => 41.36,
            'longitude' => 69.29,
            'radius_km' => 7.5,
            'open_time' => '09:00',
            'close_time' => '23:00',
        ], $overrides);
    }

    public function test_the_owner_creates_and_edits_a_branch_with_working_hours(): void
    {
        // 10:00 in Tashkent
        Carbon::setTestNow(Carbon::parse('2026-10-20 05:00:00', 'UTC'));

        $id = $this->as($this->owner, 'POST', '/api/v1/owner/branches', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Yunusobod filiali')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.radius_km', 7.5)
            ->assertJsonPath('data.working_hours', ['open' => '09:00', 'close' => '23:00', 'all_day' => false, 'overnight' => false, 'timezone' => 'Asia/Tashkent'])
            ->assertJsonPath('data.is_open_now', true)
            ->json('data.id');

        $branch = Branch::findOrFail($id);
        $this->assertSame($this->store->id, (int) $branch->store_id);
        $this->assertSame('09:00:00', substr((string) $branch->open_time, 0, 8));

        // Overnight hours, temporarily closed
        $this->as($this->owner, 'PATCH', "/api/v1/owner/branches/{$id}", ['open_time' => '18:00', 'close_time' => '02:00:00', 'status' => 'temporarily_closed'])
            ->assertOk()
            ->assertJsonPath('data.working_hours.overnight', true)
            ->assertJsonPath('data.working_hours.close', '02:00')
            ->assertJsonPath('data.status', 'temporarily_closed')
            ->assertJsonPath('data.is_open_now', false)
            ->assertJsonPath('data.name', 'Yunusobod filiali');

        // No hours: round the clock
        $this->as($this->owner, 'PATCH', "/api/v1/owner/branches/{$id}", ['open_time' => null, 'close_time' => null, 'status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.working_hours.all_day', true)
            ->assertJsonPath('data.is_open_now', true);

        $this->assertSame(1, AuditLog::where('action', 'branch.created')->where('store_id', $this->store->id)->count());
        $log = AuditLog::where('action', 'branch.updated')->orderBy('id')->first();
        $this->assertSame(['open_time' => '09:00', 'close_time' => '23:00', 'status' => 'active'], $log->before);
        $this->assertSame($id, (int) $log->branch_id);
    }

    public function test_validation_of_hours_location_and_region(): void
    {
        $cases = [
            'bad time' => [['open_time' => '25:00'], 'open_time', 'SS:DD'],
            'time text' => [['close_time' => '9am'], 'close_time', 'SS:DD'],
            'only opening' => [['close_time' => null], 'close_time', 'Yopilish vaqtini ham kiriting'],
            'only closing' => [['open_time' => null], 'open_time', 'Ochilish vaqtini ham kiriting'],
            'district of another region' => [['district_id' => $this->otherRegionDistrict->id], 'district_id', 'tegishli emas'],
            'latitude' => [['latitude' => 91], 'latitude', 'oralig'],
            'no address' => [['address' => null], 'address', 'Manzil majburiy'],
            'radius' => [['radius_km' => 101], 'radius_km', null],
            'status' => [['status' => 'open'], 'status', null],
            'huge region id' => [['region_id' => '1234567890123456789012'], 'region_id', null],
        ];

        foreach ($cases as $label => [$overrides, $field, $message]) {
            $response = $this->as($this->owner, 'POST', '/api/v1/owner/branches', $this->payload($overrides))
                ->assertStatus(422)
                ->assertJsonValidationErrors($field);
            if ($message !== null) {
                $this->assertStringContainsString($message, $response->json("errors.{$field}.0"), $label);
            }
        }

        // A partial update must move both ends of the window, and a new region needs its district
        $this->as($this->owner, 'PATCH', "/api/v1/owner/branches/{$this->branch->id}", ['open_time' => '10:00'])
            ->assertStatus(422)->assertJsonValidationErrors('close_time');
        $other = Region::create(['name_uz' => 'Buxoro', 'is_active' => true]);
        $this->branch->update(['region_id' => $this->region->id, 'district_id' => $this->district->id]);
        $this->as($this->owner, 'PATCH', "/api/v1/owner/branches/{$this->branch->id}", ['region_id' => $other->id])
            ->assertStatus(422)->assertJsonValidationErrors('district_id');

        $this->assertSame(3, Branch::count()); // the two fixture branches and the foreign one
    }

    public function test_who_may_list_edit_create_and_delete(): void
    {
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);

        // The owner sees every branch; a branch manager and branch staff their own
        $this->as($this->owner, 'GET', '/api/v1/owner/branches')->assertOk()->assertJsonCount(2, 'data');
        $this->as($this->manager, 'GET', '/api/v1/owner/branches')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->branch->id);
        $this->as($cashier, 'GET', '/api/v1/owner/branches')->assertOk()->assertJsonCount(1, 'data');
        $this->as($this->manager, 'GET', "/api/v1/owner/branches/{$this->otherBranch->id}")->assertForbidden();
        $this->as($this->courier, 'GET', '/api/v1/owner/branches')->assertForbidden();
        $this->as($this->customer, 'GET', '/api/v1/owner/branches')->assertForbidden();

        // A branch manager edits their own branch's hours, not another branch
        $this->as($this->manager, 'PATCH', "/api/v1/owner/branches/{$this->branch->id}", ['open_time' => '08:00', 'close_time' => '22:00'])->assertOk();
        $this->as($this->manager, 'PATCH', "/api/v1/owner/branches/{$this->otherBranch->id}", ['name' => 'Mine'])->assertForbidden();
        $this->as($cashier, 'PATCH', "/api/v1/owner/branches/{$this->branch->id}", ['name' => 'Mine'])->assertForbidden();

        // Creating and deleting are the owner's
        $this->as($this->manager, 'POST', '/api/v1/owner/branches', $this->payload())->assertForbidden();
        $this->as($this->manager, 'DELETE', "/api/v1/owner/branches/{$this->otherBranch->id}")->assertForbidden();

        // A store may take branches.manage away from its managers
        app(PermissionService::class)->setOverride($this->store, UserRole::MANAGER, Permission::BRANCHES_MANAGE, false);
        $this->as($this->manager, 'PATCH', "/api/v1/owner/branches/{$this->branch->id}", ['name' => 'Mine'])->assertForbidden();

        // Not through a terminal PIN session
        $this->bearerJson('PATCH', "/api/v1/owner/branches/{$this->branch->id}", $this->pinSession($this->owner), ['name' => 'Mine'])
            ->assertForbidden()->assertJsonPath('data.reason', 'staff_session_not_allowed');

        $this->assertNotSame('Mine', $this->branch->refresh()->name);
        $this->assertSame('08:00', substr((string) $this->branch->open_time, 0, 5));
    }

    public function test_another_stores_branches_are_not_found(): void
    {
        $this->as($this->owner, 'GET', "/api/v1/owner/branches/{$this->foreignBranch->id}")->assertNotFound();
        $this->as($this->owner, 'PATCH', "/api/v1/owner/branches/{$this->foreignBranch->id}", ['name' => 'Mine'])->assertNotFound();
        $this->as($this->owner, 'DELETE', "/api/v1/owner/branches/{$this->foreignBranch->id}")->assertNotFound();
        $this->as($this->foreignOwner, 'GET', '/api/v1/owner/branches')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->foreignBranch->id);

        $this->assertSame('Foreign Branch', $this->foreignBranch->refresh()->name);
    }

    public function test_a_branch_with_orders_or_the_last_branch_is_not_deleted(): void
    {
        $this->makeOrder(OrderStatus::DELIVERED, ['branch_id' => $this->otherBranch->id]);

        $this->as($this->owner, 'DELETE', "/api/v1/owner/branches/{$this->otherBranch->id}")
            ->assertStatus(422)->assertJsonPath('data.reason', 'branch_in_use');
        $this->assertNotNull(Branch::find($this->otherBranch->id));

        // The foreign store's only branch
        $this->as($this->foreignOwner, 'DELETE', "/api/v1/owner/branches/{$this->foreignBranch->id}")
            ->assertStatus(422)->assertJsonPath('message', "Do'konning yagona filialini o'chirib bo'lmaydi.");

        $spare = $this->makeBranch($this->store, 'Spare');
        $this->as($this->owner, 'DELETE', "/api/v1/owner/branches/{$spare->id}")->assertOk();
        $this->assertNull(Branch::find($spare->id));
        $log = AuditLog::where('action', 'branch.deleted')->sole();
        $this->assertSame($this->store->id, (int) $log->store_id);
        $this->assertSame('Spare', $log->before['name']);
    }
}
