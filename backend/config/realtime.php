<?php

/*
|--------------------------------------------------------------------------
| Real-time: change revisions, heartbeat, channel-auth cache (wave M2)
|--------------------------------------------------------------------------
|
| App\Domain\Realtime\ChangeRevision keeps one opaque token per branch, store and customer. Every write to a row that a
| polled screen shows replaces the token after the transaction commits (App\Domain\Realtime\RevisionHooks); clients
| (ETag/If-None-Match, the `rev` field of every broadcast event) compare tokens instead of re-reading. docs/OPERATIONS.md
|
*/

return [

    // The cache store that holds the tokens. It must not be the database store (a token lookup must not touch
    // Postgres): production uses Redis. When the store is unreachable, every read returns a fresh random token, so
    // clients simply re-read (never a stale screen).
    'revision_store' => env('REALTIME_REVISION_STORE', 'redis'),

    // A token that nobody touched for this long expires; the next read issues a new one (one extra full read).
    'revision_ttl_seconds' => (int) env('REALTIME_REVISION_TTL', 7 * 24 * 3600),

    // Heartbeat: every N seconds (10, 15, 20, 30 or 60) the scheduler queues SendRealtimeHeartbeat on the broadcasts
    // queue, which sends `realtime.heartbeat` with the channel's current token to every occupied pos-branch/branch/store
    // channel. A client that hears nothing for 3 x N seconds treats real-time as down and polls fast. 0 turns it off
    // (and then GET /api/v1/realtime is never live).
    'heartbeat_seconds' => (int) env('REALTIME_HEARTBEAT_SECONDS', 30),

    // Real-time is reported unhealthy when the broadcast queue holds more jobs than this, or the oldest heartbeat
    // round-trip is older than heartbeat_stale_seconds.
    'broadcast_queue' => env('REALTIME_BROADCAST_QUEUE', 'broadcasts'),
    'queue_backlog_threshold' => (int) env('REALTIME_QUEUE_BACKLOG_THRESHOLD', 500),
    'heartbeat_stale_seconds' => (int) env('REALTIME_HEARTBEAT_STALE_SECONDS', 120),

    // POST /api/broadcasting/auth: a granted channel is remembered for this many seconds per bearer token + channel, so
    // the reconnect wave after a Reverb restart costs one Redis lookup and an HMAC per client instead of the token
    // lookup and the channel's access query. Any change to a token, terminal, user or permission override drops every
    // remembered grant at its commit; this TTL bounds the rest (an open socket keeps its channel until it closes, as
    // before). 0 turns the cache off.
    'auth_cache_seconds' => (int) env('REALTIME_AUTH_CACHE_SECONDS', 60),

    // What GET /api/v1/realtime tells clients: the safety poll while live and the poll while real-time is not live
    'poll_live_seconds' => (int) env('REALTIME_POLL_LIVE_SECONDS', 60),
    'poll_fallback_seconds' => (int) env('REALTIME_POLL_FALLBACK_SECONDS', 15),

    // Where clients open the socket, when it is not the API's own host (/app/ on the same origin): GET /api/v1/realtime
    'public' => [
        'host' => env('REVERB_PUBLIC_HOST'),
        'port' => env('REVERB_PUBLIC_PORT'),
        'scheme' => env('REVERB_PUBLIC_SCHEME'),
    ],

];
