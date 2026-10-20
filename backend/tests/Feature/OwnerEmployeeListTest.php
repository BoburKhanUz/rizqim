<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OwnerEmployeeListTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    public function test_each_employee_carries_its_branch(): void
    {
        $branchless = $this->makeStaff(UserRole::COURIER);
        $secondBranchCourier = $this->makeStaff(UserRole::COURIER, $this->otherBranch);
        $foreignStore = $this->makeStore('Foreign Grill');
        $this->makeStaff(UserRole::MANAGER, $this->makeBranch($foreignStore, 'Foreign Branch'), $foreignStore);

        $employees = collect($this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/employees')
            ->assertOk()
            ->json('data'))->keyBy('id');

        $this->assertEqualsCanonicalizing(
            [$this->manager->id, $this->courier->id, $branchless->id, $secondBranchCourier->id],
            $employees->keys()->all()
        );
        $this->assertSame(['id' => $this->branch->id, 'name' => 'Main Branch'], $employees[$this->manager->id]['branch']);
        $this->assertSame(['id' => $this->branch->id, 'name' => 'Main Branch'], $employees[$this->courier->id]['branch']);
        $this->assertSame(['id' => $this->otherBranch->id, 'name' => 'Second Branch'], $employees[$secondBranchCourier->id]['branch']);
        $this->assertArrayHasKey('branch', $employees[$branchless->id]);
        $this->assertNull($employees[$branchless->id]['branch']);

        // The keys the list had before stay
        foreach (['id', 'uuid', 'name', 'role', 'phone', 'branch_id', 'telegram_id', 'telegram_invite_token', 'created_at'] as $key) {
            $this->assertArrayHasKey($key, $employees[$this->manager->id]);
        }
    }

    public function test_branches_are_loaded_in_one_query(): void
    {
        foreach (range(1, 3) as $i) {
            $this->makeStaff(UserRole::COURIER, $this->makeBranch($this->store, "Extra Branch {$i}"));
        }

        DB::enableQueryLog();
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/employees')->assertOk();
        $branchQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'from "branches"'));
        DB::disableQueryLog();

        $this->assertCount(1, $branchQueries);
    }
}
