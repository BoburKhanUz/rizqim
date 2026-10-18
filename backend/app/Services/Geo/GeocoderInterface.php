<?php

namespace App\Services\Geo;

use Illuminate\Container\Attributes\Bind;

/**
 * Turns coordinates into a postal-style address and the other way round.
 *
 * Resolved from the container as the configured provider (services.geocoding.provider), wrapped in a result cache.
 * A new provider (Yandex, Google...) only has to implement this interface and be registered in GeocoderManager.
 */
#[Bind(GeocoderManager::class)]
interface GeocoderInterface
{
    /**
     * The address at a point, or null when the provider knows nothing about it (open water, a desert...).
     *
     * @throws GeocodingException when the provider cannot be reached or misbehaves
     */
    public function reverse(float $lat, float $lng): ?GeocodedPlace;

    /**
     * Places matching a free-text address, best match first. Empty when nothing matches.
     *
     * @return list<GeocodedPlace>
     *
     * @throws GeocodingException when the provider cannot be reached or misbehaves
     */
    public function search(string $query, int $limit = 5): array;
}
