<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Services\Audit\AuditLogger;
use App\Services\Finance\Reports\KeshbekLiabilityExport;
use App\Services\Finance\Reports\KeshbekLiabilityReport;
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
 * audit log (admin.finance.report_exported). Keshbek: the customers' keshbek balances (the platform's liability), the
 * month's movements and their reconciliation (App\Services\Finance\Reports\KeshbekLiabilityReport), with its own
 * Excel export (admin.finance.keshbek_report_exported).
 */
#[Group('Admin')]
class AdminFinanceReportController extends AdminController
{
    private const REPORT = 'array{period: string, timezone: string, totals: array{orders: int, gmv: string, commission: string, commission_refunded: string, subscription: string, total: string, referral_count: int, referral_bonuses: string, net: string}, channels: list<array{channel: string, label: string, orders: int, gmv: string, commission: string, commission_refunded: string}>, subscriptions: array{invoices: int, invoiced: string, paid: string, outstanding: string}, stores: list<array{store: array{id: int, name: string}, orders: int, gmv: string, commission: string, commission_by_channel: array<string, string>, subscription: string, total: string}>}';

    private const KESHBEK = 'array{period: string, timezone: string, as_of: string, issued: array{count: int, amount: string}, clawed_back: array{count: int, amount: string}, legacy_store_paid: array{count: int, amount: string}, spent: array{count: int, amount: string}, returned: array{count: int, amount: string}, transferred: array{count: int, amount: string}, outstanding: array{positive: string, negative: string, net: string, holders: int, debtors: int}, reconciliation: array{opening: string, movement: string, closing: string, after_period: string, expected_now: string, live: string, difference: string}, unrecorded: array{keshbek_orders_without_payment: int, invalid_payload_values: int}}';

    public function __construct(
        private readonly MonthlyRevenueReport $report,
        private readonly MonthlyRevenueExport $export,
        private readonly KeshbekLiabilityReport $keshbekReport,
        private readonly KeshbekLiabilityExport $keshbekExport,
    ) {}

    /**
     * Monthly revenue report
     *
     * `period` — "YYYY-MM", an Asia/Tashkent month (default: the current one). Commission: the orders delivered in
     * the month that are still delivered, less what partial refunds took back; by channel (`unknown` — orders from
     * before the channel was recorded). Subscriptions: the month's invoices that are not void. Stores — largest total
     * first. Owner decision 3: `referral_bonuses` — the referral bonuses RIZQIM paid out of its own income in the month
     * (`referral_count` of them), its cost; `net` = `total` − `referral_bonuses`. Amounts are decimal strings.
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

    /**
     * Keshbek report
     *
     * `period` — "YYYY-MM", an Asia/Tashkent month (default: the current one). The month's keshbek movements, each
     * `{count, amount}`: `issued` — referral bonuses the platform granted (store_transactions `referral_bonus`,
     * amount > 0); `clawed_back` — those taken back (amount < 0); `legacy_store_paid` — the bonuses stores paid before
     * owner decision 3's addition (`commission` rows with the referral note; not the platform's); `spent` — keshbek
     * payments (payments.provider `keshbek`, by created_at, whatever their status now); `returned` — what refunds gave
     * back to the balance (the keshbek payment's refunded_to_balance_parts and refunded_to_balance; a payment written
     * at the refund of a legacy order whose debit left no row, `backfilled_without_debit_record`, counts as spent at
     * the order's placing and as returned at the refund); `transferred` — balances moved from one account to another
     * (an invite row merged into the account that accepted it; a debt counts negative): net 0 platform-wide, not part
     * of the movement below. `reconciliation`:
     * `opening` (movements before the month) + `movement` = `closing` (month end, from movements); + `after_period` =
     * `expected_now`; `live` = SUM(users.balance) now; `difference` = `live` − `expected_now`, 0 while every balance
     * change is recorded. `outstanding` — positive balances (the liability) and negative ones (debts) now, not at the
     * month end. `unrecorded.keshbek_orders_without_payment` — keshbek orders with no keshbek payment row (a standing
     * one's debit is unrecorded, the difference shows −total; a refund writes the missing row, so it drops out);
     * `unrecorded.invalid_payload_values` — payload values of keshbek payments the report could not read (an amount
     * that is not a plain decimal, a time that is not ISO 8601 with an offset or Z): an unreadable amount is not
     * counted, an unreadable time falls back to the payment's created_at. One database snapshot for the whole report.
     * Amounts are decimal strings.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::KESHBEK . '}')]
    public function keshbek(Request $request): JsonResponse
    {
        return $this->success($this->presentKeshbek($this->keshbekReport->build($this->period($request))));
    }

    /**
     * Keshbek report (Excel)
     *
     * The same report as an .xlsx workbook (sheet "Keshbek"). Needs admin.export as well; each export is recorded in
     * the audit log.
     */
    public function keshbekExport(Request $request, AuditLogger $audit): BinaryFileResponse
    {
        $period = $this->period($request);
        $report = $this->keshbekReport->build($period);

        $path = tempnam(sys_get_temp_dir(), 'rizqim-keshbek-');
        if ($path === false) {
            abort(500, "Faylni tayyorlab bo'lmadi.");
        }
        $this->keshbekExport->write($report, $path);

        $audit->record('admin.finance.keshbek_report_exported', null, [], [], [
            'period' => $period,
            'outstanding' => $report['outstanding']['positive']->toString(),
            'difference' => $report['reconciliation']['difference']->toString(),
        ], [
            'actor' => $this->member($request),
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return response()->download($path, "rizqim-keshbek-{$period}.xlsx", [
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
     * @param  array<string, mixed>  $report  KeshbekLiabilityReport::build()
     * @return array<string, mixed>
     */
    private function presentKeshbek(array $report): array
    {
        $money = fn (Money $amount) => $amount->toString();
        $source = fn (array $row) => ['count' => $row['count'], 'amount' => $money($row['amount'])];

        return [
            'period' => $report['period'],
            'timezone' => $report['timezone'],
            'as_of' => $report['as_of'],
            'issued' => $source($report['issued']),
            'clawed_back' => $source($report['clawed_back']),
            'legacy_store_paid' => $source($report['legacy_store_paid']),
            'spent' => $source($report['spent']),
            'returned' => $source($report['returned']),
            'transferred' => $source($report['transferred']),
            'outstanding' => [
                'positive' => $money($report['outstanding']['positive']),
                'negative' => $money($report['outstanding']['negative']),
                'net' => $money($report['outstanding']['net']),
                'holders' => $report['outstanding']['holders'],
                'debtors' => $report['outstanding']['debtors'],
            ],
            'reconciliation' => array_map($money, $report['reconciliation']),
            'unrecorded' => $report['unrecorded'],
        ];
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
            'totals' => ['orders' => $report['totals']['orders'], 'referral_count' => $report['totals']['referral_count']]
                + array_map($money, array_diff_key($report['totals'], ['orders' => 0, 'referral_count' => 0])),
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
