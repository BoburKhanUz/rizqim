<?php

use App\Support\SentryScrubber;

/**
 * Sentry (xatolarni kuzatish). To'liq env bilan boshqariladi: SENTRY_LARAVEL_DSN bo'sh bo'lsa SDK hech narsa yubormaydi
 * va ilova odatdagidek ishlaydi. Sozlash: docs/OPERATIONS.md.
 *
 * Maxfiy ma'lumot (Authorization, initData, OTP kod, telefon, to'lov kalitlari, karta raqamlari) yuborishdan oldin
 * App\Support\SentryScrubber orqali tozalanadi. Callable'lar [Class, 'method'] ko'rinishida: config:cache closure'ni
 * saqlay olmaydi.
 *
 * @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/
 */
$dsn = env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN'));

return [

    // Bo'sh yoki null = Sentry o'chiq
    'dsn' => is_string($dsn) && trim($dsn) !== '' ? trim($dsn) : null,

    // Deploy versiyasi (masalan git sha): xato qaysi deploydan boshlanganini ko'rsatadi
    'release' => env('SENTRY_RELEASE') ?: null,

    // Bo'sh bo'lsa APP_ENV ishlatiladi
    'environment' => env('SENTRY_ENVIRONMENT') ?: env('APP_ENV', 'production'),

    // Xatolarning qancha qismi yuboriladi (1.0 = hammasi)
    'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 1.0),

    // Performance trace'lar: qasddan past (0.05 = 5%); 0 = o'chiq
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.05),
    'profiles_sample_rate' => null,

    'enable_logs' => false,
    'enable_metrics' => false,

    // Foydalanuvchi IP/email/cookie yuborilmaydi; so'rov tanasi (telefon, OTP, initData bor) hech qachon
    'send_default_pii' => false,
    'max_request_body_size' => 'none',

    'before_send' => [SentryScrubber::class, 'beforeSend'],
    'before_send_transaction' => [SentryScrubber::class, 'beforeSendTransaction'],
    'before_breadcrumb' => [SentryScrubber::class, 'beforeBreadcrumb'],

    'ignore_transactions' => [
        '/up',
        '/health',
        '/api/v1/health',
    ],

    // Breadcrumb'lar: SQL parametrlari (telefon, kod) hech qachon yig'ilmaydi
    'breadcrumbs' => [
        'logs' => true,
        'cache' => false,
        'livewire' => false,
        'sql_queries' => true,
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => true,
        'notifications' => false,
    ],

    'tracing' => [
        'queue_job_transactions' => true,
        'queue_jobs' => true,
        'sql_queries' => true,
        'sql_bindings' => false,
        'sql_origin' => false,
        'views' => false,
        'livewire' => false,
        'http_client_requests' => true,
        'cache' => false,
        'redis_commands' => false,
        'notifications' => false,
        'missing_routes' => false,
        'continue_after_response' => true,
        'default_integrations' => true,
    ],

];
