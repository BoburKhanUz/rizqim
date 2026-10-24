<?php

namespace App\Http\Controllers\Api\V1\PosHall;

use App\Domain\Pos\Billing\BillStatus;
use App\Domain\Pos\Orders\PosCharge;
use App\Domain\Pos\Orders\PosLineBuilder;
use App\Domain\Pos\Orders\PosOnlineOrders;
use App\Domain\Pos\Orders\PosOrderException;
use App\Domain\Pos\Orders\PosOrderService;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Enums\CancelReason;
use App\Enums\FireMode;
use App\Enums\KitchenItemStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Order\StaffOrderController;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\User;
use App\Services\Order\StaffOrderPresenter;
use App\Support\RowId;
use Closure;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * P3-12: POS hall orders (docs/API.md §13, docs/POS_SPEC.md §2.4–2.10): open, rounds, send to the kitchen, void,
 * guests, move, merge, waive or restore a charge; P3 integration fixes: the open orders list (all channels), a line
 * served, cancelling a whole order. Business rules: PosOrderService; money: PosChargesCalculator.
 *
 * The waiter rule (POS_SPEC 11, WaiterAccess) applies to every change: a waiter changes their own orders (an order
 * without a waiter becomes theirs); another waiter's order needs a manager's `approval_token` for orders.manage
 * (403 `approval_required` with `permission: orders.manage` otherwise).
 */
#[Group('POS: orders', "Kassa va ofitsiant: zal buyurtmasi, bosqichlar, oshxonaga yuborish, o'chirish, ko'chirish, birlashtirish, xona puli va xizmat haqi (P3-12).")]
class PosOrderController extends PosHallController
{
    public const ITEM = 'array{id: int, round_no: int|null, product_variant_id: int|null, product_name: string, variant_name: string|null, quantity: int|float, unit: string, quantity_label: string, unit_price: string, modifiers: list<array{id: int, name: string, group_name: string|null, quantity: int, price_delta: string}>, modifiers_total: string, total_price: string, note: string|null, seat_no: int|null, fire_mode: string|null, due_at: string|null, kitchen_status: string, station_id: int|null, sent_at: string|null, ready_at: string|null, added_by: array{id: int, name: string|null}|null, added_at: string|null, voided_at: string|null, voided_by: array{id: int, name: string|null}|null, void_reason: string|null}';

    public const CHARGES = 'array{items_subtotal: string, discount: string, service_charge_percent: string, service_charge_amount: string, place_fee_amount: string, room_fee_amount: string, room_tariff_id: int|null, business_date: string|null, day_type: string|null, min_spend: string, min_spend_topup_amount: string, waived: list<string>, total: string, waivers: list<array{charge: string, reason: string, waived_by: int|null, approved_by: int|null, waived_at: string|null}>}';

    public const LIST_ITEM = 'array{uuid: string, short_code: string|null, status: string, channel: string, type: string, branch_id: int, version: int, hall: array{id: int, name: string|null}|null, table: array{id: int, name: string, kind: string}|null, hall_ref: string|null, table_ref: string|null, guests_count: int|null, waiter: array{id: int, name: string|null}|null, items_count: int, subtotal: string, total_price: string, payment_status: string, bill_locked: bool, bill_requested: bool, fiscal_pending: bool, booking_id: int|null, business_date: string|null, opened_at: string|null, closed_at: string|null, updated_at: string|null, online: ' . StaffOrderController::ORDER . '|null}';

    public const ORDER = 'array{uuid: string, short_code: string|null, status: string, channel: string, type: string, store_id: int, branch_id: int|null, version: int, hall: array{id: int, name: string|null}|null, table: array{id: int, name: string, kind: string}|null, hall_ref: string|null, table_ref: string|null, guests_count: int|null, waiter: array{id: int, name: string|null}|null, opened_by: int, device_id: int|null, booking_id: int|null, booking_uuid: string|null, deposit_available: string, bill_locked: bool, bill_requested_at: string|null, notes: string|null, opened_at: string|null, updated_at: string|null, subtotal: string, discount: string, discount_kind: \'percent\'|\'amount\'|null, discount_value: string|null, discount_reason: string|null, discount_by: int|null, service_charge_amount: string, place_fee_amount: string, room_fee_amount: string, min_spend_topup_amount: string, total_price: string, charges: ' . self::CHARGES . ', offline: array{local_number: string|null, created_at: string|null, synced_at: string|null, flags: list<array{flag: string, op_id: string|null, amount: string|null, name: string|null}>}|null, rounds: int, items: list<' . self::ITEM . '>}';

