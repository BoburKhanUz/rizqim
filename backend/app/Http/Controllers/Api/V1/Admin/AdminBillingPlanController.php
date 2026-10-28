<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PlanFeature;
use App\Http\Requests\Admin\AdminRules;
use App\Models\StoreSubscription;
use App\Models\TariffPlan;
use App\Services\Admin\AdminAudit;
use App\Services\Finance\Billing\BillingPresenter;
use App\Support\Money;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * P4-21: the tariff catalog (docs/API.md, "Billing v2"). Read: admin.subscriptions.manage or admin.finance.view.
 * Change: admin.system.manage, a step-up and a `reason`, audited with the plan's fields before and after. A price
 * change reaches the invoices of the months that end after it; the invoices already issued keep their lines.
 */
#[Group('Admin')]
class AdminBillingPlanController extends AdminController
{
    public function __construct(
        private readonly BillingPresenter $presenter,
    ) {}

    /**
     * Tariff catalog
     *
     * The v2 plans (Start, Asosiy, Pro) in catalog order; `all=1` adds the P1-23 plans (version 1: readable, for the
     * stores still on them). Money as decimal strings; `features`: App\Enums\PlanFeature values the plan has (base ones
     * included); `subscriptions`: current subscriptions on the plan. `feature_catalog`: every feature with its label
     * and whether it is a base one (never gated).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{plans: list<' . BillingPresenter::PLAN . '>, feature_catalog: list<array{key: string, label: string, base: bool}>}}')]
    public function index(Request $request): JsonResponse
    {
        $request->validate(['all' => 'nullable|boolean']);

        $plans = TariffPlan::query()
            ->when(!$request->boolean('all'), fn ($query) => $query->where('version', '>=', 2))
            ->orderByDesc('version')->orderBy('sort_order')->orderBy('id')
            ->get();
        $counts = StoreSubscription::query()->current()->selectRaw('tariff_plan_id, COUNT(*) AS n')->groupBy('tariff_plan_id')->pluck('n', 'tariff_plan_id');

        return $this->success([
            'plans' => $plans->map(fn (TariffPlan $plan) => $this->presenter->plan($plan, (int) ($counts[$plan->id] ?? 0)))->values()->all(),
            'feature_catalog' => array_map(fn (PlanFeature $feature) => ['key' => $feature->value, 'label' => $feature->label(), 'base' => $feature->isBase()], PlanFeature::cases()),
        ]);
    }

    /**
     * Change a v2 plan
     *
     * Any of `name`, `description`, `is_active` (an inactive plan cannot be assigned; its stores keep it), `monthly_fee`,
     * `yearly_fee`, `included_pos`, `included_kds`, `extra_pos_fee`, `extra_kds_fee` (so'm, decimal strings or numbers,
     * up to 2 decimals), `trial_days` (0–90, for new subscriptions), `features` (the gated features it has: a list of
     * App\Enums\PlanFeature values; base ones are always on), `sort_order` (its place: higher is an upgrade); `reason`
     * required. P1-23 plans are not changed here (404). Answers the plan.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BillingPresenter::PLAN . '}')]
    #[ApiDocResponse(404, 'No such v2 plan (not_found)', type: 'array{success: false, message: string, code: string, data: array{reason: string}}')]
    public function update(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|min:2|max:100',
            'description' => 'sometimes|nullable|string|max:1000',
            'is_active' => 'sometimes|boolean',
            'monthly_fee' => 'sometimes|numeric|min:0|max:999999999|decimal:0,2',
            'yearly_fee' => 'sometimes|numeric|min:0|max:999999999|decimal:0,2',
            'included_pos' => 'sometimes|integer|min:0|max:100',
            'included_kds' => 'sometimes|integer|min:0|max:100',
            'extra_pos_fee' => 'sometimes|numeric|min:0|max:999999999|decimal:0,2',
            'extra_kds_fee' => 'sometimes|numeric|min:0|max:999999999|decimal:0,2',
            'trial_days' => 'sometimes|integer|min:0|max:90',
            'sort_order' => 'sometimes|integer|min:0|max:10000',
            'features' => 'sometimes|array|max:50',
            'features.*' => ['string', Rule::in(PlanFeature::gatedValues())],
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        $plan = TariffPlan::where('code', $code)->where('version', '>=', 2)->first();
        if ($plan === null) {
            return $this->refuse('Tarif topilmadi.', 'not_found', 404);
        }

        $before = $this->presenter->plan($plan);
        DB::transaction(function () use ($plan, $data) {
            $plan = TariffPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail();
            $update = array_intersect_key($data, array_flip(['name', 'description', 'is_active', 'included_pos', 'included_kds', 'trial_days', 'sort_order']));
            foreach (['monthly_fee' => 'monthly_base_fee', 'yearly_fee' => 'yearly_fee', 'extra_pos_fee' => 'extra_pos_fee', 'extra_kds_fee' => 'extra_kds_fee'] as $field => $column) {
                if (array_key_exists($field, $data)) {
                    $update[$column] = Money::of((string) $data[$field])->toString();
                }
            }
            if (array_key_exists('features', $data)) {
                $update['features'] = array_values(array_unique(array_map('strval', $data['features'])));
            }
            if (isset($update['included_pos']) || isset($update['included_kds'])) {
                $update['included_terminals'] = (int) ($update['included_pos'] ?? $plan->included_pos) + (int) ($update['included_kds'] ?? $plan->included_kds);
            }
            $plan->update($update);
        });
        $plan->refresh();
        $after = $this->presenter->plan($plan);

        unset($before['subscriptions'], $after['subscriptions']);
        AdminAudit::for($request)->subject($plan)->changes($before, $after)->meta(['code' => $plan->code]);
        if ($before === $after) {
            AdminAudit::for($request)->skip();
        }

        return $this->success($this->presenter->plan($plan), 'Tarif saqlandi. Yangi narx keyingi hisob-fakturalardan amal qiladi.');
    }
}
