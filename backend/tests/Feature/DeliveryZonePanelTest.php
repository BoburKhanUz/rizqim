<?php

namespace Tests\Feature;

use App\Enums\CourierType;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Filament\Resources\DeliveryZones\DeliveryZoneResource;
use App\Filament\Resources\DeliveryZones\Pages\CreateDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\EditDeliveryZone;
use App\Filament\Resources\DeliveryZones\Pages\ListDeliveryZones;
use App\Filament\Resources\Employees\Pages\ManageEmployees;
use App\Models\DeliveryZone;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\RolePermissions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The store panel's zone editor: owner only (zones.manage), always the panel store's zones, GeoJSON validated.
 */
class DeliveryZonePanelTest extends TestCase
{
    use RefreshDatabase;
    use AccessFixtures;
    use DeliveryZoneFixtures;

    private Store $foreignStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->foreignStore = $this->makeStore('Foreign Grill');
    }

    private function signIn(User $user, ?Store $tenant = null): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');
        Filament::setTenant($tenant ?? $this->store);
    }

    private function formData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Markaz',
            'priority' => 3,
            'active' => true,
            'delivery_price' => 12000,
            'min_order_amount' => 30000,
            'free_delivery_from' => 150000,
            'eta_minutes' => 40,
            'polygon' => json_encode($this->boxPolygon(self::CENTRE_BOX)),
        ], $overrides);
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

    public function test_managers_and_other_staff_cannot_open_the_zone_screens(): void
    {
        $zone = $this->makeZone();

        foreach ([$this->manager, $this->courier, $this->makeStaff(UserRole::CASHIER)] as $staff) {
            $this->signIn($staff);
            $this->assertFalse(DeliveryZoneResource::canViewAny());
            $this->assertFalse(DeliveryZoneResource::canCreate());
            $this->assertFalse(DeliveryZoneResource::canEdit($zone));
            $this->assertFalse(DeliveryZoneResource::canDelete($zone));
            Livewire::test(ListDeliveryZones::class)->assertForbidden();
        }
    }

    public function test_the_owner_can_manage_zones(): void
    {
        $zone = $this->makeZone();
        $this->signIn($this->owner);

        $this->assertTrue(DeliveryZoneResource::canViewAny());
        $this->assertTrue(DeliveryZoneResource::canCreate());
        $this->assertTrue(DeliveryZoneResource::canEdit($zone));
        Livewire::test(ListDeliveryZones::class)->assertOk()->assertCanSeeTableRecords([$zone]);
    }

    // ---- tenant isolation ------------------------------------------------------------------------------------------

    public function test_the_list_and_queries_show_only_the_panel_stores_zones(): void
    {
        $mine = $this->makeZone();
        $foreign = $this->makeZone(['name' => 'Begona'], $this->foreignStore);
        $this->signIn($this->owner);

        Livewire::test(ListDeliveryZones::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$foreign]);
        $this->assertFalse(DeliveryZoneResource::canEdit($foreign));
    }

    public function test_a_foreign_zone_cannot_be_opened_or_changed_through_the_panel(): void
    {
        $foreign = $this->makeZone(['name' => 'Begona'], $this->foreignStore);
        $tenant = $this->store->uuid;

        $this->actingAs($this->owner)->get("/admin/{$tenant}/delivery-zones/{$foreign->getRouteKey()}/edit")->assertNotFound();
        $this->actingAs($this->owner)->get("/admin/{$tenant}/delivery-zones")->assertOk();
        $this->actingAs($this->owner)->get("/admin/{$tenant}/delivery-zones/create")->assertOk();
    }

    public function test_a_manager_gets_403_on_the_zone_pages(): void
    {
        $this->actingAs($this->manager)->get("/admin/{$this->store->uuid}/delivery-zones")->assertForbidden();
    }

    public function test_a_zone_is_created_in_the_panel_store_whatever_the_form_says(): void
    {
        $this->signIn($this->owner);

        Livewire::test(CreateDeliveryZone::class)
            ->fillForm($this->formData())
            ->call('create')
            ->assertHasNoFormErrors();

        $zone = DeliveryZone::sole();
        $this->assertSame($this->store->id, $zone->store_id);
        $this->assertSame('Markaz', $zone->name);
        $this->assertSame('12000.00', $zone->delivery_price);
        $this->assertSame('150000.00', $zone->free_delivery_from);
        $this->assertSame(40, $zone->eta_minutes);
        $this->assertSame(3, $zone->priority);
        $this->assertSame('Polygon', $zone->polygon['type']);
        $this->assertSame('41.300000', $zone->min_lat);
    }

    public function test_a_branch_of_another_store_is_refused(): void
    {
        $foreignBranch = $this->makeBranch($this->foreignStore, 'Foreign Branch');
        $this->signIn($this->owner);

        Livewire::test(CreateDeliveryZone::class)
            ->fillForm($this->formData(['branch_id' => $foreignBranch->id]))
            ->call('create')
            ->assertHasFormErrors(['branch_id']);

        $this->assertDatabaseCount('delivery_zones', 0);

        Livewire::test(CreateDeliveryZone::class)
            ->fillForm($this->formData(['branch_id' => $this->branch->id]))
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame($this->branch->id, DeliveryZone::sole()->branch_id);
    }

    // ---- GeoJSON ---------------------------------------------------------------------------------------------------

    public function test_pasted_geojson_feature_is_accepted_and_stored_as_a_clean_geometry(): void
    {
        $this->signIn($this->owner);
        $feature = ['type' => 'Feature', 'properties' => ['name' => 'x'], 'geometry' => $this->boxPolygon(self::CITY_BOX)];

        Livewire::test(CreateDeliveryZone::class)
            ->fillForm($this->formData(['polygon' => json_encode($feature)]))
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($this->boxPolygon(self::CITY_BOX), DeliveryZone::sole()->polygon);
    }

    public function test_invalid_geojson_is_rejected_with_a_message(): void
    {
        $this->signIn($this->owner);
        $open = ['type' => 'Polygon', 'coordinates' => [[[69.2, 41.3], [69.3, 41.3], [69.3, 41.4], [69.2, 41.4]]]];

        foreach (['', 'nope', json_encode(['type' => 'Point', 'coordinates' => [69.2, 41.3]]), json_encode($open)] as $polygon) {
            Livewire::test(CreateDeliveryZone::class)
                ->fillForm($this->formData(['polygon' => $polygon]))
                ->call('create')
                ->assertHasFormErrors(['polygon']);
        }

        $this->assertDatabaseCount('delivery_zones', 0);
    }

    public function test_money_and_limits_are_validated(): void
    {
        $this->signIn($this->owner);

        foreach ([['delivery_price' => -1], ['min_order_amount' => -5], ['free_delivery_from' => -1], ['eta_minutes' => 0], ['name' => '']] as $bad) {
            Livewire::test(CreateDeliveryZone::class)
                ->fillForm($this->formData($bad))
                ->call('create')
                ->assertHasFormErrors(array_keys($bad));
        }
    }

    public function test_the_edit_form_shows_the_stored_polygon_as_json_and_saves_changes(): void
    {
        $zone = $this->makeZone(['name' => 'Eski']);
        $this->signIn($this->owner);

        $component = Livewire::test(EditDeliveryZone::class, ['record' => $zone->getRouteKey()])
            ->assertFormSet(['name' => 'Eski', 'polygon' => json_encode($zone->fresh()->polygon)]);

        $component->fillForm(['name' => 'Yangi', 'polygon' => json_encode($this->boxPolygon(self::CITY_BOX)), 'active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $zone->refresh();
        $this->assertSame('Yangi', $zone->name);
        $this->assertFalse($zone->active);
        $this->assertSame('41.250000', $zone->min_lat);
    }

    public function test_the_map_editor_view_renders_with_pinned_assets(): void
    {
        $this->signIn($this->owner);

        $html = Livewire::test(CreateDeliveryZone::class)->html();

        $this->assertStringContainsString('leaflet/1.9.4/leaflet.min.js', $html);
        $this->assertStringContainsString('leaflet.draw/1.0.4/leaflet.draw.js', $html);
        $this->assertStringContainsString('GeoJSON', $html);
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

    public function test_the_owner_sees_the_courier_type_but_only_a_superadmin_changes_it(): void
    {
        $this->signIn($this->owner);
        Livewire::test(ManageEmployees::class)
            ->assertCanSeeTableRecords([$this->courier])
            ->callAction('create', data: ['name' => 'Yangi Kuryer', 'role' => UserRole::COURIER->value, 'branch_id' => $this->branch->id, 'courier_type' => CourierType::FLEET->value])
            ->assertHasNoActionErrors();

        $this->assertSame(CourierType::OWN, User::where('name', 'Yangi Kuryer')->sole()->courier_type, 'the owner cannot make a courier a fleet courier');

        $admin = User::create(['name' => 'Admin', 'phone' => '+998901112233', 'password' => bcrypt('x'), 'role' => UserRole::SUPER_ADMIN->value, 'telegram_id' => '900000001']);
        $this->signIn($admin);
        Livewire::test(ManageEmployees::class)
            ->callAction('create', data: ['name' => 'Rizqim Kuryer', 'role' => UserRole::COURIER->value, 'branch_id' => $this->branch->id, 'courier_type' => CourierType::FLEET->value])
            ->assertHasNoActionErrors();

        $this->assertSame(CourierType::FLEET, User::where('name', 'Rizqim Kuryer')->sole()->courier_type);
    }
}
