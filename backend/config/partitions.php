<?php

/*
 * Wave M3 (docs/scale/M3_DESIGN.md sections 3-5): the journal tables partitioned by month (App\Support\Partitions).
 *
 * Boundaries are UTC months (the app timezone is UTC; the columns are timestamp(0) holding UTC). Every table keeps a
 * DEFAULT partition as a safety net: a row of a month that is not attached yet still goes in (an audit or fiscal insert
 * inside a till transaction never fails), `db:partitions:ensure` moves it out, `db:partitions:status` reports it.
 */
return [
    // The first month the migration creates (a database whose rows are older gets months from its oldest row)
    'first_month' => env('PARTITIONS_FIRST_MONTH', '2026-01'),

    // Months attached ahead of the current one (db:partitions:ensure, daily); fewer than 2 is a problem in the status
    'months_ahead' => 3,

    // The partition_journals migration converts a table inline only below this many rows (pg_class.reltuples, and an
    // exact count); above it the migration refuses and says how long the conversion would lock the table: run it in a
    // quiet window with PARTITIONS_INLINE_MAX_ROWS=<rows + margin> php artisan migrate (read from the process
    // environment at that moment, also with a cached config)
    'inline_max_rows' => (int) env('PARTITIONS_INLINE_MAX_ROWS', 200000),

    // lock_timeout of the maintenance DDL (attach, detach, the inline conversion) and how often it is retried: a till's
    // insert never waits longer than this behind it
    'lock_timeout' => env('PARTITIONS_LOCK_TIMEOUT', '2s'),
    'detach_lock_timeout' => env('PARTITIONS_DETACH_LOCK_TIMEOUT', '3s'),
    'lock_retries' => 3,

    // Archive: a `verified` detached partition is dropped this many days after verification. Without a verified
    // off-site copy nothing is dropped, unless ARCHIVE_ALLOW_LOCAL_ONLY=1 (then an `uploaded`, local-only copy suffices)
    'drop_after_days' => 3,
    'allow_local_only' => (bool) env('ARCHIVE_ALLOW_LOCAL_ONLY', false),

    'tables' => [
        'audit_logs' => [
            'column' => 'created_at',
            // Months kept attached, counting the current one
            'online_months' => (int) env('ARCHIVE_AUDIT_MONTHS', 7),
            // true: detached, dumped and verified off-site before the drop (never deleted from the archive)
            'archive' => true,
        ],
        'fiscal_receipts' => [
            'column' => 'created_at',
            'online_months' => (int) env('ARCHIVE_FISCAL_MONTHS', 13),
            'archive' => true,
        ],
        'notification_logs' => [
            'column' => 'sent_at',
            'online_months' => (int) env('ARCHIVE_NOTIFICATION_MONTHS', 6),
            // Old notifications are worthless: detached and dropped without an archive
            'archive' => false,
        ],
        // Wave P step 2 (docs/ANALYTICS_PLAN.md A.3, section 7 question 1): raw Mini App events, kept 3 months (counting
        // the current one), then dropped without an archive; the daily aggregates of step 16 keep the numbers
        'analytics_events' => [
            'column' => 'received_at',
            'online_months' => (int) env('ANALYTICS_EVENTS_MONTHS', 3),
            'archive' => false,
            // Months before the online window are never created (first_month does not apply): a new table has no old
            // rows, and an empty month made only to be dropped the same night is noise
            'only_online_months' => true,
        ],
    ],
];