    /**
     * One POS order
     *
     * The order as the till shows it: place, guests, waiter, `version` (send it back with the next change), the
     * charge lines (`charges`: service charge %, room fee by day type, minimum spend and its top-up, waived lines) and
     * every line with its round, firing and kitchen state. Needs orders.view in the order's branch.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ORDER . '}')]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $this->staff($request);

        return $this->success($this->presenter->order($this->order($user, $uuid, Permission::ORDERS_VIEW)));
    }

    /**
     * Open a POS order
     *
     * A hall order (`pos_hall`) at a table, room or tapchan (`table_id`) or without one, or a takeaway
     * (`pos_takeaway`). `guests_count` (hall: at least 1) drives the per-guest charges and the automatic "choy-non"
     * lines. `items` (optional) is the first round; `send: true` sends its `now` lines to the kitchen at once.
     * `external_id`: the device's own id for the order; a repeat returns the same order. A room with an open order is
     * 422 `table_occupied`; a closed zone or inactive place 422 `table_inactive`. Needs orders.manage.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function store(Request $request, PosOrderService $orders, PosLineBuilder $lines): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);

        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1',
            'channel' => ['required', 'string', Rule::in([OrderChannel::POS_HALL->value, OrderChannel::POS_TAKEAWAY->value])],
            'table_id' => 'nullable|integer|min:1',
            'guests_count' => 'nullable|integer|min:0|max:500',
            'waiter_id' => 'nullable|integer|min:1',
            'external_id' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
            'send' => 'nullable|boolean',
            'items' => 'nullable|array|max:100',
            'items.*.variant_id' => 'required|uuid',
            'items.*.quantity' => 'required|numeric|gt:0|max:9999',
            'items.*.modifiers' => 'nullable|array|max:50',
            'items.*.note' => 'nullable|string|max:255',
            'items.*.seat_no' => 'nullable|integer|min:1|max:500',
            'items.*.fire_mode' => 'nullable|string|in:now,hold',
            'items.*.due_at' => 'nullable|date',
        ]);

        $branch = $this->branchFor($request, $user, Permission::ORDERS_MANAGE);
        $channel = OrderChannel::from($data['channel']);
        $table = null;
        if (isset($data['table_id'])) {
            $table = DiningTable::where('branch_id', $branch->id)->find((int) $data['table_id']);
            if ($table === null) {
                throw ValidationException::withMessages(['table_id' => 'Stol yoki xona topilmadi.']);
            }
        }

        $guests = isset($data['guests_count']) ? (int) $data['guests_count'] : ($channel === OrderChannel::POS_HALL ? 1 : 0);
        if ($channel === OrderChannel::POS_HALL && $guests < 1) {
            throw ValidationException::withMessages(['guests_count' => "Mehmonlar soni kamida 1 bo'lishi kerak."]);
        }

        $parsed = $lines->parse($data['items'] ?? [], FireMode::NOW);
        $deviceId = $this->permissions->terminalOf($user)?->id;

        return $this->change($request, fn () => $orders->openOrder($branch, $channel, $table, $guests, isset($data['waiter_id']) ? (int) $data['waiter_id'] : null, $deviceId, $user, [
            'items' => $parsed,
            'external_id' => $data['external_id'] ?? null,
            'send' => (bool) ($data['send'] ?? false),
            'note' => $data['note'] ?? null,
        ]), 'Buyurtma ochildi.', 201);
    }

    /**
     * Add a round of lines
     *
     * A new round (`round_no`, who added it, when): lines priced by the order's channel, with the menu's rules (kg and
     * fractions in steps, modifiers, stop-list, kazan portions, stock; in fiscal mode the MXIK code). `fire_mode`
     * `now` (default) goes with the next send, `hold` only when sent by id; `due_at` for a pre-order; `seat_no` and
     * `note` per line. `send: true` sends the round's `now` lines at once. Needs orders.manage.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function addRound(Request $request, string $uuid, PosOrderService $orders, PosLineBuilder $lines): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'items' => 'required|array|min:1|max:100',
            'fire_mode' => 'nullable|string|in:now,hold',
            'send' => 'nullable|boolean',
            'items.*.variant_id' => 'required|uuid',
            'items.*.quantity' => 'required|numeric|gt:0|max:9999',
            'items.*.modifiers' => 'nullable|array|max:50',
            'items.*.note' => 'nullable|string|max:255',
            'items.*.seat_no' => 'nullable|integer|min:1|max:500',
            'items.*.fire_mode' => 'nullable|string|in:now,hold',
            'items.*.due_at' => 'nullable|date',
            'approval_token' => 'nullable|string|max:255',
        ]);

        $order = $this->order($user, $uuid, Permission::ORDERS_MANAGE);
        $parsed = $lines->parse($data['items'], FireMode::from($data['fire_mode'] ?? FireMode::NOW->value));

        return $this->change($request, fn () => $orders->addRound($order, (int) $data['version'], $user, $parsed, (bool) ($data['send'] ?? false), $data['approval_token'] ?? null)['order'], "Taomlar qo'shildi.");
    }

    /**
     * Send lines to the kitchen
     *
     * Without `item_ids`: every line not sent yet whose `fire_mode` is `now`. With them: exactly those (held lines
     * included). Each gets its kitchen station and `sent_at`; the first send starts the order (preparing). 422
     * `nothing_to_send` when there is nothing new. Needs orders.manage.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function send(Request $request, string $uuid, PosOrderService $orders): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'item_ids' => 'nullable|array|max:200',
            'item_ids.*' => 'integer|min:1',
            'approval_token' => 'nullable|string|max:255',
        ]);

        $order = $this->order($user, $uuid, Permission::ORDERS_MANAGE);
        $ids = isset($data['item_ids']) ? array_values(array_map('intval', $data['item_ids'])) : null;

        return $this->change($request, fn () => $orders->send($order, (int) $data['version'], $user, $ids, $data['approval_token'] ?? null), 'Oshxonaga yuborildi.');
    }

    /**
     * Void a line
     *
     * Before the line reached the kitchen: orders.manage, its stock and kazan portion go back. Once sent (or after the
     * bill was printed): `reason` is required and orders.void_item, or a manager's one-time `approval_token` (POST
     * /pos/approvals); the kitchen sees it struck through. `restock` (sent lines) returns the portion when it was not
     * cooked. 403 `approval_required` / `approval_invalid`; 422 `already_void`; 409 `paid_bill_affected` for a line on a
     * bill being paid (refund that payment first, P3-35).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function void(Request $request, string $uuid, string $item, PosOrderService $orders): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:255',
            'approval_token' => 'nullable|string|max:255',
            'restock' => 'nullable|boolean',
        ]);

        $order = $this->order($user, $uuid, Permission::ORDERS_MANAGE);
        $itemId = RowId::parse($item);
        if ($itemId === null) {
            return $this->error('Taom topilmadi.', ['reason' => 'item_not_found'], 404);
        }

        return $this->change($request, fn () => $orders->void(
            $order,
            (int) $data['version'],
            $user,
            $itemId,
            $data['reason'] ?? null,
            $data['approval_token'] ?? null,
            array_key_exists('restock', $data) && $data['restock'] !== null ? (bool) $data['restock'] : null,
        ), "Taom o'chirildi.");
    }

    /**
     * Change the guests count
     *
     * The per-guest charges follow; guests who joined get the automatic "choy-non" lines as a new round. Needs
     * orders.manage. 409 `paid_bill_affected` when a charge a paid bill carries would go down (P3-35).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function guests(Request $request, string $uuid, PosOrderService $orders): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'guests_count' => 'required|integer|min:1|max:500',
            'approval_token' => 'nullable|string|max:255',
        ]);

        $order = $this->order($user, $uuid, Permission::ORDERS_MANAGE);

        return $this->change($request, fn () => $orders->setGuests($order, (int) $data['version'], $user, (int) $data['guests_count'], $data['approval_token'] ?? null), 'Mehmonlar soni yangilandi.');
    }

    /**
     * Move the order to another table
     *
     * Another table, room or tapchan of the same branch: the new hall's service charge and the new place's room tariff
     * and minimum spend apply. A room with an open order is 422 `table_occupied` (merge instead). Needs tables.manage.
     * 409 `paid_bill_affected` when a charge a paid bill carries would go down (P3-35).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function move(Request $request, string $uuid, PosOrderService $orders): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'table_id' => 'required|integer|min:1',
            'approval_token' => 'nullable|string|max:255',
        ]);

        $order = $this->order($user, $uuid, Permission::TABLES_MANAGE);
        $table = DiningTable::where('branch_id', $order->branch_id)->find((int) $data['table_id']);
        if ($table === null) {
            throw ValidationException::withMessages(['table_id' => 'Stol yoki xona topilmadi.']);
        }

        return $this->change($request, fn () => $orders->move($order, (int) $data['version'], $user, $table, $data['approval_token'] ?? null), "Buyurtma ko'chirildi.");
    }

    /**
     * Merge another order into this one
     *
     * `source_uuid`'s lines move to this order (after its rounds), its guests are added and it is closed as merged.
     * Both open hall orders of one branch, no printed bill, no payment; both versions are checked. Needs tables.manage.
     * P3-35: a booking's order hands its booking and deposits to this order; orders of two different bookings are 422
     * `merge_bookings_conflict`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function merge(Request $request, string $uuid, PosOrderService $orders): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'source_uuid' => 'required|uuid',
            'source_version' => 'required|integer|min:0',
            'approval_token' => 'nullable|string|max:255',
        ]);

        $order = $this->order($user, $uuid, Permission::TABLES_MANAGE);
        $source = $this->order($user, (string) $data['source_uuid'], Permission::TABLES_MANAGE);

        return $this->change($request, fn () => $orders->merge($order, (int) $data['version'], $source, (int) $data['source_version'], $user, $data['approval_token'] ?? null), 'Buyurtmalar birlashtirildi.');
    }

    /**
     * Waive a charge line
     *
     * `service_charge`, `place_fee`, `room_fee` or `min_spend_topup` taken off this order with a `reason` (the guest
     * objected; the manager decided): orders.discount or a manager's `approval_token`. Audited. Needs orders.manage.
     * 409 `paid_bill_affected` when a paid bill carries it (P3-35).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function waive(Request $request, string $uuid, string $charge, PosOrderService $orders): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'reason' => 'required|string|min:2|max:255',
            'approval_token' => 'nullable|string|max:255',
        ]);

        $order = $this->order($user, $uuid, Permission::ORDERS_MANAGE);

        return $this->change($request, fn () => $orders->waive($order, (int) $data['version'], $user, $this->charge($charge), (string) $data['reason'], $data['approval_token'] ?? null), 'Qator olib tashlandi.');
    }

    /**
     * Give or take off a discount
     *
     * P3-37. `kind` `percent` (`value` 0 < v ≤ 100, two decimals) or `amount` (`value` in so'm, 0 < v ≤ the dishes'
     * subtotal); `value` 0 takes the discount off. `reason` is required unless `value` is 0 (up to 255). The
     * discount is on the dishes (`charges.items_subtotal`), before the service charge; `discount` is its amount in
     * whole so'm (half-up). A percent stays a percent: adding or voiding lines works it out again; an amount never
     * exceeds the dishes. Who: orders.discount — any discount; a cashier (pos.take_payment) up to the branch's
     * `pos_settings.cashier_discount_max_percent` (an amount by its percent of the dishes), while no bill was printed;
     * otherwise 403 `approval_required` (`permission: orders.discount`): send a manager's one-time `approval_token`
     * for orders.discount (POST /pos/approvals). Taking a discount off needs orders.manage only. The waiter rule
     * applies. 409 `paid_bill_affected` once a bill of the order has a payment or is paid (refund first); 409
     * `version_conflict`; 422 `order_closed`; 422 validation (`value` above the dishes, `reason` missing). A printed
     * bill no longer matches: bill again (POST /pos/orders/{uuid}/bills). Audited (`pos.order.discount`, old and
     * new); the owner is alerted at `pos_settings.alerts.discount_percent` and above; `order.items_changed` with
     * `change: charges_changed`. Not available offline (no sync operation).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function discount(Request $request, string $uuid, PosOrderService $orders): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'kind' => 'required|string|in:percent,amount',
            'value' => ['required', 'numeric', 'min:0', 'max:9999999999', 'decimal:0,2'],
            'reason' => 'nullable|string|max:255',
            'approval_token' => 'nullable|string|max:255',
        ], [
            'kind.in' => "Chegirma turi percent yoki amount bo'lishi kerak.",
            'value.decimal' => "Chegirma ko'pi bilan 2 kasr xonali son bo'lishi kerak.",
        ]);

        $order = $this->order($user, $uuid, Permission::ORDERS_VIEW);

        return $this->change($request, fn () => $orders->discount(
            $order,
            (int) $data['version'],
            $user,
            (string) $data['kind'],
            (string) $data['value'],
            $data['reason'] ?? null,
            $data['approval_token'] ?? null,
        ), 'Chegirma saqlandi.');
    }

    /**
     * Restore a waived charge line
     *
     * Puts the charge back (orders.manage). 422 `not_waived` when it was not waived.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function restoreCharge(Request $request, string $uuid, string $charge, PosOrderService $orders): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate(['version' => 'required|integer|min:0', 'approval_token' => 'nullable|string|max:255']);

        $order = $this->order($user, $uuid, Permission::ORDERS_MANAGE);

        return $this->change($request, fn () => $orders->restoreCharge($order, (int) $data['version'], $user, $this->charge($charge), $data['approval_token'] ?? null), 'Qator qaytarildi.');
    }

    /**
     * Orders of the branch for the till
     *
     * `scope`:
     *
     *  - `open` (the default): one queue for the till (POS_SPEC 2.5): every POS order still open (accepted, preparing,
     *    ready, not closed — paid ones still waiting for their receipt included, `fiscal_pending`) and every accepted
     *    online order (Telegram, mobile, aggregator: accepted, preparing, ready), oldest first, at most 300;
     *  - `incoming` (P3-26): the online orders waiting to be accepted (`new`), oldest first: accept or reject them with
     *    POST /pos/orders/{uuid}/accept|reject;
     *  - `closed` (P3-26): the POS orders closed (paid) on one business day (`business_date`, YYYY-MM-DD; default: the
     *    branch's business day now), newest first, at most 300: the till's list to refund from.
     *
     * `bill_requested`: a waiter asked for the bill. `online`: an online order's board card (customer, address, lines,
     * payment; contact details for orders.manage), null for a POS order. `branch_id`: the caller's branch by default (a terminal PIN
     * session: its terminal's). Needs orders.view in the branch.
     */
    #[QueryParameter('scope', description: 'open (default) | incoming | closed', type: "'open'|'incoming'|'closed'", required: false)]
    #[QueryParameter('business_date', description: "scope=closed: the business day (YYYY-MM-DD); default: the branch's business day now.", type: 'string', required: false)]
    #[QueryParameter('branch_id', description: "The branch; default: the caller's (or their terminal's).", type: 'integer', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . self::LIST_ITEM . '>}')]
    public function index(Request $request): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate([
            'scope' => ['nullable', 'string', Rule::in(['open', 'incoming', 'closed'])],
            'business_date' => 'nullable|date_format:Y-m-d',
            'branch_id' => 'nullable|integer|min:1',
        ], [
            'scope.in' => "Ro'yxat turi noto'g'ri: open, incoming yoki closed.",
            'business_date.date_format' => "Ish kuni YYYY-MM-DD ko'rinishida bo'lishi kerak.",
        ]);
        $branch = $this->branchFor($request, $user, Permission::ORDERS_VIEW);
        $scope = $data['scope'] ?? 'open';

