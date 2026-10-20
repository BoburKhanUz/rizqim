<?php

namespace Tests\Unit;

use App\Domain\Delivery\Geometry;
use App\Domain\Delivery\InvalidGeometryException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Zone geometry on real Tashkent coordinates. GeoJSON positions are [lng, lat]; the helpers take (lat, lng).
 */
class DeliveryGeometryTest extends TestCase
{
    // Amir Temur Square, Chorsu Bazaar, Tashkent TV Tower, Minor Mosque, Tashkent airport
    private const AMIR_TEMUR = [41.3111, 69.2797];
    private const CHORSU = [41.3265, 69.2358];
    private const TV_TOWER = [41.3394, 69.2866];
    private const AIRPORT = [41.2579, 69.2812];

    /** A box around the centre: lat 41.30..41.32, lng 69.26..69.30. */
    private function centre(): array
    {
        return Geometry::normalize(['type' => 'Polygon', 'coordinates' => [self::ring(69.26, 41.30, 69.30, 41.32)]]);
    }

    /** The centre box with a hole around Amir Temur Square (lat 41.308..41.314, lng 69.275..69.285). */
    private function centreWithHole(): array
    {
        return Geometry::normalize(['type' => 'Polygon', 'coordinates' => [
            self::ring(69.26, 41.30, 69.30, 41.32),
            self::ring(69.275, 41.308, 69.285, 41.314),
        ]]);
    }

    private static function ring(float $minLng, float $minLat, float $maxLng, float $maxLat): array
    {
        return [[$minLng, $minLat], [$maxLng, $minLat], [$maxLng, $maxLat], [$minLng, $maxLat], [$minLng, $minLat]];
    }

    public function test_point_inside_and_outside_a_polygon(): void
    {
        $zone = $this->centre();

        $this->assertTrue(Geometry::contains($zone, ...self::AMIR_TEMUR));
        $this->assertFalse(Geometry::contains($zone, ...self::CHORSU));
        $this->assertFalse(Geometry::contains($zone, ...self::TV_TOWER));
        $this->assertFalse(Geometry::contains($zone, ...self::AIRPORT));
    }

    public function test_edges_and_corners_belong_to_the_zone(): void
    {
        $zone = $this->centre();

        $this->assertTrue(Geometry::contains($zone, 41.30, 69.28), 'bottom edge');
        $this->assertTrue(Geometry::contains($zone, 41.32, 69.28), 'top edge');
        $this->assertTrue(Geometry::contains($zone, 41.31, 69.26), 'west edge');
        $this->assertTrue(Geometry::contains($zone, 41.31, 69.30), 'east edge');
        $this->assertTrue(Geometry::contains($zone, 41.30, 69.26), 'corner');
        $this->assertTrue(Geometry::contains($zone, 41.32, 69.30), 'corner');
        // A hair outside is outside
        $this->assertFalse(Geometry::contains($zone, 41.299999, 69.28));
        $this->assertFalse(Geometry::contains($zone, 41.31, 69.300001));
    }

    public function test_a_point_on_a_slanted_edge_is_inside(): void
    {
        $triangle = Geometry::normalize(['type' => 'Polygon', 'coordinates' => [[[69.20, 41.20], [69.30, 41.20], [69.20, 41.30], [69.20, 41.20]]]]);

        // The hypotenuse runs from (69.30, 41.20) to (69.20, 41.30): its midpoint is (69.25, 41.25)
        $this->assertTrue(Geometry::contains($triangle, 41.25, 69.25));
        $this->assertTrue(Geometry::contains($triangle, 41.22, 69.22));
        $this->assertFalse(Geometry::contains($triangle, 41.26, 69.26));
    }

    public function test_a_hole_is_outside_but_its_edge_is_inside(): void
    {
        $zone = $this->centreWithHole();

        $this->assertFalse(Geometry::contains($zone, ...self::AMIR_TEMUR), 'inside the hole');
        $this->assertTrue(Geometry::contains($zone, 41.305, 69.28), 'ring of the zone around the hole');
        $this->assertTrue(Geometry::contains($zone, 41.311, 69.275), 'on the hole edge');
        $this->assertTrue(Geometry::contains($zone, 41.308, 69.28), 'on the hole corner row');
        $this->assertFalse(Geometry::contains($zone, 41.311, 69.2751), 'just inside the hole');
        $this->assertFalse(Geometry::contains($zone, ...self::CHORSU), 'outside the outer ring');
    }

