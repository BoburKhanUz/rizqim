<?php

/*
| P3-17: bookings, deposits and customer accounts (nasiya). docs/API.md "Bron va nasiya".
*/

return [
    // A booking without an end time holds its room this long (choyxona evenings: 4–6 hours, research report R14).
    // A branch may override it with branches.pos_settings.booking_slot_minutes.
    'default_slot_minutes' => (int) env('POS_BOOKING_SLOT_MINUTES', 300),

    // Longest booking and longest calendar request.
    'max_slot_minutes' => 24 * 60,
    'calendar_max_days' => 31,

    // The owner hears on Telegram about every new credit (nasiya) of at least this much (so'm); 0: every one.
    'credit_alert_threshold' => (string) env('POS_CREDIT_ALERT_THRESHOLD', '500000'),

    // Fiscal rules for deposits that the research could not confirm (report §5, T3). A store overrides each with the
    // same key in stores.fiscal_settings (bool). Check both with the accountant before a real pilot.
    //  - refunding a deposit: no receipt by default (the accountant's advice: a refund act and the original advance
    //    receipt); true issues a REFUND receipt against the ADVANCE receipt;
    //  - retaining a deposit (cancellation, no-show): a SALE receipt for the booking service (charge `booking_fee`,
    //    MXIK 10202002003000002 by default) paid from the advance; false issues none.
    'deposit_refund_receipt' => (bool) env('POS_DEPOSIT_REFUND_RECEIPT', false),
    'retained_deposit_receipt' => (bool) env('POS_RETAINED_DEPOSIT_RECEIPT', true),
];
