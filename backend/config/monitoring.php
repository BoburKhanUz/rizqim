<?php

/*
|--------------------------------------------------------------------------
| Monitoring (P4-15): Boshqaruv markazi → Monitoring
|--------------------------------------------------------------------------
|
| telegram: how often the ops group hears about alerts (docs/API.md "Monitoring"). The chat itself is
| services.telegram.ops_chat_id (TELEGRAM_OPS_CHAT_ID), else the superadmin chat.
| sentry_project_url: where "Sentry'da ochish" leads; no API calls are made. version / commit: set at build time
| (APP_VERSION, APP_COMMIT), else the Sentry release.
|
*/

return [
    'telegram' => [
        // The same rule + subject is announced at most once per this many minutes
        'cooldown_minutes' => (int) env('MONITORING_TELEGRAM_COOLDOWN', 15),
        // Alert messages per hour in all; more are held back (one notice says so)
        'hourly_limit' => (int) env('MONITORING_TELEGRAM_HOURLY_LIMIT', 20),
    ],

    'sentry_project_url' => (string) env('SENTRY_PROJECT_URL', ''),

    'version' => (string) (env('APP_VERSION') ?: env('SENTRY_RELEASE', '')),
    'commit' => (string) (env('APP_COMMIT') ?: env('SENTRY_RELEASE', '')),

    // Reverb reachability: how long a result is reused (the Holat tab polls) and the request timeout, in seconds
    'reverb' => [
        'cache_seconds' => 15,
        'timeout' => 2,
    ],
];
