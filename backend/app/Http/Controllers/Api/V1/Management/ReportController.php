<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Enums\Permission;
use App\Models\Store;
use App\Services\Order\SalesReportService;
use App\Support\UzValidation;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * P2-12: sales reports for the partner web panel (reports.view, with the store's overrides): sales by period, best
 * sellers, by channel and by branch, and their CSV download. The same figures as GET /owner/analytics
 * (SalesReportService on OwnerAnalyticsService), with dates in the store's timezone and money as strings.
 * A branch-bound manager sees their own branch only.
 */
#[Group('Owner: reports', "Savdo hisobotlari: davr, eng ko'p sotilganlar, kanal va filial bo'yicha, CSV eksport (P2-12).")]
class ReportController extends ManagementController
{
    private const PERIOD = 'array{from: string, to: string, days: int, timezone: string, basis: string}';

    /**
     * Sales for a period
     *
     * `from` and `to` (YYYY-MM-DD, both included, at most 366 days) are calendar days of the store's timezone; an
     * order counts on the day it was placed. `revenue` is the total of the delivered orders. `series` has one row per
     * `group_by` bucket (day; week from Monday; month), zeros included. `branch_id` narrows to a branch (a branch-bound
     * manager always gets theirs). Money is a string with two decimals.
     */
    #[QueryParameter('from', description: 'Birinchi kun (YYYY-MM-DD).', type: 'string')]
    #[QueryParameter('to', description: 'Oxirgi kun (YYYY-MM-DD), ko\'pi bilan 366 kun.', type: 'string')]
    #[QueryParameter('branch_id', description: 'Filial.', type: 'int', required: false)]
    #[QueryParameter('group_by', description: 'day | week | month (standart day).', type: 'string', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{period: array{from: string, to: string, days: int, timezone: string, basis: string, group_by: string}, branch_id: int|null, summary: array{orders_count: int, delivered_count: int, canceled_count: int, refunded_count: int, active_count: int, revenue: string, average_check: string}, by_status: array<string, int>, series: list<array{date: string, orders: int, delivered: int, revenue: string}>, by_channel: list<array{channel: string, label: string, orders: int, revenue: string}>, by_branch: list<array{branch_id: int|null, name: string|null, orders: int, delivered: int, revenue: string}>, by_payment_method: list<array{method: string, orders: int, revenue: string}>, by_order_type: list<array{type: string, orders: int, revenue: string}>}}')]
    public function sales(Request $request, SalesReportService $reports): JsonResponse
    {
        [$store, $branchId, $first, $last] = $this->scope($request, $reports, SalesReportService::MAX_RANGE_DAYS, [
            'group_by' => 'nullable|string|in:day,week,month',
        ]);

        return $this->success($reports->sales($store, $branchId, $first, $last, (string) $request->input('group_by', 'day')));
    }

    /**
     * Best sellers for a period
     *
     * Among the delivered orders, by the name the item had when sold: `sort` quantity (default) or revenue, `limit`
     * 1–50 (default 10). `quantity` is a whole number for pieces and a decimal for kg / l.
     */
    #[QueryParameter('from', description: 'Birinchi kun (YYYY-MM-DD).', type: 'string')]
    #[QueryParameter('to', description: 'Oxirgi kun (YYYY-MM-DD), ko\'pi bilan 366 kun.', type: 'string')]
    #[QueryParameter('branch_id', description: 'Filial.', type: 'int', required: false)]
    #[QueryParameter('limit', description: '1–50, standart 10.', type: 'int', required: false)]
    #[QueryParameter('sort', description: 'quantity | revenue.', type: 'string', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{period: ' . self::PERIOD . ', branch_id: int|null, sort: string, products: list<array{name: string, quantity: int|float, revenue: string, orders: int}>}}')]
    public function topProducts(Request $request, SalesReportService $reports): JsonResponse
    {
        [$store, $branchId, $first, $last] = $this->scope($request, $reports, SalesReportService::MAX_RANGE_DAYS, [
            'limit' => 'nullable|integer|between:1,' . SalesReportService::TOP_MAX,
            'sort' => 'nullable|string|in:quantity,revenue',
        ]);

        return $this->success($reports->topProducts($store, $branchId, $first, $last, $request->integer('limit', 10), (string) $request->input('sort', 'quantity')));
    }

    /**
     * Download a report as CSV
     *
     * `report`: `orders` (one row per order, at most 93 days and 50 000 orders), `sales` (the `group_by` series and a
     * total row, at most 366 days) or `products` (the top 50, `sort` as in top-products). Streamed as
     * `text/csv; charset=UTF-8` with a BOM; money in UZS with two decimals; times in the store's timezone.
     * Too many orders for one file: 422, pick a shorter period. Rate limited (`report-csv`).
     */
    #[QueryParameter('report', description: 'orders | sales | products.', type: 'string')]
    #[QueryParameter('from', description: 'Birinchi kun (YYYY-MM-DD).', type: 'string')]
    #[QueryParameter('to', description: 'Oxirgi kun (YYYY-MM-DD).', type: 'string')]
    #[QueryParameter('branch_id', description: 'Filial.', type: 'int', required: false)]
    #[QueryParameter('group_by', description: 'sales uchun: day | week | month.', type: 'string', required: false)]
    #[QueryParameter('sort', description: 'products uchun: quantity | revenue.', type: 'string', required: false)]
    #[ApiDocResponse(200, type: 'string', mediaType: 'text/csv', description: 'CSV fayl (UTF-8, BOM bilan)')]
    #[ApiDocResponse(422, type: 'array{success: false, message: string, data: array{reason: string, limit: int}}', description: 'Davrda juda ko\'p buyurtma (`too_many_rows`)')]
    public function export(Request $request, SalesReportService $reports): StreamedResponse|JsonResponse
    {
        $report = (string) $request->input('report');
        $maxDays = $report === 'orders' ? (int) config('orders.reports.max_custom_days', 93) : SalesReportService::MAX_RANGE_DAYS;

        [$store, $branchId, $first, $last] = $this->scope($request, $reports, $maxDays, [
            'report' => 'required|string|in:orders,sales,products',
            'group_by' => 'nullable|string|in:day,week,month',
            'sort' => 'nullable|string|in:quantity,revenue',
        ]);

        if ($report === 'orders' && $reports->countOrders($store, $branchId, $first, $last) > SalesReportService::EXPORT_MAX_ROWS) {
            return $this->error(
                "Bu davrda buyurtmalar juda ko'p (" . SalesReportService::EXPORT_MAX_ROWS . " tadan ortiq). Qisqaroq davr tanlang.",
                ['reason' => 'too_many_rows', 'limit' => SalesReportService::EXPORT_MAX_ROWS],
                422,
            );
        }

        $groupBy = (string) $request->input('group_by', 'day');
        $sort = (string) $request->input('sort', 'quantity');

        return response()->streamDownload(function () use ($reports, $report, $store, $branchId, $first, $last, $groupBy, $sort) {
            $out = fopen('php://output', 'wb');
            match ($report) {
                'orders' => $reports->writeOrdersCsv($out, $store, $branchId, $first, $last),
                'sales' => $reports->writeSalesCsv($out, $store, $branchId, $first, $last, $groupBy),
                default => $reports->writeProductsCsv($out, $store, $branchId, $first, $last, $sort),
            };
            fclose($out);
        }, $reports->filename($report, $first, $last), [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Validates the period (and the endpoint's own fields) and resolves the store, the branch and the two days.
     *
     * @param  array<string, string>  $extra
     * @return array{0: Store, 1: int|null, 2: CarbonImmutable, 3: CarbonImmutable}
     */
    private function scope(Request $request, SalesReportService $reports, int $maxDays, array $extra): array
    {
        $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to' => [
                'required', 'date_format:Y-m-d', 'after_or_equal:from',
                function (string $attribute, mixed $value, \Closure $fail) use ($request, $maxDays) {
                    $from = $request->input('from');
                    if (!is_string($from) || !is_string($value) || !CarbonImmutable::hasFormat($from, 'Y-m-d') || !CarbonImmutable::hasFormat($value, 'Y-m-d')) {
                        return;
                    }
                    if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($value)) + 1 > $maxDays) {
                        $fail("Davr ko'pi bilan {$maxDays} kun bo'lishi mumkin.");
                    }
                },
            ],
            'branch_id' => UzValidation::rowId(),
        ] + $extra, UzValidation::messages([
            'from.required' => 'Davrning boshlanish sanasini kiriting.',
            'to.required' => 'Davrning tugash sanasini kiriting.',
            'from.date_format' => "Boshlanish sanasi noto'g'ri (YYYY-MM-DD).",
            'to.date_format' => "Tugash sanasi noto'g'ri (YYYY-MM-DD).",
            'to.after_or_equal' => "Tugash sanasi boshlanish sanasidan oldin bo'lishi mumkin emas.",
            'report.required' => 'Hisobot turini tanlang (orders, sales yoki products).',
        ]), [
            'branch_id' => 'Filial',
            'group_by' => 'Guruhlash',
            'limit' => 'Soni',
            'sort' => 'Saralash',
            'report' => 'Hisobot turi',
        ]);

        $store = $this->currentStore($request);
        $branch = $this->branchFilter($request, Permission::REPORTS_VIEW);
        $range = $reports->range($store, (string) $request->input('from'), (string) $request->input('to'));

        return [$store, $branch?->id !== null ? (int) $branch->id : null, $range['from'], $range['to']];
    }
}
