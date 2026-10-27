<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Order;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\AdminPii;
use App\Services\Admin\Orders\AdminOrderPresenter;
use App\Services\Admin\Orders\AdminOrderQuery;
use App\Services\Admin\Orders\OrderSlaSettings;
use App\Services\Admin\Orders\StoreQualityService;
use App\Http\Requests\Admin\AdminRules;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-14: Buyurtmalar (admin.orders.view): the marketplace orders of every store (the Telegram bot and Mini App, the
 * mobile app; POS and aggregator orders stay out), each with its SLA state computed on the server; the order card; store
 * quality; the SLA limits (changed with admin.system.manage and a step-up).
 */
#[Group('Admin')]
class AdminOrderController extends AdminController
{
    private const ROW = 'array{uuid: string, short_code: string|null, status: string, status_label: string, status_group: string, type: string, channel: string, channel_label: string, store: array{id: int, name: string}|null, branch: array{id: int, name: string}|null, region: array{id: int, name: string}|null, customer_name: string|null, total: string, payment_method: string, payment_status: string, courier: array{id: int, name: string}|null, sla: array{stage: string, stage_label: string, state: string, limit_minutes: int, started_at: string, warning_at: string, due_at: string, late_by_minutes: int, minutes_left: int}|null, refund_pending: bool, manual_refund_required: bool, created_at: string|null, updated_at: string|null}';

    public function __construct(
        private readonly AdminOrderQuery $orders,
        private readonly AdminOrderPresenter $presenter,
        private readonly OrderSlaSettings $sla,
        private readonly AdminPii $pii,
    ) {}

