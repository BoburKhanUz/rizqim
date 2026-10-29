<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Integrations\IntegrationEventDirection;
use App\Domain\Integrations\IntegrationEventStatus;
use App\Services\Admin\Integrations\IntegrationEvents;
use App\Services\Admin\Integrations\StuckIntegrationOrders;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-31 Integratsiyalar → Hodisalar jurnali and Qotib qolganlar (admin.integrations.view), read-only. Request and
 * response bodies go through AuditRedactor. "Qayta yuborish" comes with the phase 5 adapters.
 */
#[Group('Admin')]
class AdminIntegrationEventController extends AdminController
{
    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    public function __construct(
        private readonly IntegrationEvents $events,
        private readonly StuckIntegrationOrders $stuck,
    ) {}

    /**
     * Integration events
     *
     * Newest first, by cursor (`cursor` from `next_cursor`, `per_page` 1-100, default 25). Filters: `connection` (uuid),
     * `store_id`, `status` (pending, sent, acknowledged, failed, dead), `type` (order.push, menu.sync, webhook.status,
     * ...), `direction` (inbound, outbound), `from` / `to` (Y-m-d, Tashkent). The rows have no bodies; the event does.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . IntegrationEvents::ROW . '>, next_cursor: string|null, per_page: int}}')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'connection' => 'nullable|uuid',
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'status' => ['nullable', Rule::enum(IntegrationEventStatus::class)],
            'type' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_.]+$/'],
            'direction' => ['nullable', Rule::enum(IntegrationEventDirection::class)],
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'cursor' => 'nullable|string|max:500',
            'per_page' => 'nullable|integer|min:1|max:100',
        ], UzValidation::messages());

        $page = $this->events->query($filters)->orderByDesc('id')->cursorPaginate((int) ($filters['per_page'] ?? 25));

        return $this->success([
            'data' => $this->events->rows($page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
            'per_page' => $page->perPage(),
        ]);
    }

    /**
     * An integration event
     *
     * The row plus the idempotency key, the signature check, the last attempt and the request and response bodies
     * (keys that look secret "[redacted]", phone numbers masked).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . IntegrationEvents::DETAIL . '}')]
    #[ApiDocResponse(404, 'No such event (not_found)', type: self::ERROR)]
    public function show(string $uuid): JsonResponse
    {
        $event = $this->events->find($uuid);

        return $event === null ? $this->refuse('Hodisa topilmadi.', 'not_found', 404) : $this->success($this->events->detail($event));
    }

    /**
     * Stuck orders
     *
     * Orders pushed to an outside POS and not confirmed for 2 minutes while still open, the oldest first (at most
     * 100; `count` is all of them). Empty until phase 5.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{count: int, data: list<' . StuckIntegrationOrders::ROW . '>, stuck_after_minutes: int}}')]
    public function stuck(): JsonResponse
    {
        return $this->success([...$this->stuck->list(), 'stuck_after_minutes' => StuckIntegrationOrders::STUCK_AFTER_MINUTES]);
    }
}
