<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Kitchen\PrepTimeReport;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Enums\Permission;
use App\Models\KitchenStation;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

#[Group('Owner: reports', "Savdo hisobotlari: davr, eng ko'p sotilganlar, kanal va filial bo'yicha, CSV eksport (P2-12).")]
class PrepTimeReportController extends ManagementController
{
    /**
     * Kitchen prep time report (tayyorlash vaqti)
     *
     * P6-33 (KDS v2): how long the kitchen took for the lines that reached it in the branch's business days `from`..`to`
     * (YYYY-MM-DD, default today; at most 31 days; the branch's day start, the store's time zone): `count`, `median_seconds`,
     * `p90_seconds`, and against the time targets `with_target`, `over_target`, `over_target_share` (percent, a string),
     * for the whole branch (`summary`), per station, per dish (busiest first) and per local hour (0–23, all 24 rows).
     * A line counts from when it reached the kitchen (`sent_at`; an online line: the order's acceptance) to its
     * `ready_at` bump; voided lines never. `orders`: each order's first line in to its kitchen done (the expo's
     * assembly, else its last line ready). `expo`: the expo station's assembly time (last line ready → assembled), null
     * without an expo station. `station_id` narrows the lines (not `orders`). Needs reports.view and the module `kds`.
     */
    #[QueryParameter('branch_id', description: 'Required unless the caller is bound to a branch.', type: 'int')]
    #[QueryParameter('from', description: 'First business day, YYYY-MM-DD (default: today).', type: 'string')]
    #[QueryParameter('to', description: 'Last business day, YYYY-MM-DD (default: from; at most 31 days).', type: 'string')]
    #[QueryParameter('station_id', description: "Only this station's lines (a station of the branch).", type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PrepTimeReport::SHAPE . '}')]
    public function show(Request $request, PrepTimeReport $report): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'station_id' => 'nullable|integer|min:1|max:999999999999999999',
        ], [
            'to.after_or_equal' => "Oxirgi kun birinchi kundan oldin bo'lmasin.",
        ]);
        $branch = $this->branch($request, Permission::REPORTS_VIEW);

        $from = $data['from'] ?? BusinessDay::of($branch);
        $to = $data['to'] ?? $from;
        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) >= PrepTimeReport::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'Hisobot ko\'pi bilan ' . PrepTimeReport::MAX_DAYS . ' kunlik bo\'lishi mumkin.']);
        }

        $stationId = isset($data['station_id']) ? (int) $data['station_id'] : null;
        if ($stationId !== null && !KitchenStation::where('branch_id', $branch->id)->whereKey($stationId)->exists()) {
            throw ValidationException::withMessages(['station_id' => 'Sex topilmadi (u shu filialniki bo\'lishi kerak).']);
        }

        return $this->success($report->build($branch, $from, $to, $stationId));
    }
}