        $pos = [OrderChannel::POS_HALL->value, OrderChannel::POS_TAKEAWAY->value];
        $query = Order::query()
            ->with(['waiter:id,name', 'diningTable', 'hall'])
            ->withCount(['items as items_count' => fn ($query) => $query->where('kitchen_status', '!=', KitchenItemStatus::VOID->value)])
            ->where('store_id', $user->store_id)
            ->where('branch_id', $branch->id)
            ->addSelect(['fiscal_pending' => OrderBill::selectRaw('count(*)')
                ->whereColumn('order_bills.order_id', 'orders.id')
                ->whereNotIn('order_bills.status', [BillStatus::PAID->value, BillStatus::VOID->value])
                ->whereIn('order_bills.fiscal_status', ['pending', 'failed'])]);

        match ($scope) {
            'incoming' => $query->whereNotIn('channel', $pos)
                ->where('status', OrderStatus::NEW->value)
                ->orderBy('created_at')->orderBy('id'),
            'closed' => $query->whereIn('channel', $pos)
                ->whereNotNull('closed_at')
                ->whereDate('business_date', $data['business_date'] ?? BusinessDay::of($branch))
                ->orderByDesc('closed_at')->orderByDesc('id'),
            default => $query->whereIn('status', [OrderStatus::ACCEPTED->value, OrderStatus::PREPARING->value, OrderStatus::READY->value])
                ->where(fn ($query) => $query
                    ->where(fn ($query) => $query->whereIn('channel', $pos)->whereNull('closed_at'))
                    ->orWhereNotIn('channel', $pos))
                ->orderBy('created_at')->orderBy('id'),
        };

