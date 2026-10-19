<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | Production: "reverb" (the self-hosted Reverb server, Docker service `reverb`). "null" or "log" turn real-time
    | updates off and nothing else changes: the Mini App falls back to polling (tests use "null").
    | Channels and their access rules: routes/channels.php; events: app/Events/Broadcast. docs/OPERATIONS.md
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    'connections' => [

        // The backend publishes over HTTP to the Reverb server: inside Docker that is reverb:8080 (docker-compose.yml
        // sets REVERB_HOST/PORT/SCHEME for the backend containers), on a VPS 127.0.0.1:8080. Clients connect through
        // the Mini App's own origin (nginx proxies /app/ and /apps/), see the VITE_REVERB_* build args.
        'reverb' => [
            'driver' => 'reverb',
            // '' rather than null when unset: the broadcaster is built at boot (routes/channels.php), and a null
            // key would stop every request and artisan command instead of only the real-time updates
            'key' => env('REVERB_APP_KEY', ''),
            'secret' => env('REVERB_APP_SECRET', ''),
            'app_id' => env('REVERB_APP_ID', ''),
            'options' => [
                'host' => env('REVERB_HOST', '127.0.0.1'),
                'port' => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            'client_options' => [
                // A Reverb outage must not hold a queue worker for long (Guzzle options)
                'timeout' => 5,
                'connect_timeout' => 3,
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
