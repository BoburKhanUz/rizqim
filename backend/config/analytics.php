<?php

/*
 * Wave P, step 2 (docs/ANALYTICS_PLAN.md 4.1, A.3, A.4): first-party Mini App analytics events, POST /api/v1/analytics/events.
 *
 * Off by default: no event is stored and the per-user daily counters (events, sessions) are not touched until the
 * privacy policy text of step 1 is published (plan section 9) and the owner switches it on with ANALYTICS_ENABLED=1.
 * The route's per-request limiter (user, session, address) runs before the controller and still counts every request.
 */
return [
    'enabled' => (bool) env('ANALYTICS_ENABLED', false),

    // Ingestion limits (A.4). The per-request limits are the `analytics-events` rate limiter (AppServiceProvider);
    // events_per_day (stored events) and sessions_per_day (new session ids) are per user and enforced by
    // App\Domain\Analytics\EventIngestor. nginx has its own coarser limits in front (backend/docker/pools.sh, NGINX_ANALYTICS_*)
    'limits' => [
        'max_body_bytes' => 32768,
        'max_events' => 50,
        'requests_per_minute_user' => 20,
        'requests_per_minute_session' => 20,
        // Generous: behind a carrier NAT many customers share one address (as the telegram-auth limiter)
        'requests_per_minute_ip' => 600,
        'events_per_day' => 3000,
        // One account making many one-event sessions would skew a store's session-counted funnel
        'sessions_per_day' => 50,
    ],
];