    public function test_multipolygon_with_two_islands_and_a_hole(): void
    {
        $zone = Geometry::normalize(['type' => 'MultiPolygon', 'coordinates' => [
            // Centre with a hole around Amir Temur Square
            [self::ring(69.26, 41.30, 69.30, 41.32), self::ring(69.275, 41.308, 69.285, 41.314)],
            // Chorsu on its own
            [self::ring(69.22, 41.32, 69.25, 41.335)],
        ]]);

        $this->assertTrue(Geometry::contains($zone, ...self::CHORSU), 'second polygon');
        $this->assertTrue(Geometry::contains($zone, 41.305, 69.28), 'first polygon');
        $this->assertFalse(Geometry::contains($zone, ...self::AMIR_TEMUR), "first polygon's hole is not rescued by the second polygon");
        $this->assertFalse(Geometry::contains($zone, ...self::TV_TOWER));
        $this->assertFalse(Geometry::contains($zone, 41.31, 69.255), 'the gap between the islands');
    }

    public function test_a_concave_polygon(): void
    {
        // An L: the lower bar spans lng 69.20..69.30, the upright lng 69.20..69.23
        $l = Geometry::normalize(['type' => 'Polygon', 'coordinates' => [[
            [69.20, 41.20], [69.30, 41.20], [69.30, 41.22], [69.23, 41.22], [69.23, 41.30], [69.20, 41.30], [69.20, 41.20],
        ]]]);

        $this->assertTrue(Geometry::contains($l, 41.21, 69.28));
        $this->assertTrue(Geometry::contains($l, 41.28, 69.21));
        $this->assertFalse(Geometry::contains($l, 41.28, 69.28), 'the notch inside the bounding box');
        $this->assertSame(
            ['min_lat' => 41.20, 'max_lat' => 41.30, 'min_lng' => 69.20, 'max_lng' => 69.30],
            Geometry::boundingBox($l)
        );
    }

    public function test_bounding_box_and_area(): void
    {
        $this->assertSame(
            ['min_lat' => 41.30, 'max_lat' => 41.32, 'min_lng' => 69.26, 'max_lng' => 69.30],
            Geometry::boundingBox($this->centre())
        );

        // 0.04 deg of longitude at 41.3 N is about 3.34 km, 0.02 deg of latitude about 2.22 km
        $this->assertEqualsWithDelta(7.4, Geometry::areaKm2($this->centre()), 0.2);
        // The hole (0.01 x 0.006 deg, about 0.56 km2) is taken off
        $this->assertEqualsWithDelta(
            Geometry::areaKm2($this->centre()) - 0.56,
            Geometry::areaKm2($this->centreWithHole()),
            0.05
        );
    }

    public function test_haversine_distance(): void
    {
        $this->assertEqualsWithDelta(0.0, Geometry::haversineKm(...self::AMIR_TEMUR, ...self::AMIR_TEMUR), 1e-9);
        // One degree of latitude
        $this->assertEqualsWithDelta(111.19, Geometry::haversineKm(41.0, 69.0, 42.0, 69.0), 0.05);
        // Tashkent - Samarkand, about 267 km
        $this->assertEqualsWithDelta(267.0, Geometry::haversineKm(41.2995, 69.2401, 39.6270, 66.9750), 1.0);
        // Amir Temur Square - TV Tower, about 3.2 km
        $this->assertEqualsWithDelta(3.2, Geometry::haversineKm(...self::AMIR_TEMUR, ...self::TV_TOWER), 0.1);
        // Symmetric
        $this->assertSame(
            Geometry::haversineKm(...self::CHORSU, ...self::AIRPORT),
            Geometry::haversineKm(...self::AIRPORT, ...self::CHORSU)
        );
    }

    public function test_feature_and_single_feature_collection_and_json_string_are_accepted(): void
    {
        $geometry = ['type' => 'Polygon', 'coordinates' => [self::ring(69.26, 41.30, 69.30, 41.32)]];
        $feature = ['type' => 'Feature', 'properties' => [], 'geometry' => $geometry];

        $this->assertSame($geometry, Geometry::normalize($feature));
        $this->assertSame($geometry, Geometry::normalize(['type' => 'FeatureCollection', 'features' => [$feature]]));
        $this->assertSame($geometry, Geometry::normalize(json_encode($feature)));
    }

