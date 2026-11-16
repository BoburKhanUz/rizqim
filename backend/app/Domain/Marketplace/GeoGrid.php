<?php

namespace App\Domain\Marketplace;

/**
 * The area documents' geo cells: a plain latitude/longitude grid (config marketplace.cell_degrees, 0.05 by default).
 *
 * Why a grid: no dependency (H3 needs an extension), the cell of a point is two floor() calls, and every store is in
 * one country band (37-46 N), so cells are close to equal in size. A geohash would give the same cells with base32
 * names. A cell lists every store with a branch whose delivery circle may touch some point of the cell (the distance
 * from the cell centre is at most the radius plus half the cell's diagonal); the exact "distance <= radius_km" check
 * runs per request over that short list, so the answer is the same as the old query for every point.
 */
final class GeoGrid
{
    public const EARTH_RADIUS_KM = 6371.0;

    /** Kilometres per degree of latitude */
    private const KM_PER_DEGREE = 111.195;

    public function __construct(private readonly float $size = 0.05) {}

    public static function make(): self
    {
        $size = (float) config('marketplace.cell_degrees', 0.05);

        return new self($size >= 0.005 && $size <= 1.0 ? $size : 0.05);
    }

    /**
     * The cell id of a point: "c<size>:<row>:<col>".
     */
    public function cellOf(float $lat, float $lng): string
    {
        return sprintf('c%s:%d:%d', rtrim(rtrim(number_format($this->size, 4, '.', ''), '0'), '.'), (int) floor($lat / $this->size), (int) floor($lng / $this->size));
    }

    /**
     * The centre of a cell and half of its diagonal in km (on the cell's edge nearer the equator, the wider one).
     *
     * @return array{lat: float, lng: float, half_diagonal_km: float}
     */
    public function geometry(string $cell): array
    {
        [, $row, $col] = explode(':', $cell);
        $south = (int) $row * $this->size;
        $west = (int) $col * $this->size;
        $nearEquator = min(abs($south), abs($south + $this->size));
        $height = $this->size * self::KM_PER_DEGREE;
        $width = $this->size * self::KM_PER_DEGREE * cos(deg2rad($nearEquator));

        return [
            'lat' => $south + $this->size / 2,
            'lng' => $west + $this->size / 2,
            'half_diagonal_km' => sqrt($height * $height + $width * $width) / 2 + 0.05,
        ];
    }

    /**
     * The same great-circle distance the SQL of the old queries computed (spherical law of cosines, R = 6371 km),
     * with the cosine clamped so a point on the branch itself is 0 km, not NaN.
     */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $cos = cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lng2) - deg2rad($lng1))
            + sin(deg2rad($lat1)) * sin(deg2rad($lat2));

        return self::EARTH_RADIUS_KM * acos(max(-1.0, min(1.0, $cos)));
    }
}
