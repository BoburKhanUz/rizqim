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
    ],

    'payme' => [
        'secret_key' => env('PAYME_SECRET_KEY'),
        'merchant_id' => env('PAYME_MERCHANT_ID', '65eb2b988f01b97b0a7c4912'),
    ],

];