        $orders = $query->limit(300)->get();
        // The online cards' relations (lines, history, branch; the customer and courier for orders.manage) in a few queries
        $online = $orders->reject(fn (Order $order) => $order->channel->isPos());
        if ($online->isNotEmpty()) {
            (new \Illuminate\Database\Eloquent\Collection($online->all()))->load(app(StaffOrderPresenter::class)->relationsFor($user));
        }

        return $this->success($orders->map(fn (Order $order) => $this->listItem($order, $user))->values()->all());
    }

    /**
     * Accept an online order
     *
     * A new online order (Telegram, mobile, aggregator) of the branch is accepted at the till: `new` → `accepted`
     * (the customer is told, the kitchen gets it). Accepted already: 200 unchanged. Not new any more (rejected,
     * cancelled): 422. A POS order: 422 `not_online_order`. Optional `version` (409 when stale). Needs orders.manage.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::LIST_ITEM . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function accept(Request $request, string $uuid, PosOnlineOrders $online): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate(['version' => 'nullable|integer|min:0']);

        $order = $this->onlineOrder($user, $uuid);

        return $this->online($request, fn () => $online->accept($order, $user, isset($data['version']) ? (int) $data['version'] : null), 'Buyurtma qabul qilindi.');
    }

    /**
     * Reject an online order
     *
     * A new online order is rejected at the till: `new` → `rejected` (the customer is told; an online payment is
     * returned as for any rejection). `reason` (required, what the customer and the owner read) and optional
     * `cancel_reason` (customer_request, out_of_stock, store_closed, courier_unavailable, other; default other). Needs
     * orders.cancel, or a manager's one-time `approval_token` for orders.cancel (POST /pos/approvals; 403
     * `approval_required`). Rejected already: 200 unchanged; not new any more: 422. Optional `version`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::LIST_ITEM . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function reject(Request $request, string $uuid, PosOnlineOrders $online): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'nullable|integer|min:0',
            'reason' => 'required|string|min:2|max:255',
            'cancel_reason' => ['nullable', 'string', Rule::in(array_values(array_diff(CancelReason::staffValues(), [CancelReason::NO_SHOW->value])))],
            'approval_token' => 'nullable|string|max:255',
        ], [
            'reason.required' => 'Rad etish sababini yozing.',
            'reason.min' => 'Rad etish sababini yozing.',
            'cancel_reason.in' => "Rad etish sababi noto'g'ri.",
        ]);

        $order = $this->onlineOrder($user, $uuid);
        $reason = isset($data['cancel_reason']) ? CancelReason::from($data['cancel_reason']) : CancelReason::OTHER;

        return $this->online($request, fn () => $online->reject($order, $user, (string) $data['reason'], $reason, $data['approval_token'] ?? null, isset($data['version']) ? (int) $data['version'] : null), 'Buyurtma rad etildi.');
    }

    /**
     * Ask the till for the bill
     *
     * The waiter's "Schyot so'rash": the order is marked `bill_requested_at` and the till hears
     * `order.bill_requested` on `pos-branch.{id}` (the floor map shows "Schyot so'raldi"); no bill is made here, the
     * cashier makes and prints it. Asked already: 200 with the order unchanged. Optional `version` (409 when stale).
     * Needs orders.manage; the waiter rule applies (`approval_token`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function requestBill(Request $request, string $uuid, PosOrderService $orders): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'nullable|integer|min:0',
            'approval_token' => 'nullable|string|max:255',
        ]);

        $order = $this->order($user, $uuid, Permission::ORDERS_MANAGE);

        return $this->change($request, fn () => $orders->requestBill($order, isset($data['version']) ? (int) $data['version'] : null, $user, $data['approval_token'] ?? null), "Schyot so'raldi.");
    }

    /**
     * One row of the till's order lists.
     *
     * @return array<string, mixed>
     */
    private function listItem(Order $order, User $viewer): array
    {
        $order->loadMissing(['waiter:id,name', 'diningTable', 'hall']);
        if ($order->getAttribute('items_count') === null) {
            $order->loadCount(['items as items_count' => fn ($query) => $query->where('kitchen_status', '!=', KitchenItemStatus::VOID->value)]);
        }

        return [
            'uuid' => (string) $order->uuid,
            'short_code' => $order->short_code,
            'status' => $order->status->value,
            'channel' => $order->channel->value,
            'type' => $order->order_type->value,
            'branch_id' => (int) $order->branch_id,
            'version' => (int) $order->version,
            'hall' => $order->hall_id !== null ? ['id' => (int) $order->hall_id, 'name' => $order->hall->name ?? $order->hall_ref] : null,
            'table' => $order->diningTable !== null ? ['id' => (int) $order->diningTable->id, 'name' => $order->diningTable->name, 'kind' => $order->diningTable->kind->value] : null,
            'hall_ref' => $order->hall_ref,
            'table_ref' => $order->table_ref,
            'guests_count' => $order->guests_count,
            'waiter' => $order->waiter ? ['id' => (int) $order->waiter->id, 'name' => $order->waiter->name] : null,
            'items_count' => (int) $order->getAttribute('items_count'),
            'subtotal' => (string) $order->subtotal,
            'total_price' => (string) $order->total_price,
            'payment_status' => $order->payment_status->value,
            'bill_locked' => $order->getAttribute('bill_locked_at') !== null,
            'bill_requested' => $order->bill_requested_at !== null,
            'fiscal_pending' => (int) $order->getAttribute('fiscal_pending') > 0,
            'booking_id' => $order->getAttribute('booking_id') !== null ? (int) $order->getAttribute('booking_id') : null,
            'business_date' => $order->business_date?->toDateString(),
            'opened_at' => $order->created_at?->toJSON(),
            'closed_at' => $order->closed_at?->toJSON(),
            'updated_at' => $order->updated_at?->toJSON(),
            // P3-26: an online order's card as the staff board shows it (customer and address for orders.manage, lines,
            // payment, waiting time), so the till accepts or rejects it from this list alone; null for a POS order
            'online' => $order->channel->isPos() ? null : app(StaffOrderPresenter::class)->order($order, $viewer),
        ];
    }

    /**
     * An online order of the caller's branch (orders.view there; another store's or branch's: 404; a POS order: 422).
     */
    private function onlineOrder(User $user, string $uuid): Order
    {
        $order = Str::isUuid($uuid) ? Order::where('uuid', $uuid)->first() : null;
        if (!$order instanceof Order || !$this->scope->allows($user, Permission::ORDERS_VIEW, $order)) {
            throw new HttpResponseException($this->error('Buyurtma topilmadi yoki sizning filialingizga tegishli emas.', [], 404));
        }
        if ($order->channel->isPos()) {
            throw new HttpResponseException($this->error("Bu kassa (POS) buyurtmasi: onlayn buyurtma emas.", ['reason' => 'not_online_order'], 422));
        }

        return $order;
    }

    /**
     * Runs an online order's change with the Idempotency-Key and answers with its list row.
     *
     * @param  Closure(): Order  $change
     */
    private function online(Request $request, Closure $change, string $message): JsonResponse
    {
        try {
            $order = DB::transaction(function () use ($request, $change) {
                $order = $change();
                $this->idempotency->markApplied($request);

                return $order;
            });
        } catch (PosOrderException $e) {
            return $this->error($e->getMessage(), ['reason' => $e->reason] + $e->data, $e->status);
        } catch (ConflictHttpException $e) {
            return $this->error($e->getMessage(), ['reason' => 'idempotency_conflict'], 409);
        }

        $user = $request->user();

        return $this->success($this->listItem($order->fresh() ?? $order, $user instanceof User ? $user : $this->staff($request)), $message);
    }

    /**
     * Mark a line served
     *
     * The line reached the guests: `ready` (the kitchen display bumped it) or `sent` (a station without a display,
     * e.g. tea) becomes `served`. 422 `already_served`, `item_not_ready` (not sent yet or voided). Needs orders.manage;
     * the waiter rule applies (`approval_token`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function served(Request $request, string $uuid, string $item, PosOrderService $orders): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'approval_token' => 'nullable|string|max:255',
        ]);

        $order = $this->order($user, $uuid, Permission::ORDERS_MANAGE);
        $itemId = RowId::parse($item);
        if ($itemId === null) {
            return $this->error('Taom topilmadi.', ['reason' => 'item_not_found'], 404);
        }

        return $this->change($request, fn () => $orders->serve($order, (int) $data['version'], $user, $itemId, $data['approval_token'] ?? null), 'Taom berildi.');
    }

    /**
     * Cancel the whole order
     *
     * `reason` is required. orders.cancel, or a manager's one-time `approval_token` for orders.cancel (POST
     * /pos/approvals; 403 `approval_required`). An order with a payment is refused: 409 `has_payments` (refund the
     * payments first, POST /pos/refunds). Every line still on the order is voided — lines already sent get a kitchen
     * void ("O'CHIRILDI"), unsent lines give their stock and kazan portion back — open or printed bills are voided, the
     * order becomes `canceled` and its table free; a booking's order completes the booking (its deposits stay
     * `taken`, to refund or retain). Audited; the owner is alerted. P3-37: a closed order whose every payment was
     * refunded in full (`payment_status` refunded) is cancelled too, with the same permission: it becomes `canceled`,
     * keeps `closed_at`, its lines and paid bills stay, no receipt is issued (the refund receipts already reverse the
     * sale). Any other closed or cancelled order: 422 `order_closed`. 409 `version_conflict`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ORDER . '}')]
    #[HeaderParameter('Idempotency-Key', description: 'Required: a new UUID per action, the same one when retrying it (428 without).', type: 'string')]
    public function cancel(Request $request, string $uuid, PosOrderService $orders): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'version' => 'required|integer|min:0',
            'reason' => 'required|string|min:2|max:255',
            'approval_token' => 'nullable|string|max:255',
        ]);

        $order = $this->order($user, $uuid, Permission::ORDERS_VIEW);

        return $this->change($request, fn () => $orders->cancel($order, (int) $data['version'], $user, (string) $data['reason'], $data['approval_token'] ?? null), 'Buyurtma bekor qilindi.');
    }

    private function charge(string $value): PosCharge
    {
        return PosCharge::tryFrom($value)
            ?? throw new HttpResponseException($this->error("Noma'lum qator: " . mb_substr($value, 0, 30), ['reason' => 'unknown_charge'], 404));
    }
}
