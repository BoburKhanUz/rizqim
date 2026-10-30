<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Enums\CancelReason;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Notifications\OrderAlertService;
use App\Services\Order\KitchenService;
use App\Services\Order\OrderQueryService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use App\Services\Order\StaffOrderPresenter;
use App\Services\Order\StaffOrderScope;
use App\Support\RowId;
use App\Traits\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * P2-11: orders for the store's staff (partner web board, Mini App staff screens, kitchen display; docs/API.md §9).
 *
 * The owner sees every branch of the store; managers, cashiers, waiters and cooks their branch (staff without a
 * branch: the whole store); a staff PIN session on a terminal paired to a branch: that branch (StaffOrderScope).
 * Another store's or another branch's order is a 404, like a missing one. Couriers and customers keep GET /orders,
 * whose shape and meaning are unchanged. The customer's contact details and address only reach staff with
 * orders.manage (StaffOrderPresenter): a cook never gets them.
 */
#[Group('Staff orders', "Xodimlar uchun buyurtmalar: doska, tafsilot, holat, oshxona ekrani (P2-11).")]
class StaffOrderController extends Controller
{
    use ApiResponse;

    public const ITEM = 'array{id: int, product_name: string, variant_name: string|null, quantity: int|float, unit: string, quantity_label: string, modifiers: list<array{name: string, group_name?: string, quantity: int, price_delta?: string}>, kitchen_status: string, ready_at: string|null, round_no?: int|null, fire_mode?: string, station_id?: int|null, product_id?: int|null, category_id?: int|null, sent_at?: string|null, due_at?: string|null, seat_no?: int|null, note?: string|null, voided_at?: string|null, void_reason?: string|null, course_no?: int|null, weight_status?: string|null, wait_minutes?: int|null, unit_price?: string, modifiers_total?: string, total_price?: string}';

    public const ORDER = 'array{uuid: string, short_code: string|null, status: string, status_label: string, type: string, channel: string, store_id: int, branch_id: int|null, branch_name: string|null, payment_method: string, payment_status: string, subtotal: string, discount: string, delivery_price: string, service_charge_amount: string, place_fee_amount: string, total_price: string, notes: string|null, guests_count: int|null, hall_ref: string|null, table_ref: string|null, cancel_reason: string|null, cancel_note: string|null, customer: array{name: string|null, phone: string|null}|null, delivery: array{address: string|null, latitude: float|null, longitude: float|null, zone_name: string|null}|null, courier: array{id: int, name: string|null, phone: string|null}|null, items_total: int, items_ready: int, items: list<' . self::ITEM . '>, created_at: string|null, updated_at: string|null, accepted_at: string|null, preparing_at: string|null, ready_at: string|null, closed_at: string|null, waiting_seconds: int}';

    private const DETAIL = 'array{uuid: string, short_code: string|null, status: string, status_label: string, type: string, channel: string, store_id: int, branch_id: int|null, branch_name: string|null, payment_method: string, payment_status: string, subtotal: string, discount: string, delivery_price: string, service_charge_amount: string, place_fee_amount: string, total_price: string, notes: string|null, guests_count: int|null, hall_ref: string|null, table_ref: string|null, cancel_reason: string|null, cancel_note: string|null, customer: array{name: string|null, phone: string|null}|null, delivery: array{address: string|null, latitude: float|null, longitude: float|null, zone_name: string|null}|null, courier: array{id: int, name: string|null, phone: string|null}|null, items_total: int, items_ready: int, items: list<' . self::ITEM . '>, created_at: string|null, updated_at: string|null, accepted_at: string|null, preparing_at: string|null, ready_at: string|null, closed_at: string|null, waiting_seconds: int, status_history: list<array{from_status: string|null, status: string, created_at: string|null}>}';

