<?php

namespace Tests\Feature\PosBilling;

use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P3-26 (shift gaps): GET /pos/shifts/current for an owner or manager without a terminal (the branch's open shifts,
 * #14), and a drawer movement with its people as {id, name} like the X report (#9).
 */
class PosBackendGapsShiftsTest extends TestCase
{
    use PosBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosBilling();
    }

    public function test_the_owner_and_the_manager_see_the_branchs_open_shifts(): void
    {
        $this->posAs($this->manager, 'GET', '/shifts/current')->assertOk()
            ->assertJsonPath('data.shift', null)
            ->assertJsonPath('data.shifts', []);

        $first = $this->openShift('100000');
        $secondTill = $this->pairTerminal($this->owner, $this->branch);
        $second = $this->openShift('50000', $secondTill['device']);
        $otherTill = $this->pairTerminal($this->owner, $this->otherBranch);
        $other = $this->openShift('10000', $otherTill['device'], $this->makeStaff(UserRole::CASHIER, $this->otherBranch));

        // The branch manager: their branch's open shifts, newest first, each with its X report
        $data = $this->posAs($this->manager, 'GET', '/shifts/current')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing([$first->uuid, $second->uuid], array_column(array_column($data['shifts'], 'shift'), 'uuid'));
        $this->assertSame($data['shifts'][0]['shift']['uuid'], $data['shift']['uuid']);
        $this->assertSame($data['shifts'][0]['report']['cash']['opening'], $data['report']['cash']['opening']);
        $this->assertArrayHasKey('by_tender', $data['shifts'][1]['report']);

        // The owner: one branch by branch_id, or every branch
        $this->assertSame([$other->uuid], array_column(array_column($this->posAs($this->owner, 'GET', "/shifts/current?branch_id={$this->otherBranch->id}")->assertOk()->json('data.shifts'), 'shift'), 'uuid'));
        $this->assertCount(3, $this->posAs($this->owner, 'GET', '/shifts/current')->assertOk()->json('data.shifts'));

        // Another branch for the manager: 403; another store's: 404; the terminal keeps getting its own
        $this->posAs($this->manager, 'GET', "/shifts/current?branch_id={$this->otherBranch->id}")->assertForbidden();
        $foreign = $this->makeBranch($this->makeStore('Begona'), 'Begona');
        $this->posAs($this->owner, 'GET', "/shifts/current?branch_id={$foreign->id}")->assertNotFound();
        $this->pos('GET', '/shifts/current')->assertOk()
            ->assertJsonPath('data.shift.uuid', $first->uuid)
            ->assertJsonCount(1, 'data.shifts');
    }

    public function test_a_drawer_movement_names_its_employee_cashier_and_approver(): void
    {
        $shift = $this->openShift('100000');

        $movement = $this->pos('POST', "/shifts/{$shift->uuid}/cash-movements", ['type' => 'wage', 'category' => 'daily_wage', 'amount' => '20000', 'employee_id' => $this->waiter->id, 'approval_token' => $this->approval('pos.cash_in_out')])
            ->assertCreated()->json('data.movement');
        $this->assertSame(['id' => $this->waiter->id, 'name' => $this->waiter->name], $movement['employee']);
        $this->assertSame(['id' => $this->cashier->id, 'name' => $this->cashier->name], $movement['user']);
        $this->assertSame(['id' => $this->manager->id, 'name' => $this->manager->name], $movement['approver']);
        $this->assertSame($this->waiter->id, $movement['employee_id']);
        $this->assertSame($this->manager->id, $movement['approved_by']);

        $row = collect($this->pos('GET', "/shifts/{$shift->uuid}/x-report")->assertOk()->json('data.report.movements'))->firstWhere('uuid', $movement['uuid']);
        $this->assertSame([$movement['employee'], $movement['user'], $movement['approver']], [$row['employee'], $row['user'], $row['approver']]);
    }
}
