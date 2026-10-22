<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\AutoGuestItem;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\RoomTariff;
use App\Models\ServiceFeeSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P3-12: the web panel's halls, tables (one by one and the whole floor map), room tariffs with the branch calendar,
 * the per-guest items and a hall's own service charge: permissions, branch and store isolation, no terminal PIN
 * session, and the guards (unique names, places in use).
 */
class PosHallManagementTest extends TestCase
{
    use PosHallFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosHall();
    }

    public function test_the_owner_lays_out_halls_and_tables(): void
    {
        $hall = $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/halls', [
            'branch_id' => $this->branch->id, 'name' => 'Yozgi hovli', 'sort_order' => 3,
        ])->assertCreated()->assertJsonPath('data.name', 'Yozgi hovli')->assertJsonPath('data.is_active', true)->json('data');

        $this->postJson('/api/v1/owner/halls', ['branch_id' => $this->branch->id, 'name' => 'yozgi HOVLI'])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        $table = $this->postJson('/api/v1/owner/dining-tables', [
            'hall_id' => $hall['id'], 'name' => 'So\'ri 1', 'kind' => 'tapchan', 'seats' => 10,
            'layout' => ['x' => 10, 'y' => 20.5, 'w' => 4, 'h' => 3], 'min_spend' => '300000', 'min_spend_mode' => 'per_table',
        ])->assertCreated()
            ->assertJsonPath('data.kind', 'tapchan')
            ->assertJsonPath('data.branch_id', $this->branch->id)
            ->assertJsonPath('data.layout.y', '20.50')
            ->assertJsonPath('data.min_spend', '300000.00')
            ->json('data');

        $this->postJson('/api/v1/owner/dining-tables', ['hall_id' => $hall['id'], 'name' => '1-XONA'])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        // The whole map at once; another branch's table is refused and nothing is saved
        $other = $this->place(Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->otherBranch->id, 'name' => 'B']), 'B-1', 'table');
        $this->putJson('/api/v1/owner/dining-tables/layout', ['branch_id' => $this->branch->id, 'tables' => [
            ['id' => $table['id'], 'layout' => ['x' => 1, 'y' => 1, 'w' => 2, 'h' => 2]],
            ['id' => $other->id, 'layout' => ['x' => 5, 'y' => 5, 'w' => 2, 'h' => 2]],
        ]])->assertStatus(422);
        $this->assertSame('10.00', DiningTable::find($table['id'])->layout_x);

        $this->putJson('/api/v1/owner/dining-tables/layout', ['branch_id' => $this->branch->id, 'tables' => [
            ['id' => $table['id'], 'hall_id' => $this->hall->id, 'sort_order' => 9, 'layout' => ['x' => 1, 'y' => 2, 'w' => 3, 'h' => 4]],
            ['id' => $this->room->id, 'layout' => ['x' => 7, 'y' => 7, 'w' => 2, 'h' => 2]],
        ]])->assertOk();
        $moved = DiningTable::find($table['id']);
        $this->assertSame((int) $this->hall->id, (int) $moved->hall_id);
        $this->assertSame('3.00', $moved->layout_w);
        $this->assertTrue(AuditLog::where('action', 'pos.layout_saved')->exists());

        $list = $this->getJson('/api/v1/owner/halls?branch_id=' . $this->branch->id)->assertOk()->json('data');
        $this->assertSame(['Asosiy zal', 'VIP', 'Yozgi hovli'], array_column($list, 'name'));
        $this->assertContains("So'ri 1", array_column($list[0]['tables'], 'name'));

        // A seasonal zone switched off, then edited
        $this->patchJson("/api/v1/owner/halls/{$hall['id']}", ['is_active' => false, 'name' => 'Yozgi zona'])
            ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.name', 'Yozgi zona');
        $this->patchJson("/api/v1/owner/dining-tables/{$this->room->id}", ['seats' => 12, 'min_spend' => null])
            ->assertOk()->assertJsonPath('data.seats', 12)->assertJsonPath('data.min_spend', null);
    }

    public function test_a_place_or_hall_with_an_open_order_is_not_deleted(): void
    {
        $this->openedOrder($this->openPos($this->cashier, $this->room));

        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/v1/owner/dining-tables/{$this->room->id}")
            ->assertStatus(422)->assertJsonPath('data.reason', 'table_in_use');
        $this->deleteJson("/api/v1/owner/halls/{$this->hall->id}")
            ->assertStatus(422)->assertJsonPath('data.reason', 'hall_in_use');

        $this->deleteJson("/api/v1/owner/dining-tables/{$this->table->id}")->assertOk();
        $this->assertSoftDeleted($this->table);
        $this->deleteJson("/api/v1/owner/halls/{$this->vipHall->id}")->assertOk();
        $this->assertSoftDeleted($this->vipHall);
        $this->assertSoftDeleted($this->vipRoom);
    }

    public function test_room_tariffs_and_the_branch_calendar(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $byKind = $this->postJson('/api/v1/owner/room-tariffs', [
            'branch_id' => $this->branch->id, 'table_kind' => 'room', 'day_type' => 'weekday', 'price' => '100000',
        ])->assertCreated()->assertJsonPath('data.table_kind', 'room')->assertJsonPath('data.price', '100000.00')->json('data');

        $own = $this->postJson('/api/v1/owner/room-tariffs', [
            'branch_id' => $this->branch->id, 'dining_table_id' => $this->vipRoom->id, 'table_kind' => 'tapchan',
            'day_type' => 'weekend', 'price' => '0', 'valid_from' => '2026-11-01', 'valid_to' => '2026-12-31',
        ])->assertCreated()
            ->assertJsonPath('data.dining_table_name', 'VIP-1')
            ->assertJsonPath('data.table_kind', null) // a place's own tariff
            ->assertJsonPath('data.price', '0.00')
            ->json('data');

        $this->postJson('/api/v1/owner/room-tariffs', ['branch_id' => $this->branch->id, 'day_type' => 'weekday', 'price' => '1'])
            ->assertStatus(422)->assertJsonValidationErrors('table_kind');
        $this->postJson('/api/v1/owner/room-tariffs', ['branch_id' => $this->branch->id, 'table_kind' => 'room', 'day_type' => 'someday', 'price' => '1'])
            ->assertStatus(422)->assertJsonValidationErrors('day_type');
        $otherTable = $this->place(Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->otherBranch->id, 'name' => 'B']), 'B-1', 'room');
        $this->postJson('/api/v1/owner/room-tariffs', ['branch_id' => $this->branch->id, 'dining_table_id' => $otherTable->id, 'day_type' => 'weekday', 'price' => '1'])
            ->assertStatus(422)->assertJsonValidationErrors('dining_table_id');

        $this->patchJson("/api/v1/owner/room-tariffs/{$byKind['id']}", ['price' => '120000'])->assertOk()->assertJsonPath('data.price', '120000.00');

        $this->putJson('/api/v1/owner/room-tariffs/calendar', [
            'branch_id' => $this->branch->id, 'weekend_days' => [7, 6, 5], 'holidays' => ['2026-12-31', '2026-03-21'],
        ])->assertOk()->assertJsonPath('data.weekend_days', [5, 6, 7])->assertJsonPath('data.holidays', ['2026-03-21', '2026-12-31']);

        $this->getJson('/api/v1/owner/room-tariffs?branch_id=' . $this->branch->id)
            ->assertOk()
            ->assertJsonCount(2, 'data.tariffs')
            ->assertJsonPath('data.calendar.weekend_days', [5, 6, 7]);

        $this->deleteJson("/api/v1/owner/room-tariffs/{$own['id']}")->assertOk();
        $this->assertSame(1, RoomTariff::count());
    }

    public function test_per_guest_items_and_a_halls_own_service_charge(): void
    {
        $this->actingAs($this->owner, 'sanctum');

        $rule = $this->postJson('/api/v1/owner/auto-guest-items', [
            'branch_id' => $this->branch->id, 'product_variant_id' => $this->tea->id, 'quantity_per_guest' => '0.5',
        ])->assertCreated()->assertJsonPath('data.product_name', 'Choy')->assertJsonPath('data.quantity_per_guest', 0.5)->json('data');
        $this->postJson('/api/v1/owner/auto-guest-items', [
            'branch_id' => $this->branch->id, 'product_variant_id' => $this->tea->id, 'quantity_per_guest' => '1',
        ])->assertStatus(422);
        $this->postJson('/api/v1/owner/auto-guest-items', [
            'branch_id' => $this->branch->id, 'hall_id' => $this->vipHall->id, 'product_variant_id' => $this->tea->id, 'quantity_per_guest' => '1',
        ])->assertCreated()->assertJsonPath('data.hall_id', $this->vipHall->id);

        // Another store's variant
        $foreign = $this->makeStore('Boshqa');
        $foreignProduct = \App\Models\Product::create(['store_id' => $foreign->id, 'category_id' => $this->product->category_id, 'name' => 'X', 'slug' => 'x-' . uniqid(), 'status' => 'active']);
        $foreignVariant = $this->makeVariant($foreignProduct, '1000');
        $this->postJson('/api/v1/owner/auto-guest-items', [
            'branch_id' => $this->branch->id, 'product_variant_id' => $foreignVariant->id, 'quantity_per_guest' => '1',
        ])->assertStatus(422)->assertJsonValidationErrors('product_variant_id');

        $this->patchJson("/api/v1/owner/auto-guest-items/{$rule['id']}", ['quantity_per_guest' => '1', 'is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->getJson('/api/v1/owner/auto-guest-items?branch_id=' . $this->branch->id)->assertOk()->assertJsonCount(2, 'data');
        $this->deleteJson("/api/v1/owner/auto-guest-items/{$rule['id']}")->assertOk();
        $this->assertSame(1, AutoGuestItem::count());

        // The VIP hall's own service charge, next to the branch's
        $this->putJson('/api/v1/owner/service-fees', [
            'branch_id' => $this->branch->id, 'service_charge_percent' => 10, 'place_fee_amount' => 0, 'place_fee_mode' => 'per_person',
        ])->assertOk();
        $this->putJson('/api/v1/owner/service-fees', [
            'hall_id' => $this->vipHall->id, 'service_charge_percent' => 15, 'place_fee_amount' => 0, 'place_fee_mode' => 'per_person',
        ])->assertOk()->assertJsonPath('data.hall_id', $this->vipHall->id)->assertJsonPath('data.branch_id', $this->branch->id);
        $this->putJson('/api/v1/owner/service-fees', [
            'hall_id' => $this->vipHall->id, 'service_charge_percent' => 20, 'place_fee_amount' => 0, 'place_fee_mode' => 'per_person',
        ])->assertOk();
        $this->assertSame(2, ServiceFeeSetting::count());
        $this->assertSame('20.00', ServiceFeeSetting::where('hall_id', $this->vipHall->id)->value('service_charge_percent'));
        $this->getJson('/api/v1/owner/halls?branch_id=' . $this->branch->id)->assertOk()->assertJsonPath('data.1.service_fee.service_charge_percent', '20.00');

        $this->deleteJson("/api/v1/owner/service-fees/halls/{$this->vipHall->id}")->assertOk();
        $this->assertSame(1, ServiceFeeSetting::count());
    }

    public function test_permissions_branch_and_store_isolation(): void
    {
        $otherHall = Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->otherBranch->id, 'name' => 'B zal']);
        $foreignStore = $this->makeStore('Begona');
        $foreignBranch = $this->makeBranch($foreignStore, 'Begona filial');
        $foreignHall = Hall::create(['store_id' => $foreignStore->id, 'branch_id' => $foreignBranch->id, 'name' => 'Begona zal']);

        // The branch manager: their branch only
        $this->actingAs($this->manager, 'sanctum');
        $this->getJson('/api/v1/owner/halls')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/owner/halls?branch_id=' . $this->otherBranch->id)->assertForbidden();
        $this->patchJson("/api/v1/owner/halls/{$otherHall->id}", ['name' => 'X'])->assertForbidden();
        $this->postJson('/api/v1/owner/halls', ['branch_id' => $this->otherBranch->id, 'name' => 'X'])->assertForbidden();
        $this->postJson('/api/v1/owner/dining-tables', ['hall_id' => $otherHall->id, 'name' => 'X'])->assertForbidden();
        $this->patchJson("/api/v1/owner/halls/{$foreignHall->id}", ['name' => 'X'])->assertNotFound();
        $this->getJson('/api/v1/owner/halls?branch_id=' . $foreignBranch->id)->assertNotFound();
        $this->patchJson("/api/v1/owner/halls/{$this->hall->id}", ['sort_order' => 5])->assertOk();
        // Tariffs and per-guest items are settings (settings.manage: the owner)
        $this->getJson('/api/v1/owner/room-tariffs')->assertForbidden();
        $this->postJson('/api/v1/owner/auto-guest-items', ['branch_id' => $this->branch->id])->assertForbidden();

        // A cook has no tables.manage
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->makeCook(), 'sanctum')->getJson('/api/v1/owner/halls')->assertForbidden();

        // The owner of another store sees none of these
        $this->app['auth']->forgetGuards();
        $foreignOwner = $this->makeStaff(UserRole::OWNER, null, $foreignStore);
        $this->actingAs($foreignOwner, 'sanctum')->getJson('/api/v1/owner/halls')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/v1/owner/dining-tables/{$this->room->id}")->assertNotFound();
        $this->patchJson("/api/v1/owner/room-tariffs/" . RoomTariff::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'table_kind' => 'room', 'day_type' => 'weekday', 'price' => '1',
        ])->id, ['price' => '2'])->assertNotFound();
        $this->deleteJson("/api/v1/owner/service-fees/halls/{$this->hall->id}")->assertNotFound();

        // Never from a terminal PIN session
        $terminal = $this->pairTerminal($this->owner, $this->branch);
        $token = $this->pinSession($terminal, $this->owner);
        $this->bearerJson('GET', '/api/v1/owner/halls', $token)->assertForbidden();
        $this->bearerJson('POST', '/api/v1/owner/room-tariffs', $token, ['branch_id' => $this->branch->id])->assertForbidden();
    }
}
