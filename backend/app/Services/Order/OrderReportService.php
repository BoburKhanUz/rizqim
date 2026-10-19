<?php

namespace App\Services\Order;

use App\Enums\UserRole;
use App\Jobs\SendOrderReport;
use App\Models\Category;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The orders CSV report an owner/manager gets in Telegram. The request only queues it (SendOrderReport); the
 * worker builds it as of the request's time and sends it as a document.
 */
class OrderReportService
{
    // Orders (with their items, products and people) held in memory at once while the CSV is written
    private const CHUNK = 200;

    /**
     * Where the report goes: the user's own chat, else the store's admin chat.
     */
    public function chatIdFor(User $user): int|string|null
    {
        return $user->telegram_id ?: Store::find($user->store_id)?->telegram_admin_chat_id;
    }

    /**
     * @param array $input timeframe (today|weekly|monthly|yearly|custom), start_date, end_date
     */
    public function queue(User $user, int|string $chatId, array $input): void
    {
        // On its own queue (config orders.reports.queue, see SendOrderReport), so a long report never holds up Telegram alerts
        SendOrderReport::dispatch(
            (int) $user->store_id,
            $user->role === UserRole::MANAGER && $user->branch_id ? (int) $user->branch_id : null,
            (string) $input['timeframe'],
            $input['start_date'] ?? null,
            $input['end_date'] ?? null,
            $chatId,
            now()->toImmutable(),
        );
    }

