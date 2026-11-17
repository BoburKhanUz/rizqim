<?php

namespace App\Domain\Marketplace;

/**
 * Where a browse request is: a geo cell with the exact point, a district (d:<id>), a region (r:<id>) or everywhere.
 */
final class Area
{
    public function __construct(
        public readonly string $key,
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
    ) {}

    public function isCell(): bool
    {
        return str_starts_with($this->key, 'c');
    }

    public function hasPoint(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    /**
     * The point rounded to about 1 m: what an ETag of a distance-dependent answer depends on.
     */
    public function pointKey(): string
    {
        return $this->hasPoint() ? sprintf('%.5f,%.5f', $this->lat, $this->lng) : '-';
    }
}
