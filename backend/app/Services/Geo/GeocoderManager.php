<?php

namespace App\Services\Geo;

use Illuminate\Support\Manager;

/**
 * The geocoder the app talks to: delegates to the provider named in services.geocoding.provider.
 *
 * To add a provider, write a GeocoderInterface implementation (HTTP calls only: caching is applied here) and add a
 * create<Name>Driver() method, e.g. createYandexDriver() for GEOCODING_PROVIDER=yandex.
 */
class GeocoderManager extends Manager implements GeocoderInterface
{
    // A week by default: addresses hardly change, and the public Nominatim instance requires clients to cache.
    // Never less than a day, whatever GEOCODING_CACHE_TTL says.
    private const DEFAULT_CACHE_TTL = 604800;

    private const MIN_CACHE_TTL = 86400;

    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('services.geocoding.provider', 'nominatim');
    }

    public function reverse(float $lat, float $lng): ?GeocodedPlace
    {
        return $this->driver()->reverse($lat, $lng);
    }

    public function search(string $query, int $limit = 5): array
    {
        return $this->driver()->search($query, $limit);
    }

    protected function createNominatimDriver(): GeocoderInterface
    {
        return $this->cached(new NominatimGeocoder((array) $this->config->get('services.nominatim', [])), 'nominatim');
    }

    private function cached(GeocoderInterface $geocoder, string $provider): GeocoderInterface
    {
        $ttl = (int) $this->config->get('services.geocoding.cache_ttl', self::DEFAULT_CACHE_TTL);

        return new CachedGeocoder($geocoder, $this->container->make('cache.store'), $provider, max(self::MIN_CACHE_TTL, $ttl));
    }
}
