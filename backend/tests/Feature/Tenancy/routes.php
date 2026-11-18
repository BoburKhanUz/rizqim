<?php

/*
 * M4 stage 1: the manifest of tenant routes (docs/scale/M4_DESIGN.md 3.1), read by CrossTenantRouteTest and
 * ListLeakTest.
 *
 * routes: "METHOD uri" => [
 *   'params' => fn (array $s) => [...]   the path parameters naming side $s's records (TenancyWorld keys),
 *   'body'   => fn (array $s) => [...]   the JSON body (the query string of a GET); ids from $s, everything else the same
 *                                        for both sides, so that A's call and B's call differ in ids only,
 *   'ok'     => int | [actor => int|list<int>]   what A's own call answers per actor (owner, manager, cashier; device
 *                                        for a route behind auth:device): 2xx, or a 409/422 about the record's state
 *                                        or the body that only a found record can give. 401/403/404 here means the
 *                                        actor is refused for its own store too (permission): that actor's B call is
 *                                        still checked but proves nothing about the store,
 *   'device' => 'till' | 'kds' | 'display'   which of A's terminals calls a device route (default till),
 *   'self_refused' => [actor, ...]       actors whose `ok` is a refusal of A's own call that is not about the store
 *                                        (a 409/422 such as "only on a till"): counted like a 403,
 *   'filter' => true                     the ids are a list's filter: B's call may answer 2xx with A's own (or no) rows,
 *   'refused' => fn (array $json) => bool   a batch answering 200 refuses per item: whether B's call was refused,
 * ]
 * not_tenant: routes outside the tenant prefixes (or under them but not tenant data), with the reason.
 * known: findings handed to the fixing agent; CrossTenantRouteTest::test_known_findings_are_fixed fails until each holds.
 */

use Illuminate\Database\Eloquent\Model;

$v = fn (Model $model): int => (int) $model->fresh()->version;
$o = fn (int|array $owner, int|array|null $manager = null, int|array|null $cashier = null): array => [
    'owner' => $owner,
    'manager' => $manager ?? $owner,
    'cashier' => $cashier ?? $owner,
];
$branch = fn (array $s) => ['branch_id' => $s['branch']->id];

