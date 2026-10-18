<?php

namespace App\Services\Geo;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * OpenStreetMap Nominatim, used within the public instance's usage policy
 * (https://operations.osmfoundation.org/policies/nominatim/): identifying User-Agent, a single request at a time
 * and at most one per second for the whole platform, no bulk use. Results are cached by CachedGeocoder.
 *
 * The one-request-per-second rule is enforced across all PHP workers with a cache lock that is held while a request
 * is in flight, plus the time of the previous request. Set min_interval to 0 for a self-hosted Nominatim.
 */
class NominatimGeocoder implements GeocoderInterface
{
    private const DEFAULT_BASE_URL = 'https://nominatim.openstreetmap.org';

    public const LOCK_KEY = 'geocoding:nominatim:lock';

    private const LAST_REQUEST_KEY = 'geocoding:nominatim:last-request-at';

    // Longer than a slow request (timeout) plus the pause between requests, so a crashed worker cannot block everyone for long
    private const LOCK_SECONDS = 15;

    /**
     * @param  array<string, mixed>  $config  services.nominatim
     */
    public function __construct(private readonly array $config = []) {}

    public function reverse(float $lat, float $lng): ?GeocodedPlace
    {
        $data = $this->get('reverse', [
            'format' => 'jsonv2',
            'lat' => $this->coordinate($lat),
            'lon' => $this->coordinate($lng),
            'zoom' => 18, // house level
            'addressdetails' => 1,
        ]);

        // "Unable to geocode" (open water...) is a 200 with an error field, not a 404
        if (isset($data['error'])) {
            return null;
        }

        return $this->place($data);
    }

    public function search(string $query, int $limit = 5): array
    {
        $rows = $this->get('search', [
            'format' => 'jsonv2',
            'q' => $query,
            'limit' => max(1, min($limit, 10)),
            'countrycodes' => $this->setting('country_codes', 'uz'),
            'addressdetails' => 1,
        ]);

        $places = [];
        foreach ($rows as $row) {
            $place = $this->place($row);
            if ($place !== null) {
                $places[] = $place;
            }
        }

        return $places;
    }

    /**
     * @param  array<string, scalar>  $query
     * @return array<mixed>
     */
    private function get(string $endpoint, array $query): array
    {
        try {
            /** @var Response $response */
            $response = $this->withRequestSlot(fn (): Response => $this->client()->get($endpoint, $query));
        } catch (ConnectionException $e) {
            // The exception text carries the request URL, i.e. the user's coordinates or address: keep it out of the message
            throw new GeocodingException('Nominatim is unreachable (timeout or network error).', 0, $e);
        }

        if (!$response->successful()) {
            throw new GeocodingException("Nominatim answered HTTP {$response->status()}.");
        }

        $data = $response->json();
        if (!is_array($data)) {
            throw new GeocodingException('Nominatim returned an unreadable response.');
        }

        return $data;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) $this->setting('base_url', self::DEFAULT_BASE_URL), '/'))
            ->withUserAgent($this->userAgent())
            ->withHeaders(['Accept-Language' => (string) $this->setting('accept_language', 'uz,ru')])
            ->acceptJson()
            ->timeout((int) $this->setting('timeout', 5));
    }

    private function userAgent(): string
    {
        $contact = $this->setting('email') ?: config('app.url');

        return 'RizqimApp/1.0 (+' . $contact . ')';
    }

    /**
     * Runs one provider request while holding the platform-wide request slot.
     *
     * @template T
     *
     * @param  Closure(): T  $request
     * @return T
     *
     * @throws GeocodingException when no slot frees up in time
     * @throws ConnectionException from $request, passed through (the provider is unreachable)
     */
    private function withRequestSlot(Closure $request): mixed
    {
        $interval = (float) $this->setting('min_interval', 1.0);
        if ($interval <= 0) {
            return $request();
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        try {
            $lock->block((float) $this->setting('lock_wait', 3.0));
        } catch (LockTimeoutException $e) {
            throw new GeocodingException('Nominatim is busy: no free request slot.', 0, $e);
        }

        try {
            $remaining = (float) Cache::get(self::LAST_REQUEST_KEY, 0.0) + $interval - $this->microtime();
            if ($remaining > 0) {
                Sleep::usleep((int) ceil($remaining * 1_000_000));
            }
            Cache::put(self::LAST_REQUEST_KEY, $this->microtime(), 60);

            return $request();
        } finally {
            $lock->release();
        }
    }

    // Carbon rather than microtime(): follows Carbon::setTestNow() and a faked Sleep in tests
    private function microtime(): float
    {
        return (float) Carbon::now()->format('U.u');
    }

    private function place(mixed $row): ?GeocodedPlace
    {
        if (!is_array($row)) {
            return null;
        }

        $lat = $row['lat'] ?? null;
        $lng = $row['lon'] ?? null;
        $name = $row['display_name'] ?? null;

        if (!is_numeric($lat) || !is_numeric($lng) || !is_string($name) || trim($name) === '') {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return new GeocodedPlace($this->address($name, $row['address'] ?? null), $lat, $lng);
    }

    /**
     * display_name always ends with the postcode and the country, which are noise in an order address.
     */
    private function address(string $displayName, mixed $details): string
    {
        $noise = [];
        foreach (['postcode', 'country'] as $field) {
            if (is_array($details) && is_string($details[$field] ?? null)) {
                $noise[] = trim($details[$field]);
            }
        }

        $parts = array_values(array_filter(
            array_map('trim', explode(',', $displayName)),
            fn (string $part): bool => $part !== '' && !in_array($part, $noise, true),
        ));

        return $parts === [] ? trim($displayName) : implode(', ', $parts);
    }

    private function coordinate(float $value): string
    {
        return number_format($value, 5, '.', '');
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
