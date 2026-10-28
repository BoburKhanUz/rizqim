<?php

/*
| P4-23: the Moliya section of the Boshqaruv markazi (docs/API.md "Moliya").
*/

return [
    // Owner decision 28.10.2026: a manual adjustment or a payout of this much (so'm) or more needs a second member's
    // approval (four eyes: the one who asked or approved first never completes it, a superadmin neither).
    'four_eyes_threshold' => env('FINANCE_FOUR_EYES_THRESHOLD', '1000000.00'),
    // Review W2: adjustments applied on one member's word in this many hours — by the same member, or on the same
    // store — count with a new one against the threshold, so splitting a sum never avoids the second approval.
    'four_eyes_window_hours' => 24,

    // An adjustment's optional document (a bank statement, an act): private disk, never public.
    'adjustments' => [
        'disk' => env('FINANCE_ADJUSTMENT_DISK', 'local'),
        'directory' => 'finance/adjustments',
        'max_kb' => 10240,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    ],

    // Top-ups still pending this long after they started are shown as stuck (the Bugun source uses the same).
    'topup_stuck_minutes' => 30,
];
