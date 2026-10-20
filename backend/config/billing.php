<?php

/*
| P1-23: tariff plans and monthly subscription invoices (docs/BILLING.md).
*/

return [
    // Billing months, invoice periods and the scheduled run follow this timezone
    'timezone' => 'Asia/Tashkent',

    // An invoice is due this many days after its month ends; an unpaid one past it makes the subscription past_due
    'invoice_due_days' => 10,

    // Invoice numbers: {prefix}-{YYYYMM}-{store id, 6 digits}, e.g. OB-202610-000042 (one invoice per store and month)
    'invoice_prefix' => 'OB',
];
