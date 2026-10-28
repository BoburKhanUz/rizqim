<?php

namespace Tests\Feature;

use App\Enums\BranchStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\DeliveryZone;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P2-12: the owner's delivery zone CRUD for the partner web panel (zones.manage), with the panel editor's rules.
 */
class ManagementZonesApiTest extends TestCase
{
    use RefreshDatabase;
    use ManagementFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Markaz',
            'branch_id' => $this->branch->id,
            'priority' => 3,
            'delivery_price' => '12000',
            'min_order_amount' => 30000,
            'free_delivery_from' => '150000.50',
            'eta_minutes' => 40,
            'polygon' => $this->boxPolygon(self::CENTRE_BOX),
        ], $overrides);
    }

    public function test_the_owner_creates_reads_updates_and_deletes_a_zone(): void
    {
        $created = $this->as($this->owner, 'POST', '/api/v1/owner/delivery-zones', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Markaz')
            ->assertJsonPath('data.branch.id', $this->branch->id)
            ->assertJsonPath('data.delivery_price', '12000.00')
            ->assertJsonPath('data.min_order_amount', '30000.00')
            ->assertJsonPath('data.free_delivery_from', '150000.50')
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.bbox.min_lat', '41.300000')
            ->assertJsonPath('data.bbox.max_lng', '69.300000')
            ->json('data');

        $zone = DeliveryZone::findOrFail($created['id']);
        $this->assertSame($this->store->id, (int) $zone->store_id);

        $this->as($this->owner, 'GET', '/api/v1/owner/delivery-zones')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $zone->id);
        $this->as($this->owner, 'GET', "/api/v1/owner/delivery-zones/{$zone->id}")->assertOk()->assertJsonPath('data.polygon.type', 'Polygon');

        // A new shape: the bounding box follows the polygon
        $this->as($this->owner, 'PATCH', "/api/v1/owner/delivery-zones/{$zone->id}", [
            'polygon' => json_encode($this->boxPolygon(self::CITY_BOX)),
            'delivery_price' => 9000,
            'active' => false,
        ])->assertOk()
            ->assertJsonPath('data.delivery_price', '9000.00')
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.bbox.min_lat', '41.250000')
            ->assertJsonPath('data.bbox.max_lat', '41.360000')
            ->assertJsonPath('data.name', 'Markaz');

        $update = AuditLog::where('action', 'delivery_zone.updated')->sole();
        $this->assertSame(['delivery_price' => '12000.00', 'active' => true], $update->before);
        $this->assertSame(['polygon_changed' => true], $update->payload_json);
        $this->assertSame($this->owner->id, $update->user_id);

        $this->as($this->owner, 'GET', '/api/v1/owner/delivery-zones?active=1')->assertOk()->assertJsonCount(0, 'data');

        $this->as($this->owner, 'DELETE', "/api/v1/owner/delivery-zones/{$zone->id}")->assertOk();
        $this->assertNull(DeliveryZone::find($zone->id));
        $this->assertSame(1, AuditLog::where('action', 'delivery_zone.created')->where('store_id', $this->store->id)->count());
        $this->assertSame(1, AuditLog::where('action', 'delivery_zone.deleted')->where('store_id', $this->store->id)->count());
    }

    public function test_the_polygon_and_fields_follow_the_editor_rules(): void
    {
        $openRing = ['type' => 'Polygon', 'coordinates' => [[[69.26, 41.30], [69.30, 41.30], [69.30, 41.32], [69.26, 41.32]]]];
        $line = ['type' => 'Polygon', 'coordinates' => [[[69.26, 41.30], [69.27, 41.30], [69.28, 41.30], [69.26, 41.30]]]];
        $tooMany = ['type' => 'Polygon', 'coordinates' => [array_merge(
            array_map(fn (int $i) => [69.0 + $i / 10000, 41.0 + (($i % 2) * 0.01)], range(0, 510)),
            [[69.0, 41.0]],
        )]];

        $cases = [
            'point' => [['polygon' => ['type' => 'Point', 'coordinates' => [69.2, 41.3]]], 'polygon', 'Polygon yoki MultiPolygon'],
            'open ring' => [['polygon' => $openRing], 'polygon', null],
            'zero area' => [['polygon' => $line], 'polygon', null],
            'too many points' => [['polygon' => $tooMany], 'polygon', null],
            'bad json' => [['polygon' => '{"type": "Polygon"'], 'polygon', "noto'g'ri formatda"],
            'out of range' => [['polygon' => ['type' => 'Polygon', 'coordinates' => [[[200, 41.3], [69.3, 41.3], [69.3, 41.32], [200, 41.3]]]]], 'polygon', null],
            'no polygon' => [['polygon' => null], 'polygon', 'Hudud majburiy'],
            'negative price' => [['delivery_price' => -1], 'delivery_price', 'kamida 0'],
            'three decimals' => [['min_order_amount' => '100.123'], 'min_order_amount', 'xonali'],
            'priority' => [['priority' => 1001], 'priority', null],
            'eta' => [['eta_minutes' => 0], 'eta_minutes', null],
            'long name' => [['name' => str_repeat('a', 121)], 'name', "ko'pi bilan 120"],
            'huge branch id' => [['branch_id' => '99999999999999999999'], 'branch_id', null],
        ];

        foreach ($cases as $label => [$overrides, $field, $message]) {
            $response = $this->as($this->owner, 'POST', '/api/v1/owner/delivery-zones', $this->payload($overrides))
                ->assertStatus(422)
                ->assertJsonValidationErrors($field);
            if ($message !== null) {
                $this->assertStringContainsString($message, $response->json("errors.{$field}.0"), $label);
            }
        }

        $this->assertSame(0, DeliveryZone::count());
    }

    /**
     * P4-33 (ported from the old store panel's zone editor): a pasted GeoJSON Feature is accepted and only its geometry
     * is stored.
     */
    public function test_a_pasted_geojson_feature_is_stored_as_a_clean_geometry(): void
    {
        $feature = ['type' => 'Feature', 'properties' => ['name' => 'x'], 'geometry' => $this->boxPolygon(self::CITY_BOX)];

        $this->as($this->owner, 'POST', '/api/v1/owner/delivery-zones', $this->payload(['polygon' => json_encode($feature)]))
            ->assertCreated()
            ->assertJsonPath('data.bbox.min_lat', '41.250000');

        $this->assertSame($this->boxPolygon(self::CITY_BOX), DeliveryZone::sole()->polygon);
    }

    public function test_the_branch_must_be_an_active_branch_of_the_store(): void
    {
        $closed = $this->makeBranch($this->store, 'Closed');
        $closed->update(['status' => BranchStatus::INACTIVE]);

        foreach ([$this->foreignBranch->id, $closed->id] as $branchId) {
            $this->as($this->owner, 'POST', '/api/v1/owner/delivery-zones', $this->payload(['branch_id' => $branchId]))
                ->assertStatus(422)
                ->assertJsonPath('errors.branch_id.0', 'Filial topilmadi yoki faol emas.');
        }

        // No branch: the nearest open one takes the orders
        $this->as($this->owner, 'POST', '/api/v1/owner/delivery-zones', $this->payload(['branch_id' => null]))
            ->assertCreated()->assertJsonPath('data.branch', null);
    }

    public function test_only_zones_manage_holders_reach_the_endpoints(): void
    {
        $zone = $this->makeZone();
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $storeWideManager = $this->makeStaff(UserRole::MANAGER);

        foreach ([$this->manager, $storeWideManager, $cashier, $this->courier, $this->customer] as $user) {
            $this->as($user, 'GET', '/api/v1/owner/delivery-zones')->assertForbidden();
            $this->as($user, 'POST', '/api/v1/owner/delivery-zones', $this->payload())->assertForbidden();
            $this->as($user, 'PATCH', "/api/v1/owner/delivery-zones/{$zone->id}", ['name' => 'X'])->assertForbidden();
            $this->as($user, 'DELETE', "/api/v1/owner/delivery-zones/{$zone->id}")->assertForbidden();
        }
        $this->as(null, 'GET', '/api/v1/owner/delivery-zones')->assertUnauthorized();

        // Not even through a terminal PIN session of the owner
        $this->bearerJson('GET', '/api/v1/owner/delivery-zones', $this->pinSession($this->owner))
            ->assertForbidden()->assertJsonPath('data.reason', 'staff_session_not_allowed');

        $this->assertSame('Markaz', $zone->refresh()->name);
    }

    public function test_another_stores_zones_are_not_found(): void
    {
        $foreign = $this->makeZone(['name' => 'Foreign'], $this->foreignStore);
        $this->makeZone();

        $this->as($this->owner, 'GET', '/api/v1/owner/delivery-zones')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Markaz');
        $this->as($this->owner, 'GET', "/api/v1/owner/delivery-zones/{$foreign->id}")->assertNotFound();
        $this->as($this->owner, 'PATCH', "/api/v1/owner/delivery-zones/{$foreign->id}", ['name' => 'Mine'])->assertNotFound();
        $this->as($this->owner, 'DELETE', "/api/v1/owner/delivery-zones/{$foreign->id}")->assertNotFound();
        $this->as($this->owner, 'GET', '/api/v1/owner/delivery-zones/99999999999999999999')->assertNotFound();

        $this->assertSame('Foreign', $foreign->refresh()->name);
    }

    public function test_zones_manage_cannot_be_granted_to_managers(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(PermissionService::class)->setOverride($this->store, UserRole::MANAGER, \App\Enums\Permission::ZONES_MANAGE, true);
    }
}