    private const TICKET = 'array{uuid: string, short_code: string|null, status: string, type: string, channel: string, branch_id: int|null, hall_ref: string|null, table_ref: string|null, table_label: string|null, guests_count: int|null, notes: string|null, items_total: int, items_ready: int, items: list<' . self::ITEM . '>, created_at: string|null, accepted_at: string|null, preparing_at: string|null, ready_at: string|null, canceled_at: string|null, waiting_seconds: int, queue_number: int|null, pickup_status: \\App\\Domain\\Pos\\Orders\\PickupStatus|null}';

    /** Longest look-back of `canceled_within` (minutes). */
    public const CANCELED_WITHIN_MAX = 60;

    public const DEFAULT_PER_PAGE = 30;

    public const MAX_PER_PAGE = 100;

    /** Longest date range of one list request (the board's history filter), in days. */
    public const MAX_RANGE_DAYS = 93;

    /** Most tickets one kitchen display response carries (meta.truncated says more exist). */
    public const TICKETS_CAP = 200;

    public function __construct(
        protected StaffOrderScope $scope,
        protected StaffOrderPresenter $presenter,
        protected OrderQueryService $orders,
        protected OrderStatusService $orderStatus,
        protected KitchenService $kitchen,
        protected OrderAlertService $alerts,
    ) {}

    /**
     * Branch orders for staff (board)
     *
     * The orders of the caller's branches, newest first, keyset-paginated: send `meta.next_cursor` back as `cursor`
     * (with the same filters) until it is null. Filters: `branch_id` (one branch; another branch the caller does not
     * work in is 403, another store's 404), `scope` (`active`: still in progress, `history`: delivered, cancelled,
     * rejected, refunded), `status` (comma-separated), `type`, `channel`, `date` or `from`/`to` (days in the store's
     * time zone, at most 93 days), `q` (the start of the order number). Needs orders.view (owner, manager, cashier,
     * waiter, cook). Contact details and the address only for orders.manage. P2-30: `waiting_seconds` as on the kitchen
     * tickets (from acceptance, or creation, to now or to when it became ready).
     */
    #[QueryParameter('branch_id', description: 'One branch of the store; omit for every branch the caller works in.', type: 'int')]
    #[QueryParameter('scope', description: '`active` (in progress) or `history` (finished).', type: "'active'|'history'")]
    #[QueryParameter('status', description: 'Comma-separated order statuses, e.g. `new,accepted`.', type: 'string')]
    #[QueryParameter('type', description: 'delivery, pickup or dine_in.', type: "'delivery'|'pickup'|'dine_in'")]
    #[QueryParameter('channel', description: 'telegram, mobile, pos_hall, pos_takeaway or aggregator.', type: 'string')]
    #[QueryParameter('date', description: 'One day (Y-m-d, store time zone); same as from=to=date.', type: 'string')]
    #[QueryParameter('from', description: 'First day (Y-m-d, store time zone), inclusive.', type: 'string')]
    #[QueryParameter('to', description: 'Last day (Y-m-d, store time zone), inclusive.', type: 'string')]
    #[QueryParameter('q', description: 'Start of the order number (short code), 1-20 letters or digits.', type: 'string')]
    #[QueryParameter('per_page', description: 'Page size, 1-100 (larger values are capped).', type: 'int', default: self::DEFAULT_PER_PAGE)]
    #[QueryParameter('cursor', description: '`meta.next_cursor` of the previous page.', type: 'string')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . self::ORDER . '>, meta: array{next_cursor: string|null, per_page: int}}')]
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $statuses = OrderStatus::cases();
        $request->validate([
            'branch_id' => 'nullable|string|max:20',
            'scope' => 'nullable|string|in:active,history',
            'status' => 'nullable|string|max:200',
            'type' => ['nullable', 'string', Rule::in(array_map(fn (OrderType $type) => $type->value, OrderType::cases()))],
            'channel' => ['nullable', 'string', Rule::in(array_map(fn (OrderChannel $channel) => $channel->value, OrderChannel::cases()))],
            'date' => 'nullable|date_format:Y-m-d|prohibits:from,to',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'q' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'per_page' => 'nullable|integer|min:1',
            'cursor' => 'nullable|string|max:200',
        ], [
            'date.date_format' => "Sana Y-m-d ko'rinishida bo'lishi kerak.",
            'from.date_format' => "Sana Y-m-d ko'rinishida bo'lishi kerak.",
            'to.date_format' => "Sana Y-m-d ko'rinishida bo'lishi kerak.",
            'to.after_or_equal' => "Tugash sanasi boshlanish sanasidan oldin bo'lishi mumkin emas.",
            'q.regex' => "Buyurtma raqamida faqat harf va raqam bo'ladi.",
        ]);

