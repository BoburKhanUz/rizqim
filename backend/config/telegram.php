<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mock Mini App Authentication
    |--------------------------------------------------------------------------
    |
    | Accept unsigned "mock_" initData for local development. Only honoured
    | when APP_ENV=local as well; must stay false on any shared server.
    |
    */

    'allow_mock_auth' => filter_var(env('TELEGRAM_ALLOW_MOCK_AUTH', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Webhook URL
    |--------------------------------------------------------------------------
    |
    | Public HTTPS URL registered by `php artisan telegram:set-webhook`.
    | Defaults to APP_URL + /api/v1/telegram/webhook when empty.
    |
    */

    'webhook_url' => env('TELEGRAM_WEBHOOK_URL'),

];