$pos = [
    'POST api/v1/pos/approvals' => [
        'body' => fn (array $s) => ['permission' => 'orders.void_item', 'user_id' => $s['manager']->id, 'pin' => '5926'],
        'self_refused' => ['owner', 'manager'], 'ok' => $o(422, 422, 201),
    ],
    'GET api/v1/pos/banquets' => ['body' => $branch, 'ok' => $o(200, 200, 200)],
    'POST api/v1/pos/banquets' => [
        'body' => fn (array $s) => [
            'branch_id' => $s['branch']->id, 'event_date' => '2026-09-12', 'slot' => 'evening', 'hall_ids' => [$s['banquet_hall']->id],
            'event_type' => 'toy', 'customer_name' => 'Yangi mijoz', 'guests_guaranteed' => 80, 'banquet_package_id' => $s['banquet_package']->id,
        ],
        'ok' => $o(201, 201, 403),
    ],
    'GET api/v1/pos/banquets/calendar' => ['body' => fn (array $s) => ['branch_id' => $s['branch']->id, 'from' => '2026-08-01', 'to' => '2026-08-31'], 'ok' => $o(200)],
    'GET api/v1/pos/banquets/catalog' => ['body' => $branch, 'ok' => $o(200)],
    'GET api/v1/pos/banquets/osh-calculator' => ['body' => fn (array $s) => ['persons' => 100, 'branch_id' => $s['branch']->id], 'ok' => $o(200)],
    'GET api/v1/pos/banquets/shopping-list' => ['body' => fn (array $s) => ['branch_id' => $s['branch']->id, 'from' => '2026-08-01', 'to' => '2026-08-31'], 'ok' => $o(200)],
    'GET api/v1/pos/banquets/shopping-list/export' => ['body' => fn (array $s) => ['branch_id' => $s['branch']->id, 'from' => '2026-08-01', 'to' => '2026-08-31'], 'ok' => $o(200)],
    'GET api/v1/pos/banquets/{uuid}' => ['params' => fn (array $s) => ['uuid' => $s['banquet']->uuid], 'ok' => $o(200)],
    'PATCH api/v1/pos/banquets/{uuid}' => [
        'params' => fn (array $s) => ['uuid' => $s['banquet']->uuid],
        'body' => fn (array $s) => ['guests_guaranteed' => 120, 'version' => $v($s['banquet'])],
        'ok' => $o(200, 200, 403),
    ],
    'GET api/v1/pos/banquets/{uuid}/cancellation' => ['params' => fn (array $s) => ['uuid' => $s['banquet']->uuid], 'ok' => $o(200, 200, 403)],
    'POST api/v1/pos/banquets/{uuid}/cancellation' => [
        'params' => fn (array $s) => ['uuid' => $s['banquet']->uuid],
        'body' => fn (array $s) => ['reason' => 'Mijoz voz kechdi'],
        'ok' => $o(200, 200, 403),
    ],
    'GET api/v1/pos/banquets/{uuid}/contract' => ['params' => fn (array $s) => ['uuid' => $s['banquet']->uuid], 'ok' => $o(200, 200, 403)],
    'POST api/v1/pos/banquets/{uuid}/contract/number' => ['params' => fn (array $s) => ['uuid' => $s['banquet']->uuid], 'ok' => $o(200, 200, 403)],
    'POST api/v1/pos/banquets/{uuid}/freeze' => [
        'params' => fn (array $s) => ['uuid' => $s['banquet']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['banquet'])],
        'ok' => $o(200, 200, 403),
    ],
    'PATCH api/v1/pos/banquets/{uuid}/guests' => [
        'params' => fn (array $s) => ['uuid' => $s['banquet']->uuid],
        'body' => fn (array $s) => ['guests_actual' => 90, 'version' => $v($s['banquet'])],
        'ok' => $o(409, 409, 403),
    ],
    'GET api/v1/pos/banquets/{uuid}/payments' => ['params' => fn (array $s) => ['uuid' => $s['banquet']->uuid], 'ok' => $o(200)],
    'PUT api/v1/pos/banquets/{uuid}/payments' => [
        'params' => fn (array $s) => ['uuid' => $s['banquet']->uuid],
        'body' => fn (array $s) => ['rows' => [
            ['uuid' => $s['banquet_row']->uuid, 'kind' => 'deposit', 'due_date' => '2026-06-16', 'amount' => '5000000'],
            ['kind' => 'final', 'due_date' => '2026-08-19', 'amount' => '14000000'],
        ]],
        'ok' => $o(200, 200, 403),
    ],
    'DELETE api/v1/pos/banquets/{uuid}/payments/transfers/{entry}' => [
        'params' => fn (array $s) => ['uuid' => $s['banquet']->uuid, 'entry' => $s['banquet_entry']->uuid],
        'body' => fn (array $s) => ['reason' => 'Xato kiritildi'],
        'ok' => $o(200, 200, 403),
    ],
    'POST api/v1/pos/banquets/{uuid}/payments/{row}/bank-transfer' => [
        'params' => fn (array $s) => ['uuid' => $s['banquet']->uuid, 'row' => $s['banquet_row']->uuid],
        'body' => fn (array $s) => ['amount' => '1000000', 'reference' => 'PP-7'],
        'ok' => $o(201, 201, 403),
    ],
    'POST api/v1/pos/banquets/{uuid}/payments/{row}/pay' => [
        'params' => fn (array $s) => ['uuid' => $s['banquet']->uuid, 'row' => $s['banquet_row']->uuid],
        'body' => fn (array $s) => ['tender' => 'cash'],
        'ok' => $o(409, 409, 201),
    ],
    'GET api/v1/pos/banquets/{uuid}/production-sheet' => ['params' => fn (array $s) => ['uuid' => $s['banquet']->uuid], 'ok' => $o(200)],
    'GET api/v1/pos/banquets/{uuid}/production-sheet/export' => ['params' => fn (array $s) => ['uuid' => $s['banquet']->uuid], 'ok' => $o(200)],
    'POST api/v1/pos/banquets/{uuid}/settle' => ['params' => fn (array $s) => ['uuid' => $s['banquet']->uuid], 'ok' => $o(409)],
    'GET api/v1/pos/banquets/{uuid}/settlement' => ['params' => fn (array $s) => ['uuid' => $s['banquet']->uuid], 'ok' => $o(200)],
    'POST api/v1/pos/banquets/{uuid}/settlement/refund-excess' => [
        'params' => fn (array $s) => ['uuid' => $s['banquet']->uuid],
        'body' => fn (array $s) => ['tender' => 'cash'],
        'ok' => $o(404),
    ],
    'PUT api/v1/pos/banquets/{uuid}/staff' => [
        'params' => fn (array $s) => ['uuid' => $s['banquet']->uuid],
        'body' => fn (array $s) => ['user_ids' => [$s['waiter']->id]],
        'ok' => $o(200, 200, 403),
    ],
    'GET api/v1/pos/banquets/{uuid}/staff' => ['params' => fn (array $s) => ['uuid' => $s['banquet']->uuid], 'ok' => $o(200)],
    'POST api/v1/pos/banquets/{uuid}/start' => [
        'params' => fn (array $s) => ['uuid' => $s['banquet']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['banquet'])],
        'ok' => $o(409),
    ],
    'POST api/v1/pos/banquets/{uuid}/unfreeze' => [
        'params' => fn (array $s) => ['uuid' => $s['banquet']->uuid],
        'body' => fn (array $s) => ['reason' => 'Tuzatish', 'version' => $v($s['banquet'])],
        'ok' => $o(409, 409, 403),
    ],
    'GET api/v1/pos/banquets/{uuid}/warnings' => ['params' => fn (array $s) => ['uuid' => $s['banquet']->uuid], 'ok' => $o(200)],

    'GET api/v1/pos/bill-print-requests' => ['ok' => ['device' => 200]],
    'POST api/v1/pos/bill-print-requests/{requestId}/printed' => ['params' => fn (array $s) => ['requestId' => $s['print_request']], 'ok' => ['device' => 200]],
    'POST api/v1/pos/bills/{uuid}/fiscal-retry' => ['params' => fn (array $s) => ['uuid' => $s['paid_bill']->uuid], 'ok' => $o(200)],
    'POST api/v1/pos/bills/{uuid}/payments' => [
        'params' => fn (array $s) => ['uuid' => $s['bill']->uuid],
        'body' => fn (array $s) => ['tenders' => [['tender' => 'cash', 'amount' => (string) $s['bill']->fresh()->total]]],
        'ok' => $o(409, 409, 200),
    ],
    'POST api/v1/pos/bills/{uuid}/print' => ['params' => fn (array $s) => ['uuid' => $s['bill']->uuid], 'ok' => $o(200)],
    'POST api/v1/pos/bills/{uuid}/print-request' => ['params' => fn (array $s) => ['uuid' => $s['bill']->uuid], 'ok' => $o(200)],
    'GET api/v1/pos/bills/{uuid}/qr' => ['params' => fn (array $s) => ['uuid' => $s['bill']->uuid], 'ok' => $o(200)],
    'POST api/v1/pos/bills/{uuid}/qr' => [
        'params' => fn (array $s) => ['uuid' => $s['bill']->uuid],
        'body' => fn (array $s) => ['provider' => 'click'],
        'ok' => $o(409, 409, 201),
    ],

    'GET api/v1/pos/bookings' => ['body' => $branch, 'ok' => $o(200)],
    'POST api/v1/pos/bookings' => [
        'body' => fn (array $s) => ['branch_id' => $s['branch']->id, 'dining_table_id' => $s['free_table']->id, 'starts_at' => '2026-06-21T19:00:00+05:00', 'contact_name' => 'Yangi mehmon'],
        'ok' => $o(201),
    ],
    'GET api/v1/pos/bookings/calendar' => ['body' => fn (array $s) => ['branch_id' => $s['branch']->id, 'from' => '2026-06-01', 'to' => '2026-06-30'], 'ok' => $o(200)],
    'GET api/v1/pos/bookings/{uuid}' => ['params' => fn (array $s) => ['uuid' => $s['booking']->uuid], 'ok' => $o(200)],
    'PATCH api/v1/pos/bookings/{uuid}' => [
        'params' => fn (array $s) => ['uuid' => $s['booking']->uuid],
        'body' => fn (array $s) => ['guests_planned' => 7, 'version' => $v($s['booking'])],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/bookings/{uuid}/arrive' => [
        'params' => fn (array $s) => ['uuid' => $s['booking']->uuid],
        'body' => fn (array $s) => ['guests' => 6, 'dining_table_id' => $s['free_table']->id, 'version' => $v($s['booking'])],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/bookings/{uuid}/cancel' => [
        'params' => fn (array $s) => ['uuid' => $s['booking']->uuid],
        'body' => fn (array $s) => ['reason' => 'Mehmon kelmaydi', 'version' => $v($s['booking'])],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/bookings/{uuid}/confirm' => [
        'params' => fn (array $s) => ['uuid' => $s['booking']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['booking'])],
        'ok' => $o(422),
    ],
    'GET api/v1/pos/bookings/{uuid}/confirmation' => ['params' => fn (array $s) => ['uuid' => $s['booking']->uuid], 'ok' => $o(200)],
    'POST api/v1/pos/bookings/{uuid}/confirmation/send' => [
        'params' => fn (array $s) => ['uuid' => $s['booking']->uuid],
        'body' => fn (array $s) => ['channel' => 'sms'],
        'ok' => $o(422),
    ],
    'POST api/v1/pos/bookings/{uuid}/deposits' => [
        'params' => fn (array $s) => ['uuid' => $s['booking']->uuid],
        'body' => fn (array $s) => ['amount' => '20000', 'tender' => 'cash'],
        'ok' => $o(409, 409, 201),
    ],
    'POST api/v1/pos/bookings/{uuid}/deposits/{payment}/refund' => [
        'params' => fn (array $s) => ['uuid' => $s['booking']->uuid, 'payment' => $s['deposit']->uuid],
        'body' => fn (array $s) => ['reason' => 'Qaytarildi', 'approval_token' => $s['approval']['pos.refund']],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/bookings/{uuid}/deposits/{payment}/retain' => [
        'params' => fn (array $s) => ['uuid' => $s['booking']->uuid, 'payment' => $s['deposit']->uuid],
        'body' => fn (array $s) => ['reason' => 'Ushlab qolindi'],
        'ok' => $o(422),
    ],
    'POST api/v1/pos/bookings/{uuid}/items' => [
        'params' => fn (array $s) => ['uuid' => $s['booking']->uuid],
        'body' => fn (array $s) => ['product_variant_id' => $s['variant']->id, 'quantity' => '3'],
        'ok' => $o(201),
    ],
    'PATCH api/v1/pos/bookings/{uuid}/items/{item}' => [
        'params' => fn (array $s) => ['uuid' => $s['booking']->uuid, 'item' => $s['booking_item']->id],
        'body' => fn (array $s) => ['quantity' => '4'],
        'ok' => $o(200),
    ],
    'DELETE api/v1/pos/bookings/{uuid}/items/{item}' => [
        'params' => fn (array $s) => ['uuid' => $s['booking']->uuid, 'item' => $s['booking_item']->id],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/bookings/{uuid}/no-show' => [
        'params' => fn (array $s) => ['uuid' => $s['booking']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['booking'])],
        'ok' => $o(422),
    ],

    'GET api/v1/pos/bootstrap' => ['body' => $branch, 'ok' => $o(200)],
    'GET api/v1/pos/catalog' => ['body' => fn (array $s) => ['store_id' => $s['store']->id, 'branch_id' => $s['branch']->id], 'ok' => $o(200)],
    'GET api/v1/pos/clock' => ['ok' => $o(200)],
    'POST api/v1/pos/clock/in' => ['ok' => $o(403, 403, 201)],
    'POST api/v1/pos/clock/out' => ['ok' => $o(403, 403, 409)],

    'GET api/v1/pos/customer-accounts' => ['ok' => $o(200)],
    'POST api/v1/pos/customer-accounts' => [
        'body' => fn (array $s) => ['name' => 'Yangi nasiya', 'branch_id' => $s['branch']->id, 'approval_token' => $s['approval']['customer_accounts.manage']],
        'ok' => $o(201),
    ],
    'GET api/v1/pos/customer-accounts/debtors' => ['ok' => $o(200)],
    'GET api/v1/pos/customer-accounts/members/lookup' => ['body' => fn (array $s) => ['code' => $s['member']->code], 'ok' => $o(200)],
    'GET api/v1/pos/customer-accounts/{uuid}' => ['params' => fn (array $s) => ['uuid' => $s['account']->uuid], 'ok' => $o(200)],
    'PATCH api/v1/pos/customer-accounts/{uuid}' => [
        'params' => fn (array $s) => ['uuid' => $s['account']->uuid],
        'body' => fn (array $s) => ['note' => 'Yangilandi', 'approval_token' => $s['approval']['customer_accounts.manage']],
        'ok' => $o(200, 403, 200),
    ],
    'POST api/v1/pos/customer-accounts/{uuid}/adjustments' => [
        'params' => fn (array $s) => ['uuid' => $s['account']->uuid],
        'body' => fn (array $s) => ['amount' => '1000', 'note' => 'Tuzatish'],
        'ok' => $o(201, 403, 403),
    ],
    'GET api/v1/pos/customer-accounts/{uuid}/corporate-statement' => ['params' => fn (array $s) => ['uuid' => $s['corporate']->uuid], 'body' => fn (array $s) => ['month' => '2026-06'], 'ok' => $o(200, 200, 403)],
    'GET api/v1/pos/customer-accounts/{uuid}/corporate-statement/export' => ['params' => fn (array $s) => ['uuid' => $s['corporate']->uuid], 'body' => fn (array $s) => ['month' => '2026-06'], 'ok' => $o(200, 200, 403)],
    'GET api/v1/pos/customer-accounts/{uuid}/members' => ['params' => fn (array $s) => ['uuid' => $s['corporate']->uuid], 'ok' => $o(200, 403, 403)],
    'POST api/v1/pos/customer-accounts/{uuid}/members' => [
        'params' => fn (array $s) => ['uuid' => $s['corporate']->uuid],
        'body' => fn (array $s) => ['name' => 'Yangi xodim'],
        'ok' => $o(201, 403, 403),
    ],
    'POST api/v1/pos/customer-accounts/{uuid}/members/bulk' => [
        'params' => fn (array $s) => ['uuid' => $s['corporate']->uuid],
        'body' => fn (array $s) => ['lines' => "Ali Valiyev, +998901234567"],
        'ok' => $o(201, 403, 403),
    ],
    'PATCH api/v1/pos/customer-accounts/{uuid}/members/{member}' => [
        'params' => fn (array $s) => ['uuid' => $s['corporate']->uuid, 'member' => $s['member']->uuid],
        'body' => fn (array $s) => ['note' => 'Yangilandi'],
        'ok' => $o(200, 403, 403),
    ],
    'POST api/v1/pos/customer-accounts/{uuid}/repayments' => [
        'params' => fn (array $s) => ['uuid' => $s['account']->uuid],
        'body' => fn (array $s) => ['amount' => '1000', 'tender' => 'cash'],
        'ok' => $o(422),
    ],
    'GET api/v1/pos/customer-accounts/{uuid}/statement' => ['params' => fn (array $s) => ['uuid' => $s['account']->uuid], 'ok' => $o(200)],

    'POST api/v1/pos/display/cart' => [
        'body' => fn (array $s) => ['state' => 'cart', 'seq' => 1, 'lines' => [['name' => 'Choy', 'qty' => '1', 'price' => '5000', 'total' => '5000']], 'subtotal' => '5000', 'total' => '5000'],
        'ok' => ['device' => 200],
    ],
    'GET api/v1/pos/display/queue' => ['ok' => ['device' => 200], 'device' => 'display'],
    'GET api/v1/pos/display/state' => ['ok' => ['device' => 200], 'device' => 'display'],
    'GET api/v1/pos/fiscal-receipts/{uuid}' => ['params' => fn (array $s) => ['uuid' => $s['fiscal_receipt']->uuid], 'ok' => $o(200)],
    'GET api/v1/pos/floor' => ['body' => $branch, 'ok' => $o(200)],
    'GET api/v1/pos/lan' => ['ok' => ['device' => 200]],

    'GET api/v1/pos/orders' => ['body' => $branch, 'ok' => $o(200)],
    'POST api/v1/pos/orders' => [
        'body' => fn (array $s) => ['branch_id' => $s['branch']->id, 'channel' => 'pos_hall', 'table_id' => $s['free_table']->id, 'guests_count' => 2,
            'items' => [['variant_id' => $s['tea']->uuid, 'quantity' => 1]]],
        'ok' => $o(201),
    ],
    'GET api/v1/pos/orders/{uuid}' => ['params' => fn (array $s) => ['uuid' => $s['order']->uuid], 'ok' => $o(200)],
    'POST api/v1/pos/orders/{uuid}/accept' => [
        'params' => fn (array $s) => ['uuid' => $s['online_order']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['online_order'])],
        'ok' => $o(200),
    ],
    'GET api/v1/pos/orders/{uuid}/bills' => ['params' => fn (array $s) => ['uuid' => $s['billed_order']->uuid], 'ok' => $o(200)],
    'POST api/v1/pos/orders/{uuid}/bills' => [
        'params' => fn (array $s) => ['uuid' => $s['order2']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['order2']), 'mode' => 'single'],
        'ok' => $o(201),
    ],
    'POST api/v1/pos/orders/{uuid}/call' => ['params' => fn (array $s) => ['uuid' => $s['queue_order']->uuid], 'ok' => $o(422)],
    'POST api/v1/pos/orders/{uuid}/cancel' => [
        'params' => fn (array $s) => ['uuid' => $s['order2']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['order2']), 'reason' => 'Mehmon ketdi', 'approval_token' => $s['approval']['orders.cancel']],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/orders/{uuid}/charges/{charge}/restore' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid, 'charge' => 'service_charge'],
        'body' => fn (array $s) => ['version' => $v($s['order'])],
        'ok' => $o(422),
    ],
    'POST api/v1/pos/orders/{uuid}/charges/{charge}/waive' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid, 'charge' => 'service_charge'],
        'body' => fn (array $s) => ['version' => $v($s['order']), 'reason' => 'Mehmon norozi', 'approval_token' => $s['approval']['orders.discount']],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/orders/{uuid}/close' => ['params' => fn (array $s) => ['uuid' => $s['billed_order']->uuid], 'ok' => $o(409)],
    'POST api/v1/pos/orders/{uuid}/courses/{course}/send' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid, 'course' => 1],
        'body' => fn (array $s) => ['version' => $v($s['order'])],
        'ok' => $o(422),
    ],
    'POST api/v1/pos/orders/{uuid}/discount' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['order']), 'kind' => 'percent', 'value' => 5, 'reason' => 'Doimiy mijoz'],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/orders/{uuid}/guests' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['order']), 'guests_count' => 3],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/orders/{uuid}/handed' => ['params' => fn (array $s) => ['uuid' => $s['queue_order']->uuid], 'ok' => $o(200)],
    'POST api/v1/pos/orders/{uuid}/items/{item}/seat' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid, 'item' => $s['held_item']->id],
        'body' => fn (array $s) => ['version' => $v($s['order']), 'seat_no' => 1],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/orders/{uuid}/items/{item}/served' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid, 'item' => $s['item']->id],
        'body' => fn (array $s) => ['version' => $v($s['order'])],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/orders/{uuid}/items/{item}/void' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid, 'item' => $s['held_item']->id],
        'body' => fn (array $s) => ['version' => $v($s['order']), 'reason' => 'Xato'],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/orders/{uuid}/items/{item}/weight' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid, 'item' => $s['weight_item']->id],
        'body' => fn (array $s) => ['version' => $v($s['order']), 'quantity' => '1.2'],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/orders/{uuid}/merge' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['order']), 'source_uuid' => $s['order2']->uuid, 'source_version' => $v($s['order2'])],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/orders/{uuid}/move' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['order']), 'table_id' => $s['free_table']->id],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/orders/{uuid}/packaging/{rule}/remove' => [
        'params' => fn (array $s) => ['uuid' => $s['paid_order']->uuid, 'rule' => 1],
        'body' => fn (array $s) => ['version' => $v($s['paid_order']), 'reason' => 'Kerak emas'],
        'ok' => $o(422),
    ],
    'POST api/v1/pos/orders/{uuid}/packaging/{rule}/restore' => [
        'params' => fn (array $s) => ['uuid' => $s['paid_order']->uuid, 'rule' => 1],
        'body' => fn (array $s) => ['version' => $v($s['paid_order'])],
        'ok' => $o(422),
    ],
    'PUT api/v1/pos/orders/{uuid}/pickup-time' => [
        'params' => fn (array $s) => ['uuid' => $s['queue_order']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['queue_order']), 'pickup_at' => '2026-06-10T14:00:00+05:00'],
        'ok' => $o(409),
    ],
    'POST api/v1/pos/orders/{uuid}/promo' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['order']), 'code' => 'YOQKOD'],
        'ok' => $o(404),
    ],
    'DELETE api/v1/pos/orders/{uuid}/promo' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['order'])],
        'ok' => $o(422),
    ],
    'POST api/v1/pos/orders/{uuid}/ready' => ['params' => fn (array $s) => ['uuid' => $s['queue_order']->uuid], 'ok' => $o(200)],
    'POST api/v1/pos/orders/{uuid}/reject' => [
        'params' => fn (array $s) => ['uuid' => $s['online_order']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['online_order']), 'reason' => 'Mahsulot tugadi', 'cancel_reason' => 'out_of_stock', 'approval_token' => $s['approval']['orders.cancel']],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/orders/{uuid}/request-bill' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['order'])],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/orders/{uuid}/rounds' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['order']), 'items' => [['variant_id' => $s['tea']->uuid, 'quantity' => 1]]],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/orders/{uuid}/send' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['order'])],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/orders/{uuid}/table-label' => [
        'params' => fn (array $s) => ['uuid' => $s['queue_order']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['queue_order']), 'table_label' => '12'],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/orders/{uuid}/transfer' => [
        'params' => fn (array $s) => ['uuid' => $s['order']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['order']), 'waiter_id' => $s['waiter']->id, 'reason' => 'Smena', 'approval_token' => $s['approval']['orders.transfer']],
        'ok' => $o(200),
    ],
    'GET api/v1/pos/pickup-slots' => ['body' => $branch, 'ok' => $o(200)],
    'GET api/v1/pos/qr/{uuid}' => ['params' => fn (array $s) => ['uuid' => $s['qr']->uuid], 'ok' => $o(200)],
    'DELETE api/v1/pos/qr/{uuid}' => ['params' => fn (array $s) => ['uuid' => $s['qr']->uuid], 'body' => fn (array $s) => ['reason' => 'Bekor'], 'ok' => $o(200)],
    'POST api/v1/pos/qr/{uuid}/simulate-paid' => ['params' => fn (array $s) => ['uuid' => $s['qr']->uuid], 'ok' => $o(409)],
    'GET api/v1/pos/queue' => ['body' => $branch, 'ok' => $o(200)],
    'POST api/v1/pos/queue/blocks' => ['body' => $branch, 'self_refused' => ['owner', 'manager'], 'ok' => $o(422, 422, 200)],
    'POST api/v1/pos/refunds' => [
        'body' => fn (array $s) => ['payment_uuid' => $s['payment']->uuid, 'amount' => '500', 'reason' => 'Qaytarish', 'approval_token' => $s['approval']['pos.refund']],
        'ok' => $o(409, 409, 201),
    ],
    'POST api/v1/pos/shifts' => ['body' => fn (array $s) => ['opening_cash' => '1000'], 'self_refused' => ['owner', 'manager'], 'ok' => $o(422, 422, 409)],
    'GET api/v1/pos/shifts' => ['body' => $branch, 'filter' => true, 'ok' => $o(200)],
    'GET api/v1/pos/shifts/current' => ['body' => $branch, 'filter' => true, 'ok' => $o(200)],
    'GET api/v1/pos/shifts/{uuid}' => ['params' => fn (array $s) => ['uuid' => $s['shift']->uuid], 'ok' => $o(200)],
    'PUT api/v1/pos/shifts/{uuid}/bank-report' => [
        'params' => fn (array $s) => ['uuid' => $s['shift']->uuid],
        'body' => fn (array $s) => ['source' => 'manual', 'bank_total' => '0', 'bank_count' => 0],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/shifts/{uuid}/cash-movements' => [
        'params' => fn (array $s) => ['uuid' => $s['shift']->uuid],
        'body' => fn (array $s) => ['type' => 'cash_in', 'category' => 'other', 'amount' => '1000', 'note' => 'Maydalash uchun', 'approval_token' => $s['approval']['pos.cash_in_out']],
        'ok' => $o(201),
    ],
    'POST api/v1/pos/shifts/{uuid}/close' => [
        'params' => fn (array $s) => ['uuid' => $s['shift']->uuid],
        'body' => fn (array $s) => ['counted_breakdown' => [], 'force' => true, 'reason' => 'Tekshiruv', 'approval_token' => $s['approval']['orders.cancel']],
        'ok' => $o(200),
    ],
    'GET api/v1/pos/shifts/{uuid}/x-report' => ['params' => fn (array $s) => ['uuid' => $s['shift']->uuid], 'ok' => $o(200)],
    'POST api/v1/pos/sync' => [
        'body' => fn (array $s) => ['operations' => [[
            'op_id' => '6f1d2c3b-4a59-4e1f-9a7b-0c1d2e3f4a5b', 'seq' => 2, 'type' => 'order.open', 'staff_id' => $s['cashier']->id, 'occurred_at' => '2026-06-10T06:55:00Z',
            'payload' => ['order' => '7a2e3d4c-5b6a-4f20-8b9c-1d2e3f4a5b6c', 'local_number' => 'K1-001', 'channel' => 'pos_hall', 'table_id' => $s['free_table']->id, 'guests_count' => 2,
                'items' => [['line_id' => '8b3f4e5d-6c7b-4031-9cad-2e3f4a5b6c7d', 'variant_id' => $s['tea']->uuid, 'quantity' => '1', 'unit_price' => '5000']]],
        ]]],
        'refused' => fn (array $json) => collect($json['data']['results'] ?? [])->every(fn ($result) => !in_array($result['status'] ?? null, ['applied', 'duplicate'], true)),
        'ok' => ['device' => 200],
    ],
    'GET api/v1/pos/sync/errors' => ['body' => $branch, 'ok' => $o(200)],
    'POST api/v1/pos/sync/errors/{opId}/resolve' => [
        'params' => fn (array $s) => ['opId' => $s['sync_error']],
        'body' => fn (array $s) => ['resolution' => 'dismissed', 'note' => 'Tekshirildi'],
        'ok' => $o(200, 200, 403),
    ],
    'GET api/v1/pos/sync/status' => ['ok' => ['device' => 200]],
    'PUT api/v1/pos/tables/{id}/state' => [
        'params' => fn (array $s) => ['id' => $s['free_table']->id],
        'body' => fn (array $s) => ['state' => 'cleaning'],
        'ok' => $o(200),
    ],
    'GET api/v1/pos/tips' => ['body' => fn (array $s) => ['shift_uuid' => $s['shift']->uuid], 'ok' => $o(200)],
    'POST api/v1/pos/tips/{uuid}/corrections' => [
        'params' => fn (array $s) => ['uuid' => $s['tip']->uuid],
        'body' => fn (array $s) => ['amount' => '1000', 'mode' => 'personal', 'staff_id' => $s['waiter']->id, 'reason' => 'Xato', 'approval_token' => $s['approval']['tips.manage']],
        'ok' => $o(200),
    ],
    'POST api/v1/pos/vouchers/sell' => [
        'body' => fn (array $s) => ['face_value' => '50000', 'tender' => 'cash', 'branch_id' => $s['branch']->id],
        'ok' => $o(409, 409, 201),
    ],
    'GET api/v1/pos/vouchers/{code}' => ['params' => fn (array $s) => ['code' => $s['voucher_code']], 'ok' => $o(200)],
    'GET api/v1/pos/waitlist' => ['body' => $branch, 'ok' => $o(200, 200, 403)],
    'POST api/v1/pos/waitlist' => [
        'body' => fn (array $s) => ['guest_name' => 'Yangi navbat', 'party_size' => 2, 'branch_id' => $s['branch']->id],
        'ok' => $o(201, 201, 403),
    ],
    'PATCH api/v1/pos/waitlist/{uuid}' => [
        'params' => fn (array $s) => ['uuid' => $s['waitlist']->uuid],
        'body' => fn (array $s) => ['party_size' => 4, 'version' => $v($s['waitlist'])],
        'ok' => $o(200, 200, 403),
    ],
    'POST api/v1/pos/waitlist/{uuid}/cancel' => [
        'params' => fn (array $s) => ['uuid' => $s['waitlist']->uuid],
        'body' => fn (array $s) => ['reason' => 'Ketdi', 'version' => $v($s['waitlist'])],
        'ok' => $o(200, 200, 403),
    ],
    'POST api/v1/pos/waitlist/{uuid}/no-show' => [
        'params' => fn (array $s) => ['uuid' => $s['waitlist']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['waitlist'])],
        'ok' => $o(200, 200, 403),
    ],
    'POST api/v1/pos/waitlist/{uuid}/notify' => [
        'params' => fn (array $s) => ['uuid' => $s['waitlist']->uuid],
        'body' => fn (array $s) => ['version' => $v($s['waitlist'])],
        'ok' => $o(200, 200, 403),
    ],
    'POST api/v1/pos/waitlist/{uuid}/seat' => [
        'params' => fn (array $s) => ['uuid' => $s['waitlist']->uuid],
        'body' => fn (array $s) => ['dining_table_id' => $s['free_table']->id, 'version' => $v($s['waitlist'])],
        'ok' => $o(200, 200, 403),
    ],
];

