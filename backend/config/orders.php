<?php

return [

    // Cash payment is blocked once `threshold` cash pickup orders within `window_days` (0 = no limit) were
    // cancelled by the customer or after the store accepted them. Store refusals never count; threshold 0 disables.
    'cash_pickup_ban' => [
        'window_days' => (int) env('ORDERS_CASH_PICKUP_BAN_WINDOW_DAYS', 30),
        'threshold' => (int) env('ORDERS_CASH_PICKUP_BAN_THRESHOLD', 1),
    ],

    // Online orders still new and unpaid this many minutes after checkout are cancelled, giving back their stock
    // and promo code use (App\Jobs\CancelUnpaidOnlineOrdersJob, every minute). 0 disables.
    'unpaid_online_ttl_minutes' => (int) env('ORDERS_UNPAID_ONLINE_TTL_MINUTES', 30),

    // Paid before the store sees the order (cash is paid on handover, keshbek at checkout).
    'online_payment_methods' => ['click', 'payme', 'uzum', 'paynet'],

    // Online methods with no payment webhook yet, so nothing could ever mark such an order paid: refused at checkout
    // and shown as {enabled: false, available: false}; stores' saved settings for them are kept. They stay in the list
    // above so unpaid orders placed with them earlier still expire.
    'unavailable_payment_methods' => ['uzum', 'paynet'],

    // A delivered order whose ledger booking (commission, settlement, referral reward) failed is booked again every
    // 10 minutes (App\Jobs\RetryFailedSettlementsJob) until it succeeds or this many bookings failed, the delivery's own
    // included; then it is left for a superadmin, with a louder alert.
    'settlement_retry_max_attempts' => (int) env('ORDERS_SETTLEMENT_RETRY_MAX_ATTEMPTS', 12),

    'reports' => [
        // Queue the CSV report exports (App\Jobs\SendOrderReport) go to, served by a worker of their own so a long
        // report never holds up Telegram alerts on the default queue
        'queue' => env('ORDERS_REPORTS_QUEUE', 'reports'),
        // Longest custom period (days, both ends included) a report may cover
        'max_custom_days' => 93,
    ],

];
