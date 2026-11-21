<?php

/*
| POS switches that are not a store's own setting. docs/API.md "Imzolangan navbat", docs/scale/M5_DESIGN.md §4.
*/

return [
    // Wave M5 S3, the kill switch of the signed offline queue. On (the default): GET /pos/bootstrap says
    // `offline.signing: true` and a till that holds a key signs what it queues. Off (POS_SYNC_SIGNING=false, then
    // restart the workers): bootstrap says `signing: false`, tills queue unsigned as before S3, and no store is
    // treated as enforcing signatures (every sale would be flagged). What still arrives signed is checked and
    // recorded all the same. For an emergency only: a signing bug in a released app.
    'sync_signing' => (bool) env('POS_SYNC_SIGNING', true),

    'releases' => [
        // Wave M5 S5, owner answer 8: days from declaring a version the minimum to the red notice and the owner's
        // message (PosAppReleases::graceDays). The till is never stopped.
        'grace_days' => (int) env('POS_RELEASE_GRACE_DAYS', 14),
        // GET /pos/releases (public): requests per minute per address
        'manifest_per_minute' => (int) env('POS_RELEASE_MANIFEST_PER_MINUTE', 30),
    ],
];
