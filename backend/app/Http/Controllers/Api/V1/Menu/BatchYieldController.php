<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Domain\Pos\Menu\BatchYieldReport;
use App\Domain\Pos\Menu\DailyMenuService;
use App\Enums\Permission;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

#[Group('Owner: batches', "Qozon / partiya qoldig'i: ochish, to'ldirish, tuzatish, yopish (P1-21); keyingi partiya vaqti va hisobdan chiqarish (P6-15). Oshpaz, menejer, ega.")]
class BatchYieldController extends MenuStaffController
{
    /**
     * Yield report (hosil hisoboti)
     *
     * P6-15, module `batches`: for the batches started between two business days (`from`, `to`, YYYY-MM-DD; default
     * today; at most 93 days) what was cooked (`produced`), sold, written off, what is left and the unexplained
     * `variance`, per batch (`batches`) and per day and dish (`days`); `write_offs` adds the written-off portions by
     * reason and `in_sets` the portions of dishes sold inside a kompleks. Quantities are numbers in the batch's unit
     * (portions, kg); percents are strings. Needs reports.view or batches.manage.
     */
    #[QueryParameter('branch_id', description: 'Required unless the caller is bound to a branch.', type: 'int')]
    #[QueryParameter('from', description: 'First business day, YYYY-MM-DD (default: today).', type: 'string')]
    #[QueryParameter('to', description: 'Last business day, YYYY-MM-DD (default: from).', type: 'string')]
    public function show(Request $request, BatchYieldReport $report, DailyMenuService $days): JsonResponse
    {
        $data = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ]);
        $permission = $this->permissions->has($request->user(), Permission::REPORTS_VIEW) ? Permission::REPORTS_VIEW : Permission::BATCHES_MANAGE;
        $branch = $this->branch($request, $permission);

        $from = $data['from'] ?? $days->today($branch);
        $to = $data['to'] ?? $from;
        if (\Carbon\CarbonImmutable::parse($from)->diffInDays(\Carbon\CarbonImmutable::parse($to)) >= BatchYieldReport::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'Hisobot ko\'pi bilan ' . BatchYieldReport::MAX_DAYS . ' kunlik bo\'lishi mumkin.']);
        }

        return $this->success($report->build($branch, $from, $to));
    }
}
