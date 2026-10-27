<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // auth:sanctum accepts user tokens only (a paired terminal's token is not a user)
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],

        // auth:device: a paired POS/KDS terminal's own token (App\Auth\DeviceTokenGuard)
        'device' => [
            'driver' => 'device-token',
            'provider' => 'devices',
        ],

        // P4-11: the RIZQIM team's admin API sessions (App\Auth\AdminTokenGuard, docs/API.md "Admin API")
        'admin' => [
            'driver' => 'admin-token',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        'devices' => [
            'driver' => 'eloquent',
            'model' => App\Models\Device::class,
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | Phone Verification (SMS OTP)
    |--------------------------------------------------------------------------
    |
    | Returning the OTP in the API response is a local development aid only:
    | it also requires APP_ENV=local. Send limits live in the "otp-send"
    | rate limiter (AppServiceProvider).
    |
    */

    'phone_verification' => [
        'expose_code_in_response' => filter_var(env('SMS_EXPOSE_CODE_IN_RESPONSE', false), FILTER_VALIDATE_BOOLEAN),
        // Guesses per code; a resend keeps the previous live code, so at most two codes are open at once
        'max_attempts' => 5,
        // Guesses per phone in 24 hours, over all codes
        'max_attempts_per_day' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Device sessions, terminals and staff PINs (docs/AUTH.md)
    |--------------------------------------------------------------------------
    |
    | access_ttl: minutes an app's access token lives when it signed in with a
    | device (it is renewed with the refresh token); refresh_ttl_days: how long
    | a refresh token lives unused. The Mini App's tokens keep
    | SANCTUM_TOKEN_EXPIRATION. device_ttl_days: a paired terminal's own token
    | (0 = until revoked). staff_session_ttl: minutes of a PIN session.
    |
    */

    'tokens' => [
        'access_ttl' => (int) env('AUTH_ACCESS_TOKEN_TTL', 15),
        'refresh_ttl_days' => (int) env('AUTH_REFRESH_TOKEN_TTL_DAYS', 30),
        'device_ttl_days' => (int) env('AUTH_DEVICE_TOKEN_TTL_DAYS', 365),
        'staff_session_ttl' => (int) env('AUTH_STAFF_SESSION_TTL', 720),
        'max_devices_per_user' => 20,
        // A retry with the refresh token spent this many seconds ago, whose new pair was never used, rotates once more
        // instead of counting as reuse (a lost response); at most 60
        'refresh_grace_seconds' => 30,
    ],

    'pairing' => [
        'ttl' => (int) env('AUTH_PAIRING_CODE_TTL', 10),
        'max_active' => 5,
        // POST /devices/pair per /24 (IPv6 /64) subnet; plus 10 per address
        'subnet_attempts_per_minute' => 30,
        // Platform-wide attempts in a minute that log a warning (never blocks)
        'alert_attempts_per_minute' => 600,
    ],

    'pin' => [
        'max_attempts' => (int) env('AUTH_PIN_MAX_ATTEMPTS', 5),
        'lockout_seconds' => (int) env('AUTH_PIN_LOCKOUT_SECONDS', 60),
        'max_lockout_seconds' => 3600,
        // Failures of one person on all terminals of the store before a lockout (moving terminals does not reset it)
        'max_attempts_per_user' => (int) env('AUTH_PIN_MAX_ATTEMPTS_PER_USER', 10),
        // How long a PIN attempt waits for the same person's attempt in progress elsewhere
        'attempt_wait_seconds' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Initial Super Admin (SuperAdminSeeder only)
    |--------------------------------------------------------------------------
    |
    | Required outside local/testing; the password must be at least 12
    | characters. An existing account with this email is never modified.
    |
    */

    'superadmin' => [
        'email' => env('SUPERADMIN_EMAIL'),
        'password' => env('SUPERADMIN_PASSWORD'),
    ],

];
