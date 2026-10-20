<?php

namespace Tests\Feature;

use App\Enums\BranchStatus;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * GET /api/v1/stores/{store}/zones and GET /api/v1/delivery/quote: public, read-only, computed by ZoneResolver.
 */
class DeliveryQuoteApiTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use DeliveryZoneFixtures;

    // Amir Temur Square (inside the centre box), Chorsu Bazaar (outside it)
    private const INSIDE = ['lat' => 41.3111, 'lng' => 69.2797];
    private const OUTSIDE = ['lat' => 41.3265, 'lng' => 69.2358];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        // The fixture branches sit on 41.311081, 69.240562; the tests move them where they need them
        Branch::where('store_id', $this->store->id)->where('id', '!=', $this->branch->id)->delete();
        RateLimiter::clear('delivery-quote');
    }

    private function quote(array $query): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/v1/delivery/quote?' . http_build_query($query));
    }

    // ---- zones list ------------------------------------------------------------------------------------------------

    public function test_zone_list_is_public_and_hides_internal_fields(): void
    {
        $this->makeZone(['name' => 'Markaz', 'free_delivery_from' => 150000, 'eta_minutes' => 40, 'priority' => 2, 'branch_id' => $this->branch->id]);

        $response = $this->getJson("/api/v1/stores/{$this->store->uuid}/zones")->assertOk();

        $response->assertJsonPath('success', true)->assertJsonCount(1, 'data');
        $zone = $response->json('data.0');
        $this->assertSame(['delivery_price', 'eta_minutes', 'free_delivery_from', 'id', 'min_order_amount', 'name', 'polygon', 'priority'], collect($zone)->keys()->sort()->values()->all());
        $this->assertSame('Markaz', $zone['name']);
        $this->assertSame('12000.00', $zone['delivery_price']);
        $this->assertSame('30000.00', $zone['min_order_amount']);
        $this->assertSame('150000.00', $zone['free_delivery_from']);
        $this->assertSame(40, $zone['eta_minutes']);
        $this->assertSame('Polygon', $zone['polygon']['type']);
        $this->assertSame([69.26, 41.3], $zone['polygon']['coordinates'][0][0]);
    }

    public function test_zone_list_has_active_zones_only_best_first_and_works_by_numeric_id(): void
    {
        $this->makeZone(['name' => 'Past', 'priority' => 1]);
        $this->makeZone(['name' => 'Yuqori', 'priority' => 9]);
        $this->makeZone(['name' => "O'chirilgan", 'active' => false, 'priority' => 99]);

        $this->getJson("/api/v1/stores/{$this->store->id}/zones")->assertOk()
            ->assertJsonPath('data.0.name', 'Yuqori')
            ->assertJsonPath('data.1.name', 'Past')
            ->assertJsonCount(2, 'data');
    }

    public function test_zone_list_never_shows_another_stores_zones(): void
    {
        $foreignStore = $this->makeStore('Foreign Grill');
        $this->makeZone(['name' => 'Begona'], $foreignStore);

        $this->getJson("/api/v1/stores/{$this->store->uuid}/zones")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/stores/{$foreignStore->uuid}/zones")->assertOk()->assertJsonPath('data.0.name', 'Begona');
    }

    public function test_unknown_hidden_or_malformed_stores_give_404(): void
    {
        $this->getJson('/api/v1/stores/00000000-0000-4000-8000-000000000000/zones')->assertNotFound();
        $this->getJson('/api/v1/stores/999999/zones')->assertNotFound();
        $this->getJson('/api/v1/stores/not-a-store/zones')->assertNotFound();

        $this->store->forceFill(['is_active' => false])->save();
        $this->getJson("/api/v1/stores/{$this->store->uuid}/zones")->assertNotFound();
        $this->quote(['store' => $this->store->uuid] + self::INSIDE)->assertNotFound();
    }

    // ---- quote -----------------------------------------------------------------------------------------------------

    public function test_quote_inside_a_zone(): void
    {
        $this->branch->update(['latitude' => 41.3138, 'longitude' => 69.2790]);
        $zone = $this->makeZone(['free_delivery_from' => 150000, 'eta_minutes' => 35]);

        $response = $this->quote(['store' => $this->store->uuid, 'subtotal' => 80000] + self::INSIDE)->assertOk();

        $response->assertExactJson(['success' => true, 'message' => '', 'data' => [
            'zone' => [
                'id' => $zone->id,
                'name' => 'Markaz',
                'delivery_price' => '12000.00',
                'min_order_amount' => '30000.00',
                'free_delivery_from' => '150000.00',
                'eta_minutes' => 35,
            ],
            'branch' => ['id' => $this->branch->id, 'name' => 'Main Branch', 'distance_km' => $response->json('data.branch.distance_km')],
            'delivery_price' => '12000.00',
            'min_order_amount' => '30000.00',
            'free_delivery_applies' => false,
            'meets_min_order' => true,
            'eta' => ['minutes' => 35],
        ]]);
        $this->assertEqualsWithDelta(0.3, $response->json('data.branch.distance_km'), 0.1);
    }

    public function test_quote_without_subtotal_leaves_the_order_dependent_flags_open(): void
    {
        $this->makeZone(['free_delivery_from' => 150000]);

        $this->quote(['store' => $this->store->id] + self::INSIDE)->assertOk()
            ->assertJsonPath('data.delivery_price', '12000.00')
            ->assertJsonPath('data.free_delivery_applies', false)
            ->assertJsonPath('data.meets_min_order', null)
            ->assertJsonPath('data.eta', null)
            ->assertJsonPath('data.zone.eta_minutes', null);
    }

    public function test_free_delivery_applies_from_the_threshold_inclusive(): void
    {
        $this->makeZone(['free_delivery_from' => 150000]);

        $this->quote(['store' => $this->store->uuid, 'subtotal' => '149999.99'] + self::INSIDE)->assertOk()
            ->assertJsonPath('data.free_delivery_applies', false)
            ->assertJsonPath('data.delivery_price', '12000.00');

        $this->quote(['store' => $this->store->uuid, 'subtotal' => 150000] + self::INSIDE)->assertOk()
            ->assertJsonPath('data.free_delivery_applies', true)
            ->assertJsonPath('data.delivery_price', '0.00')
            ->assertJsonPath('data.zone.delivery_price', '12000.00');
    }

    public function test_no_free_delivery_without_a_threshold(): void
    {
        $this->makeZone();

        $this->quote(['store' => $this->store->uuid, 'subtotal' => 99999999] + self::INSIDE)->assertOk()
            ->assertJsonPath('data.free_delivery_applies', false)
            ->assertJsonPath('data.zone.free_delivery_from', null);
    }

    public function test_minimum_order_is_reported_not_enforced(): void
    {
        $this->makeZone();

        $this->quote(['store' => $this->store->uuid, 'subtotal' => 29999.99] + self::INSIDE)->assertOk()
            ->assertJsonPath('data.meets_min_order', false)
            ->assertJsonPath('data.min_order_amount', '30000.00');
        $this->quote(['store' => $this->store->uuid, 'subtotal' => 30000] + self::INSIDE)->assertOk()
            ->assertJsonPath('data.meets_min_order', true);
    }

    public function test_the_best_zone_prices_the_quote(): void
    {
        $this->makeZone(['name' => 'Shahar', 'delivery_price' => 20000, 'priority' => 1], box: self::CITY_BOX);
        $this->makeZone(['name' => 'Markaz', 'delivery_price' => 8000, 'priority' => 1]);

        $this->quote(['store' => $this->store->uuid] + self::INSIDE)->assertOk()
            ->assertJsonPath('data.zone.name', 'Markaz')
            ->assertJsonPath('data.delivery_price', '8000.00');
        $this->quote(['store' => $this->store->uuid, 'lat' => 41.34, 'lng' => 69.33])->assertOk()
            ->assertJsonPath('data.zone.name', 'Shahar');
    }

    public function test_outside_every_zone_is_422_outside_zone(): void
    {
        $this->makeZone();

        $this->quote(['store' => $this->store->uuid] + self::OUTSIDE)->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.reason', 'outside_zone')
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'hududdan tashqarida'));
    }

    public function test_a_store_without_zones_is_422_zones_not_configured(): void
    {
        $this->makeZone(['active' => false]);

        $this->quote(['store' => $this->store->uuid] + self::INSIDE)->assertStatus(422)
            ->assertJsonPath('data.reason', 'zones_not_configured');
    }

    public function test_the_zones_own_branch_serves_it_and_when_closed_the_quote_is_refused(): void
    {
        $this->branch->update(['latitude' => 41.35, 'longitude' => 69.30]);
        $near = Branch::create(['store_id' => $this->store->id, 'name' => 'Yaqin', 'latitude' => 41.3112, 'longitude' => 69.2798, 'radius_km' => 5, 'status' => BranchStatus::ACTIVE->value]);
        $this->makeZone(['branch_id' => $this->branch->id]);

        // The zone names the far branch although another one is closer
        $this->quote(['store' => $this->store->uuid] + self::INSIDE)->assertOk()
            ->assertJsonPath('data.branch.id', $this->branch->id);

        $this->branch->update(['status' => BranchStatus::TEMPORARILY_CLOSED->value]);
        $this->quote(['store' => $this->store->uuid] + self::INSIDE)->assertStatus(422)
            ->assertJsonPath('data.reason', 'branch_closed');
        $this->assertNotNull($near);
    }

    public function test_a_zone_without_a_branch_goes_to_the_nearest_open_one(): void
    {
        $this->branch->update(['latitude' => 41.3112, 'longitude' => 69.2798, 'open_time' => '09:00:00', 'close_time' => '18:00:00']);
        $far = Branch::create(['store_id' => $this->store->id, 'name' => 'Uzoq', 'latitude' => 41.35, 'longitude' => 69.30, 'radius_km' => 5, 'status' => BranchStatus::ACTIVE->value]);
        $this->makeZone();

        $this->travelTo(now('Asia/Tashkent')->setTime(12, 0));
        $this->quote(['store' => $this->store->uuid] + self::INSIDE)->assertOk()->assertJsonPath('data.branch.id', $this->branch->id);

        $this->travelTo(now('Asia/Tashkent')->setTime(21, 0));
        $this->quote(['store' => $this->store->uuid] + self::INSIDE)->assertOk()->assertJsonPath('data.branch.id', $far->id);

        $far->update(['status' => BranchStatus::INACTIVE->value]);
        $this->quote(['store' => $this->store->uuid] + self::INSIDE)->assertStatus(422)->assertJsonPath('data.reason', 'branch_closed');
    }

    public function test_quote_never_uses_another_stores_zone_or_branch(): void
    {
        $foreignStore = $this->makeStore('Foreign Grill');
        $this->makeBranch($foreignStore, 'Foreign Branch');
        $this->makeZone(['name' => 'Begona'], $foreignStore);

        $this->quote(['store' => $this->store->uuid] + self::INSIDE)->assertStatus(422)
            ->assertJsonPath('data.reason', 'zones_not_configured');
    }

    public function test_quote_input_is_validated(): void
    {
        $this->makeZone();
        $store = $this->store->uuid;

        $this->quote(['lat' => 41.3, 'lng' => 69.2])->assertStatus(422)->assertJsonValidationErrors('store');
        $this->quote(['store' => $store, 'lng' => 69.2])->assertStatus(422)->assertJsonValidationErrors('lat');
        $this->quote(['store' => $store, 'lat' => 41.3])->assertStatus(422)->assertJsonValidationErrors('lng');
        $this->quote(['store' => $store, 'lat' => 91, 'lng' => 69.2])->assertStatus(422)->assertJsonValidationErrors('lat');
        $this->quote(['store' => $store, 'lat' => 41.3, 'lng' => 181])->assertStatus(422)->assertJsonValidationErrors('lng');
        $this->quote(['store' => $store, 'lat' => 'abc', 'lng' => 69.2])->assertStatus(422)->assertJsonValidationErrors('lat');
        $this->quote(['store' => $store, 'subtotal' => -1] + self::INSIDE)->assertStatus(422)->assertJsonValidationErrors('subtotal');
        $this->quote(['store' => $store, 'subtotal' => 'lots'] + self::INSIDE)->assertStatus(422)->assertJsonValidationErrors('subtotal');
    }

    public function test_the_quote_is_rate_limited(): void
    {
        $this->makeZone();
        $query = ['store' => $this->store->uuid] + self::INSIDE;

        foreach (range(1, 60) as $i) {
            $this->quote($query)->assertOk();
        }

        $this->quote($query)->assertStatus(429);
    }

    public function test_no_token_is_needed_and_a_token_changes_nothing(): void
    {
        $this->makeZone();
        $query = ['store' => $this->store->uuid] + self::INSIDE;

        $anonymous = $this->quote($query)->assertOk()->json('data');
        $asCustomer = $this->actingAs($this->customer, 'sanctum')->quote($query)->assertOk()->json('data');

        $this->assertSame($anonymous, $asCustomer);
    }
}
