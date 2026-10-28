<?php

/*
|--------------------------------------------------------------------------
| Boshqaruv markazi: the RIZQIM team's admin API (P4-11, docs/API.md "Admin API")
|--------------------------------------------------------------------------
|
| app_url: where the platform/admin web app is served; invite links point
| there ({app_url}/invite/{token}). Session: absolute lifetime and idle
| timeout of an admin token (App\Auth\AdminTokenGuard), how long a step-up
| confirmation lasts (`admin.recent`). Login: challenge lifetime, the
| lockout after bad passwords or codes per account + IP.
|
*/

return [
    'app_url' => rtrim((string) env('ADMIN_APP_URL', 'http://localhost:3100'), '/'),

    'session' => [
        'lifetime_minutes' => (int) env('ADMIN_SESSION_LIFETIME', 720),
        'idle_minutes' => (int) env('ADMIN_SESSION_IDLE', 60),
        'reauth_minutes' => 10,
    ],

    'login' => [
        'challenge_minutes' => 5,
        'max_attempts' => 5,
        'lockout_minutes' => 15,
        // Bad codes of one account from every address together (a guessed password tried from many IPs)
        'max_code_attempts_per_account' => 15,
        // Bad passwords of one account from every address together; per-IP counters key IPv6 by /64
        'max_password_attempts_per_account' => 15,
        // Sign-in attempts on emails that are no team member go to the app log (not audit_logs), at most this many lines a minute
        'unknown_email_log_per_minute' => 30,
    ],

    'invite' => [
        'ttl_hours' => 72,
    ],

    'two_factor' => [
        'issuer' => 'RIZQIM',
        // Accepted clock drift either side, in 30 s steps
        'window' => 1,
        'recovery_codes' => 10,
        // 2FA can be set up only this long after an accepted invite or a 2FA reset (rizqim:admin:invite re-opens it)
        'setup_window_hours' => 24,
    ],

    // P4-31 Ko'rsatkichlar: how long the metrics page's numbers are cached (MRR, churn: App\Services\Admin\Metrics)
    'metrics' => [
        'cache_seconds' => (int) env('ADMIN_METRICS_CACHE_SECONDS', 300),
    ],

    // P4-31 Excel export of the lists (App\Services\Admin\Export): the most rows one file holds, and how many exports
    // one member may start a minute
    'export' => [
        'max_rows' => (int) env('ADMIN_EXPORT_MAX_ROWS', 50000),
        'per_minute' => 10,
    ],
];
