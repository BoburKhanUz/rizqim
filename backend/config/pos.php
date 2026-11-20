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
];
