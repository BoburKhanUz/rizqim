<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The Mini App normally calls the API same-origin (its nginx / the Vite dev
    | server proxy /api), so CORS only matters when the API lives on another
    | domain. Set CORS_ALLOWED_ORIGINS to a comma-separated list of origins;
    | when empty, the origins of TELEGRAM_WEBAPP_URL and APP_URL are allowed.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => (static function (): array {
        $origins = array_map(
            static fn (string $origin): string => rtrim(trim($origin), '/'),
            explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
        );
        $origins = array_values(array_filter($origins));

        if ($origins !== []) {
            return $origins;
        }

        $originOf = static function (mixed $url): ?string {
            $parts = is_string($url) ? parse_url($url) : false;

            if (empty($parts['scheme']) || empty($parts['host'])) {
                return null;
            }

            return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        };

        return array_values(array_unique(array_filter([
            $originOf(env('TELEGRAM_WEBAPP_URL')),
            $originOf(env('APP_URL')),
        ])));
    })(),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // P2-33: a cross-origin page (the partner web on its own domain) must be able to read the CSV export's file
    // name and the rate limiter's wait time. Wave M2: and the change token of a conditional GET (App\Http\Middleware\
    // ConditionalGet: ETag, X-Revision, X-Revision-Scope), which its revision tracking reads (docs/API.md 7.1)
    'exposed_headers' => ['Content-Disposition', 'Retry-After', 'ETag', 'X-Revision', 'X-Revision-Scope'],

    // Wave M2: the browser keeps a preflight answer (per origin, URL, method and request headers) for this long instead
    // of sending OPTIONS before every cross-origin poll. Safe to cache: the origins above are fixed at boot (env,
    // config cache) and change only with a deploy, and the real response is checked against them on every request
    // anyway, so a removed origin loses access at once. 7200 s is Chromium's cap (Firefox allows more).
    'max_age' => 7200,

    'supports_credentials' => false,

];
