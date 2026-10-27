<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Alert;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Monitoring\AlertEvaluator;
use App\Services\Admin\Monitoring\AlertKind;
use App\Services\Admin\Monitoring\AlertState;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-15: Monitoring → Ogohlantirishlar. Reading needs admin.monitoring.view; acknowledging and resolving
 * admin.monitoring.manage. Alerts are opened and closed by the evaluator (App\Services\Admin\Monitoring\AlertEvaluator),
 * never created through the API.
 */
#[Group('Admin')]
class AdminMonitoringAlertController extends AdminController
{
    /** The unresolved set is small (one per rule and subject); it is returned whole, up to this many. */
    private const ACTIVE_LIMIT = 200;

    public function __construct(
        private readonly AlertEvaluator $evaluator,
    ) {}

    /**
     * Alerts
     *
     * `state`: `active` (default: open and acknowledged together), `open`, `acknowledged`, `resolved` or `all`.
     * Unresolved alerts come whole (up to 200): open before acknowledged, critical before warning, newest first. With
     * `resolved` or `all` the history is paged, newest first (`cursor` from `next_cursor`, `per_page` 1–100, default
     * 25). Filters: `kind`, `severity`, `store_id`. `counts`: open, acknowledged and resolved in the last 7 days.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<array{id: int, kind: string, kind_label: string, severity: string, state: string, title: string, details: array<string, mixed>|null, store: array{id: int, name: string}|null, first_seen_at: string, last_seen_at: string, count: int, acknowledged: array{by: array{id: int, name: string}|null, at: string, note: string|null}|null, resolved: array{by: array{id: int, name: string}|null, at: string, note: string|null, resolution: string|null}|null}>, next_cursor: string|null, prev_cursor: string|null, per_page: int, counts: array{open: int, acknowledged: int, resolved_7d: int}}}')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'state' => ['nullable', Rule::in(['active', 'all', ...AlertState::values()])],
            'kind' => ['nullable', Rule::in(AlertKind::values())],
            'severity' => ['nullable', Rule::in(['critical', 'warning', 'info'])],
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'per_page' => 'nullable|integer|min:1|max:100',
            'cursor' => 'nullable|string|max:500',
        ], UzValidation::messages());

        $state = $filters['state'] ?? 'active';
        $query = Alert::query()
            ->with(['store:id,name', 'acknowledgedBy:id,name', 'resolvedBy:id,name'])
            ->when($request->filled('kind'), fn (Builder $q) => $q->where('kind', $filters['kind']))
            ->when($request->filled('severity'), fn (Builder $q) => $q->where('severity', $filters['severity']))
            ->when($request->filled('store_id'), fn (Builder $q) => $q->where('store_id', $request->integer('store_id')));

        if (in_array($state, ['active', AlertState::OPEN->value, AlertState::ACKNOWLEDGED->value], true)) {
            $query->when($state === 'active', fn (Builder $q) => $q->unresolved(), fn (Builder $q) => $q->where('state', $state))
                ->orderByDesc('state') // open > acknowledged
                ->orderByRaw("case severity when 'critical' then 0 when 'warning' then 1 else 2 end")
                ->orderByDesc('id');
            $alerts = $query->limit(self::ACTIVE_LIMIT)->get();
            $page = ['next_cursor' => null, 'prev_cursor' => null, 'per_page' => self::ACTIVE_LIMIT];
        } else {
            $query->when($state === AlertState::RESOLVED->value, fn (Builder $q) => $q->where('state', AlertState::RESOLVED->value))
                ->orderByDesc('id');
            $paginator = $query->cursorPaginate($request->integer('per_page', 25))->withQueryString();
            $alerts = collect($paginator->items());
            $page = [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'per_page' => $paginator->perPage(),
            ];
        }

        return $this->success([
            'data' => $alerts->map(fn (Alert $alert) => $this->present($alert))->values()->all(),
            ...$page,
            'counts' => [
                'open' => Alert::query()->where('state', AlertState::OPEN->value)->count(),
                'acknowledged' => Alert::query()->where('state', AlertState::ACKNOWLEDGED->value)->count(),
                'resolved_7d' => Alert::query()->where('state', AlertState::RESOLVED->value)->where('resolved_at', '>=', now()->subDays(7))->count(),
            ],
        ]);
    }

    /**
     * One alert
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int, kind: string, kind_label: string, severity: string, state: string, title: string, details: array<string, mixed>|null, store: array{id: int, name: string}|null, first_seen_at: string, last_seen_at: string, count: int, acknowledged: array{by: array{id: int, name: string}|null, at: string, note: string|null}|null, resolved: array{by: array{id: int, name: string}|null, at: string, note: string|null, resolution: string|null}|null}}')]
    #[ApiDocResponse(404, 'No such alert', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function show(int $id): JsonResponse
    {
        $alert = $this->find($id);

        return $alert === null ? $this->notFound() : $this->success($this->present($alert));
    }

    /**
     * Acknowledge an alert
     *
     * "I am on it": the alert leaves the Bugun attention list and stays open for everyone else with the member's name
     * on it. Optional `note` (up to 500 characters). Only an `open` alert can be acknowledged (409 `alert_not_open`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int, kind: string, state: string}}')]
    #[ApiDocResponse(409, 'The alert is not open any more (alert_not_open)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function acknowledge(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|min:2|max:500'], UzValidation::messages());
        $alert = $this->find($id);
        if ($alert === null) {
            return $this->notFound();
        }
        if ($alert->state !== AlertState::OPEN->value) {
            return $this->refuse('Bu ogohlantirish allaqachon qabul qilingan yoki hal bo\'lgan.', 'alert_not_open', 409);
        }

        $before = $alert->state;
        $this->evaluator->acknowledge($alert, $this->member($request), $data['note'] ?? null);

        AdminAudit::for($request)->subject($alert)->store($alert->store_id)
            ->changes(['state' => $before], ['state' => $alert->state])
            ->meta(['kind' => $alert->kind, 'note' => $data['note'] ?? null]);

        return $this->success($this->present($alert->refresh()), 'Ogohlantirish qabul qilindi.');
    }

    /**
     * Resolve an alert
     *
     * Marks it done by hand; optional `note`. If the problem is still there the next minute's check opens a new alert.
     * The ops group is told (when its open message went out). 409 `alert_resolved` if it is already resolved.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int, kind: string, state: string}}')]
    #[ApiDocResponse(409, 'Already resolved (alert_resolved)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function resolve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => 'nullable|string|min:2|max:500'], UzValidation::messages());
        $alert = $this->find($id);
        if ($alert === null) {
            return $this->notFound();
        }
        if ($alert->isResolved()) {
            return $this->refuse('Bu ogohlantirish allaqachon hal bo\'lgan.', 'alert_resolved', 409);
        }

        $before = $alert->state;
        $this->evaluator->resolveByMember($alert, $this->member($request), $data['note'] ?? null);

        AdminAudit::for($request)->subject($alert)->store($alert->store_id)
            ->changes(['state' => $before], ['state' => $alert->state])
            ->meta(['kind' => $alert->kind, 'note' => $data['note'] ?? null]);

        return $this->success($this->present($alert->refresh()), 'Ogohlantirish hal qilindi.');
    }

    private function find(int $id): ?Alert
    {
        return Alert::query()->with(['store:id,name', 'acknowledgedBy:id,name', 'resolvedBy:id,name'])->find($id);
    }

    private function notFound(): JsonResponse
    {
        return $this->refuse('Ogohlantirish topilmadi.', 'not_found', 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Alert $alert): array
    {
        return [
            'id' => (int) $alert->id,
            'kind' => $alert->kind,
            'kind_label' => AlertKind::tryFrom($alert->kind)?->label() ?? $alert->kind,
            'severity' => $alert->severity,
            'state' => $alert->state,
            'title' => $alert->title,
            'details' => $alert->details,
            'store' => $alert->store ? ['id' => (int) $alert->store->id, 'name' => (string) $alert->store->name] : null,
            'first_seen_at' => $alert->first_seen_at->toIso8601String(),
            'last_seen_at' => $alert->last_seen_at->toIso8601String(),
            'count' => (int) $alert->count,
            'acknowledged' => $alert->acknowledged_at ? [
                'by' => $alert->acknowledgedBy ? ['id' => (int) $alert->acknowledgedBy->id, 'name' => (string) $alert->acknowledgedBy->name] : null,
                'at' => $alert->acknowledged_at->toIso8601String(),
                'note' => $alert->acknowledge_note,
            ] : null,
            'resolved' => $alert->resolved_at ? [
                'by' => $alert->resolvedBy ? ['id' => (int) $alert->resolvedBy->id, 'name' => (string) $alert->resolvedBy->name] : null,
                'at' => $alert->resolved_at->toIso8601String(),
                'note' => $alert->resolve_note,
                'resolution' => $alert->resolution,
            ] : null,
        ];
    }
}
