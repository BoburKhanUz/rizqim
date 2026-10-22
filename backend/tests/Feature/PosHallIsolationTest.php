<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Hall;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * P3-12: who works with which POS order and floor: another store (404), another branch (404/403), a terminal PIN
 * session of another branch, a cook; the terminal recorded on the order and the audit log; the floor map's states.
 */
class PosHallIsolationTest extends TestCase
{
    use PosHallFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosHall();
    }

    public function test_another_store_and_another_branch_never_reach_the_order(): void
    {
        $order = $this->openedOrder($this->openPos($this->cashier, $this->room, 2, ['items' => [$this->line($this->tea, 1)]]));

        $foreignStore = $this->makeStore('Begona');
        $foreignOwner = $this->makeStaff(UserRole::OWNER, null, $foreignStore);
        $otherManager = $this->makeStaff(UserRole::MANAGER, $this->otherBranch);
        $otherWaiter = $this->makeStaff(UserRole::WAITER, $this->otherBranch);

        foreach ([$foreignOwner, $otherManager, $otherWaiter] as $stranger) {
            $this->pos($stranger, 'GET', "/pos/orders/{$order->uuid}")->assertNotFound();
            $this->pos($stranger, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'items' => [$this->line($this->tea, 1)]])->assertNotFound();
            $this->pos($stranger, 'POST', "/pos/orders/{$order->uuid}/guests", ['version' => 1, 'guests_count' => 3])->assertNotFound();
            $this->pos($stranger, 'POST', "/pos/orders/{$order->uuid}/move", ['version' => 1, 'table_id' => $this->table->id])->assertNotFound();
        }

        // Opening in a branch the caller does not work in
        $this->pos($otherWaiter, 'POST', '/pos/orders', ['branch_id' => $this->branch->id, 'channel' => 'pos_hall', 'guests_count' => 2])->assertForbidden();
        $foreignBranch = $this->makeBranch($foreignStore, 'Begona filial');
        $this->pos($this->cashier, 'POST', '/pos/orders', ['branch_id' => $foreignBranch->id, 'channel' => 'pos_hall', 'guests_count' => 2])->assertNotFound();
        // Another branch's table
        $otherTable = $this->place(Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->otherBranch->id, 'name' => 'B']), 'B-1', 'table');
        $this->openPos($this->cashier, $otherTable)->assertStatus(422)->assertJsonValidationErrors('table_id');
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/move", ['version' => 1, 'table_id' => $otherTable->id])
            ->assertStatus(422)->assertJsonValidationErrors('table_id');

        // Another branch's order cannot be merged in
        $otherOrder = $this->openedOrder($this->pos($this->owner, 'POST', '/pos/orders', ['branch_id' => $this->otherBranch->id, 'channel' => 'pos_hall', 'guests_count' => 2]));
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/merge", ['version' => 1, 'source_uuid' => $otherOrder->uuid, 'source_version' => 1])->assertNotFound();
        $this->pos($this->owner, 'POST', "/pos/orders/{$order->uuid}/merge", ['version' => 1, 'source_uuid' => $otherOrder->uuid, 'source_version' => 1])
            ->assertStatus(422)->assertJsonPath('data.reason', 'merge_not_allowed');

        // A cook sees, but does not run, orders
        $cook = $this->makeCook();
        $this->pos($cook, 'GET', "/pos/orders/{$order->uuid}")->assertOk();
        $this->pos($cook, 'POST', "/pos/orders/{$order->uuid}/send", ['version' => 1])->assertForbidden();
        $this->openPos($cook)->assertForbidden();
    }

    public function test_a_terminal_pin_session_works_in_its_branch_only_and_is_recorded(): void
    {
        $terminal = $this->pairTerminal($this->owner, $this->branch);
        $token = $this->pinSession($terminal, $this->waiter);

        // Without branch_id: the terminal's branch
        $response = $this->pos($token, 'POST', '/pos/orders', ['channel' => 'pos_hall', 'table_id' => $this->room->id, 'guests_count' => 3])->assertCreated();
        $order = Order::where('uuid', $response->json('data.uuid'))->firstOrFail();
        $this->assertSame((int) $terminal['device']->id, (int) $order->device_id);
        $this->assertSame((int) $terminal['device']->id, (int) AuditLog::where('action', 'pos.order_opened')->value('device_id'));
        $this->pos($token, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'items' => [$this->line($this->tea, 1)]])->assertOk();
        $this->pos($token, 'GET', '/pos/floor')->assertOk()->assertJsonPath('data.branch_id', $this->branch->id);

        // A store-wide owner signed in on the other branch's terminal works that branch only
        $otherTerminal = $this->pairTerminal($this->owner, $this->otherBranch);
        $ownerToken = $this->pinSession($otherTerminal, $this->owner, '5927');
        $this->pos($ownerToken, 'GET', "/pos/orders/{$order->uuid}")->assertNotFound();
        $this->pos($ownerToken, 'POST', "/pos/orders/{$order->uuid}/guests", ['version' => 2, 'guests_count' => 4])->assertNotFound();
        $this->pos($ownerToken, 'POST', '/pos/orders', ['branch_id' => $this->branch->id, 'channel' => 'pos_hall', 'guests_count' => 2])->assertForbidden();
        $this->pos($ownerToken, 'GET', '/pos/floor?branch_id=' . $this->branch->id)->assertForbidden();

        // The terminal's own token is not a staff sign-in
        $this->withBearer($terminal['token'])->withHeaders(['Idempotency-Key' => 'k-1'])
            ->postJson('/api/v1/pos/orders', ['channel' => 'pos_hall', 'guests_count' => 2])->assertUnauthorized();
    }

    public function test_the_floor_map_shows_each_places_state(): void
    {
        $occupied = $this->openedOrder($this->openPos($this->cashier, $this->room, 6, ['items' => [$this->line($this->tea, 2, ['fire_mode' => 'hold'])]]));
        $overdue = $this->openedOrder($this->openPos($this->cashier, $this->tapchan, 3));
        Order::whereKey($overdue->id)->update(['created_at' => now()->subHours(7)]);
        $printed = $this->openedOrder($this->openPos($this->cashier, $this->vipRoom, 2));
        $hasBillLock = Schema::hasColumn('orders', 'bill_locked_at');
        if ($hasBillLock) {
            DB::table('orders')->where('id', $printed->id)->update(['bill_locked_at' => now()]);
        }
        $takeaway = $this->openedOrder($this->pos($this->cashier, 'POST', '/pos/orders', ['branch_id' => $this->branch->id, 'channel' => 'pos_takeaway']));
        $this->place($this->hall, 'Eski stol', 'table', ['is_active' => false]);

        $floor = $this->pos($this->waiter, 'GET', '/pos/floor')->assertOk()->json('data');
        $this->assertSame(['Asosiy zal', 'VIP'], array_column($floor['halls'], 'name'));
        $places = collect($floor['halls'])->flatMap(fn ($hall) => $hall['tables'])->keyBy('name');

        $this->assertArrayNotHasKey('Eski stol', $places->all());
        $this->assertSame('occupied', $places['1-xona']['status']);
        $this->assertSame($occupied->uuid, $places['1-xona']['orders'][0]['uuid']);
        $this->assertSame(6, $places['1-xona']['orders'][0]['guests_count']);
        $this->assertSame(1, $places['1-xona']['orders'][0]['unsent_count']);
        $this->assertSame('overdue', $places['Tapchan 1']['status']);
        $this->assertSame('free', $places['Stol 5']['status']);
        $this->assertSame($hasBillLock ? 'bill_printed' : 'occupied', $places['VIP-1']['status']);
        $this->assertSame([$takeaway->uuid], array_column($floor['untabled_orders'], 'uuid'));

        // A closed seasonal hall leaves the map once nothing is open there
        $printed->forceFill(['status' => 'delivered'])->save();
        $this->vipHall->forceFill(['is_active' => false])->save();
        $this->assertSame(['Asosiy zal'], array_column($this->pos($this->waiter, 'GET', '/pos/floor')->json('data.halls'), 'name'));
    }

    public function test_a_booking_about_to_arrive_marks_the_place_reserved_soon(): void
    {
        if (!Schema::hasTable('bookings')) {
            $this->markTestSkipped('P3-17 bookings are not in this tree.');
        }

        DB::table('bookings')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'dining_table_id' => $this->vipRoom->id,
            'starts_at' => now()->addMinutes(40),
            'ends_at' => now()->addHours(5),
            'guests_planned' => 12,
            'contact_name' => 'Jamshid',
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $places = collect($this->pos($this->cashier, 'GET', '/pos/floor')->json('data.halls'))->flatMap(fn ($hall) => $hall['tables'])->keyBy('name');
        $this->assertSame('reserved_soon', $places['VIP-1']['status']);
        $this->assertSame(12, $places['VIP-1']['reservation']['guests']);
        $this->assertSame('free', $places['1-xona']['status']);
    }
}
