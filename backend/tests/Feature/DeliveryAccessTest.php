<?php

namespace Tests\Feature;

use App\Enums\CourierType;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\RolePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Delivery rights of a store: zones are the owner's (zones.manage, never granted to others; the zone API itself is in
 * ManagementZonesApiTest), and couriers are the restaurant's own unless the platform says otherwise.
 */
class DeliveryAccessTest extends TestCase
{
    use RefreshDatabase;
    use AccessFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    // ---- permission ------------------------------------------------------------------------------------------------

    public function test_only_the_owner_holds_zones_manage_and_it_cannot_be_granted(): void
    {
        foreach (UserRole::cases() as $role) {
            $holds = in_array(Permission::ZONES_MANAGE, RolePermissions::for($role), true);
            $this->assertSame(in_array($role, [UserRole::OWNER, UserRole::SUPER_ADMIN], true), $holds, "zones.manage for {$role->value}");
        }

        $this->assertFalse(Permission::ZONES_MANAGE->isGrantable());
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/permissions', [
            'role' => 'manager', 'permission' => 'zones.manage', 'allowed' => true,
        ])->assertUnprocessable();
        $this->assertFalse(app(PermissionService::class)->has($this->manager, Permission::ZONES_MANAGE));
    }

    // ---- courier type ----------------------------------------------------------------------------------------------

    public function test_couriers_are_the_restaurants_own_by_default_and_the_list_shows_the_type(): void
    {
        $this->assertSame(CourierType::OWN, $this->courier->fresh()->courier_type);

        $this->courier->forceFill(['courier_type' => CourierType::FLEET])->save();
        $this->branch->update(['status' => 'active']);

        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/couriers')->assertOk()
            ->assertJsonFragment(['id' => $this->courier->id, 'courier_type' => 'fleet']);
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/employees')->assertOk()
            ->assertJsonFragment(['id' => $this->courier->id, 'courier_type' => 'fleet']);
    }

    /**
     * P4-33 (ported from the old store panel's employee form): a store cannot make its own courier a RIZQIM fleet
     * courier; whatever it sends, a new courier is the restaurant's own.
     */
    public function test_the_owner_cannot_make_a_courier_a_fleet_courier(): void
    {
        $id = $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/staff', [
            'name' => 'Yangi Kuryer', 'role' => UserRole::COURIER->value, 'branch_id' => $this->branch->id, 'courier_type' => CourierType::FLEET->value,
        ])->assertCreated()->json('data.staff.id');

        $this->assertSame(CourierType::OWN, User::findOrFail($id)->courier_type);
    }
}
