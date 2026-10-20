<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use App\Services\Geo\GeocodedPlace;
use App\Services\Geo\GeocoderInterface;
use App\Services\Geo\GeocodingException;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Lets the Mini App turn a map pin into an address and an address into a pin without talking to a geocoder itself.
 */
#[Group('Geocoding', "Xarita nuqtasi va manzil o'rtasida (server orqali).")]
class GeocodeController extends Controller
{
    use ApiResponse;

    private const SEARCH_RESULTS = 5;

    public function __construct(private readonly GeocoderInterface $geocoder) {}

    /**
     * Address of a map point
     *
     * GET /geocode/reverse?lat=&lng=  ->  {success, data: {address, lat, lng}}. 404 when the point has no address,
     * 503 (maybe with Retry-After) when the geocoding provider does not answer: let the customer type the address.
     */
    public function reverse(Request $request): JsonResponse
    {
        $point = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            $place = $this->geocoder->reverse((float) $point['lat'], (float) $point['lng']);
        } catch (GeocodingException $e) {
            return $this->unavailable($e);
        }

        if ($place === null) {
            return $this->error("Bu nuqta uchun manzil topilmadi. Manzilni qo'lda kiriting.", [], 404);
        }

        return $this->success($place->toArray());
    }

    /**
     * Search addresses
     *
     * GET /geocode/search?q=  ->  {success, data: [{address, lat, lng}, ...]}  (best match first, empty when nothing matches)
     * 503 (maybe with Retry-After) when the geocoding provider does not answer.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<array{address: string, lat: float, lng: float}>}')]
    public function search(Request $request): JsonResponse
    {
        $input = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:200'],
        ]);

        try {
            $places = $this->geocoder->search($input['q'], self::SEARCH_RESULTS);
        } catch (GeocodingException $e) {
            return $this->unavailable($e);
        }

        return $this->success(array_map(fn (GeocodedPlace $place): array => $place->toArray(), $places));
    }

    // 503, not 502/500: the Mini App keeps the pin and lets the customer type the address, so this is expected now and then
    private function unavailable(GeocodingException $e): JsonResponse
    {
        // Only the reason: the request carries the customer's location
        Log::warning('Geocoding failed: ' . $e->getMessage(), ['provider' => config('services.geocoding.provider')]);

        $response = $this->error("Manzil xizmati hozir javob bermayapti. Birozdan so'ng qayta urinib ko'ring yoki manzilni qo'lda kiriting.", [], 503);

        // Known when the provider was only busy with another request: the slot frees up within seconds
        return $e->retryAfter !== null ? $response->header('Retry-After', (string) $e->retryAfter) : $response;
    }
}