    /**
     * Orders (all stores)
     *
     * Newest first, cursor pages (`cursor` from `next_cursor`, `per_page` 1–100, default 30). Filters: `status` — a
     * status group: `active` (not finished), `new`, `kitchen` (accepted, preparing, ready), `en_route` (courier
     * assigned, on the way), `done` (delivered, refunded), `canceled` (canceled, rejected); `channel` (telegram,
     * mobile); `region_id` (the branch's region); `store_id`; `sla` — `late` or `warning` (the running
     * stage at 80–100% of its limit); `from` / `to` (dates in Asia/Tashkent, inclusive); `q` — the short code (with or
     * without #), the start of the uuid, or the customer's phone: 4+ of its digits with admin.pii.view, otherwise only
     * the whole number (+998 and nine digits, or the nine digits). `counts` has every group under the same filters
     * (done and canceled count today only when no from/to is given). `sla` on each row: null when no stage runs
     * (waiting for an online payment, ready, with a courier not yet on the way, finished). `manual_refund_required`:
     * a cancelled, rejected or refunded order with money still to give back (`refunds.refundable` > 0 on the card).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::ROW . '>, next_cursor: string|null, per_page: int, counts: array{active: int, new: int, kitchen: int, en_route: int, done: int, canceled: int}, sla_limits: array{accept: int, prepare: int, deliver: int}, server_time: string}}')]
    public function index(Request $request): JsonResponse
    {
        $id = 'nullable|integer|min:1|max:999999999999999999';
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(AdminOrderQuery::GROUPS))],
            'channel' => ['nullable', Rule::in(AdminOrderQuery::channels())],
            'region_id' => $id,
            'store_id' => $id,
            'sla' => ['nullable', Rule::in(['late', 'warning'])],
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'q' => 'nullable|string|min:2|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
            'cursor' => 'nullable|string|max:500',
        ], UzValidation::messages());
        $limits = $this->sla->limits();
        // A part of a phone only for those who may see phones; everyone else finds an order by the whole number
        $phoneFragments = $this->pii->canReveal($this->member($request));

        $query = $this->orders->filter($this->orders->base(), $filters, $limits, true, $phoneFragments);
        $this->orders->withStageTimes($query);
        AdminOrderPresenter::withRefundPending($query);

        $page = $query
            ->with(AdminOrderPresenter::LIST_RELATIONS)
            ->orderByDesc('orders.created_at')
            ->orderByDesc('orders.id')
            ->cursorPaginate($request->integer('per_page', 30) ?: 30)
            ->withQueryString();

        $now = now();

        return $this->success([
            'data' => $this->presenter->rows($page->items(), $limits, $now),
            'next_cursor' => $page->nextCursor()?->encode(),
            'per_page' => $page->perPage(),
            'counts' => $this->orders->counts($filters, $limits, $phoneFragments),
            'sla_limits' => $limits,
            'server_time' => $now->toIso8601String(),
        ]);
    }

    /**
     * Order card
     *
     * Everything about one marketplace order: status history with who did it, items, totals, payments and refund
     * requests (`refunds.refundable` = paid − refunded), courier, customer, store and branch (phones masked unless
     * admin.pii.view; every reveal is audited), fiscal receipts, the running SLA, the SLA timeline per stage and the
     * recorded SLA crossings, the audit entries about the order, and `actions` — what this member may do now.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(404, 'Not a marketplace order', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $order = $this->find($uuid);
        if ($order === null) {
            return $this->notFound();
        }

        return $this->success($this->presenter->card($order, $this->member($request), $this->sla->limits()));
    }

    /**
     * Store quality (last 30 days)
     *
     * Per store: `orders` that reached the store (not an unpaid online order, not one the customer cancelled while new),
     * `acceptance_rate` (0–1), `avg_accept_minutes` (cash and keshbek orders, checkout → accepted by staff),
     * `avg_prep_minutes` (accepted → ready), `cancel_rate` (0–1), the store's `rating` and the ratings given in the
     * period. With `store_id`: that store; without: up to 50 stores with orders in the period, the weakest first.
     * Cached 5 minutes.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{period: array{from: string, to: string, days: int}, stores: list<array{store: array{id: int, name: string}, orders: int, accepted: int, acceptance_rate: float|null, avg_accept_minutes: float|null, avg_prep_minutes: float|null, canceled: int, cancel_rate: float|null, rating: string|null, review_count: int, ratings_in_period: int, rating_in_period: float|null}>}}')]
    public function storeQuality(Request $request, StoreQualityService $quality): JsonResponse
    {
        $data = $request->validate([
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
        ], UzValidation::messages());

        return $this->success($quality->report(isset($data['store_id']) ? (int) $data['store_id'] : null));
    }

    /**
     * SLA limits
     *
     * Minutes for each stage: `accept` (checkout → accepted), `prepare` (accepted → ready), `deliver` (on the way →
     * delivered); `warning_ratio` — a stage is "warning" from this share of its limit, "late" from the limit.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{limits: array{accept: int, prepare: int, deliver: int}, defaults: array{accept: int, prepare: int, deliver: int}, bounds: array<string, array{int, int}>, warning_ratio: float}}')]
    public function sla(): JsonResponse
    {
        return $this->success($this->slaPayload());
    }

    /**
     * Change the SLA limits
     *
     * Any of `accept` (1–120), `prepare` (5–300), `deliver` (5–300) minutes, and `reason`. admin.system.manage and a
     * step-up. Takes effect at once: the list, the attention rows and the next watcher run use the new limits.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{limits: array{accept: int, prepare: int, deliver: int}, defaults: array{accept: int, prepare: int, deliver: int}, bounds: array<string, array{int, int}>, warning_ratio: float}}')]
    public function updateSla(Request $request): JsonResponse
    {
        $rules = ['reason' => AdminRules::reason()];
        foreach (OrderSlaSettings::BOUNDS as $stage => [$min, $max]) {
            $rules[$stage] = ['sometimes', 'required', 'integer', "min:{$min}", "max:{$max}"];
        }
        $data = $request->validate($rules, UzValidation::messages());

        $minutes = array_intersect_key($data, OrderSlaSettings::KEYS);
        $change = $this->sla->update(array_map('intval', $minutes));

        $audit = AdminAudit::for($request)->changes($change['before'], $change['after']);
        if ($change['before'] === $change['after']) {
            $audit->skip();
        }

        return $this->success($this->slaPayload(), 'SLA chegaralari saqlandi.');
    }

    /**
     * @return array{limits: array{accept: int, prepare: int, deliver: int}, defaults: array<string, int>, bounds: array<string, array{int, int}>, warning_ratio: float}
     */
    private function slaPayload(): array
    {
        return [
            'limits' => $this->sla->limits(),
            'defaults' => OrderSlaSettings::DEFAULTS,
            'bounds' => OrderSlaSettings::BOUNDS,
            'warning_ratio' => OrderSlaSettings::WARNING_RATIO,
        ];
    }

    private function find(string $uuid): ?Order
    {
        return $this->orders->base()->where('orders.uuid', $uuid)->first();
    }

    private function notFound(): JsonResponse
    {
        return $this->refuse('Buyurtma topilmadi.', 'not_found', 404);
    }
}