        $wanted = [];
        foreach (array_filter(explode(',', (string) $request->query('status', ''))) as $value) {
            $status = OrderStatus::tryFrom(trim($value));
            if ($status === null) {
                throw ValidationException::withMessages(['status' => "Noto'g'ri holat: " . Str::limit(trim($value), 30)]);
            }
            $wanted[] = $status->value;
        }

        $query = $this->scope->query($user);

        if (($branchId = $request->query('branch_id')) !== null) {
            $query->where('orders.branch_id', $this->branch($user, $branchId)->id);
        }

        $scope = $request->query('scope');
        if ($scope !== null) {
            $finished = array_map(fn (OrderStatus $status) => $status->value, OrderQueryService::TERMINAL_STATUSES);
            $inScope = $scope === 'history'
                ? $finished
                : array_values(array_diff(array_map(fn (OrderStatus $status) => $status->value, $statuses), $finished));
            $wanted = $wanted === [] ? $inScope : array_values(array_intersect($wanted, $inScope));
            if ($wanted === []) {
                $query->whereRaw('1 = 0');
            }
        }
        if ($wanted !== []) {
            $query->whereIn('orders.status', array_values(array_unique($wanted)));
        }

        $query->when($request->query('type'), fn ($q, $type) => $q->where('orders.order_type', $type))
            ->when($request->query('channel'), fn ($q, $channel) => $q->where('orders.channel', $channel));

        if ($code = $request->query('q')) {
            $query->where('orders.short_code', 'like', strtoupper((string) $code) . '%');
        }

        $this->applyDates($request, $user, $query);

        $perPage = min((int) ($request->query('per_page') ?? self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE);

        $cursor = $request->query('cursor');
        if ($cursor !== null) {
            $after = is_string($cursor) ? $this->orders->decodeCursor($cursor) : null;
            if ($after === null) {
                throw ValidationException::withMessages(['cursor' => "Noto'g'ri yoki eskirgan cursor. Ro'yxatni boshidan yuklang."]);
            }
            // A row comparison, so PostgreSQL seeks the (…, created_at, id) index instead of filtering newer rows
            $query->whereRaw('(orders.created_at, orders.id) < (?, ?)', [$after['created_at'], $after['id']]);
        }

        $rows = $query->with($this->presenter->relationsFor($user))
            ->orderByDesc('orders.created_at')
            ->orderByDesc('orders.id')
            ->limit($perPage + 1)
            ->get();

        $page = $rows->take($perPage)->values();
        $last = $page->last();

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $page->map(fn (Order $order) => $this->presenter->order($order, $user))->all(),
            'meta' => [
                'next_cursor' => $rows->count() > $perPage && $last ? $this->orders->encodeCursor($last) : null,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * One order for staff (detail)
     *
     * Items with modifiers, weights and kitchen state, notes, every timestamp and the status history. 404 for an order
     * of another store or of a branch the caller does not work in.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::DETAIL . '}')]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $this->user($request);
        $order = $this->find($user, $uuid, Permission::ORDERS_VIEW);

