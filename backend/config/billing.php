<?php

/*
| P1-23: tariff plans and monthly subscription invoices (docs/BILLING.md).
| P4-21: Billing v2 — the Start / Asosiy / Pro catalog, yearly periods, entitlements (plan features), commission by
| channel, the 50% discount rule, dunning and the subscription part of the debt.
*/

return [
    // Billing months, invoice periods and the scheduled run follow this timezone
    'timezone' => 'Asia/Tashkent',

    // An invoice is due this many days after its month ends; an unpaid one past it makes the subscription past_due
    'invoice_due_days' => 10,

    // Invoice numbers: {prefix}-{YYYYMM}-{store id, 6 digits}, e.g. OB-202610-000042 (one invoice per store and month)
    'invoice_prefix' => 'OB',

    // P4-21: a yearly invoice (paid in advance for 12 billing months): OBY-202611-000042; an immediate upgrade of a
    // yearly subscription: OBU-202611-000042-1 (the last part counts the upgrades of that month)
    'yearly_invoice_prefix' => 'OBY',
    'upgrade_invoice_prefix' => 'OBU',

    /*
    | P4-21: the v2 tariff catalog (docs/BILLING.md, owner decision 24.10.2026). Seeded into tariff_plans (version 2) by
    | the 2026_11_04_100100 migration; afterwards the plans are edited through the admin API (PUT /admin/billing/plans/
    | {code}), so these values are only the starting point. Money is in so'm, as decimal strings. `features` are
    | App\Enums\PlanFeature values on top of the always-on base (PlanFeature::base()).
    */
    'catalog' => [
        'start' => [
            'name' => 'Start',
            'sort_order' => 10,
            'monthly_fee' => '149000.00',
            'yearly_fee' => '1490000.00',
            'included_pos' => 1,
            'included_kds' => 0,
            'extra_pos_fee' => '59000.00',
            'extra_kds_fee' => '29000.00',
            'trial_days' => 14,
            'features' => [],
            'description' => "Zal, xona puli, choy-non, smena va Z-hisobot, RIZQIM buyurtmalari; 1 kassa.",
        ],
        'asosiy' => [
            'name' => 'Asosiy',
            'sort_order' => 20,
            'monthly_fee' => '249000.00',
            'yearly_fee' => '2490000.00',
            'included_pos' => 1,
            'included_kds' => 1,
            'extra_pos_fee' => '59000.00',
            'extra_kds_fee' => '29000.00',
            'trial_days' => 14,
            'features' => ['bookings', 'deposits', 'customer_accounts', 'kds', 'service_charge'],
            'description' => "Start + bron va bo'nak, nasiya, oshxona ekrani (KDS), xizmat haqi; 2 terminal (1 kassa, 1 KDS).",
        ],
        'pro' => [
            'name' => 'Pro',
            'sort_order' => 30,
            'monthly_fee' => '449000.00',
            'yearly_fee' => '4490000.00',
            'included_pos' => 2,
            'included_kds' => 1,
            'extra_pos_fee' => '59000.00',
            'extra_kds_fee' => '29000.00',
            'trial_days' => 14,
            'features' => ['bookings', 'deposits', 'customer_accounts', 'kds', 'service_charge', 'multi_branch_reports', 'aggregator_orders', 'banquet'],
            'description' => "Asosiy + filiallar hisoboti, keyin Yandex/Uzum buyurtmalari va banket; 3 terminal (2 kassa, 1 KDS).",
        ],
    ],

    // A yearly period bills this many months' base fee for 12 months (owner decision: 12 months for the price of 10)
    'yearly_months_charged' => 10,

    /*
    | A store with no subscription and none in the past on a v2 plan (every store before `billing:migrate-plans`, and
    | stores that only use the marketplace) keeps every feature, as before Billing v2. Set to false once every POS store
    | is on a v2 plan: such stores then get only the base features.
    */
    'unsubscribed_keeps_all_features' => true,

    /*
    | P4-21: RIZQIM's commission on an order, by channel, in percent (owner decision 24.10.2026). A store may get its own
    | rate per channel (admin, audited: stores.commission_overrides). docs/BILLING.md, "Komissiya".
    */
    'commission' => [
        'rizqim_courier' => '15.00',  // delivered by a RIZQIM fleet courier
        'own_delivery' => '10.00',    // delivered by the store's own courier
        'pickup' => '5.00',           // the customer picks it up
        'pos' => '0.00',              // the hall and takeaway at the store's own till
        'aggregator' => '0.00',       // Yandex Eats, Uzum Tezkor: the aggregator takes its own commission
    ],

    // 50% off the monthly base fee of a month when the store's commission in the month before it was over the threshold
    'commission_discount' => [
        'threshold' => '1000000.00',
        'percent' => '50.00',
    ],

    /*
    | Dunning (owner decision 28.10.2026): reminders on these days after the due date (Telegram to the owner and the
    | store's admin chat, and the partner web notice), then from the day after `lock_after_days` the subscription is
    | locked: settings and features outside the plan close. The till never stops taking payments or printing receipts.
    */
    'dunning' => [
        'reminder_days' => [1, 3, 7],
        'lock_after_days' => 7,
        // Local time the daily reminder run starts (not at night)
        'run_at' => '10:05',
    ],

    // The most an admin may extend a trial by, in days from today
    'max_trial_extension_days' => 90,
];
