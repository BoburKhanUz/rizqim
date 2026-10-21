<?php

namespace App\Services\Order;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Store;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * P2-12: the partner web panel's sales reports (GET /owner/reports/*) and their CSV export, on the same figures as the
 * Mini App's analytics screen (OwnerAnalyticsService builds every aggregate): orders count by the calendar day they
 * were placed, revenue is the total of the delivered ones, money is summed by the database and handled as Money.
 * Days are the store's own timezone (stores.timezone, Asia/Tashkent by default) and a range is at most
 * MAX_RANGE_DAYS long, so no request reads a store's whole history.
 */
class SalesReportService
{
    public const MAX_RANGE_DAYS = 366;

    public const TOP_MAX = 50;

    /** The orders CSV streams at most this many rows; a longer period is refused up front (422). */
    public const EXPORT_MAX_ROWS = 50000;

    private const CHUNK = 500;

    public function __construct(
        private readonly OwnerAnalyticsService $analytics,
    ) {}

    public function timezone(Store $store): string
    {
        return \App\Support\StoreTimezone::of($store); // P2-33: shared with every other store date
    }

    /**
     * The first and last day (Y-m-d, both included) as dates of the store's timezone.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public function range(Store $store, string $from, string $to): array
    {
        $timezone = $this->timezone($store);

        return [
            'from' => CarbonImmutable::createFromFormat('!Y-m-d', $from, $timezone),
            'to' => CarbonImmutable::createFromFormat('!Y-m-d', $to, $timezone),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sales(Store $store, ?int $branchId, CarbonImmutable $first, CarbonImmutable $last, string $groupBy): array
    {
        $orders = fn () => $this->analytics->ordersBetween((int) $store->id, $branchId, $first, $last);
        $totals = $this->analytics->groupTotals($orders());
        $byStatus = $totals['by_status'];
        $canceled = ($byStatus[OrderStatus::CANCELED->value] ?? 0) + ($byStatus[OrderStatus::REJECTED->value] ?? 0);
        $refunded = $byStatus[OrderStatus::REFUNDED->value] ?? 0;

        return [
            'period' => $this->period($store, $first, $last) + ['group_by' => $groupBy],
            'branch_id' => $branchId,
            'summary' => [
                'orders_count' => $totals['orders'],
                'delivered_count' => $totals['delivered'],
                'canceled_count' => $canceled,
                'refunded_count' => $refunded,
                'active_count' => $totals['orders'] - $totals['delivered'] - $canceled - $refunded,
                'revenue' => $totals['revenue']->toString(),
                'average_check' => $this->analytics->average($totals['revenue'], $totals['delivered'])->toString(),
            ],
            'by_status' => $byStatus === [] ? new \stdClass() : $byStatus,
            'series' => array_map(
                fn (array $row) => [...$row, 'revenue' => $row['revenue']->toString()],
                $this->analytics->series($orders(), $first, $last, $groupBy),
            ),
            'by_channel' => array_map(
                fn (array $row) => ['channel' => $row['key'], 'label' => OrderChannel::tryFrom($row['key'])?->label() ?? $row['key'], 'orders' => $row['orders'], 'revenue' => $row['revenue']],
                $this->breakdown($totals['channels']),
            ),
            'by_branch' => array_map(
                fn (array $row) => [...$row, 'revenue' => $row['revenue']->toString()],
                $this->analytics->byBranch($orders()),
            ),
            'by_payment_method' => array_map(
                fn (array $row) => ['method' => $row['key'], 'orders' => $row['orders'], 'revenue' => $row['revenue']],
                $this->breakdown($totals['payments']),
            ),
            'by_order_type' => array_map(
                fn (array $row) => ['type' => $row['key'], 'orders' => $row['orders'], 'revenue' => $row['revenue']],
                $this->breakdown($totals['types']),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function topProducts(Store $store, ?int $branchId, CarbonImmutable $first, CarbonImmutable $last, int $limit, string $sort): array
    {
        $rows = $this->analytics->topProductRows($this->analytics->ordersBetween((int) $store->id, $branchId, $first, $last), $limit, $sort);

        return [
            'period' => $this->period($store, $first, $last),
            'branch_id' => $branchId,
            'sort' => $sort,
            'products' => array_map(fn (array $row) => [
                'name' => $row['name'],
                'quantity' => $row['quantity']->toNumber(),
                'revenue' => $row['revenue']->toString(),
                'orders' => $row['orders'],
            ], $rows),
        ];
    }

    public function countOrders(Store $store, ?int $branchId, CarbonImmutable $first, CarbonImmutable $last): int
    {
        return $this->analytics->ordersBetween((int) $store->id, $branchId, $first, $last)->count();
    }

    /**
     * One row per order, newest first, written a chunk at a time (the caller streams it). Money in UZS with two
     * decimals, times in the store's timezone, UTF-8 with a BOM so Excel reads Uzbek and Russian text.
     *
     * @param  resource  $out
     */
    public function writeOrdersCsv($out, Store $store, ?int $branchId, CarbonImmutable $first, CarbonImmutable $last): void
    {
        $timezone = $this->timezone($store);
        $this->bom($out);
        $this->row($out, [
            'Buyurtma', 'Sana', 'Filial', 'Kanal', 'Turi', 'Holati', "To'lov usuli", "To'lov holati",
            'Taomlar (UZS)', 'Chegirma (UZS)', 'Yetkazib berish (UZS)', 'Xizmat haqi (UZS)', 'Joy narxi (UZS)', 'Jami (UZS)', 'Kuryer',
        ]);

        $rows = $this->analytics->ordersBetween((int) $store->id, $branchId, $first, $last)
            ->leftJoin('branches', 'branches.id', '=', 'orders.branch_id')
            ->leftJoin('users as couriers', 'couriers.id', '=', 'orders.courier_id')
            ->select([
                'orders.id', 'orders.short_code', 'orders.created_at', 'orders.channel', 'orders.order_type', 'orders.status',
                'orders.payment_method', 'orders.payment_status', 'orders.subtotal', 'orders.discount', 'orders.delivery_price',
                'orders.service_charge_amount', 'orders.place_fee_amount', 'orders.total_price',
                'branches.name as branch_name', 'couriers.name as courier_name',
            ])
            ->lazyByIdDesc(self::CHUNK, 'orders.id', 'id');

        foreach ($rows as $order) {
            $this->row($out, [
                '#' . ($order->short_code ?? $order->id),
                CarbonImmutable::parse((string) $order->created_at, 'UTC')->setTimezone($timezone)->format('Y-m-d H:i'),
                $order->branch_name ?? '',
                OrderChannel::tryFrom((string) $order->channel)?->label() ?? (string) $order->channel,
                (string) $order->order_type,
                OrderStatus::tryFrom((string) $order->status)?->label() ?? (string) $order->status,
                (string) ($order->payment_method ?? ''),
                (string) ($order->payment_status ?? ''),
                Money::of($order->subtotal ?? null)->toString(),
                Money::of($order->discount ?? null)->toString(),
                Money::of($order->delivery_price ?? null)->toString(),
                Money::of($order->service_charge_amount ?? null)->toString(),
                Money::of($order->place_fee_amount ?? null)->toString(),
                Money::of($order->total_price ?? null)->toString(),
                $order->courier_name ?? '',
            ]);
        }
    }

