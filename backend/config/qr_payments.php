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

    // POST /pos/qr/{uuid}/simulate-paid confirms a sandbox QR (a sandbox driver only); off in production
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
    ],

    'uzqr' => [
        // name => class implementing App\Domain\Pos\QrPayments\UzQr\UzQrGateway (the real bank/provider driver comes
        // with P3-32); a store picks one in stores.fiscal_settings.uzqr.driver
        'drivers' => [
            'sandbox' => App\Domain\Pos\QrPayments\UzQr\SandboxUzQrGateway::class,
        ],
        // The merchant name and city the sandbox puts into its EMV payload
        'sandbox_city' => 'TASHKENT',
    ],
];
