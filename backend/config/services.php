<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'webapp_url' => env('TELEGRAM_WEBAPP_URL'),
        // Platform alerts for superadmins (e.g. a delivered order whose ledger booking failed); none when unset
        'superadmin_chat_id' => env('TELEGRAM_SUPERADMIN_CHAT_ID'),
        // P4-15: the ops group that hears about monitoring alerts (opened and resolved); the superadmin chat when unset
        'ops_chat_id' => env('TELEGRAM_OPS_CHAT_ID'),
    ],

    // P4-32: the partner web panel (partner/web), where a store's top-up payment returns to; APP_URL when unset
    'partner_web' => [
        'url' => env('PARTNER_WEB_URL'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Platform-level merchant credentials. Secrets deliberately have no default: webhooks fail closed when unset.
    'click' => [
        'secret_key' => env('CLICK_SECRET_KEY'),
        'service_id' => env('CLICK_SERVICE_ID', '32104'),
        'merchant_id' => env('CLICK_MERCHANT_ID', '24185'),
        // P3-14: Merchant API user (Click cabinet) for sending fiscal receipt items; unset = no Click fiscal data
        'merchant_user_id' => env('CLICK_MERCHANT_USER_ID'),
    ],

    'payme' => [
        'secret_key' => env('PAYME_SECRET_KEY'),
        'merchant_id' => env('PAYME_MERCHANT_ID', '65eb2b988f01b97b0a7c4912'),
    ],

    // Review S#7: Tizim → To'lov tizimlari may put a platform provider in `test` (Payme's sandbox checkout) on
    // APP_ENV=production only with this set: a live customer would otherwise pay into the sandbox
    'payments' => [
        'allow_test_mode' => (bool) env('PAYMENTS_ALLOW_TEST_MODE', false),
    ],

    // Address search / reverse geocoding behind /api/v1/geocode/* (see App\Services\Geo). The Mini App never calls a
    // geocoder directly: the server adds the identification, caching and rate limiting a provider's usage policy asks for.
    'geocoding' => [
        // Only "nominatim" is implemented; others are added in App\Services\Geo\GeocoderManager
        'provider' => env('GEOCODING_PROVIDER', 'nominatim'),
        // Seconds an answer is remembered (minimum one day, default a week)
        'cache_ttl' => (int) env('GEOCODING_CACHE_TTL', 604800),
    ],

    'nominatim' => [
        'base_url' => env('NOMINATIM_BASE_URL', 'https://nominatim.openstreetmap.org'),
        // Contact put into the User-Agent as the usage policy of the public instance requires; falls back to APP_URL
        'email' => env('NOMINATIM_EMAIL'),
        'accept_language' => 'uz,ru',
        'country_codes' => 'uz',
        'timeout' => 5,
        // The public instance allows 1 request per second for the whole platform. 0 turns the pause off (self-hosted Nominatim only)
        'min_interval' => (float) env('NOMINATIM_MIN_INTERVAL', 1),
        // Seconds a request may wait for the request slot before the API answers 503 (with Retry-After): short, so a
        // queue of cache misses never ties up PHP workers
        'lock_wait' => 0.5,
    ],

];
