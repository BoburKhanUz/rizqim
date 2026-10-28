<?php

/*
|--------------------------------------------------------------------------
| Boshqaruv markazi → Mijozlar va yordam (P4-31, docs/API.md "Mijozlar va yordam")
|--------------------------------------------------------------------------
|
| complaint_waiting_hours: a complaint still new or in progress on the support
| side (and not answered by the store) for longer than this shows in
| "Bugun → Diqqat talab qiladi" and in the inbox's "Kutayotgan" filter.
| suspicious: the thresholds of Shubhali holatlar (App\Services\Admin\Customers\SuspiciousCases).
|
*/

return [
    'complaint_waiting_hours' => (int) env('SUPPORT_COMPLAINT_WAITING_HOURS', 4),

    'suspicious' => [
        // A customer with at least this many cancelled orders (by them, at their request, not picked up) in the window
        'cancellations_days' => 7,
        'cancellations_min' => 3,
        // One promo code used from at least this many accounts sharing a phone number or a Telegram account
        'promo_days' => 30,
        'promo_min_accounts' => 2,
        // Refund requests (pending or approved) adding up to factor × the average request and at least the minimum
        'refund_days' => 30,
        'refund_factor' => 3,
        'refund_min_amount' => '100000',
        // Rows per signal
        'limit' => 50,
    ],
];
