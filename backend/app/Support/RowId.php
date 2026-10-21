<?php

namespace App\Support;

/**
 * A row id taken from a request (a query parameter, a route segment): a positive whole number that fits the bigint
 * id columns. Anything longer would reach Postgres as an out-of-range literal and fail the query (a 500), so callers
 * answer 422 (or 404) when this returns null (P1-34).
 */
final class RowId
{
    /** Route constraint for numeric id segments: at most 18 digits, always within bigint. */
    public const ROUTE_PATTERN = '[0-9]{1,18}';

    public static function parse(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value) || !ctype_digit($value)) {
            return null;
        }

        $id = filter_var(ltrim($value, '0'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }
}
