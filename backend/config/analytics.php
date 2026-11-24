<?php

/*
 * Wave P, step 2 (docs/ANALYTICS_PLAN.md 4.1, A.3, A.4): first-party Mini App analytics events, POST /api/v1/analytics/events.
 *
 * Off by default: nothing is written (not even the per-user daily budget) until the privacy policy text of step 1 is
 * published (plan section 9) and the owner switches it on with ANALYTICS_ENABLED=1.
 */
return [
    'enabled' => (bool) env('ANALYTICS_ENABLED', false),

    // Ingestion limits (A.4). The per-request limits are the `analytics-events` rate limiter (AppServiceProvider);
    // events_per_day is a budget per user counted in events, enforced by App\Domain\Analytics\EventIngestor
    'limits' => [
        'max_body_bytes' => 32768,
        'max_events' => 50,
        'requests_per_minute_user' => 20,
        'requests_per_minute_session' => 20,
        'requests_per_minute_ip' => 120,
        'events_per_day' => 3000,
    ],
];
