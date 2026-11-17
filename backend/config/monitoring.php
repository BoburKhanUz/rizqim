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
        // M3: ops messages are sent at once by the process that raises them (the scheduler), not through the queue a
        // dead worker would hold them in. One request waits at most `timeout` seconds; one evaluation spends at most
        // `run_budget_seconds` on Telegram in all; after a failed request Telegram is skipped for `breaker_seconds`.
        // A message that is not sent at once is handed to the queue instead (delivered once a worker runs).
        'timeout' => 5,
        'run_budget_seconds' => 10,
        'breaker_seconds' => 120,
    ],

    // M3 (docs/scale/M3_DESIGN.md section 7): the server-level checks
    'sql_statement_timeout' => '2s',

    // Point-in-time recovery (pgBackRest, the `backup` service): PITR_ENABLED=1 in the root .env. Unset and no fresh
    // `pitr` marker that says otherwise = off: wal_archive, pitr_backup_stale and restore_drill_stale stay quiet.
    'pitr' => [
        'enabled' => (bool) env('PITR_ENABLED', false),
    ],

    // The dead-man switch (healthchecks.io or alike): pinged after every successful alert evaluation. Unset = no call.
    'healthchecks_ping_url' => (string) env('HEALTHCHECKS_PING_URL', ''),
    'healthchecks_timeout' => 3,

    'postgres' => [
        // PgBouncer's max_db_connections (docker-compose.yml): one client address holding this share is reported too
        'pgbouncer_max_connections' => (int) env('PGBOUNCER_MAX_DB_CONNECTIONS', 30),
        // Sessions that may legitimately run long (the nightly pg_dump, pgBackRest)
        'long_transaction_exclude' => ['pg_dump', 'pg_dumpall', 'pgBackRest%', 'pg_basebackup'],
    ],

    // table_bloat: the hot tables, and the dead-row counts below which nothing is reported
    'bloat' => [
        'tables' => ['orders', 'order_items', 'order_bills', 'payments', 'idempotency_keys', 'personal_access_tokens', 'product_variants', 'stores'],
        'min_dead_rows' => 100000,
        'vacuum_min_dead_rows' => 10000,
    ],

    // tls_expiry: the host whose certificate is checked (default: APP_URL's host, https only); the result is reused
    'tls' => [
        'host' => (string) env('MONITORING_TLS_HOST', ''),
        'port' => 443,
        'timeout' => 5,
        'cache_minutes' => 60,
    ],

    // queue_worker_stale: QueueHeartbeatJob goes to this queue every minute
    'queue_heartbeat_queue' => 'default',

    'sentry_project_url' => (string) env('SENTRY_PROJECT_URL', ''),

    'version' => (string) (env('APP_VERSION') ?: env('SENTRY_RELEASE', '')),
    'commit' => (string) (env('APP_COMMIT') ?: env('SENTRY_RELEASE', '')),

    // Reverb reachability: how long a result is reused (the Holat tab polls) and the request timeout, in seconds
    'reverb' => [
        'cache_seconds' => 15,
        'timeout' => 2,
    ],
];
