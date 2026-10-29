<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Integrations\IntegrationConnection;
use App\Domain\Integrations\IntegrationConnectionStatus;
use App\Domain\Integrations\IntegrationDirection;
use App\Domain\Integrations\IntegrationMode;
use App\Domain\Integrations\IntegrationProvider;
use App\Http\Requests\Admin\AdminRules;
use App\Enums\AdminPermission;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\Integrations\IntegrationConnections;
use App\Services\Admin\Integrations\IntegrationEvents;
use App\Services\Admin\Integrations\StuckIntegrationOrders;
use App\Services\Admin\Pos\PosPage;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-31 Integratsiyalar → Ulanishlar (admin.integrations.view): every store's connections to outside systems and a
 * connection's card. Pausing and resuming: admin.integrations.manage, a step-up and a `reason`. Credentials are never
 * answered: names, "configured" and, for a member who manages integrations, the last 4 characters of a long one.
 */
#[Group('Admin')]
class AdminIntegrationConnectionController extends AdminController
{
    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    private const CARD = 'array{credentials: list<array{key: string, configured: bool, last4: string|null}>, settings: array<string, mixed>, recent_events: list<' . IntegrationEvents::ROW . '>, menu_map: array{total: int, by_type: array<string, int>}, stuck_orders: int}';

    public function __construct(
        private readonly IntegrationConnections $connections,
        private readonly IntegrationEvents $events,
        private readonly StuckIntegrationOrders $stuck,
        private readonly AdminPermissionService $permissions,
    ) {}

    /**
     * Connections
     *
     * Pages (`page`, `per_page` 1-100, default 30), the least healthy first. Filters: `status` (configuring, active,
     * paused, disconnected), `provider` (iiko, alipos, yandex_eats, uzum_tezkor, partner_api, ...), `direction`
     * (rizqim_to_pos, aggregator_to_rizqim), `mode` (sandbox, production), `health` (good: score 80+, attention: 50-79,
     * disconnected: under 50, unknown: no score yet), `store_id`, `q` (store or branch name). `counts` ignore the
     * filters. Empty until phase 5 connects the first system.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . IntegrationConnections::ROW . '>, ' . PosPage::SHAPE . ', counts: array{total: int, configuring: int, active: int, paused: int, disconnected: int, attention: int}, stuck_orders: int}}')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(IntegrationConnectionStatus::class)],
            'provider' => ['nullable', Rule::enum(IntegrationProvider::class)],
            'direction' => ['nullable', Rule::enum(IntegrationDirection::class)],
            'mode' => ['nullable', Rule::enum(IntegrationMode::class)],
            'health' => ['nullable', Rule::in(['good', 'attention', 'disconnected', 'unknown'])],
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'q' => 'nullable|string|min:2|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ], UzValidation::messages());

        $page = $this->connections->query($filters)
            ->orderByRaw('health_score asc nulls last')->orderByDesc('id')
            ->paginate(PosPage::perPage($filters['per_page'] ?? null));

        return $this->success(PosPage::of($page, array_map(fn (IntegrationConnection $connection) => $this->connections->row($connection), $page->items()), [
            'counts' => $this->connections->counts(),
            'stuck_orders' => $this->stuck->query()->count(),
        ]));
    }

    /**
     * A connection
     *
     * The list row plus `credentials` (key names, `configured`, `last4`: the last 4 characters of a credential of 16 or more, only with `admin.integrations.manage`, else null), `settings` (secret-looking keys
     * "[redacted]", phones masked), the last 10 events, the menu map counts (`external_refs` by type) and how many of
     * its orders are stuck.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . IntegrationConnections::ROW . '&' . self::CARD . '}')]
    #[ApiDocResponse(404, 'No such connection (not_found)', type: self::ERROR)]
    public function show(Request $request, string $uuid): JsonResponse
    {
        $connection = $this->connections->find($uuid);

        return $connection === null ? $this->notFound() : $this->success($this->card($request, $connection));
    }

    /**
     * Pause a connection
     *
     * `reason`. Nothing is sent to or taken from the outside system while it is paused (phase 5 adapters check it).
     * Pausing a paused one changes nothing. Answers the card.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . IntegrationConnections::ROW . '&' . self::CARD . '}')]
    public function pause(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $connection = $this->connections->find($uuid);
        if ($connection === null) {
            return $this->notFound();
        }

        $before = $connection->status->value;
        $changed = $this->connections->pause($connection, $this->member($request), trim($data['reason']));
        $this->audit($request, $connection, $before, $changed);

        return $this->success($this->card($request, $this->connections->find($uuid) ?? $connection), "Ulanish to'xtatildi.");
    }

    /**
     * Resume a connection
     *
     * `reason`. Back to the status it had before the pause. Resuming one that is not paused changes nothing.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . IntegrationConnections::ROW . '&' . self::CARD . '}')]
    public function resume(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $connection = $this->connections->find($uuid);
        if ($connection === null) {
            return $this->notFound();
        }

        $before = $connection->status->value;
        $changed = $this->connections->resume($connection);
        $this->audit($request, $connection, $before, $changed);

        return $this->success($this->card($request, $this->connections->find($uuid) ?? $connection), 'Ulanish qayta yoqildi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Request $request, IntegrationConnection $connection): array
    {
        // The last 4 characters of a long credential: only for a member who manages integrations
        return $this->connections->card($connection, $this->events, $this->stuck, $this->permissions->has($this->member($request), AdminPermission::INTEGRATIONS_MANAGE));
    }

    private function audit(Request $request, IntegrationConnection $connection, string $before, bool $changed): void
    {
        $audit = AdminAudit::for($request)->subject($connection)->store((int) $connection->store_id)
            ->changes(['status' => $before], ['status' => $connection->status->value])
            ->meta(['provider' => $connection->provider->value]);
        if (!$changed) {
            $audit->skip();
        }
    }

    private function notFound(): JsonResponse
    {
        return $this->refuse('Ulanish topilmadi.', 'not_found', 404);
    }
}
