<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Audit\AuditLogger;
use App\Services\Finance\Reports\MonthlyRevenueExport;
use App\Services\Finance\Reports\MonthlyRevenueReport;
use App\Support\Money;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * P4-23: Moliya → Oylik hisobot: RIZQIM's revenue for a month (commission by channel and subscriptions) and per store
 * (App\Services\Finance\Reports\MonthlyRevenueReport); the Excel export also needs admin.export and is recorded in the
 * audit log (admin.finance.report_exported).
 */
#[Group('Admin')]
class AdminFinanceReportController extends AdminController
{
    private const REPORT = 'array{period: string, timezone: string, totals: array{orders: int, gmv: string, commission: string, commission_refunded: string, subscription: string, total: string}, channels: list<array{channel: string, label: string, orders: int, gmv: string, commission: string, commission_refunded: string}>, subscriptions: array{invoices: int, invoiced: string, paid: string, outstanding: string}, stores: list<array{store: array{id: int, name: string}, orders: int, gmv: string, commission: string, commission_by_channel: array<string, string>, subscription: string, total: string}>}';

    public function __construct(
        private readonly MonthlyRevenueReport $report,
        private readonly MonthlyRevenueExport $export,
    ) {}

    /**
     * Monthly revenue report
     *
     * `period` — "YYYY-MM", an Asia/Tashkent month (default: the current one). Commission: the orders delivered in
     * the month that are still delivered, less what partial refunds took back; by channel (`unknown` — orders from
     * before the channel was recorded). Subscriptions: the month's invoices that are not void. Stores — largest total
     * first. Amounts are decimal strings.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::REPORT . '}')]
    public function monthly(Request $request): JsonResponse
    {
        $period = $this->period($request);

        return $this->success($this->present($this->report->build($period)));
    }

    /**
     * Monthly revenue report (Excel)
     *
     * The same report as an .xlsx workbook ("Umumiy" and "Ovqatxonalar" sheets). Needs admin.export as well; each
     * export is recorded in the audit log.
     */
    public function export(Request $request, AuditLogger $audit): BinaryFileResponse
    {
        $period = $this->period($request);
        $report = $this->report->build($period);

        $path = tempnam(sys_get_temp_dir(), 'rizqim-report-');
        if ($path === false) {
            abort(500, "Faylni tayyorlab bo'lmadi.");
        }
        $this->export->write($report, $path);

        $audit->record('admin.finance.report_exported', null, [], [], [
            'period' => $period,
            'stores' => count($report['stores']),
            'total' => $report['totals']['total']->toString(),
        ], [
            'actor' => $this->member($request),
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return response()->download($path, "rizqim-daromad-{$period}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend();
    }

    private function period(Request $request): string
    {
        $data = $request->validate([
            'period' => ['nullable', 'string', 'regex:/^20\d{2}-(0[1-9]|1[0-2])$/'],
        ], UzValidation::messages(['period.regex' => "Oy YYYY-MM ko'rinishida bo'lishi kerak (masalan 2026-10)."]));

        return $data['period'] ?? Carbon::now($this->report->timezone())->format('Y-m');
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function present(array $report): array
    {
        $money = fn (Money $amount) => $amount->toString();

        return [
            'period' => $report['period'],
            'timezone' => $report['timezone'],
            'totals' => ['orders' => $report['totals']['orders']] + array_map($money, array_diff_key($report['totals'], ['orders' => 0])),
            'channels' => array_map(fn (array $row) => [
                'channel' => $row['channel'],
                'label' => $row['label'],
                'orders' => $row['orders'],
                'gmv' => $money($row['gmv']),
                'commission' => $money($row['commission']),
                'commission_refunded' => $money($row['commission_refunded']),
            ], $report['channels']),
            'subscriptions' => ['invoices' => $report['subscriptions']['invoices']] + array_map($money, array_diff_key($report['subscriptions'], ['invoices' => 0])),
            'stores' => array_map(fn (array $row) => [
                'store' => ['id' => $row['store_id'], 'name' => $row['store_name']],
                'orders' => $row['orders'],
                'gmv' => $money($row['gmv']),
                'commission' => $money($row['commission']),
                'commission_by_channel' => array_map($money, $row['commission_by_channel']),
                'subscription' => $money($row['subscription']),
                'total' => $money($row['total']),
            ], $report['stores']),
        ];
    }
}