    /**
     * The sales series (one row per day, week or month) and a total row.
     *
     * @param  resource  $out
     */
    public function writeSalesCsv($out, Store $store, ?int $branchId, CarbonImmutable $first, CarbonImmutable $last, string $groupBy): void
    {
        $report = $this->sales($store, $branchId, $first, $last, $groupBy);
        $this->bom($out);
        $this->row($out, ['Davr', 'Buyurtmalar', 'Yetkazilgan', 'Tushum (UZS)']);
        foreach ($report['series'] as $row) {
            $this->row($out, [$row['date'], (string) $row['orders'], (string) $row['delivered'], $row['revenue']]);
        }
        $summary = $report['summary'];
        $this->row($out, ['Jami', (string) $summary['orders_count'], (string) $summary['delivered_count'], $summary['revenue']]);
    }

    /**
     * @param  resource  $out
     */
    public function writeProductsCsv($out, Store $store, ?int $branchId, CarbonImmutable $first, CarbonImmutable $last, string $sort): void
    {
        $report = $this->topProducts($store, $branchId, $first, $last, self::TOP_MAX, $sort);
        $this->bom($out);
        $this->row($out, ['Taom', 'Miqdor', 'Buyurtmalar', 'Tushum (UZS)']);
        foreach ($report['products'] as $product) {
            $this->row($out, [$product['name'], (string) $product['quantity'], (string) $product['orders'], $product['revenue']]);
        }
    }

    public function filename(string $report, CarbonImmutable $first, CarbonImmutable $last): string
    {
        return sprintf('rizqim_%s_%s_%s.csv', $report, $first->format('Ymd'), $last->format('Ymd'));
    }

    /**
     * @return array{from: string, to: string, days: int, timezone: string, basis: string}
     */
    private function period(Store $store, CarbonImmutable $first, CarbonImmutable $last): array
    {
        return [
            'from' => $first->toDateString(),
            'to' => $last->toDateString(),
            'days' => (int) $first->diffInDays($last) + 1,
            'timezone' => $this->timezone($store),
            'basis' => 'created_at',
        ];
    }

    /**
     * @param  array<string, array{orders: int, revenue: Money}>  $buckets
     * @return list<array{key: string, orders: int, revenue: string}>
     */
    private function breakdown(array $buckets): array
    {
        $rows = [];
        foreach ($buckets as $key => $bucket) {
            $rows[] = ['key' => (string) $key, 'orders' => $bucket['orders'], 'revenue' => $bucket['revenue']];
        }
        usort($rows, fn (array $a, array $b) => $b['revenue']->compareTo($a['revenue']) ?: $b['orders'] <=> $a['orders']);

        return array_map(fn (array $row) => [...$row, 'revenue' => $row['revenue']->toString()], $rows);
    }

    /**
     * @param  resource  $out
     */
    private function bom($out): void
    {
        fwrite($out, "\xEF\xBB\xBF");
    }

    /**
     * A CSV line; a cell a spreadsheet would read as a formula (=, +, -, @, tab, CR) is quoted with a leading ' so a
     * product or branch name can never run in the owner's Excel.
     *
     * @param  resource  $out
     * @param  list<string>  $cells
     */
    private function row($out, array $cells): void
    {
        fputcsv($out, array_map(function (string $cell): string {
            return $cell !== '' && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($cell) ? "'" . $cell : $cell;
        }, $cells), ',', '"', '');
    }
}
