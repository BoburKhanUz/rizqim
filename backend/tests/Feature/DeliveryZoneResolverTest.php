<?php

namespace Tests\Feature;

use App\Domain\Delivery\ZoneResolver;
use App\Enums\BranchStatus;
use App\Models\Branch;
use App\Models\DeliveryZone;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DeliveryZoneResolverTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use DeliveryZoneFixtures;

    private ZoneResolver $resolver;

    // Amir Temur Square, Chorsu Bazaar
    private const AMIR_TEMUR = [41.3111, 69.2797];
    private const CHORSU = [41.3265, 69.2358];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->resolver = new ZoneResolver();
    }

    // ---- zone model ------------------------------------------------------------------------------------------------

    public function test_saving_a_zone_derives_the_bounding_box_and_area_and_cleans_the_polygon(): void
    {
        $zone = $this->makeZone(['polygon' => [
            'type' => 'Feature',
            'geometry' => ['type' => 'Polygon', 'coordinates' => [[[69.2600004, 41.30], [69.30, 41.30], [69.30, 41.32], [69.26, 41.32], [69.26, 41.30]]]],
        ]])->refresh();

        $this->assertSame('Polygon', $zone->polygon['type']);
        $this->assertSame([69.26, 41.3], $zone->polygon['coordinates'][0][0]);
        $this->assertSame('41.300000', $zone->min_lat);
        $this->assertSame('41.320000', $zone->max_lat);
        $this->assertSame('69.260000', $zone->min_lng);
        $this->assertSame('69.300000', $zone->max_lng);
        $this->assertEqualsWithDelta(7.4, (float) $zone->area_km2, 0.2);
        $this->assertSame('12000.00', $zone->delivery_price);
        $this->assertNull($zone->free_delivery_from);
        $this->assertTrue($zone->active);
    }

    public function test_updating_the_polygon_refreshes_the_bounding_box(): void
    {
        $zone = $this->makeZone();

        $zone->update(['polygon' => $this->boxPolygon(self::CITY_BOX)]);

        $this->assertSame('41.250000', $zone->fresh()->min_lat);
        $this->assertSame('69.350000', $zone->fresh()->max_lng);
    }

    public function test_derived_columns_cannot_be_mass_assigned(): void
    {
        $zone = $this->makeZone(['min_lat' => 10, 'area_km2' => 1]);

        $this->assertSame('41.300000', $zone->fresh()->min_lat);
        $this->assertGreaterThan(1, (float) $zone->fresh()->area_km2);
    }

    public function test_an_invalid_polygon_is_not_saved(): void
    {
        $this->expectException(\App\Domain\Delivery\InvalidGeometryException::class);

        $this->makeZone(['polygon' => ['type' => 'Polygon', 'coordinates' => [[[69.2, 41.3], [69.3, 41.3], [69.2, 41.3]]]]]);
    }

    public function test_a_zone_cannot_name_a_branch_of_another_store(): void
    {
        $foreignStore = $this->makeStore('Foreign Grill');
        $foreignBranch = $this->makeBranch($foreignStore, 'Foreign Branch');

        $this->expectException(InvalidArgumentException::class);

        $this->makeZone(['branch_id' => $foreignBranch->id]);
    }

    // ---- resolve ---------------------------------------------------------------------------------------------------

    public function test_resolves_the_zone_around_a_point_and_null_outside(): void
    {
        $zone = $this->makeZone();

        $this->assertTrue($zone->is($this->resolver->resolve($this->store, ...self::AMIR_TEMUR)));
        $this->assertTrue($zone->is($this->resolver->resolve($this->store->id, ...self::AMIR_TEMUR)));
        $this->assertNull($this->resolver->resolve($this->store, ...self::CHORSU));
    }

    public function test_a_point_inside_the_bounding_box_but_outside_the_polygon_is_not_in_the_zone(): void
    {
        // Triangle (69.20,41.20) - (69.30,41.20) - (69.20,41.30): its bounding box also covers the empty upper right corner
        $this->makeZone(['polygon' => ['type' => 'Polygon', 'coordinates' => [[[69.20, 41.20], [69.30, 41.20], [69.20, 41.30], [69.20, 41.20]]]]]);

        $this->assertNotNull($this->resolver->resolve($this->store, 41.21, 69.21));
        $this->assertNull($this->resolver->resolve($this->store, 41.29, 69.29));
    }

    public function test_a_point_in_a_hole_falls_through_to_the_zone_behind_it(): void
    {
        $holed = $this->makeZone(['name' => 'Markaz', 'priority' => 5, 'polygon' => ['type' => 'Polygon', 'coordinates' => [
            [[69.26, 41.30], [69.30, 41.30], [69.30, 41.32], [69.26, 41.32], [69.26, 41.30]],
            [[69.275, 41.308], [69.285, 41.308], [69.285, 41.314], [69.275, 41.314], [69.275, 41.308]],
        ]]]);
        $city = $this->makeZone(['name' => 'Shahar', 'priority' => 1], box: self::CITY_BOX);

        $this->assertTrue($city->is($this->resolver->resolve($this->store, ...self::AMIR_TEMUR)), 'the hole belongs to the city zone');
        $this->assertTrue($holed->is($this->resolver->resolve($this->store, 41.305, 69.28)));
    }

    public function test_the_highest_priority_wins_whatever_the_size(): void
    {
        $small = $this->makeZone(['name' => 'Kichik', 'priority' => 1]);
        $big = $this->makeZone(['name' => 'Katta', 'priority' => 10], box: self::CITY_BOX);

        $this->assertTrue($big->is($this->resolver->resolve($this->store, ...self::AMIR_TEMUR)));
        $small->update(['priority' => 20]);
        $this->assertTrue($small->is($this->resolver->resolve($this->store, ...self::AMIR_TEMUR)));
    }

    public function test_equal_priority_prefers_the_smallest_zone_then_the_oldest(): void
    {
        $big = $this->makeZone(['name' => 'Katta'], box: self::CITY_BOX);
        $small = $this->makeZone(['name' => 'Kichik']);
        $this->assertTrue($small->is($this->resolver->resolve($this->store, ...self::AMIR_TEMUR)));

        // Two zones of the same size: the older one
        $this->makeZone(['name' => 'Egizak']);
        $this->assertTrue($small->is($this->resolver->resolve($this->store, ...self::AMIR_TEMUR)));
        $this->assertTrue($big->is($this->resolver->resolve($this->store, 41.34, 69.33)), 'only the big zone reaches here');
    }

    public function test_inactive_zones_and_other_stores_zones_are_ignored(): void
    {
        $this->makeZone(['active' => false, 'priority' => 100]);
        $foreignStore = $this->makeStore('Foreign Grill');
        $this->makeZone(['name' => 'Begona'], $foreignStore);

        $this->assertNull($this->resolver->resolve($this->store, ...self::AMIR_TEMUR));
        $this->assertFalse($this->resolver->hasZones($this->store));
        $this->assertTrue($this->resolver->hasZones($foreignStore));

        $mine = $this->makeZone(['name' => 'Mening']);
        $this->assertTrue($mine->is($this->resolver->resolve($this->store, ...self::AMIR_TEMUR)));
        $this->assertSame('Begona', $this->resolver->resolve($foreignStore, ...self::AMIR_TEMUR)->name);
    }

    public function test_the_answer_is_the_same_every_time(): void
    {
        $this->makeZone(['name' => 'A'], box: self::CITY_BOX);
        $this->makeZone(['name' => 'B'], box: self::CITY_BOX);
        $this->makeZone(['name' => 'C']);

        $ids = collect(range(1, 5))->map(fn () => $this->resolver->resolve($this->store, ...self::AMIR_TEMUR)->id)->unique();

        $this->assertCount(1, $ids);
    }

    public function test_only_the_bounding_box_candidates_are_tested_in_php(): void
    {
        $this->makeZone(['name' => 'Chorsu'], box: [69.22, 41.32, 69.25, 41.335]);
        $this->makeZone(['name' => 'Markaz']);

        $queries = [];
        \DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->assertSame('Markaz', $this->resolver->resolve($this->store, ...self::AMIR_TEMUR)->name);
        $this->assertStringContainsString('"min_lat" <=', $queries[0]);
        $this->assertStringContainsString('"max_lng" >=', $queries[0]);
    }

    // ---- nearest open branch ---------------------------------------------------------------------------------------

    private function branchAt(string $name, float $lat, float $lng, array $attributes = []): Branch
    {
        return Branch::create(array_merge([
            'store_id' => $this->store->id, 'name' => $name, 'latitude' => $lat, 'longitude' => $lng,
            'radius_km' => 5, 'status' => BranchStatus::ACTIVE->value,
        ], $attributes));
    }

    private function emptyStoreBranches(): void
    {
        Branch::where('store_id', $this->store->id)->delete();
    }

    public function test_nearest_open_branch_returns_the_closest_with_its_haversine_distance(): void
    {
        $this->emptyStoreBranches();
        $chilonzor = $this->branchAt('Chilonzor', 41.2753, 69.2036);
        $this->branchAt('Yunusobod', 41.3641, 69.2847);
        $center = $this->branchAt('Markaz', 41.3138, 69.2790);

        $nearest = $this->resolver->nearestOpenBranch($this->store, ...self::AMIR_TEMUR);

        $this->assertTrue($center->is($nearest->branch));
        $this->assertEqualsWithDelta(0.3, $nearest->distanceKm, 0.1);

        $far = $this->resolver->nearestOpenBranch($this->store, 41.2700, 69.2000);
        $this->assertTrue($chilonzor->is($far->branch));
    }

    public function test_the_closest_branch_is_skipped_when_it_is_closed_inactive_or_has_no_coordinates(): void
    {
        $this->emptyStoreBranches();
        $closed = $this->branchAt('Yopiq', 41.3112, 69.2798, ['open_time' => '09:00:00', 'close_time' => '18:00:00']);
        $this->branchAt('Faol emas', 41.3113, 69.2799, ['is_active' => false]);
        $this->branchAt('Vaqtincha yopiq', 41.3114, 69.2800, ['status' => BranchStatus::TEMPORARILY_CLOSED->value]);
        $this->branchAt('Joylashuvsiz', 41.3115, 69.2801, ['latitude' => null, 'longitude' => null]);
        $open = $this->branchAt('Ochiq', 41.3500, 69.3000);

        $evening = CarbonImmutable::parse('2026-10-22 20:00:00', 'Asia/Tashkent');
        $nearest = $this->resolver->nearestOpenBranch($this->store, ...self::AMIR_TEMUR, at: $evening);
        $this->assertTrue($open->is($nearest->branch));

        $noon = CarbonImmutable::parse('2026-10-22 12:00:00', 'Asia/Tashkent');
        $this->assertTrue($closed->is($this->resolver->nearestOpenBranch($this->store, ...self::AMIR_TEMUR, at: $noon)->branch));
    }

    public function test_no_branch_is_open_gives_null(): void
    {
        $this->emptyStoreBranches();
        $this->branchAt('Kunduzgi', 41.3112, 69.2798, ['open_time' => '09:00:00', 'close_time' => '18:00:00']);

        $this->assertNull($this->resolver->nearestOpenBranch($this->store, ...self::AMIR_TEMUR, at: CarbonImmutable::parse('2026-10-22 23:00:00', 'Asia/Tashkent')));
    }

    public function test_branch_opening_hours(): void
    {
        $branch = fn (?string $open, ?string $close) => $this->branchAt('B' . uniqid(), 41.3, 69.2, ['open_time' => $open, 'close_time' => $close]);
        $at = fn (string $time) => CarbonImmutable::parse("2026-10-22 {$time}", 'Asia/Tashkent');

        $day = $branch('09:00:00', '22:00:00');
        $this->assertTrue($this->resolver->isBranchOpen($day, $at('09:00:00'), $this->store), 'opening time is inclusive');
        $this->assertTrue($this->resolver->isBranchOpen($day, $at('15:30:00'), $this->store));
        $this->assertFalse($this->resolver->isBranchOpen($day, $at('22:00:00'), $this->store), 'closing time is exclusive');
        $this->assertFalse($this->resolver->isBranchOpen($day, $at('08:59:59'), $this->store));
        $this->assertFalse($this->resolver->isBranchOpen($day, $at('03:00:00'), $this->store));

        $night = $branch('18:00:00', '02:00:00');
        $this->assertTrue($this->resolver->isBranchOpen($night, $at('18:00:00'), $this->store));
        $this->assertTrue($this->resolver->isBranchOpen($night, $at('23:59:00'), $this->store));
        $this->assertTrue($this->resolver->isBranchOpen($night, $at('01:59:00'), $this->store));
        $this->assertFalse($this->resolver->isBranchOpen($night, $at('02:00:00'), $this->store));
        $this->assertFalse($this->resolver->isBranchOpen($night, $at('12:00:00'), $this->store));

        $this->assertTrue($this->resolver->isBranchOpen($branch('00:00:00', '00:00:00'), $at('04:00:00'), $this->store), 'equal times = round the clock');
        $this->assertTrue($this->resolver->isBranchOpen($branch(null, null), $at('04:00:00'), $this->store), 'no hours = always open');
        $this->assertTrue($this->resolver->isBranchOpen($branch('09:00', '18:00'), $at('10:00:00'), $this->store), 'HH:MM');
    }

    public function test_hours_are_read_in_the_stores_timezone(): void
    {
        $branch = $this->branchAt('Kunduzgi', 41.3, 69.2, ['open_time' => '09:00:00', 'close_time' => '18:00:00']);

        // 05:00 UTC is 10:00 in Tashkent
        $utc = CarbonImmutable::parse('2026-10-22 05:00:00', 'UTC');
        $this->assertTrue($this->resolver->isBranchOpen($branch, $utc, $this->store));
        // 14:00 UTC is 19:00 in Tashkent
        $this->assertFalse($this->resolver->isBranchOpen($branch, CarbonImmutable::parse('2026-10-22 14:00:00', 'UTC'), $this->store));

        $this->store->forceFill(['timezone' => 'UTC']);
        $this->assertFalse($this->resolver->isBranchOpen($branch, $utc, $this->store), '05:00 UTC is before opening in a UTC store');
    }

    public function test_equal_distances_keep_the_lower_branch_id(): void
    {
        $this->emptyStoreBranches();
        $first = $this->branchAt('Birinchi', 41.30, 69.20);
        $this->branchAt('Ikkinchi', 41.30, 69.20);

        $this->assertTrue($first->is($this->resolver->nearestOpenBranch($this->store, 41.31, 69.21)->branch));
    }

    public function test_other_stores_branches_are_never_returned(): void
    {
        $this->emptyStoreBranches();
        $foreignStore = $this->makeStore('Foreign Grill');
        $this->makeBranch($foreignStore, 'Foreign Branch');

        $this->assertNull($this->resolver->nearestOpenBranch($this->store, ...self::AMIR_TEMUR));
        $this->assertNotNull($this->resolver->nearestOpenBranch($foreignStore, ...self::AMIR_TEMUR));
    }
}
