<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Geo\GeocodingException;
use App\Services\Geo\NominatimGeocoder;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * The public Nominatim instance allows one request per second for the whole platform: the geocoder queues its
 * requests behind a cache lock and pauses between them. Time is faked, so nothing here really waits.
 */
class GeocodeNominatimThrottleTest extends TestCase
{
    use RefreshDatabase;

    private const NOMINATIM = 'https://nominatim.openstreetmap.org';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.nominatim.base_url' => self::NOMINATIM,
            'services.nominatim.min_interval' => 1,
            'services.nominatim.lock_wait' => 0.5,
        ]);

        Http::preventStrayRequests();
        Carbon::setTestNow('2026-10-18 10:00:00');
        Sleep::fake(syncWithCarbon: true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function geocoder(): NominatimGeocoder
    {
        return new NominatimGeocoder(config('services.nominatim'));
    }

    private function place(): array
    {
        return ['lat' => '41.3', 'lon' => '69.2', 'display_name' => 'Somewhere, Toshkent'];
    }

    private function assertSleptFor(int $milliseconds, int $times = 1): void
    {
        Sleep::assertSlept(
            fn (CarbonInterval $duration): bool => (int) round($duration->totalMilliseconds) === $milliseconds,
            $times,
        );
    }

    public function test_back_to_back_requests_are_a_second_apart_whichever_endpoint_they_use(): void
    {
        Http::fake([
            self::NOMINATIM . '/search*' => Http::response([$this->place()]),
            self::NOMINATIM . '/reverse*' => Http::response($this->place()),
        ]);
        $geocoder = $this->geocoder();

        // Nothing came before the first one
        $geocoder->search('Chilonzor');
        Sleep::assertNeverSlept();

        $geocoder->reverse(41.1, 69.1);
        $geocoder->search('Yunusobod');

        Http::assertSentCount(3);
        Sleep::assertSleptTimes(2);
        $this->assertSleptFor(1000, 2);
    }

    public function test_only_the_time_still_missing_to_a_full_second_is_waited(): void
    {
        Http::fake([self::NOMINATIM . '/*' => Http::response($this->place())]);
        $geocoder = $this->geocoder();

        $geocoder->reverse(41.1, 69.1);

        Carbon::setTestNow(Carbon::now()->addMilliseconds(400));
        $geocoder->reverse(41.2, 69.2);
        $this->assertSleptFor(600);

        // A request after a long quiet spell goes out at once
        Carbon::setTestNow(Carbon::now()->addSeconds(5));
        $geocoder->reverse(41.3, 69.3);
        Sleep::assertSleptTimes(1);
    }

    public function test_the_pause_is_shared_by_every_worker_not_kept_per_geocoder_instance(): void
    {
        Http::fake([self::NOMINATIM . '/*' => Http::response($this->place())]);

        $this->geocoder()->reverse(41.1, 69.1);
        $this->geocoder()->reverse(41.2, 69.2); // "another PHP worker"

        $this->assertSleptFor(1000);
    }

    public function test_the_slot_is_held_while_a_request_is_in_flight_and_released_afterwards(): void
    {
        $heldDuringTheRequest = null;
        Http::fake(function () use (&$heldDuringTheRequest) {
            // What a second worker would see right now
            $heldDuringTheRequest = !Cache::lock(NominatimGeocoder::LOCK_KEY, 5)->get();

            return Http::response($this->place());
        });

        $this->geocoder()->reverse(41.1, 69.1);

        $this->assertTrue($heldDuringTheRequest, 'The slot must be taken while the request is in flight');
        $this->assertTrue(Cache::lock(NominatimGeocoder::LOCK_KEY, 5)->get(), 'The slot must be free again');
    }

    public function test_the_slot_is_released_when_the_request_fails(): void
    {
        Http::fake([self::NOMINATIM . '/*' => Http::failedConnection('cURL error 28: Operation timed out')]);

        try {
            $this->geocoder()->reverse(41.1, 69.1);
            $this->fail('A timeout must surface as a GeocodingException');
        } catch (GeocodingException $e) {
            // The URL (the customer's location) is not part of the message
            $this->assertStringNotContainsString('41.1', $e->getMessage());
        }

        $this->assertTrue(Cache::lock(NominatimGeocoder::LOCK_KEY, 5)->get());
    }

    public function test_a_request_that_cannot_get_a_slot_in_time_gives_up_without_calling_the_provider(): void
    {
        Http::fake([self::NOMINATIM . '/*' => Http::response($this->place())]);
        // Another worker is busy with a (slow) request
        $this->assertTrue(Cache::lock(NominatimGeocoder::LOCK_KEY, 30)->get());

        try {
            $this->geocoder()->reverse(41.1, 69.1);
            $this->fail('Expected a GeocodingException');
        } catch (GeocodingException $e) {
            $this->assertStringContainsString('no free request slot', $e->getMessage());
        }

        Http::assertNothingSent();
        // It waited for at most the configured half second (the lock polls every 100 ms), not seconds
        $waitedMilliseconds = Carbon::parse('2026-10-18 10:00:00')->diffInMilliseconds(Carbon::now(), true);
        $this->assertGreaterThanOrEqual(300, $waitedMilliseconds);
        $this->assertLessThanOrEqual(500, $waitedMilliseconds);
        $this->assertSame(2, $e->retryAfter);
    }

    public function test_the_api_answers_503_when_the_provider_is_busy(): void
    {
        Http::fake([self::NOMINATIM . '/*' => Http::response($this->place())]);
        Cache::lock(NominatimGeocoder::LOCK_KEY, 30)->get();
        $user = User::create(['name' => 'Geo Customer', 'phone' => '+998910000088', 'password' => bcrypt('password')]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/geocode/reverse?lat=41.2995&lng=69.2401')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '2')
            ->assertJsonPath('success', false);

        Http::assertNothingSent();
    }

    public function test_the_shipped_config_fails_fast(): void
    {
        $shipped = require config_path('services.php');

        $this->assertLessThanOrEqual(0.5, $shipped['nominatim']['lock_wait']);
    }

    public function test_a_self_hosted_instance_can_switch_the_pause_and_the_queue_off(): void
    {
        config(['services.nominatim.min_interval' => 0]);
        Http::fake([self::NOMINATIM . '/*' => Http::response($this->place())]);
        // Even a held slot does not matter without a limit
        Cache::lock(NominatimGeocoder::LOCK_KEY, 30)->get();
        $geocoder = $this->geocoder();

        $geocoder->reverse(41.1, 69.1);
        $geocoder->reverse(41.2, 69.2);

        Http::assertSentCount(2);
        Sleep::assertNeverSlept();
    }
}
