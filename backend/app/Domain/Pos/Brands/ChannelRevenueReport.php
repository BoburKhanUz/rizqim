<?php

namespace App\Domain\Pos\Brands;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Brand;
use App\Models\ChannelCommission;
use App\Models\Store;
use App\Support\Money;
use App\Support\StoreTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * P6-55: the channel net revenue report ("Kanal bo'yicha sof tushum"): per period × channel × brand — orders, gross,
 * discounts, refunds, commission and net. Rules (docs/API.md "Ko'p brend"):
 *
 *  - Orders: the store's (or one branch's) orders placed in the period (store time zone days, compared in UTC as the
 *    sales report does) that were sold: `delivered` (closed) or later `refunded`.
 *  - gross = what the order cost before its discount (total_price + discount, so delivery, service, packaging lines
 *    included); discounts = its discount; refunds = the POS refunds of the order (pos_refunds), or the whole total of an
 *    order refunded without one; commission = the owner's percent for the channel × total_price when the owner set one
 *    (an aggregator's, ChannelCommissions), else the order's own RIZQIM commission snapshot (orders.commission_amount,
 *    docs/BILLING.md "Komissiya kanal bo'yicha"); net = gross − discounts − refunds − commission.
 *  - Brand split: an order with lines of several brands is split by each brand's share of its non-void line totals
 *    (the last brand takes the rounding remainder, so the brand rows add up to the order exactly); lines without a
 *    brand (the house brand) are `brand_id` null. `orders` counts an order once in every brand it has lines of; the
 *    channel and total rows count each order once.
 */
final class ChannelRevenueReport
{
    public const MAX_RANGE_DAYS = 366;

    /**
     * @return array<string, mixed>
     */
    public function build(Store $store, ?int $branchId, string $from, string $to): array
    {
        $timezone = StoreTimezone::of($store);
        $first = CarbonImmutable::createFromFormat('!Y-m-d', $from, $timezone);
        $last = CarbonImmutable::createFromFormat('!Y-m-d', $to, $timezone);
        $start = $first->startOfDay()->utc()->format('Y-m-d H:i:s');
        $end = $last->addDay()->startOfDay()->utc()->format('Y-m-d H:i:s');

        $orders = DB::table('orders')
            ->where('store_id', $store->id)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereNull('deleted_at')
            ->whereIn('status', [OrderStatus::DELIVERED->value, OrderStatus::REFUNDED->value])
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->orderBy('id')
            ->get(['id', 'channel', 'status', 'total_price', 'discount', 'commission_amount']);

        $ids = $orders->pluck('id')->map(fn ($id) => (int) $id)->all();
        $lines = [];
        $refunds = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (DB::table('order_items')->whereIn('order_id', $chunk)->whereNull('deleted_at')->whereNull('voided_at')
                ->selectRaw('order_id, brand_id, SUM(total_price) AS total')->groupBy('order_id', 'brand_id')->orderBy('order_id')->orderByRaw('COALESCE(brand_id, 0)')->get() as $row) {
                $lines[(int) $row->order_id][] = ['brand_id' => $row->brand_id !== null ? (int) $row->brand_id : null, 'total' => Money::of((string) $row->total)];
            }
            foreach (DB::table('pos_refunds')->whereIn('order_id', $chunk)->selectRaw('order_id, SUM(amount) AS total')->groupBy('order_id')->get() as $row) {
                $refunds[(int) $row->order_id] = Money::of((string) $row->total);
            }
        }

        $percents = ChannelCommission::where('store_id', $store->id)->get()->mapWithKeys(fn (ChannelCommission $row) => [$row->channel->value => (string) $row->percent])->all();

        $cells = [];   // "channel|brand" => sums
        $channels = []; // channel => sums (orders once)
        $totals = self::zero();
        foreach ($orders as $order) {
            $channel = (string) $order->channel;
            $total = Money::of((string) $order->total_price);
            $discount = Money::of((string) $order->discount);
            $refund = $refunds[(int) $order->id] ?? ((string) $order->status === OrderStatus::REFUNDED->value ? $total : Money::zero());
            $refund = Money::min($refund, $total);
            $commission = isset($percents[$channel]) ? $total->percent($percents[$channel]) : Money::of((string) ($order->commission_amount ?? '0'));
            $figures = ['gross' => $total->plus($discount), 'discounts' => $discount, 'refunds' => $refund, 'commission' => $commission];

            foreach (self::split($figures, $lines[(int) $order->id] ?? []) as $brandId => $part) {
                $key = $channel . '|' . ($brandId === 0 ? '' : $brandId);
                $cells[$key] ??= ['channel' => $channel, 'brand_id' => $brandId === 0 ? null : $brandId] + self::zero();
                self::add($cells[$key], $part);
            }
            $channels[$channel] ??= ['channel' => $channel] + self::zero();
            self::add($channels[$channel], $figures);
            self::add($totals, $figures);
        }

        $brands = Brand::where('store_id', $store->id)->get()->keyBy('id');
        $brandOf = fn (?int $id) => $id !== null && $brands->has($id)
            ? ['brand_id' => $id, 'brand_name' => (string) $brands[$id]->name, 'brand_code' => (string) $brands[$id]->short_code, 'brand_color' => (string) $brands[$id]->color]
            : ['brand_id' => $id, 'brand_name' => $id === null ? null : "#{$id}", 'brand_code' => null, 'brand_color' => null];
        $channelOf = fn (string $channel) => [
            'channel' => $channel,
            'channel_label' => OrderChannel::tryFrom($channel)?->label() ?? $channel,
            'commission_percent' => $percents[$channel] ?? null,
            'commission_source' => isset($percents[$channel]) ? 'owner' : 'billing',
        ];

        $rows = array_values($cells);
        usort($rows, fn (array $a, array $b) => [$a['channel'], $a['brand_id'] ?? 0] <=> [$b['channel'], $b['brand_id'] ?? 0]);
        $byBrand = [];
        foreach ($rows as $row) {
            $key = $row['brand_id'] ?? 0;
            $byBrand[$key] ??= ['brand_id' => $row['brand_id']] + self::zero();
            self::add($byBrand[$key], $row);
        }
        ksort($byBrand);
        ksort($channels);

        return [
            'period' => ['from' => $first->toDateString(), 'to' => $last->toDateString(), 'timezone' => $timezone],
            'branch_id' => $branchId,
            'currency' => 'UZS',
            'rows' => array_map(fn (array $row) => $channelOf($row['channel']) + $brandOf($row['brand_id']) + self::money($row), $rows),
            'by_channel' => array_map(fn (array $row) => $channelOf($row['channel']) + self::money($row), array_values($channels)),
            'by_brand' => array_map(fn (array $row) => $brandOf($row['brand_id']) + self::money($row), array_values($byBrand)),
            'totals' => self::money($totals),
        ];
    }

    /**
     * The order's figures split over its brands by their line totals; brand 0 is the house brand.
     *
     * @param  array{gross: Money, discounts: Money, refunds: Money, commission: Money}  $figures
     * @param  list<array{brand_id: int|null, total: Money}>  $lines
     * @return array<int, array{gross: Money, discounts: Money, refunds: Money, commission: Money}>
     */
    private static function split(array $figures, array $lines): array
    {
        $lines = array_values(array_filter($lines, fn (array $line) => !$line['total']->isZero()));
        $sum = Money::sum(...array_map(fn (array $line) => $line['total'], $lines));
        if (count($lines) <= 1 || !$sum->isPositive()) {
            return [($lines[0]['brand_id'] ?? null) ?? 0 => $figures];
        }

        $parts = [];
        $left = $figures;
        foreach ($lines as $i => $line) {
            $brand = $line['brand_id'] ?? 0;
            if ($i === count($lines) - 1) {
                $parts[$brand] = $left;
                break;
            }
            $share = [];
            foreach ($figures as $name => $amount) {
                $share[$name] = Money::of(\Brick\Math\BigDecimal::of($amount->toString())->multipliedBy($line['total']->toString())->dividedBy($sum->toString(), 2, \Brick\Math\RoundingMode::HalfUp));
                $left[$name] = $left[$name]->minus($share[$name]);
            }
            $parts[$brand] = $share;
        }

        return $parts;
    }

    /**
     * @return array{orders: int, gross: Money, discounts: Money, refunds: Money, commission: Money}
     */
    private static function zero(): array
    {
        return ['orders' => 0, 'gross' => Money::zero(), 'discounts' => Money::zero(), 'refunds' => Money::zero(), 'commission' => Money::zero()];
    }

    /**
     * @param  array<string, mixed>  $into
     * @param  array<string, mixed>  $figures  Money parts (and `orders` when summing rows)
     */
    private static function add(array &$into, array $figures): void
    {
        $into['orders'] += (int) ($figures['orders'] ?? 1);
        foreach (['gross', 'discounts', 'refunds', 'commission'] as $name) {
            $into[$name] = $into[$name]->plus($figures[$name]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{orders: int, gross: string, discounts: string, refunds: string, commission: string, net: string}
     */
    private static function money(array $row): array
    {
        return [
            'orders' => (int) $row['orders'],
            'gross' => $row['gross']->toString(),
            'discounts' => $row['discounts']->toString(),
            'refunds' => $row['refunds']->toString(),
            'commission' => $row['commission']->toString(),
            'net' => $row['gross']->minus($row['discounts'])->minus($row['refunds'])->minus($row['commission'])->toString(),
        ];
    }
}
