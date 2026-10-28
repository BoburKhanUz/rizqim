<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Services\Admin\Stores\StoreDirectory;
use App\Services\Admin\Stores\StoreLabels;
use App\Services\Admin\Stores\StorePresenter;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-22: Ovqatxonalar (admin.stores.view): the list of every store with its health, plan and debt; the 360° card; the
 * store and region pickers (also for Buyurtmalar, admin.orders.view). No impersonation (owner decision, 28.10.2026):
 * support sees the store's data here and the owner changes their own settings.
 */
#[Group('Admin')]
class AdminStoreController extends AdminController
{
    private const ROW = 'array{id: int, uuid: string, name: string, status: string, status_label: string, is_active: bool, is_premium: bool, is_featured: bool, region: array{id: int, name: string}|null, branches_count: int, owner: array{id: int, name: string, telegram_linked: bool}|null, plan: array{code: string, name: string, status: string, status_label: string}|null, debt_balance: string, subscription_debt: string, commission_debt: string, credit_limit: string, over_limit: bool, health: array{level: string, label: string, score: int, reasons: int, top_reason: string|null, computed_at: string|null}|null, next_contact_on: string|null, created_at: string|null}';

    public function __construct(
        private readonly StoreDirectory $directory,
        private readonly StorePresenter $presenter,
    ) {}

    /**
     * Stores
     *
     * Pages (`page`, `per_page` 1–100, default 30). Filters: `q` (name, STIR, the store's or owner's whole phone, uuid
     * start), `status`, `region_id` (any branch there), `health` (good, attention, churn_risk, none), `plan` (a tariff
     * code or none), `debt` (has_debt, over_limit: the commission debt over the credit limit), `next_contact=due`; `sort`: newest (default), name, debt, health.
     * `health_counts`: stores per level under the same filters (but health). Rows carry no phone. `credit_limit` is the
     * debt the store may have (a positive amount). Health is the hourly snapshot (the card recomputes it).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::ROW . '>, current_page: int, last_page: int, per_page: int, total: int, health_counts: array<string, int>, statuses: array<string, string>}}')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => 'nullable|string|min:2|max:100',
            'status' => ['nullable', Rule::enum(StoreStatus::class)],
            'region_id' => 'nullable|integer|min:1|max:999999999999999999',
            'health' => ['nullable', Rule::in(['good', 'attention', 'churn_risk', 'none'])],
            'plan' => 'nullable|string|max:64',
            'debt' => ['nullable', Rule::in(['has_debt', 'over_limit'])],
            'next_contact' => ['nullable', Rule::in(['due'])],
            'sort' => ['nullable', Rule::in(StoreDirectory::SORTS)],
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ], UzValidation::messages());

        $query = $this->directory->query($filters);
        $this->directory->sort($query, $filters['sort'] ?? 'newest');

        $page = $query->with(['branches' => fn ($branches) => $branches->select(['id', 'store_id', 'region_id'])->orderBy('id'), 'branches.region:id,name_uz'])
            ->withCount('branches')
            ->paginate($request->integer('per_page', 30) ?: 30)
            ->withQueryString();

        return $this->success([
            'data' => $this->presenter->rows($page->items()),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'health_counts' => $this->directory->healthCounts($filters),
            'statuses' => StoreLabels::statuses(),
        ]);
    }

    /**
     * Store card (360°)
     *
     * Everything about one store: `profile`, `application` (its onboarding, if it came through the pipeline),
     * `contacts` (owner and managers, the applicant; phones masked unless admin.pii.view, reveals audited),
     * `branches`, `status_history` (StoreStatusLog, who and why), `plan` (subscription overview; `source` says which
     * billing model answered: `billing_v2` — P4-21's store billing, as GET /admin/billing/stores/{id}: subscription,
     * entitlements, debt, commission per channel, history, invoices), `devices` (terminals online in the last 10 minutes), `fiscal`, `finance` (debt split into the
     * subscription and the commission part, wallet, credit limit, payouts with masked cards, the last ledger entries), `orders` (30-day quality as in
     * Buyurtmalar, per channel, the latest), `complaints`, `health` (computed now, with reasons), `crm` (next contact,
     * notes, tasks), `audit` (last 50 entries), `actions` (what this member may do) and `links`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array<string, mixed>}')]
    #[ApiDocResponse(404, 'No such store', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function show(Request $request, int $id): JsonResponse
    {
        $store = Store::query()->find($id);
        if ($store === null) {
            return $this->refuse('Ovqatxona topilmadi.', 'not_found', 404);
        }

        return $this->success($this->presenter->card($store, $this->member($request)));
    }

    /**
     * Store and region picker
     *
     * `q` (optional): up to 20 stores by name (or STIR, whole phone) and the active regions whose name matches; `ids[]`:
     * those stores (to show a chosen filter's name). Without both: the first 20 stores by name and every active region.
     * `districts_of` (a region id): its active districts too (else `districts` is empty). admin.stores.view or
     * admin.orders.view.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{stores: list<array{id: int, name: string, status: string, region: string|null}>, regions: list<array{id: int, name: string}>, districts: list<array{id: int, name: string}>}}')]
    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'ids' => 'nullable|array|max:20',
            'ids.*' => 'integer|min:1|max:999999999999999999',
            'districts_of' => 'nullable|integer|min:1|max:999999999999999999',
        ], UzValidation::messages());

        return $this->success($this->directory->lookup($data['q'] ?? null, array_map('intval', $data['ids'] ?? []), isset($data['districts_of']) ? (int) $data['districts_of'] : null));
    }
}
