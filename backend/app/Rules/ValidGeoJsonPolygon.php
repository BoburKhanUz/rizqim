<?php

namespace App\Rules;

use App\Domain\Delivery\Geometry;
use App\Domain\Delivery\InvalidGeometryException;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A delivery zone polygon: GeoJSON Polygon / MultiPolygon (see Geometry::normalize for the rules). Accepts the geometry
 * as an array or a JSON string; the message says what is wrong, in Uzbek.
 */
class ValidGeoJsonPolygon implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            Geometry::normalize($value);
        } catch (InvalidGeometryException $e) {
            $fail($e->getMessage());
        }
    }
}
