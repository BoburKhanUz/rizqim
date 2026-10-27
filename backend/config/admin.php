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
    ],

    'invite' => [
        'ttl_hours' => 72,
    ],

    'two_factor' => [
        'issuer' => 'RIZQIM',
        // Accepted clock drift either side, in 30 s steps
        'window' => 1,
        'recovery_codes' => 10,
    ],
];