    /**
     * The store's (a branch's) orders updated within the timeframe. A custom timeframe needs both dates (the export
     * endpoint requires them); without them it covers every order.
     *
     * @return Builder<Order>
     */
    public function query(int $storeId, ?int $branchId, string $timeframe, ?string $startDate, ?string $endDate, CarbonInterface $asOf): Builder
    {
        $query = Order::where('store_id', $storeId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        if ($timeframe === 'today') {
            $query->whereDate('updated_at', $asOf->toDateString());
        } elseif ($timeframe === 'weekly') {
            $query->where('updated_at', '>=', $asOf->copy()->subDays(7));
        } elseif ($timeframe === 'monthly') {
            $query->where('updated_at', '>=', $asOf->copy()->subDays(30));
        } elseif ($timeframe === 'yearly') {
            $query->where('updated_at', '>=', $asOf->copy()->subDays(365));
        } elseif ($timeframe === 'custom' && $startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();
            $query->whereBetween('updated_at', [$start, $end]);
        }

        return $query;
    }

    /**
     * The report: the CSV, newest order first, written to a temporary stream (kept on disk past a few MB) a chunk of
     * orders at a time, so a long period never sits in memory at once; and the caption with its totals.
     *
     * @return array{csv: resource, caption: string}
     */
    public function build(int $storeId, ?int $branchId, string $timeframe, ?string $startDate, ?string $endDate, CarbonInterface $asOf): array
    {
        $csv = fopen('php://temp/maxmemory:' . (4 * 1024 * 1024), 'w+b');
        $totals = ['delivered' => 0, 'canceled' => 0, 'revenue' => Money::zero()];

        $orders = $this->query($storeId, $branchId, $timeframe, $startDate, $endDate, $asOf)
            ->with(['items.variant.product.category', 'user', 'courier'])
            ->lazyByIdDesc(self::CHUNK);

        $this->writeCsv($csv, $orders, function (Order $order) use (&$totals) {
            $status = $order->status->value;
            if ($status === 'delivered') {
                $totals['delivered']++;
                $totals['revenue'] = $totals['revenue']->plus(Money::of($order->total_price));
            } elseif ($status === 'canceled') {
                $totals['canceled']++;
            }
        });
        rewind($csv);

        return [
            'csv' => $csv,
            'caption' => $this->caption(Store::find($storeId)?->name, $timeframe, $startDate, $endDate, $totals),
        ];
    }

    /**
     * One row per order with a column of items per category; UTF-8 BOM so Excel reads Uzbek/Russian text.
     *
     * @param resource $file
     * @param iterable<Order> $orders
     * @param callable(Order): void $each called with every order written
     */
    public function writeCsv($file, iterable $orders, callable $each): void
    {
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

        $categories = Category::all();

        // Header columns starting with base columns
        $headers = [
            'Buyurtma ID',
            'Mijoz nomi',
            'Telefon',
            'Sana'
        ];

        // Dynamically add a column for each category
        foreach ($categories as $category) {
            $headers[] = $category->name . " (Mahsulotlar)";
        }

        // Base tail columns
        $headers[] = 'Yetkazish xizmati (UZS)';
        $headers[] = 'Jami Summa (UZS)';
        $headers[] = 'To\'lov Usuli';
        $headers[] = 'Kuryer';
        $headers[] = 'Status';

        fputcsv($file, $headers);

        foreach ($orders as $order) {
            $row = [
                "#" . $order->short_code,
                $order->user->name ?? 'Mijoz',
                $order->user->phone ?? '-',
                $order->created_at->format('Y-m-d H:i')
            ];

            // For each category, collect the items of the order belonging to it
            foreach ($categories as $category) {
                $categoryItems = [];
                foreach ($order->items as $item) {
                    // Fetch category with name-based fallback
                    $itemCategoryName = $item->variant?->product?->category?->name;
                    if (!$itemCategoryName) {
                        $itemCategoryName = (stripos($item->product_name, 'cola') !== false ||
                                             stripos($item->product_name, 'water') !== false ||
                                             stripos($item->product_name, 'fanta') !== false ||
                                             stripos($item->product_name, 'sprite') !== false ||
                                             stripos($item->product_name, 'pepsi') !== false ||
                                             stripos($item->product_name, 'suv') !== false ||
                                             stripos($item->product_name, 'choy') !== false) ? 'Drinks' : 'Burgers';
                    }

                    if (strtolower($itemCategoryName) === strtolower($category->name)) {
                        $variantSuffix = $item->variant_name ? " ({$item->variant_name})" : "";
                        $categoryItems[] = "{$item->quantity}x {$item->product_name}{$variantSuffix} (" . number_format(Money::of($item->total_price)->toFloat(), 0, '.', '') . " UZS)";
                    }
                }
                $row[] = implode("\n", $categoryItems);
            }

            // Append tail items
            $row[] = number_format(Money::of($order->delivery_price ?? 0)->toFloat(), 0, '.', '');
            $row[] = number_format(Money::of($order->total_price)->toFloat(), 0, '.', '');
            $row[] = strtoupper($order->payment_method ?? 'CASH');
            $row[] = $order->courier->name ?? '-';
            $row[] = strtoupper($order->status->value ?? $order->status);

            fputcsv($file, $row);
            $each($order);
        }
    }

    /**
     * Revenue of the delivered orders and the delivered/cancelled counts, as the document's caption.
     *
     * @param array{delivered: int, canceled: int, revenue: Money} $totals
     */
    public function caption(?string $storeName, string $timeframe, ?string $startDate, ?string $endDate, array $totals): string
    {
        $timeframeLabel = $timeframe === 'custom'
            ? $startDate . " dan " . $endDate . " gacha"
            : strtoupper($timeframe);

        return "📊 *" . ($storeName ?? 'Restoran') . " - Hisoboti*\n\n"
            . "📅 *Davr:* {$timeframeLabel}\n"
            . "💰 *Jami tushum:* " . number_format($totals['revenue']->toFloat(), 0, '.', ' ') . " UZS\n"
            . "✅ *Muvaffaqiyatli:* {$totals['delivered']} ta\n"
            . "❌ *Bekor qilingan:* {$totals['canceled']} ta\n\n"
            . "📁 _Barcha ma'lumotlar XLSX (Excel) formatidagi jadvalda biriktirildi._";
    }

    public function filename(string $timeframe, CarbonInterface $asOf): string
    {
        return "report_{$timeframe}_" . $asOf->format('Y_m_d') . ".csv";
    }
}
