<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Takeaway\RoundTheClock;
use App\Enums\Permission;
use App\Support\RowId;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * P6-34 (module `round_the_clock`): the business-day report of a branch that never closes ("24/7: biznes kunlar"):
 * each business day from its start (business_day_starts_at) to the next, its orders, money, packaging, the orders opened
 * after midnight and the shifts (Z) opened on it. reports.view; never from a terminal PIN session.
 */
#[Group('Owner: packaging')]
class BranchBusinessDayReportController extends ManagementController
{
    /**
     * Business days of a branch
     *
     * `from` / `to` (YYYY-MM-DD business dates, at most 31 days; default: the last 7 days to today's business day).
     * `orders`: POS orders by their business day, online ones by when they were placed (cancelled and rejected left
     * out); `revenue` the total of those not refunded; `after_midnight_orders`: opened after midnight (their calendar
     * date is the next day); `shifts`: opened on that business day (their Z is that day's, wherever it closed).
     */
    #[QueryParameter('from', description: 'YYYY-MM-DD, the first business day.', type: 'string', required: false)]
    #[QueryParameter('to', description: 'YYYY-MM-DD, the last business day (at most 31 days after from).', type: 'string', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{branch_id: int, business_day: ' . RoundTheClock::BUSINESS_DAY . ', days: list<' . RoundTheClock::REPORT_ROW . '>}}')]
    public function index(Request $request, string $branch, RoundTheClock $roundTheClock): JsonResponse
    {
        $model = $this->branch($request, Permission::REPORTS_VIEW, RowId::parse($branch));
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $today = $roundTheClock->businessDay($model);
        $to = $data['to'] ?? $today['business_date'];
        $from = $data['from'] ?? CarbonImmutable::createFromFormat('!Y-m-d', $to)->subDays(6)->format('Y-m-d');
        if ($from > $to) {
            throw ValidationException::withMessages(['from' => "Boshlanish sanasi tugash sanasidan keyin bo'lmasligi kerak."]);
        }
        if (CarbonImmutable::createFromFormat('!Y-m-d', $from)->diffInDays(CarbonImmutable::createFromFormat('!Y-m-d', $to)) >= RoundTheClock::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => "Davr ko'pi bilan " . RoundTheClock::MAX_DAYS . ' kun bo\'lishi kerak.']);
        }

        return $this->success([
            'branch_id' => (int) $model->id,
            'business_day' => $today,
            'days' => $roundTheClock->report($model, $from, $to),
        ]);
    }
}