    public function test_positions_are_rounded_and_cut_to_two_numbers_and_repeats_dropped(): void
    {
        $clean = Geometry::normalize(['type' => 'Polygon', 'coordinates' => [[
            [69.2600001, 41.30, 450.0], [69.30, 41.30], [69.30, 41.30], [69.30, 41.32], [69.26, 41.32], [69.26, 41.30],
        ]]]);

        $this->assertSame([[69.26, 41.3], [69.3, 41.3], [69.3, 41.32], [69.26, 41.32], [69.26, 41.3]], $clean['coordinates'][0]);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidGeometries(): array
    {
        $box = self::ring(69.26, 41.30, 69.30, 41.32);

        return [
            'not JSON' => ['{nope', "noto'g'ri formatda"],
            'a number' => [42, 'GeoJSON ko'],
            'wrong type' => [['type' => 'Point', 'coordinates' => [69.2, 41.3]], 'Polygon yoki MultiPolygon'],
            'a line' => [['type' => 'LineString', 'coordinates' => [[69.2, 41.3], [69.3, 41.3]]], 'Polygon yoki MultiPolygon'],
            'no coordinates' => [['type' => 'Polygon', 'coordinates' => []], "bo'sh"],
            'two features' => [['type' => 'FeatureCollection', 'features' => []], 'aynan bitta'],
            'open ring' => [['type' => 'Polygon', 'coordinates' => [array_slice($box, 0, 4)]], 'yopilmagan'],
            'too few points' => [['type' => 'Polygon', 'coordinates' => [[[69.2, 41.3], [69.3, 41.3], [69.2, 41.3]]]], 'kamida 3'],
            'a segment closed on itself' => [['type' => 'Polygon', 'coordinates' => [[[69.2, 41.3], [69.3, 41.3], [69.25, 41.3], [69.2, 41.3]]]], 'yaroqsiz'],
            'all points equal' => [['type' => 'Polygon', 'coordinates' => [[[69.2, 41.3], [69.2, 41.3], [69.2, 41.3], [69.2, 41.3]]]], 'kamida 3'],
            'collinear ring' => [['type' => 'Polygon', 'coordinates' => [[[69.2, 41.3], [69.3, 41.3], [69.4, 41.3], [69.2, 41.3]]]], 'yaroqsiz'],
            'lat out of range' => [['type' => 'Polygon', 'coordinates' => [[[69.2, 91.0], [69.3, 41.3], [69.25, 41.4], [69.2, 91.0]]]], 'chegaradan tashqarida'],
            'lng out of range' => [['type' => 'Polygon', 'coordinates' => [[[181.0, 41.3], [69.3, 41.3], [69.25, 41.4], [181.0, 41.3]]]], 'chegaradan tashqarida'],
            'lat and lng swapped' => [['type' => 'Polygon', 'coordinates' => [[[41.3, 100.2], [41.4, 100.3], [41.35, 100.4], [41.3, 100.2]]]], 'chegaradan tashqarida'],
            'non numeric position' => [['type' => 'Polygon', 'coordinates' => [[['69.2', '41.3'], [69.3, 41.3], [69.25, 41.4], ['69.2', '41.3']]]], 'raqamlaridan'],
            'one number position' => [['type' => 'Polygon', 'coordinates' => [[[69.2], [69.3, 41.3], [69.25, 41.4], [69.2]]]], 'raqamlaridan'],
            'hole outside the outer ring' => [['type' => 'Polygon', 'coordinates' => [$box, self::ring(69.5, 41.5, 69.6, 41.6)]], 'teshik'],
            'degenerate hole' => [['type' => 'Polygon', 'coordinates' => [$box, [[69.27, 41.31], [69.28, 41.31], [69.29, 41.31], [69.27, 41.31]]]], 'yaroqsiz'],
        ];
    }

    #[DataProvider('invalidGeometries')]
    public function test_invalid_geometry_is_rejected_with_a_reason(mixed $input, string $expectedMessage): void
    {
        try {
            Geometry::normalize($input);
            $this->fail('Expected InvalidGeometryException');
        } catch (InvalidGeometryException $e) {
            $this->assertStringContainsString($expectedMessage, $e->getMessage());
        }
    }

    public function test_vertex_limit_is_500_not_counting_the_closing_point(): void
    {
        $this->assertSame(500, count(Geometry::normalize(['type' => 'Polygon', 'coordinates' => [$this->circle(500)]])['coordinates'][0]) - 1);

        $this->expectException(InvalidGeometryException::class);
        $this->expectExceptionMessage('500');
        Geometry::normalize(['type' => 'Polygon', 'coordinates' => [$this->circle(501)]]);
    }

    public function test_vertex_limit_counts_all_polygons_and_holes_together(): void
    {
        $this->expectException(InvalidGeometryException::class);

        Geometry::normalize(['type' => 'MultiPolygon', 'coordinates' => [
            [$this->circle(300)],
            [$this->circle(201, 69.6)],
        ]]);
    }

    public function test_too_many_polygons_are_rejected(): void
    {
        $polygons = array_map(fn (int $i) => [self::ring(69.0 + $i * 0.01, 41.0, 69.005 + $i * 0.01, 41.005)], range(0, Geometry::MAX_POLYGONS));

        $this->expectException(InvalidGeometryException::class);
        Geometry::normalize(['type' => 'MultiPolygon', 'coordinates' => $polygons]);
    }

    /**
     * A closed ring of $n distinct vertices on a small circle.
     *
     * @return list<list<float>>
     */
    private function circle(int $n, float $centreLng = 69.24): array
    {
        $ring = [];
        for ($i = 0; $i < $n; $i++) {
            $angle = 2 * M_PI * $i / $n;
            $ring[] = [round($centreLng + 0.05 * cos($angle), 6), round(41.31 + 0.05 * sin($angle), 6)];
        }
        $ring[] = $ring[0];

        return $ring;
    }
}
