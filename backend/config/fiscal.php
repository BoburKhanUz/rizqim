<?php

/*
| P3-11: fiscal receipts (App\Domain\Fiscal, docs/API.md "Fiskal cheklar"). A store picks its driver in
| stores.fiscal_settings.driver; a store whose fiscal mode is off always uses the `null` driver ("fiskal emas").
*/

return [

    // The driver of a store that turned fiscal mode on without choosing one. Until a real virtual kassa provider is
    // connected (P3-32) both drivers produce receipts marked non_fiscal ("Bu fiskal chek emas").
    'default_driver' => env('FISCAL_DRIVER', 'sandbox'),

    // name => class implementing App\Domain\Fiscal\Gateways\FiscalGateway
    'drivers' => [
        'sandbox' => App\Domain\Fiscal\Gateways\SandboxFiscalGateway::class,
        'null' => App\Domain\Fiscal\Gateways\NullFiscalGateway::class,
    ],

    // The regime a store that has not chosen one is treated as (stores.tax_regime null): the standard 12% VAT
    'default_tax_regime' => 'vat',

    'retry' => [
        // Attempts per receipt (the first one included) before it is marked `failed`; a manual retry adds one more
        'max_attempts' => (int) env('FISCAL_RETRY_MAX_ATTEMPTS', 12),
        // Wait after the n-th failed attempt (the last value repeats): 30 s, 1, 2, 5, 10, 30 min, then hourly
        'backoff_seconds' => [30, 60, 120, 300, 600, 1800, 3600],
        // How long an attempt holds a receipt: a worker that died mid-call is picked up again after this
        'lease_seconds' => (int) env('FISCAL_RETRY_LEASE_SECONDS', 120),
        'queue' => env('FISCAL_QUEUE', 'default'),
    ],

    'sandbox' => [
        // Where the fake receipt QR points: a reserved, never-resolving domain, so nobody mistakes it for a real check
        'qr_base_url' => env('FISCAL_SANDBOX_QR_URL', 'https://fiscal-sandbox.invalid/check'),
        // Upper bound for stores.fiscal_settings.sandbox.latency_ms
        'max_latency_ms' => 3000,
    ],

    // Receipt lines for charges that are not menu items (FiscalLineBuilder::forCharge). A store overrides any of them
    // in fiscal_settings.charges.<key>. MXIK codes found in the research (docs/research/reports, "Choyxona", 5):
    // 10202002003000002 "boshqa bron va joy band qilish xizmatlari", 10202002004000001 "majburiy depozit uchun
    // oldindan to'lov". Room fee, place fee, minimum-spend top-up and service charge belong to group 10202002, but
    // their exact codes are not published: the owner must set them (checked on tasnif.soliq.uz) before selling them.
    'charges' => [
        'room_fee' => ['name' => 'Xona puli', 'mxik' => null, 'unit' => 'xizmat'],
        'place_fee' => ['name' => 'Joy puli', 'mxik' => null, 'unit' => 'xizmat'],
        'min_spend_topup' => ['name' => "Minimal summaga qo'shimcha", 'mxik' => null, 'unit' => 'xizmat'],
        'service_charge' => ['name' => 'Xizmat haqi', 'mxik' => null, 'unit' => 'xizmat'],
        'deposit' => ['name' => 'Avans (bron)', 'mxik' => '10202002004000001', 'unit' => 'xizmat'],
        'booking_fee' => ['name' => 'Bron xizmati', 'mxik' => '10202002003000002', 'unit' => 'xizmat'],
        'credit' => ['name' => "Qarz to'lovi (nasiya)", 'mxik' => '10202002004000001', 'unit' => 'xizmat'],
        // Delivery of an online order (P3-14, Payme/Click receipts): group 10202002, exact code not published
        'delivery' => ['name' => 'Yetkazib berish', 'mxik' => null, 'unit' => 'xizmat'],
    ],

];