        return $this->success($this->presenter->order($order, $user, true));
    }

    /**
     * Change an order's status (staff)
     *
     * By permission (docs/AUTH.md §6): orders.manage (owner, manager, cashier, waiter) accepts, starts, marks ready and
     * hands over (pickup, hall: delivered); a cook (kitchen.manage) only starts cooking (accepted → preparing) and marks
     * ready (preparing → ready); cancelling or rejecting needs orders.cancel and a `reason`. The order's state machine
     * decides what can follow what (422 otherwise). Couriers are assigned with POST /orders/{uuid}/assign-courier.
     * Marking an order ready marks all its lines ready in the kitchen. The same status again answers 200 unchanged.
     * P2-30: `on_the_way` (the assigned courier picked the order up: courier_assigned → on_the_way) with
     * orders.assign_courier; an order without an assigned courier is a 422 (assign one first).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::DETAIL . '}')]
    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        $user = $this->user($request);

        $request->validate([
            'status' => 'required|string|in:accepted,preparing,ready,on_the_way,delivered,canceled,rejected',
            'reason' => ['nullable', 'required_if:status,canceled,rejected', 'string', Rule::in(CancelReason::staffValues())],
            'note' => 'nullable|string|max:500',
        ], [
            'reason.required_if' => 'Bekor qilish sababini tanlang.',
            'reason.in' => "Bekor qilish sababi noto'g'ri.",
            'note.max' => "Izoh ko'pi bilan 500 belgi bo'lishi mumkin.",
        ]);

        $order = $this->find($user, $uuid, Permission::ORDERS_VIEW);
        $status = OrderStatus::from((string) $request->input('status'));

        // P2-30: the pick-up is the assigned courier's; with none assigned the state machine would pick one by itself
        if ($status === OrderStatus::ON_THE_WAY && $order->status !== OrderStatus::ON_THE_WAY) {
            if (!$this->scope->allows($user, Permission::ORDERS_ASSIGN_COURIER, $order)) {
                return $this->error('Ruxsat etilmagan', [], 403);
            }
            if ($order->courier_id === null) {
                return $this->error('Avval buyurtmaga kuryer biriktiring.', ['reason' => 'courier_required'], 422);
            }
        }

        try {
            $result = $this->orderStatus->transition($order, $status, $user, [
                'cancel_reason' => $status->isCancellation() ? $request->input('reason') : null,
                'cancel_note' => $status->isCancellation() ? $request->input('note') : null,
                'notes' => "Xodim holatni o'zgartirdi: {$status->value} ({$user->name})",
            ]);
        } catch (OrderTransitionException $e) {
            if ($e->reason === 'reason') {
                throw ValidationException::withMessages(['reason' => $e->getMessage()]);
            }

            return $this->error($e->getMessage(), [], $e->status);
        }

        // P2-33: not for a quiet kitchen correction (ready again after a recall)
        if ($result->changed && !$result->quiet) {
            $this->alerts->statusChanged($result->order, $status->value);
        }

        return $this->success(
            $this->presenter->order($result->order->fresh() ?? $result->order, $user, true),
            $result->changed ? 'Buyurtma holati yangilandi.' : 'Buyurtma allaqachon shu holatda.'
        );
    }

    /**
     * Kitchen display tickets
     *
     * The orders the kitchen is cooking (accepted and preparing; with `include_ready=1` also the ready ones not yet
     * handed over), oldest first, at most 200 (`meta.truncated`). P2-33: also an order a courier was assigned to while
     * it was cooking (courier_assigned) as long as a line is still pending; with `include_ready=1` every
     * courier_assigned order (the courier has not picked it up yet). Each ticket: lines with modifiers and
     * quantities/weights, each line's kitchen state, the notes (phone numbers masked), when it came in and was
     * accepted, and `waiting_seconds`. No customer data, no prices. Needs kitchen.manage or orders.view.
     *
     * P3-26: with `canceled_within` (minutes, 1–60) the orders cancelled or rejected within that time that had
     * reached the kitchen (a line sent, or an online order accepted) are listed too, after the live ones, with their
     * `status` (canceled / rejected) and `canceled_at`, their lines as they were (voided ones with `voided_at`), so a
     * display strikes them through instead of losing them; `canceled_at` is null on a live ticket.
     *
     * P6-13: a counter order paid first is closed (delivered) while its queue number is still `preparing`: it stays
     * listed (with `include_ready=1` also while `ready`) until the number is handed over.
     */
    #[Group('Kitchen')]
    #[QueryParameter('branch_id', description: 'One branch; omit for every branch the caller works in (a branch terminal: its branch).', type: 'int')]
    #[QueryParameter('include_ready', description: '1 (or true) to also list orders that are ready but not handed over yet.', type: 'bool')]
    #[QueryParameter('canceled_within', description: 'Minutes (1-60): also list the orders cancelled or rejected that long ago at most, with canceled_at.', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . self::TICKET . '>, meta: array{truncated: bool}}')]
    public function tickets(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $request->validate([
            'branch_id' => 'nullable|string|max:20',
            // Review P3-35: `true`/`false` as a query string carries them too (1/0 as before)
            'include_ready' => ['nullable', Rule::in(['0', '1', 'true', 'false'])],
            'canceled_within' => 'nullable|integer|min:1|max:' . self::CANCELED_WITHIN_MAX,
        ], [
            'canceled_within.*' => "Bekor qilingan buyurtmalar davri 1 dan " . self::CANCELED_WITHIN_MAX . " daqiqagacha bo'lishi kerak.",
        ]);

        $includeReady = $request->boolean('include_ready');
        $statuses = [OrderStatus::ACCEPTED->value, OrderStatus::PREPARING->value];
        if ($includeReady) {
            $statuses[] = OrderStatus::READY->value;
            $statuses[] = OrderStatus::COURIER_ASSIGNED->value;
        }

        // P2-33: keyed on the lines too: a courier was assigned while the kitchen was still cooking, so the order is
        // courier_assigned with lines still pending; it stays on the display until they are bumped
        $query = $this->scope->query($user)->where(function ($query) use ($statuses, $includeReady) {
            $query->whereIn('orders.status', $statuses);
            // P6-13: a counter order paid first is closed (delivered) while the kitchen still cooks its queue number
            // (a number never handed over drops off after a day)
            $query->orWhere(fn ($query) => $query->where('orders.status', OrderStatus::DELIVERED->value)
                ->whereIn('orders.channel', [OrderChannel::POS_HALL->value, OrderChannel::POS_TAKEAWAY->value])
                ->whereIn('orders.pickup_status', $includeReady ? ['preparing', 'ready'] : ['preparing'])
                ->where('orders.closed_at', '>=', now()->subDay()));
            if (!$includeReady) {
                $query->orWhere(fn ($query) => $query->where('orders.status', OrderStatus::COURIER_ASSIGNED->value)
                    ->whereExists(fn ($lines) => $lines->selectRaw('1')->from('order_items')
                        ->whereColumn('order_items.order_id', 'orders.id')
                        ->where('order_items.kitchen_status', \App\Enums\KitchenItemStatus::PENDING->value)));
            }
        });
        if (($branchId = $request->query('branch_id')) !== null) {
            $query->where('orders.branch_id', $this->branch($user, $branchId)->id);
        }

        // P3-12: a POS order reaches the kitchen line by line: listed once a line was sent, or a held pre-order line
        // has its due time (StaffOrderPresenter::kitchenSees); online orders as before
        $query->where(fn ($query) => $query->whereNotIn('orders.channel', [OrderChannel::POS_HALL->value, OrderChannel::POS_TAKEAWAY->value])
            ->orWhereExists(fn ($lines) => $lines->selectRaw('1')->from('order_items')
                ->whereColumn('order_items.order_id', 'orders.id')
                ->whereNull('order_items.deleted_at')
                ->where(fn ($line) => $line->whereNotNull('order_items.sent_at')
                    ->orWhere(fn ($line) => $line->where('order_items.fire_mode', \App\Enums\FireMode::HOLD->value)
                        ->whereNotNull('order_items.due_at')
                        ->where('order_items.kitchen_status', '!=', \App\Enums\KitchenItemStatus::VOID->value)))));

        $rows = $query->with(StaffOrderPresenter::RELATIONS)
            ->orderBy('orders.created_at')
            ->orderBy('orders.id')
            ->limit(self::TICKETS_CAP + 1)
            ->get();
        $now = now();

        // P3-26: what the kitchen lost lately (a cancelled or rejected order), so the display strikes it through
        if ($request->filled('canceled_within') && $rows->count() <= self::TICKETS_CAP) {
            $rows = $rows->concat($this->recentlyCanceled($user, $request, $request->integer('canceled_within'), self::TICKETS_CAP + 1 - $rows->count()));
        }

        return response()->json([
            'success' => true,
            'message' => '',
            'data' => $rows->take(self::TICKETS_CAP)->map(fn (Order $order) => $this->presenter->ticket($order, $now))->values()->all(),
            'meta' => ['truncated' => $rows->count() > self::TICKETS_CAP],
        ]);
    }

    /**
     * Bump a line ready (kitchen display)
     *
     * The line is ready (kitchen.manage). The first bump of an accepted order starts it (preparing). When every line is
     * ready a delivery or pickup order becomes ready by itself; a hall order (dine_in) stays preparing until it is
     * closed. P2-33: a courier_assigned order (a courier was assigned while it was cooking) takes bumps too, with no
     * status move. 422 when the order is not in the kitchen (new, ready, on its way, finished). Bumping a ready line
     * again answers 200 unchanged. P3-26: a held pre-order line with a due time (fire_mode hold, `due_at`, not sent yet)
     * may be bumped before the till sends it — ready implies sent: it gets its station and `sent_at` now and the POS
     * order's version moves. Returns the ticket.
     */
    #[Group('Kitchen')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::TICKET . '}')]
    public function bumpItem(Request $request, string $uuid, string $item): JsonResponse
    {
        return $this->kitchenChange($request, $uuid, $item, true);
    }

    /**
     * Recall a line (kitchen display)
     *
     * Undo a mistaken bump: the line is back to pending (kitchen.manage). While the order is accepted or preparing,
     * and (P2-30) while it is ready but not yet with a courier or handed over: the order goes back to preparing
     * (`order.status_changed` and the history say so). P2-33: while a courier is assigned (courier_assigned) the line
     * is recalled with no status move. 422 once it is on its way or finished. Returns the ticket.
     */
    #[Group('Kitchen')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::TICKET . '}')]
    public function recallItem(Request $request, string $uuid, string $item): JsonResponse
    {
        return $this->kitchenChange($request, $uuid, $item, false);
    }

    protected function kitchenChange(Request $request, string $uuid, string $itemId, bool $ready): JsonResponse
    {
        $user = $this->user($request);
        // kitchen.manage itself is the route's (403); here its branch: another branch's order is a 404
        $order = $this->find($user, $uuid, Permission::KITCHEN_MANAGE);

        $id = RowId::parse($itemId);
        $item = $id !== null ? OrderItem::where('order_id', $order->id)->find($id) : null;
        if (!$item) {
            return $this->error('Taom topilmadi.', [], 404);
        }

        try {
            $result = $ready ? $this->kitchen->bump($order, $item, $user) : $this->kitchen->recall($order, $item, $user);
        } catch (OrderTransitionException $e) {
            return $this->error($e->getMessage(), ['reason' => $e->reason], $e->status);
        }

        foreach ($result['statuses'] as $status) {
            $this->alerts->statusChanged($result['order'], $status->value);
        }

        $fresh = $result['order']->fresh() ?? $result['order'];

        return $this->success(
            $this->presenter->ticket($fresh),
            $result['changed'] ? ($ready ? 'Taom tayyor.' : 'Taom qaytarildi.') : "O'zgarish yo'q."
        );
    }

    /**
     * P3-26: orders of the caller's branches cancelled or rejected in the last $minutes that had reached the kitchen: a
     * POS order with a sent line, an online order that was accepted. Newest last.
     *
     * @return \Illuminate\Support\Collection<int, Order>
     */
    protected function recentlyCanceled(User $user, Request $request, int $minutes, int $limit): \Illuminate\Support\Collection
    {
        $since = now()->subMinutes($minutes);
        $cancelled = [OrderStatus::CANCELED->value, OrderStatus::REJECTED->value];
        $pos = [OrderChannel::POS_HALL->value, OrderChannel::POS_TAKEAWAY->value];

        $query = $this->scope->query($user)
            ->whereIn('orders.status', $cancelled)
            ->whereExists(fn ($history) => $history->selectRaw('1')->from('order_status_histories')
                ->whereColumn('order_status_histories.order_id', 'orders.id')
                ->whereIn('order_status_histories.status', $cancelled)
                ->where('order_status_histories.created_at', '>=', $since))
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->whereIn('orders.channel', $pos)
                    ->whereExists(fn ($lines) => $lines->selectRaw('1')->from('order_items')
                        ->whereColumn('order_items.order_id', 'orders.id')
                        ->whereNotNull('order_items.sent_at')))
                ->orWhere(fn ($query) => $query->whereNotIn('orders.channel', $pos)
                    ->whereExists(fn ($history) => $history->selectRaw('1')->from('order_status_histories')
                        ->whereColumn('order_status_histories.order_id', 'orders.id')
                        ->where('order_status_histories.status', OrderStatus::ACCEPTED->value))));
        if (($branchId = $request->query('branch_id')) !== null) {
            $query->where('orders.branch_id', $this->branch($user, $branchId)->id);
        }

        return $query->with(StaffOrderPresenter::RELATIONS)
            ->orderBy('orders.updated_at')
            ->orderBy('orders.id')
            ->limit(max(0, $limit))
            ->get();
    }

    protected function user(Request $request): User
    {
        $user = $request->user();
        if (!$user instanceof User || !$this->scope->isStoreStaff($user)) {
            throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
        }

        return $user;
    }

    /**
     * The order behind a uuid when the caller holds $permission for it (store, branch, terminal branch); 404 otherwise.
     */
    protected function find(User $user, string $uuid, Permission $permission): Order
    {
        $order = Str::isUuid($uuid) ? Order::where('uuid', $uuid)->first() : null;

        if (!$order || !$this->scope->allows($user, $permission, $order)) {
            throw new HttpResponseException($this->error('Buyurtma topilmadi yoki sizning filialingizga tegishli emas.', [], 404));
        }

        return $order;
    }

    /**
     * The branch filter: a branch of the caller's store (404 otherwise) the caller works in (403 otherwise).
     */
    protected function branch(User $user, mixed $branchId): Branch
    {
        $id = RowId::parse($branchId);
        if ($id === null) {
            throw ValidationException::withMessages(['branch_id' => "Filial noto'g'ri."]);
        }

        $branch = Branch::where('store_id', $user->store_id)->find($id);
        if (!$branch) {
            throw new HttpResponseException($this->error('Filial topilmadi.', [], 404));
        }

        if (!$this->scope->allowsBranch($user, Permission::ORDERS_VIEW, $branch)) {
            throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
        }

        return $branch;
    }

    /**
     * date, or from/to: whole days in the store's time zone, at most MAX_RANGE_DAYS.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Order>  $query
     */
    protected function applyDates(Request $request, User $user, $query): void
    {
        $from = $request->query('date') ?? $request->query('from');
        $to = $request->query('date') ?? $request->query('to');
        if ($from === null && $to === null) {
            return;
        }

        // P2-33: an unknown stored time zone falls back to Asia/Tashkent (as the reports) instead of a 500
        $timezone = \App\Support\StoreTimezone::of($user->store);
        $start = $from !== null ? Carbon::createFromFormat('!Y-m-d', (string) $from, $timezone) : null;
        $end = $to !== null ? Carbon::createFromFormat('!Y-m-d', (string) $to, $timezone)?->addDay() : null;

        if ($start && $end && $start->diffInDays($end) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => "Davr ko'pi bilan " . self::MAX_RANGE_DAYS . " kun bo'lishi mumkin."]);
        }

        if ($start) {
            $query->where('orders.created_at', '>=', $start->utc());
        }
        if ($end) {
            $query->where('orders.created_at', '<', $end->utc());
        }
    }
}
