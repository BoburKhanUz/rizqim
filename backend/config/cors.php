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

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