$notTenant = [
    'api/v1/admin/*' => 'RIZQIM team: the separate auth:admin guard, no store, staff or terminal token passes it (routes/api_admin.php)',
    'POST api/v1/admin/auth/login' => 'admin sign-in step before the admin guard: no tenant data',
    'POST api/v1/admin/auth/2fa/setup' => 'admin sign-in step before the admin guard: no tenant data',
    'POST api/v1/admin/auth/2fa/confirm' => 'admin sign-in step before the admin guard: no tenant data',
    'POST api/v1/admin/auth/2fa/verify' => 'admin sign-in step before the admin guard: no tenant data',
    'POST api/v1/admin/auth/invite/accept' => 'admin sign-in step before the admin guard: no tenant data',
    'POST api/v1/devices/pair' => 'unauthenticated: the one-time pairing code is the credential and names its own store',
    'GET /' => 'web root',
    'GET up' => 'health probe',
    'GET health' => 'health probe',
    'GET api/v1/health' => 'health probe',
    'GET api/v1/realtime' => 'realtime status, no store data',
    'GET sanctum/csrf-cookie' => 'Sanctum CSRF cookie',
    'GET docs/api' => 'API docs (AuthorizeApiDocs)',
    'GET docs/api.json' => 'API docs (AuthorizeApiDocs)',
    'GET storage/{path}' => 'local disk files: Laravel ServeFile requires a valid signature (M4_DESIGN section 2)',
    'PUT storage/{path}' => 'local disk files: Laravel ServeFile requires a valid signature (M4_DESIGN section 2)',
    'GET api/broadcasting/auth' => 'realtime channel auth, checked per channel in routes/channels.php (M4_DESIGN section 2)',
    'POST api/broadcasting/auth' => 'realtime channel auth, checked per channel in routes/channels.php (M4_DESIGN section 2)',
    'POST api/v1/payments/click/webhook' => 'provider webhook: signed with the order\'s own merchant key, no ambient tenant',
    'POST api/v1/payments/payme/webhook' => 'provider webhook: signed with the platform or store cashbox key, no ambient tenant',
    'POST api/v1/telegram/webhook' => 'Telegram webhook: secret token, staff of the order\'s store checked per callback',
    'POST api/v1/auth/phone/select-account' => 'sign-in',
    'POST api/v1/auth/phone/send-code' => 'sign-in',
    'POST api/v1/auth/phone/verify' => 'sign-in',
    'POST api/v1/auth/refresh' => 'sign-in: the refresh token names its user',
    'POST api/v1/auth/telegram' => 'sign-in',
    'POST api/v1/auth/logout' => 'the caller\'s own token',
    'POST api/v1/auth/logout-all' => 'the caller\'s own tokens',
    'GET api/v1/user' => 'the caller\'s own profile',
    'POST api/v1/user/profile' => 'the caller\'s own profile',
    'GET api/v1/me/permissions' => 'the caller\'s own permissions',
    'GET api/v1/me/notification-preferences' => 'user-owned (RevisionMap USER)',
    'PUT api/v1/me/notification-preferences' => 'user-owned (RevisionMap USER)',
    'POST api/v1/phone/send-code' => 'the caller\'s own phone',
    'POST api/v1/phone/verify-code' => 'the caller\'s own phone',
    'GET api/v1/addresses' => 'customer: user-owned addresses',
    'POST api/v1/addresses' => 'customer: user-owned addresses',
    'PUT api/v1/addresses/{id}' => 'customer: user-owned addresses (AddressController user_id check)',
    'DELETE api/v1/addresses/{id}' => 'customer: user-owned addresses (AddressController user_id check)',
    'GET api/v1/catalog' => 'marketplace: public by store',
    'GET api/v1/catalog/availability' => 'marketplace: public by store',
    'GET api/v1/stores' => 'marketplace: public by store',
    'GET api/v1/stores/{store}/zones' => 'marketplace: public delivery zones of a store',
    'GET api/v1/stores/{store}/branches/{branch}/pickup-slots' => 'marketplace: public pickup slots of a store',
    'GET api/v1/delivery/quote' => 'marketplace: public delivery quote',
    'GET api/v1/locations' => 'platform reference data',
    'GET api/v1/features' => 'platform feature flags',
    'GET api/v1/geocode/reverse' => 'platform geocoder',
    'GET api/v1/geocode/search' => 'platform geocoder',
    'POST api/v1/store-applications' => 'public store application form',
    'POST api/v1/promo/validate' => 'customer checkout: PromoCodeService::isValidForStore',
    'GET api/v1/orders' => 'customer and courier order lists (also scanned for B by ListLeakTest)',
    'POST api/v1/orders' => 'customer checkout: CreateOrderAction checks store, branch and variants',
    'POST api/v1/orders/{uuid}/cancel' => 'customer: OrderController user_id check',
    'GET api/v1/notifications' => 'user-owned notifications (also scanned for B by ListLeakTest)',
    'POST api/v1/notifications/read' => 'user-owned notifications',
    'GET api/v1/complaints' => 'customer complaints (also scanned for B by ListLeakTest)',
    'POST api/v1/complaints' => 'customer: H2, agent F (ComplaintController)',
    'POST api/v1/complaints/{id}/escalate' => 'customer: H2, agent F (ComplaintController)',
    'POST api/v1/complaints/{id}/reply' => 'customer: H2, agent F (ComplaintController)',
    'POST api/v1/stores/{id}/rate' => 'customer: H1, agent F (StoreRatingController)',
];

return [
    'not_tenant' => $notTenant,
    'known' => [
        'POST api/v1/pos/sync' => 'T-1: an operation naming another store\'s staff_id is stored on A\'s pos_sync_operations row (PosSyncApplier.php:123, before the staff check at :139) and GET /pos/sync/errors shows that user\'s name (PosSyncPresenter.php:52)',
    ],
    'routes' => $pos,
];
