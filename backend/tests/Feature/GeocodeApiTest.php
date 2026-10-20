<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Geo\CachedGeocoder;
use App\Services\Geo\GeocodedPlace;
use App\Services\Geo\GeocoderInterface;
use App\Services\Geo\GeocoderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * /api/v1/geocode/*: the server-side proxy to the geocoding provider. No test touches the network.
 */
class GeocodeApiTest extends TestCase
{
    use RefreshDatabase;

    private const NOMINATIM = 'https://nominatim.openstreetmap.org';

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.geocoding.provider' => 'nominatim',
            'services.nominatim.base_url' => self::NOMINATIM,
            'services.nominatim.email' => 'geo@rizqim.test',
            // Pausing between requests is covered by GeocodeNominatimThrottleTest
            'services.nominatim.min_interval' => 0,
        ]);

        // Anything not explicitly faked fails the test instead of reaching the real service
        Http::preventStrayRequests();

        $this->customer = User::create(['name' => 'Geo Customer', 'phone' => '+998910000077', 'password' => bcrypt('password')]);
    }

    private function nominatimPlace(array $overrides = []): array
    {
        return array_merge([
            'place_id' => 1234,
            'licence' => 'Data © OpenStreetMap contributors, ODbL 1.0. http://osm.org/copyright',
            'osm_type' => 'way',
            'osm_id' => 5678,
            'lat' => '41.2995123',
            'lon' => '69.2401456',
            'category' => 'building',
            'type' => 'yes',
            'name' => '',
            'display_name' => '12, Amir Temur ko‘chasi, Mirzo Ulug‘bek tumani, Toshkent, 100000, Oʻzbekiston',
            'address' => [
                'house_number' => '12',
                'road' => 'Amir Temur ko‘chasi',
                'city_district' => 'Mirzo Ulug‘bek tumani',
                'city' => 'Toshkent',
                'postcode' => '100000',
                'country' => 'Oʻzbekiston',
                'country_code' => 'uz',
            ],
        ], $overrides);
    }

    private function reverse(array $query = ['lat' => 41.2995, 'lng' => 69.2401], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->customer, 'sanctum')->getJson('/api/v1/geocode/reverse?' . http_build_query($query));
    }

    private function search(string $q, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->customer, 'sanctum')->getJson('/api/v1/geocode/search?' . http_build_query(['q' => $q]));
    }

    /**
     * @return array<string, string>
     */
    private function queryOf(Request $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query;
    }

    // ---- Access ----

    public function test_both_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/geocode/reverse?lat=41.3&lng=69.2')->assertUnauthorized();
        $this->getJson('/api/v1/geocode/search?q=Chilonzor')->assertUnauthorized();

        Http::assertNothingSent();
    }

    // ---- Reverse ----

    public function test_reverse_returns_the_normalized_address(): void
    {
        Http::fake([self::NOMINATIM . '/*' => Http::response($this->nominatimPlace())]);

        $this->reverse()
            ->assertOk()
            ->assertJsonPath('success', true)
            // Postcode and country are dropped from display_name
            ->assertJsonPath('data.address', '12, Amir Temur ko‘chasi, Mirzo Ulug‘bek tumani, Toshkent')
            ->assertJsonPath('data.lat', 41.2995123)
            ->assertJsonPath('data.lng', 69.2401456)
            ->assertJsonCount(3, 'data');
    }

    public function test_reverse_identifies_the_app_and_asks_for_house_level_addresses_in_uzbek_then_russian(): void
    {
        Http::fake([self::NOMINATIM . '/*' => Http::response($this->nominatimPlace())]);

        $this->reverse(['lat' => '41.299512', 'lng' => '69.240145'])->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $query = $this->queryOf($request);

            return str_starts_with($request->url(), self::NOMINATIM . '/reverse?')
                && $request->method() === 'GET'
                && $request->header('User-Agent') === ['RizqimApp/1.0 (+geo@rizqim.test)']
                && $request->header('Accept-Language') === ['uz,ru']
                // Rounded to 5 decimals, the precision results are cached with
                && $query['lat'] === '41.29951'
                && $query['lon'] === '69.24015'
                && $query['format'] === 'jsonv2'
                && $query['zoom'] === '18';
        });
    }

    public function test_user_agent_falls_back_to_the_app_url_without_a_contact_email(): void
    {
        config(['services.nominatim.email' => null, 'app.url' => 'https://rizqim.test']);
        Http::fake([self::NOMINATIM . '/*' => Http::response($this->nominatimPlace())]);

        $this->reverse()->assertOk();

        Http::assertSent(fn (Request $request): bool => $request->header('User-Agent') === ['RizqimApp/1.0 (+https://rizqim.test)']);
    }

    public function test_the_base_url_comes_from_config(): void
    {
        config(['services.nominatim.base_url' => 'https://geo.internal.test/']);
        Http::fake(['geo.internal.test/*' => Http::response($this->nominatimPlace())]);

        $this->reverse()->assertOk();

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://geo.internal.test/reverse?'));
    }

    public function test_reverse_is_cached_so_the_second_call_makes_no_http_request(): void
    {
        Http::fake([self::NOMINATIM . '/*' => Http::response($this->nominatimPlace())]);

        $first = $this->reverse()->assertOk()->json();
        $second = $this->reverse()->assertOk()->json();

        $this->assertSame($first, $second);
        Http::assertSentCount(1);
    }

    public function test_reverse_cache_is_shared_between_users_and_keyed_by_the_point_rounded_to_five_decimals(): void
    {
        $other = User::create(['name' => 'Other', 'phone' => '+998910000078', 'password' => bcrypt('password')]);
        Http::fake([self::NOMINATIM . '/*' => Http::response($this->nominatimPlace())]);

        $this->reverse(['lat' => '41.2995001', 'lng' => '69.2401001'])->assertOk();
        // ~1 cm away: same cache entry, even for another customer
        $this->reverse(['lat' => '41.2995004', 'lng' => '69.2401004'], $other)->assertOk();
        Http::assertSentCount(1);

        // ~1 m away: a different entry
        $this->reverse(['lat' => '41.2995101', 'lng' => '69.2401001'])->assertOk();
        Http::assertSentCount(2);
    }

    public function test_reverse_answers_404_when_nothing_is_there_and_remembers_it(): void
    {
        // Open water and the like: Nominatim answers 200 with an error field
        Http::fake([self::NOMINATIM . '/*' => Http::response(['error' => 'Unable to geocode'])]);

        $this->reverse(['lat' => 0.1, 'lng' => -30.2])
            ->assertNotFound()
            ->assertJsonPath('success', false);
        $this->reverse(['lat' => 0.1, 'lng' => -30.2])->assertNotFound();

        Http::assertSentCount(1);
    }

    public function test_reverse_ignores_a_provider_row_without_usable_coordinates(): void
    {
        Http::fake([self::NOMINATIM . '/*' => Http::response($this->nominatimPlace(['lat' => null]))]);

        $this->reverse()->assertNotFound();
    }

    #[DataProvider('invalidPoints')]
    public function test_reverse_validates_the_point(array $query, string $invalidField): void
    {
        $this->reverse($query)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$invalidField]);

        Http::assertNothingSent();
    }

    public static function invalidPoints(): array
    {
        return [
            'no coordinates' => [[], 'lat'],
            'no longitude' => [['lat' => 41.3], 'lng'],
            'latitude above 90' => [['lat' => 90.5, 'lng' => 69.2], 'lat'],
            'latitude below -90' => [['lat' => -91, 'lng' => 69.2], 'lat'],
            'longitude above 180' => [['lat' => 41.3, 'lng' => 180.1], 'lng'],
            'longitude below -180' => [['lat' => 41.3, 'lng' => -181], 'lng'],
            'latitude not a number' => [['lat' => 'tashkent', 'lng' => 69.2], 'lat'],
            'longitude not a number' => [['lat' => 41.3, 'lng' => 'NaN'], 'lng'],
        ];
    }

    // ---- Search ----

    public function test_search_returns_a_normalized_list_restricted_to_uzbekistan(): void
    {
        Http::fake([self::NOMINATIM . '/*' => Http::response([
            $this->nominatimPlace(),
            $this->nominatimPlace([
                'lat' => '41.3111',
                'lon' => '69.2797',
                'display_name' => 'Chilonzor, Toshkent, Oʻzbekiston',
                'address' => ['suburb' => 'Chilonzor', 'city' => 'Toshkent', 'country' => 'Oʻzbekiston'],
            ]),
            // Unusable rows are skipped, not passed on
            $this->nominatimPlace(['lon' => 'abc']),
            $this->nominatimPlace(['display_name' => '  ']),
            'garbage',
        ])]);

        $this->search('Amir Temur 12')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.address', '12, Amir Temur ko‘chasi, Mirzo Ulug‘bek tumani, Toshkent')
            ->assertJsonPath('data.0.lat', 41.2995123)
            ->assertJsonPath('data.0.lng', 69.2401456)
            ->assertJsonPath('data.1.address', 'Chilonzor, Toshkent')
            ->assertJsonPath('data.1.lat', 41.3111)
            ->assertJsonPath('data.1.lng', 69.2797);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $query = $this->queryOf($request);

            return str_starts_with($request->url(), self::NOMINATIM . '/search?')
                && $request->header('User-Agent') === ['RizqimApp/1.0 (+geo@rizqim.test)']
                && $request->header('Accept-Language') === ['uz,ru']
                && $query['q'] === 'Amir Temur 12'
                && $query['countrycodes'] === 'uz'
                && $query['format'] === 'jsonv2'
                && $query['limit'] === '5';
        });
    }

    public function test_search_without_matches_is_an_empty_list(): void
    {
        Http::fake([self::NOMINATIM . '/*' => Http::response([])]);

        $this->search('zzzzzz nowhere')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertExactJson(['success' => true, 'message' => '', 'data' => []]);
    }

    public function test_search_is_cached_by_the_normalized_query(): void
    {
        Http::fake([self::NOMINATIM . '/*' => Http::response([$this->nominatimPlace()])]);

        $first = $this->search('Chilonzor 12')->assertOk()->json();
        $second = $this->search('  chilonzor    12 ')->assertOk()->json();

        $this->assertSame($first, $second);
        Http::assertSentCount(1);

        // A different question goes to the provider
        $this->search('Chilonzor 13')->assertOk();
        Http::assertSentCount(2);
    }

    #[DataProvider('invalidQueries')]
    public function test_search_validates_the_query(array $query): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/v1/geocode/search?' . http_build_query($query))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);

        Http::assertNothingSent();
    }

    public static function invalidQueries(): array
    {
        return [
            'missing' => [[]],
            'blank' => [['q' => '   ']],
            'too short' => [['q' => 'ab']],
            'too long' => [['q' => str_repeat('a', 201)]],
            'not a string' => [['q' => ['Chilonzor']]],
        ];
    }

    // ---- Provider trouble ----

    public function test_a_provider_failure_is_a_503_and_is_not_cached(): void
    {
        Http::fake([self::NOMINATIM . '/*' => Http::sequence()
            ->push('Bad gateway', 502)
            ->push($this->nominatimPlace())]);

        $this->reverse()->assertStatus(503)->assertJsonPath('success', false);

        // Not remembered: the next attempt asks again and succeeds
        $this->reverse()->assertOk()->assertJsonPath('data.lat', 41.2995123);
        Http::assertSentCount(2);
    }

    public function test_being_rate_limited_by_the_provider_is_a_503_and_is_not_cached(): void
    {
        Http::fake([self::NOMINATIM . '/*' => Http::sequence()
            ->push('Too many requests', 429)
            ->push([$this->nominatimPlace()])]);

        $this->search('Chilonzor')->assertStatus(503)->assertJsonPath('success', false);
        $this->search('Chilonzor')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_timeout_or_network_error_is_a_503(): void
    {
        Http::fake([self::NOMINATIM . '/*' => Http::failedConnection('cURL error 28: Operation timed out')]);

        $this->reverse()->assertStatus(503)->assertJsonPath('success', false);
        $this->search('Chilonzor')->assertStatus(503)->assertJsonPath('success', false);
    }

    public function test_an_unreadable_provider_answer_is_a_503(): void
    {
        Http::fake([self::NOMINATIM . '/*' => Http::response('<html>maintenance</html>', 200)]);

        $this->reverse()->assertStatus(503);
    }

    // ---- Rate limit ----

    public function test_lookups_are_limited_per_user_across_both_endpoints(): void
    {
        $other = User::create(['name' => 'Other', 'phone' => '+998910000079', 'password' => bcrypt('password')]);
        Http::fake([self::NOMINATIM . '/*' => Http::response($this->nominatimPlace())]);

        for ($i = 1; $i <= 30; $i++) {
            // Cached after the first one: only the counter matters here
            $this->assertNotSame(429, $this->reverse()->status(), "Request #{$i} was throttled too early");
        }

        $this->reverse()->assertStatus(429)->assertJsonPath('success', false);
        // The bucket is shared by reverse and search...
        $this->search('Chilonzor')->assertStatus(429);
        // ...and belongs to the user, not to the address they share
        $this->reverse(['lat' => 41.2995, 'lng' => 69.2401], $other)->assertOk();
    }

    // ---- Providers ----

    public function test_the_container_resolves_the_configured_provider_wrapped_in_the_cache(): void
    {
        $geocoder = $this->app->make(GeocoderInterface::class);

        $this->assertInstanceOf(GeocoderManager::class, $geocoder);
        $this->assertInstanceOf(CachedGeocoder::class, $geocoder->driver());
    }

    public function test_another_provider_can_be_plugged_in_by_config(): void
    {
        $provider = new class implements GeocoderInterface {
            public int $calls = 0;

            public function reverse(float $lat, float $lng): ?GeocodedPlace
            {
                $this->calls++;

                return new GeocodedPlace('Fake street 1', $lat, $lng);
            }

            public function search(string $query, int $limit = 5): array
            {
                $this->calls++;

                return [new GeocodedPlace("Found: {$query}", 41.0, 69.0)];
            }
        };

        $manager = new GeocoderManager($this->app);
        $manager->extend('fake', fn () => $provider);
        $this->app->instance(GeocoderInterface::class, $manager);
        config(['services.geocoding.provider' => 'fake']);

        $this->reverse(['lat' => 41.5, 'lng' => 69.5])
            ->assertOk()
            ->assertJsonPath('data', ['address' => 'Fake street 1', 'lat' => 41.5, 'lng' => 69.5]);
        $this->search('Chilonzor')->assertOk()->assertJsonPath('data.0.address', 'Found: Chilonzor');

        $this->assertSame(2, $provider->calls);
        Http::assertNothingSent();
    }

    public function test_an_unsupported_provider_in_config_is_rejected_loudly(): void
    {
        config(['services.geocoding.provider' => 'yandex']);

        $this->withoutExceptionHandling();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [yandex] not supported.');

        $this->reverse();
    }

    public function test_answers_are_cached_for_at_least_a_day_whatever_the_configured_ttl(): void
    {
        config(['services.geocoding.cache_ttl' => 60]);
        Http::fake([self::NOMINATIM . '/*' => Http::response($this->nominatimPlace())]);

        $this->reverse()->assertOk();
        $this->travel(23)->hours();
        $this->reverse()->assertOk();
        Http::assertSentCount(1);

        // ...but not forever
        $this->travel(2)->hours();
        $this->reverse()->assertOk();
        Http::assertSentCount(2);
    }

    public function test_the_default_ttl_is_longer_than_a_day(): void
    {
        $this->assertGreaterThan(86400, config('services.geocoding.cache_ttl'));
    }

    public function test_the_provider_is_asked_about_the_rounded_point_and_a_miss_is_looked_up_once(): void
    {
        $spy = new class implements GeocoderInterface {
            /** @var list<array{float, float}> */
            public array $points = [];

            public function reverse(float $lat, float $lng): ?GeocodedPlace
            {
                $this->points[] = [$lat, $lng];

                return null;
            }

            public function search(string $query, int $limit = 5): array
            {
                return [];
            }
        };
        $geocoder = new CachedGeocoder($spy, Cache::store(), 'spy', 86400);

        $this->assertNull($geocoder->reverse(41.29951234, 69.24014567));
        $this->assertNull($geocoder->reverse(41.29950987, 69.24015432)); // same ~1 m cell; "nothing there" is cached too

        $this->assertSame([[41.29951, 69.24015]], $spy->points);
    }
}
