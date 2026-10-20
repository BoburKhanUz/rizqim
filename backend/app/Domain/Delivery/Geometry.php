<?php

namespace App\Domain\Delivery;

/**
 * Plain-PHP geometry for delivery zones: GeoJSON validation, bounding box, area, point-in-polygon and distance.
 *
 * GeoJSON positions are [lng, lat] (the order the standard and Leaflet's toGeoJSON() use); everything else here that
 * takes a point takes ($lat, $lng). A zone is a closed set: a point on an edge (or on a hole's edge) belongs to it.
 */
final class Geometry
{
    public const MAX_VERTICES = 500;
    public const MAX_POLYGONS = 20;
    public const MAX_RINGS = 50;

    /** Coordinates are kept to 6 decimals (about 11 cm). */
    private const PRECISION = 6;

    /** A point closer than this many degrees (about 0.1 mm) to an edge counts as on it. */
    private const EDGE_TOLERANCE = 1e-9;

    /** Rings enclosing less than this (deg2, about 10 cm square) are degenerate. */
    private const MIN_RING_AREA = 1e-11;

    private const KM_PER_DEGREE = 111.195;
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Validate GeoJSON and return it as a clean Polygon / MultiPolygon geometry: positions cut to [lng, lat] and rounded,
     * repeated neighbouring points dropped. Accepts a JSON string, a geometry, a Feature, or a FeatureCollection of one feature.
     *
     * @return array{type: string, coordinates: list<mixed>}
     *
     * @throws InvalidGeometryException
     */
    public static function normalize(mixed $input): array
    {
        if (is_string($input)) {
            $input = json_decode($input, true);
            if (!is_array($input)) {
                throw new InvalidGeometryException("GeoJSON noto'g'ri formatda yozilgan.");
            }
        }

        if (!is_array($input)) {
            throw new InvalidGeometryException('Hudud GeoJSON ko\'rinishida bo\'lishi kerak.');
        }

        if (($input['type'] ?? null) === 'FeatureCollection') {
            $features = $input['features'] ?? null;
            if (!is_array($features) || count($features) !== 1) {
                throw new InvalidGeometryException('GeoJSON ichida aynan bitta hudud bo\'lishi kerak.');
            }
            $input = reset($features);
            if (!is_array($input)) {
                throw new InvalidGeometryException("GeoJSON noto'g'ri formatda yozilgan.");
            }
        }

        if (($input['type'] ?? null) === 'Feature') {
            $input = $input['geometry'] ?? null;
            if (!is_array($input)) {
                throw new InvalidGeometryException('GeoJSON ichida hudud (geometry) topilmadi.');
            }
        }

        $type = $input['type'] ?? null;
        $coordinates = $input['coordinates'] ?? null;

        if (!in_array($type, ['Polygon', 'MultiPolygon'], true)) {
            throw new InvalidGeometryException("Hudud turi Polygon yoki MultiPolygon bo'lishi kerak.");
        }
        if (!is_array($coordinates) || $coordinates === []) {
            throw new InvalidGeometryException("Hudud koordinatalari bo'sh.");
        }

        $polygons = $type === 'Polygon' ? [$coordinates] : $coordinates;
        if (count($polygons) > self::MAX_POLYGONS) {
            throw new InvalidGeometryException('Bitta hududda ko\'pi bilan ' . self::MAX_POLYGONS . ' ta ko\'pburchak bo\'lishi mumkin.');
        }

        $vertices = 0;
        $clean = [];
        foreach ($polygons as $polygon) {
            if (!is_array($polygon) || $polygon === [] || count($polygon) > self::MAX_RINGS) {
                throw new InvalidGeometryException("Ko'pburchak halqalari noto'g'ri berilgan.");
            }

            $rings = [];
            foreach (array_values($polygon) as $index => $ring) {
                $rings[] = self::normalizeRing($ring, $index === 0);
            }

            foreach ($rings as $ring) {
                $vertices += count($ring) - 1;
            }
            if ($vertices > self::MAX_VERTICES) {
                throw new InvalidGeometryException('Hududda ko\'pi bilan ' . self::MAX_VERTICES . ' ta nuqta bo\'lishi mumkin.');
            }

            foreach (array_slice($rings, 1) as $hole) {
                foreach ($hole as $position) {
                    if (!self::ringContains($rings[0], $position[1], $position[0], true)) {
                        throw new InvalidGeometryException("Ichki bo'shliq (teshik) hudud chegarasidan tashqarida.");
                    }
                }
            }

            $clean[] = $rings;
        }

        return $type === 'Polygon'
            ? ['type' => 'Polygon', 'coordinates' => $clean[0]]
            : ['type' => 'MultiPolygon', 'coordinates' => $clean];
    }

    /**
     * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}
     */
    public static function boundingBox(array $geometry): array
    {
        $lats = [];
        $lngs = [];
        foreach (self::polygons($geometry) as $polygon) {
            foreach ($polygon[0] as $position) {
                $lngs[] = (float) $position[0];
                $lats[] = (float) $position[1];
            }
        }

        return ['min_lat' => min($lats), 'max_lat' => max($lats), 'min_lng' => min($lngs), 'max_lng' => max($lngs)];
    }

    /**
     * Approximate area in km2 (equirectangular projection around each ring: ample for city-sized zones).
     */
    public static function areaKm2(array $geometry): float
    {
        $area = 0.0;
        foreach (self::polygons($geometry) as $polygon) {
            foreach ($polygon as $index => $ring) {
                $km2 = self::ringAreaKm2($ring);
                $area += $index === 0 ? $km2 : -$km2;
            }
        }

        return max(0.0, $area);
    }

