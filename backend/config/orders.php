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

];
