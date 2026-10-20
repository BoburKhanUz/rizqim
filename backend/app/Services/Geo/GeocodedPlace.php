<?php

namespace App\Services\Geo;

/**
 * Provider-independent geocoding result: a human readable address and where it is.
 */
final readonly class GeocodedPlace
{
    public function __construct(
        public string $address,
        public float $lat,
        public float $lng,
    ) {}

    /**
     * @param  array<string, mixed>  $data  as produced by toArray()
     */
    public static function fromArray(array $data): ?self
    {
        if (!is_string($data['address'] ?? null) || !is_numeric($data['lat'] ?? null) || !is_numeric($data['lng'] ?? null)) {
            return null;
        }

        return new self($data['address'], (float) $data['lat'], (float) $data['lng']);
    }

    /**
     * @return array{address: string, lat: float, lng: float}
     */
    public function toArray(): array
    {
        return ['address' => $this->address, 'lat' => $this->lat, 'lng' => $this->lng];
    }
}
