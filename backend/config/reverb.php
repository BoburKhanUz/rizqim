<?php

/*
 * The Reverb WebSocket server (`php artisan reverb:start`, Docker service `reverb`). The app credentials below must be
 * the same for the server and for the backend that publishes to it (config/broadcasting.php), and REVERB_APP_KEY is
 * also the Mini App's VITE_REVERB_APP_KEY. REVERB_APP_SECRET never leaves the server side. docs/DEPLOY.md
 */
return [

    'default' => env('REVERB_SERVER', 'reverb'),

    'servers' => [

        'reverb' => [
            // Where the server itself listens (inside its container); not the address clients or the backend use
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => env('REVERB_SERVER_PORT', 8080),
            'path' => env('REVERB_SERVER_PATH', ''),
            'hostname' => env('REVERB_HOST'),
            'options' => [
                'tls' => [],
            ],
            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10_000),
            // Only needed with several Reverb servers behind a load balancer (they share messages over Redis)
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
                'server' => [
                    'url' => env('REDIS_URL'),
                    'host' => env('REDIS_HOST', '127.0.0.1'),
                    'port' => env('REDIS_PORT', '6379'),
                    'username' => env('REDIS_USERNAME'),
                    'password' => env('REDIS_PASSWORD'),
                    'database' => env('REDIS_DB', '0'),
                    'timeout' => env('REDIS_TIMEOUT', 60),
                ],
            ],
            'pulse_ingest_interval' => env('REVERB_PULSE_INGEST_INTERVAL', 15),
            'telescope_ingest_interval' => env('REVERB_TELESCOPE_INGEST_INTERVAL', 15),
        ],

    ],

    'apps' => [

        'provider' => 'config',

        'apps' => [
            [
                'key' => env('REVERB_APP_KEY'),
                'secret' => env('REVERB_APP_SECRET'),
                'app_id' => env('REVERB_APP_ID'),
                'options' => [
                    'host' => env('REVERB_HOST', '127.0.0.1'),
                    'port' => env('REVERB_PORT', 8080),
                    'scheme' => env('REVERB_SCHEME', 'http'),
                    'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
                ],
                // Comma-separated hosts the browser may open a socket from (e.g. mini.example.uz); * = any. Every
                // channel is private (signed by POST /api/broadcasting/auth), so this is defence in depth only.
                'allowed_origins' => array_values(array_filter(array_map(
                    'trim',
                    explode(',', (string) (env('REVERB_ALLOWED_ORIGINS') ?: '*'))
                ))),
                'ping_interval' => env('REVERB_APP_PING_INTERVAL', 60),
                'activity_timeout' => env('REVERB_APP_ACTIVITY_TIMEOUT', 30),
                'max_connections' => env('REVERB_APP_MAX_CONNECTIONS'),
                'max_message_size' => env('REVERB_APP_MAX_MESSAGE_SIZE', 10_000),
                // Clients only listen: client-to-client messages ("whispers") are refused
                'accept_client_events_from' => env('REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM', 'none'),
                'rate_limiting' => [
                    'enabled' => env('REVERB_APP_RATE_LIMITING_ENABLED', false),
                    'max_attempts' => env('REVERB_APP_RATE_LIMIT_MAX_ATTEMPTS', 60),
                    'decay_seconds' => env('REVERB_APP_RATE_LIMIT_DECAY_SECONDS', 60),
                    'terminate_on_limit' => env('REVERB_APP_RATE_LIMIT_TERMINATE', false),
                ],
            ],
        ],

    ],

];