    /**
     * Whether the point is inside the Polygon / MultiPolygon (holes excluded, edges included).
     */
    public static function contains(array $geometry, float $lat, float $lng): bool
    {
        foreach (self::polygons($geometry) as $polygon) {
            if (self::polygonContains($polygon, $lat, $lng)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Great-circle distance in km.
     */
    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @return list<list<list<list<float>>>> polygons -> rings -> positions
     */
    private static function polygons(array $geometry): array
    {
        return ($geometry['type'] ?? null) === 'MultiPolygon' ? $geometry['coordinates'] : [$geometry['coordinates']];
    }

    /**
     * @param list<list<list<float>>> $polygon first ring outer, the rest holes
     */
    private static function polygonContains(array $polygon, float $lat, float $lng): bool
    {
        if (!self::ringContains($polygon[0], $lat, $lng, true)) {
            return false;
        }

        foreach (array_slice($polygon, 1) as $hole) {
            // Strictly inside a hole: outside the zone. A hole's edge still belongs to the zone.
            if (self::ringContains($hole, $lat, $lng, false)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ray casting (a ray towards +lng) over a closed ring of [lng, lat] positions.
     *
     * @param list<list<float>> $ring
     * @param bool $edgeInside the answer for a point on the ring itself
     */
    private static function ringContains(array $ring, float $lat, float $lng, bool $edgeInside): bool
    {
        $inside = false;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            [$xi, $yi] = [(float) $ring[$i][0], (float) $ring[$i][1]];
            [$xj, $yj] = [(float) $ring[$j][0], (float) $ring[$j][1]];

            if (self::onSegment($xi, $yi, $xj, $yj, $lng, $lat)) {
                return $edgeInside;
            }

            if (($yi > $lat) !== ($yj > $lat) && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    private static function onSegment(float $ax, float $ay, float $bx, float $by, float $px, float $py): bool
    {
        $tolerance = self::EDGE_TOLERANCE;
        if ($px < min($ax, $bx) - $tolerance || $px > max($ax, $bx) + $tolerance
            || $py < min($ay, $by) - $tolerance || $py > max($ay, $by) + $tolerance) {
            return false;
        }

        $length = hypot($bx - $ax, $by - $ay);
        if ($length < $tolerance) {
            return hypot($px - $ax, $py - $ay) <= $tolerance;
        }

        // Distance from the point to the segment's line
        return abs(($bx - $ax) * ($py - $ay) - ($by - $ay) * ($px - $ax)) / $length <= $tolerance;
    }

    /**
     * @param list<list<float>> $ring
     */
    private static function ringAreaKm2(array $ring): float
    {
        $meanLat = array_sum(array_column($ring, 1)) / count($ring);
        $xScale = cos(deg2rad($meanLat)) * self::KM_PER_DEGREE;

        return abs(self::shoelace($ring)) * $xScale * self::KM_PER_DEGREE;
    }

    /**
     * Signed area of a ring in square degrees (positions are [x, y]).
     *
     * @param list<list<float>> $ring
     */
    private static function shoelace(array $ring): float
    {
        $sum = 0.0;
        for ($i = 0, $j = count($ring) - 1; $i < count($ring); $j = $i++) {
            $sum += ($ring[$j][0] * $ring[$i][1]) - ($ring[$i][0] * $ring[$j][1]);
        }

        return $sum / 2;
    }

    /**
     * @return list<list<float>>
     *
     * @throws InvalidGeometryException
     */
    private static function normalizeRing(mixed $ring, bool $outer): array
    {
        $what = $outer ? 'Hudud chegarasi' : 'Ichki bo\'shliq';

        if (!is_array($ring)) {
            throw new InvalidGeometryException("{$what} nuqtalar ro'yxati bo'lishi kerak.");
        }

        $positions = [];
        foreach (array_values($ring) as $position) {
            if (!is_array($position) || count($position) < 2
                || !(is_int($position[0] ?? null) || is_float($position[0] ?? null))
                || !(is_int($position[1] ?? null) || is_float($position[1] ?? null))) {
                throw new InvalidGeometryException("{$what}: har bir nuqta [uzunlik, kenglik] raqamlaridan iborat bo'lishi kerak.");
            }

            [$lng, $lat] = [(float) $position[0], (float) $position[1]];
            if (!is_finite($lng) || !is_finite($lat)) {
                throw new InvalidGeometryException("{$what}: koordinatalar noto'g'ri.");
            }
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                throw new InvalidGeometryException("{$what}: koordinata chegaradan tashqarida (kenglik -90..90, uzunlik -180..180; tartib [uzunlik, kenglik]).");
            }

            $point = [round($lng, self::PRECISION), round($lat, self::PRECISION)];
            // Neighbouring repeats add nothing
            if ($positions === [] || end($positions) !== $point) {
                $positions[] = $point;
            }
        }

        if (count($positions) < 4) {
            throw new InvalidGeometryException("{$what} kamida 3 ta nuqtadan iborat bo'lishi kerak.");
        }
        if ($positions[0] !== end($positions)) {
            throw new InvalidGeometryException("{$what} yopilmagan: oxirgi nuqta birinchisi bilan bir xil bo'lishi kerak.");
        }

        $distinct = count(array_unique(array_map(fn (array $point) => $point[0] . ',' . $point[1], $positions)));
        if ($distinct < 3 || abs(self::shoelace($positions)) < self::MIN_RING_AREA) {
            throw new InvalidGeometryException("{$what} yaroqsiz: nuqtalar bir chiziqda yoki maydoni nolga teng.");
        }

        return $positions;
    }
}
