<?php

namespace App\Services\Geo;

use RuntimeException;

/**
 * The geocoding provider could not answer (timeout, HTTP error, rate limited, garbage payload, no free request slot).
 * Never cached: the next call should try again.
 */
class GeocodingException extends RuntimeException {}
