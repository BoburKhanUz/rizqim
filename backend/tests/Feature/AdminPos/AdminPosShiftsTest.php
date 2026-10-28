<?php

namespace Tests\Feature\AdminPos;

use App\Enums\PlatformRole;
use App\Models\CashMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-24 Smenalar: shifts of every store, read-only: cash differences and forced closes.
 */
class AdminPosShiftsTest extends TestCase
{
    use RefreshDatabase;
    use AdminPosFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
    }

    public function test_the_list_filters_cash_differences_forced_closes_and_open_shifts(): void
    {
        $short = $this->shift(['counted_cash' => '470000.00', 'difference' => '-30000.00', 'z_number' => 1, 'opened_at' => now()->subDays(1)]);
        $over = $this->shift(['counted_cash' => '505000.00', 'difference' => '5000.00', 'z_number' => 2, 'opened_at' => now()->subHours(30)]);
        $exact = $this->shift(['z_number' => 3, 'opened_at' => now()->subHours(20)]);
        $forced = $this->shift(['force_closed' => true, 'close_note' => 'Kassir ketib qoldi', 'forced_by' => $this->owner->id, 'z_number' => 4, 'opened_at' => now()->subHours(12)]);
        $open = $this->shift(['status' => 'open', 'closed_at' => null, 'closed_by' => null, 'expected_cash' => null, 'counted_cash' => null, 'difference' => null, 'opened_at' => now()->subHours(3)]);
        $foreign = $this->shift(['store_id' => $this->foreignStore->id, 'branch_id' => $this->foreignBranch->id, 'difference' => '-1000.00', 'counted_cash' => '499000.00', 'z_number' => 1, 'opened_at' => now()->subHours(6)]);

        $token = $this->signIn($this->makeMember(PlatformRole::OPERATOR));
        $ids = fn (string $query) => array_column($this->adminJson('GET', '/pos/shifts' . $query, $token)->assertOk()->json('data.data'), 'uuid');

        $response = $this->adminJson('GET', '/pos/shifts', $token)->assertOk();
        $this->assertSame([$open->uuid, $foreign->uuid, $forced->uuid, $exact->uuid, $short->uuid, $over->uuid], array_column($response->json('data.data'), 'uuid'));
        $this->assertSame(['difference' => 3, 'force_closed' => 1, 'open' => 1, 'all' => 6], $response->json('data.counts'));

        $this->assertSame([$foreign->uuid, $short->uuid, $over->uuid], $ids('?kind=difference'));
        $this->assertSame([$forced->uuid], $ids('?kind=force_closed'));
        $this->assertSame([$open->uuid], $ids('?kind=open'));
        $this->assertSame([$short->uuid, $over->uuid], $ids("?kind=difference&store_id={$this->store->id}"));
        $this->assertSame([], $ids('?from=' . now()->addDay()->toDateString()));
        $this->adminJson('GET', '/pos/shifts?kind=bogus', $token)->assertStatus(422);

        $row = collect($response->json('data.data'))->firstWhere('uuid', $short->uuid);
        $this->assertSame(['-30000.00', true, '470000.00', '500000.00', 'Lifecycle Burger', 'Main Branch', 1], [$row['difference'], $row['has_difference'], $row['counted_cash'], $row['expected_cash'], $row['store']['name'], $row['branch']['name'], $row['z_number']]);
        $this->assertFalse(collect($response->json('data.data'))->firstWhere('uuid', $exact->uuid)['has_difference']);
        $this->assertNull(collect($response->json('data.data'))->firstWhere('uuid', $open->uuid)['difference']);
    }

    public function test_the_card_has_the_z_report_the_banknotes_and_the_cash_movements(): void
    {
        $shift = $this->shift([
            'force_closed' => true, 'close_note' => 'Kassir ketib qoldi', 'forced_by' => $this->owner->id, 'z_number' => 7,
            'counted_breakdown' => ['200000' => 2, '50000' => 2], 'summary' => ['sales_total' => '480000.00', 'open_orders' => ['abc']],
        ]);
        CashMovement::forceCreate(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'shift_id' => $shift->id, 'type' => 'cash_out', 'category' => 'supplies', 'amount' => '30000.00', 'note' => 'Non', 'user_id' => $this->manager->id]);

        $token = $this->signIn($this->makeMember(PlatformRole::VIEWER));
        $data = $this->adminJson('GET', "/pos/shifts/{$shift->uuid}", $token)->assertOk()->json('data');

        $this->assertSame(['Kassir ketib qoldi', true, 7], [$data['close_note'], $data['force_closed'], $data['z_number']]);
        $this->assertSame($this->owner->name, $data['forced_by']['name']);
        $this->assertEquals(['200000' => 2, '50000' => 2], $data['counted_breakdown']);
        $this->assertSame('480000.00', $data['summary']['sales_total']);
        $this->assertSame([['cash_out', 'supplies', '30000.00', -1, 'Non', $this->manager->name]], array_map(
            fn (array $m) => [$m['type'], $m['category'], $m['amount'], $m['direction'], $m['note'], $m['user']['name']],
            $data['cash_movements'],
        ));

        $this->adminJson('GET', '/pos/shifts/' . \Illuminate\Support\Str::uuid(), $token)->assertNotFound()->assertJsonPath('code', 'not_found');
    }

    public function test_it_needs_devices_view(): void
    {
        $shift = $this->shift();

        $this->adminJson('GET', '/pos/shifts', $this->signIn($this->makeMember(PlatformRole::SUPPORT)))->assertForbidden();
        $this->adminJson('GET', "/pos/shifts/{$shift->uuid}", $this->signIn($this->makeMember(PlatformRole::FINANCE)))->assertForbidden();
        $this->adminJson('GET', '/pos/shifts')->assertUnauthorized();
    }
}
