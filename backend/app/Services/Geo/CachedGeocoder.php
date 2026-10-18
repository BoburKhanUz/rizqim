<?php

namespace App\Services\Geo;

use Illuminate\Contracts\Cache\Repository;

/**
 * Remembers what the real provider said, so the same address or point is never asked twice within the TTL
 * (Nominatim's policy requires caching, and every provider is rate limited or billed per request).
 *
 * "Nothing found" is remembered too; failures (GeocodingException) never are.
 */
class CachedGeocoder implements GeocoderInterface
{
    // Reverse lookups are keyed by the point rounded to 5 decimals (about 1 m): a GPS jitter is the same place
    private const COORDINATE_DECIMALS = 5;

    // OpenStreetMap gets edited: "nothing found" is not remembered for as long as an actual address
    private const NOT_FOUND_TTL_SECONDS = 86400;

    public function __construct(
        private readonly GeocoderInterface $inner,
        private readonly Repository $cache,
        private readonly string $provider,
        private readonly int $ttlSeconds,
    ) {}

    public function reverse(float $lat, float $lng): ?GeocodedPlace
    {
        $lat = round($lat, self::COORDINATE_DECIMALS);
        $lng = round($lng, self::COORDINATE_DECIMALS);

        // The provider is asked about the rounded point too, so what is cached under a key is exactly what the key says
        $key = sprintf('geocode:%s:reverse:%s', $this->provider, sprintf('%.5F,%.5F', $lat, $lng));

        return $this->remember($key, fn (): ?GeocodedPlace => $this->inner->reverse($lat, $lng));
    }

    public function search(string $query, int $limit = 5): array
    {
        $key = sprintf('geocode:%s:search:%d:%s', $this->provider, $limit, sha1($this->normalize($query)));

        $hit = $this->cache->get($key);
        if (is_array($hit) && is_array($hit['places'] ?? null)) {
            return $this->hydrate($hit['places']);
        }

        $places = $this->inner->search($query, $limit);
        $this->cache->put(
            $key,
            ['places' => array_map(fn (GeocodedPlace $place): array => $place->toArray(), $places)],
            $places === [] ? min($this->ttlSeconds, self::NOT_FOUND_TTL_SECONDS) : $this->ttlSeconds,
        );

        return $places;
    }

    /**
     * @param  callable(): ?GeocodedPlace  $resolve
     */
    private function remember(string $key, callable $resolve): ?GeocodedPlace
    {
        // Wrapped in an array: a bare null would be indistinguishable from a cache miss
        $hit = $this->cache->get($key);
        if (is_array($hit) && array_key_exists('place', $hit)) {
            return is_array($hit['place']) ? GeocodedPlace::fromArray($hit['place']) : null;
        }

        $place = $resolve();
        $this->cache->put(
            $key,
            ['place' => $place?->toArray()],
            $place === null ? min($this->ttlSeconds, self::NOT_FOUND_TTL_SECONDS) : $this->ttlSeconds,
        );

        return $place;
    }

    /**
     * @param  array<mixed>  $rows
     * @return list<GeocodedPlace>
     */
    private function hydrate(array $rows): array
    {
        $places = [];
        foreach ($rows as $row) {
            $place = is_array($row) ? GeocodedPlace::fromArray($row) : null;
            if ($place !== null) {
                $places[] = $place;
            }
        }

        return $places;
    }

    // "  Chilonzor   12 " and "chilonzor 12" are the same question
    private function normalize(string $query): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $query)));
    }
}
