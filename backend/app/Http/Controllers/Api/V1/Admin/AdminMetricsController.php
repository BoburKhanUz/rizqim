<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Admin\Metrics\ApplicationFunnel;
use App\Services\Admin\Metrics\MetricsMonths;
use App\Services\Admin\Metrics\MetricsReport;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * P4-31: Ko'rsatkichlar (admin.finance.view or admin.stores.view): MRR and how it moves, churn, active stores, GMV and
 * commission per month, and the application → active conversion funnel. Months are Asia/Tashkent months. Read-only;
 * the definitions are in docs/API.md "Ko'rsatkichlar".
 */
#[Group('Admin')]
class AdminMetricsController extends AdminController
{
    public function __construct(
        private readonly MetricsReport $report,
        private readonly ApplicationFunnel $funnel,
    ) {}

    /**
     * Metrics
     *
     * `months` — how many months back (3–24, default 12; the last one is the month in progress, `partial: true`).
     * `cards`: `mrr` (monthly recurring revenue now: active subscriptions' monthly price, a yearly one divided by 12,
     * extra terminals included, trials left out) with `mrr_change` against the start of the month, `arr` (= MRR × 12),
     * `paying_stores`, `trialing_stores`, `arpa` (MRR per paying store), `active_stores`, `churn_rate` of the latest
     * finished month (`churn_month`), `gmv_month` and `commission_month` of the month so far. `series`: per month the
     * MRR at its end, paying, trialing and active stores. `movement`: per month `opening` → `new` / `expansion` /
     * `contraction` / `churned` (`mrr` and the number of `stores`) → `closing`. `churn`: per month the stores active at
     * the start, how many churned (went inactive, or their subscription ended), `rate` and `revenue_rate` in percent.
     * `revenue`: per month marketplace `orders`, `gmv`, `commission` and paid subscription invoices from the Bugun
     * aggregate, with `missing_days` / `complete` when the aggregate lacks days. Money is a decimal string. Cached a few
     * minutes (`generated_at`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{timezone: string, generated_at: string, months: int, cards: array{mrr: string, mrr_change: array{direction: string, amount: string, percent: string|null}, arr: string, paying_stores: int, trialing_stores: int, arpa: string|null, active_stores: int, churn_rate: string|null, churn_month: string|null, gmv_month: string, commission_month: string, revenue_complete: bool}, series: list<array{month: string, partial: bool, mrr: string, paying_stores: int, trialing_stores: int, active_stores: int}>, movement: list<array{month: string, partial: bool, opening: string, closing: string, new: array{mrr: string, stores: int}, expansion: array{mrr: string, stores: int}, contraction: array{mrr: string, stores: int}, churned: array{mrr: string, stores: int}, net_new: string, paying_stores: int, trialing_stores: int}>, churn: list<array{month: string, partial: bool, active_at_start: int, active_at_end: int, churned: int, went_inactive: int, subscription_ended: int, rate: string|null, revenue_rate: string|null}>, revenue: list<array{month: string, partial: bool, orders: int, gmv: string, commission: string, subscription_revenue: string, missing_days: int, complete: bool}>}}')]
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'months' => ['nullable', 'integer', 'min:' . MetricsMonths::MIN, 'max:' . MetricsMonths::MAX],
            'refresh' => ['nullable', 'boolean'],
        ], UzValidation::messages());
        $months = (int) ($data['months'] ?? MetricsMonths::DEFAULT);

        // "Yangilash": the member asks for the numbers as they are now
        if (filter_var($data['refresh'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            Cache::forget(MetricsReport::cacheKey($months));
        }

        return $this->success($this->report->overview($months));
    }

    /**
     * Conversion funnel
     *
     * Store applications created in the last `months` months (3–24, default 12) by pipeline stage: `reached` — how many
     * got to the stage or further, `share` of all applications in percent, `median_days` spent in the stage before
     * moving on (null while nobody has). Totals: `applications`, `active`, `rejected`, `open`, `conversion` (active ÷
     * all, percent) and `median_days_to_active`. Stage moves are read from the audit log.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{months: int, from: string, applications: int, active: int, rejected: int, open: int, conversion: string|null, median_days_to_active: float|null, stages: list<array{stage: string, label: string, reached: int, share: string|null, median_days: float|null, samples: int}>}}')]
    public function funnel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'months' => ['nullable', 'integer', 'min:' . MetricsMonths::MIN, 'max:' . MetricsMonths::MAX],
        ], UzValidation::messages());

        return $this->success($this->funnel->build((int) ($data['months'] ?? MetricsMonths::DEFAULT)));
    }
}
