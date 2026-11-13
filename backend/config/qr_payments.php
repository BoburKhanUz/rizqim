<?php

/*
| P6-41/P6-42: Click, Payme and UzQR dynamic QR at the till (App\Domain\Pos\QrPayments, docs/API.md "QR to'lov
| kassada"). Click and Payme pay into the store's own merchant account (payment_settings.click / .payme) and confirm
| through their webhooks; UzQR through the store's UzQR gateway (stores.fiscal_settings.uzqr.driver).
*/

return [

    // Demo and e2e stands: every provider is served by the sandbox (no merchant account needed, confirmed by the
    // simulate endpoint). Never on in production.
    'sandbox' => (bool) env('QR_PAYMENTS_SANDBOX', false),

    // Review P6W4 S3: the providers whose sandbox may still be used while `sandbox` is off (comma-separated; only
    // "uzqr" has a sandbox driver to pick): an e2e stand that drives the real Click/Payme webhooks but has no bank for
    // UzQR (QR_PAYMENTS_SIMULATE_DRIVERS=uzqr). The sandbox UzQR gateway is offered only with `sandbox` or this.
    'simulate_drivers' => array_values(array_filter(array_map('trim', explode(',', (string) env('QR_PAYMENTS_SIMULATE_DRIVERS', ''))))),

    // POST /pos/qr/{uuid}/simulate-paid confirms a sandbox QR (a sandbox driver only, and only with `sandbox` on or
    // the provider in `simulate_drivers`: QrProviders::simulates); off in production
    'simulate' => (bool) env('QR_PAYMENTS_SIMULATE', env('APP_ENV', 'production') !== 'production'),

    // How long a QR stays valid by default, and the range the owner may set (minutes)
    'expiry' => ['default' => 5, 'min' => 1, 'max' => 30],

    // A QR the provider started paying (Click Prepare, Payme CreateTransaction) does not expire for this long
    'hold_minutes' => 10,

    // Where the customer lands after paying with the Click or Payme app (null: the site)
    'return_url' => env('QR_PAYMENTS_RETURN_URL'),

    'click' => [
        // Merchant API: DELETE /payment/reversal/{service_id}/{payment_id} (https://docs.click.uz/merchant-api-request/)
        'api_url' => env('CLICK_MERCHANT_API_URL', 'https://api.click.uz/v2/merchant'),
    ],

    'payme' => [
        // Subscribe API (receipts.cancel; X-Auth: merchant_id:key) — the Merchant API transaction id is the receipt id
        'api_url' => env('PAYME_API_URL', 'https://checkout.paycom.uz/api'),
        // A created (held) Payme transaction may stay pending at Payme this long (its 12 h timeout): the QR stays
        // payable meanwhile instead of refusing a late PerformTransaction (review P6W4 C9)
        'hold_minutes' => 720,
    ],

    'uzqr' => [
        // name => class implementing App\Domain\Pos\QrPayments\UzQr\UzQrGateway (the real bank/provider driver comes
        // with P3-32); a store picks one in stores.fiscal_settings.uzqr.driver. `sandbox` is registered only while
        // `sandbox` is on or "uzqr" is in `simulate_drivers` (QrProviders::uzqrDrivers)
        'drivers' => [
            'sandbox' => App\Domain\Pos\QrPayments\UzQr\SandboxUzQrGateway::class,
        ],
        // The merchant name and city the sandbox puts into its EMV payload
        'sandbox_city' => 'TASHKENT',
    ],
];
