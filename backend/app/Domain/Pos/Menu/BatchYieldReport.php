<?php

namespace App\Domain\Pos\Menu;

use App\Domain\Pos\Setup\PosSettings;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Models\Branch;
use App\Models\MenuBatch;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Support\Quantity;
use App\Support\StoreTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * "Hosil hisoboti" (P6-15): per batch and per day, what was cooked against what was sold and written off, so an owner
 * sees what a kazan or a tandir really yields. For every batch started in the business days from..to of the branch:
 *
 *   produced   the batch's quantity (opening plus top-ups)
 *   sold       the portions its order lines took (lines not voided, orders not canceled or rejected)
 *   written_off  taken off with a reason (menu_batch_writeoffs)
 *   left       what the batch still holds (an open batch) or held when it closed
 *   variance   produced - sold - written_off - left: the part nobody explained (recounts, a line voided after the kitchen
 *              cooked it, a cancellation after the batch closed). Zero when the numbers agree.
 *
 * All quantities are in the batch's unit (portions, kg) and are numbers. `in_sets` lists the portions of a dish sold
 * inside a kompleks (set_parts), which do not touch the batch counts.
 */
final class BatchYieldReport
{
    /** At most this many business days in one report. */
    public const MAX_DAYS = 93;

    /**
     * @return array{branch_id: int, from: string, to: string, batches: list<array<string, mixed>>, days: list<array<string, mixed>>, write_offs: list<array<string, mixed>>, in_sets: list<array<string, mixed>>}
     */
    public function build(Branch $branch, string $from, string $to): array
    {
        $store = Store::find($branch->store_id);
        $zone = StoreTimezone::of($store);
        $dayStart = PosSettings::timeOf($branch->getAttribute('business_day_starts_at'));
        $start = CarbonImmutable::parse("{$from} {$dayStart}", $zone);
        $end = CarbonImmutable::parse("{$to} {$dayStart}", $zone)->addDay();

        $batches = MenuBatch::query()
            ->where('branch_id', $branch->id)
            ->where('started_at', '>=', $start)
            ->where('started_at', '<', $end)
            ->with(['product:id,name', 'variant:id,name,unit'])
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();
        $ids = $batches->pluck('id')->all();

        $sold = $ids === [] ? [] : DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.batch_id', $ids)
            ->whereNull('order_items.deleted_at')
            ->where('order_items.kitchen_status', '!=', 'void')
            ->whereNotIn('orders.status', ['canceled', 'rejected'])
            ->groupBy('order_items.batch_id')
            ->selectRaw('order_items.batch_id as batch_id, sum(coalesce(order_items.batch_quantity, order_items.quantity)) as sold')
            ->pluck('sold', 'batch_id')
            ->all();

        $writeOffs = $ids === [] ? collect() : DB::table('menu_batch_writeoffs')
            ->whereIn('menu_batch_id', $ids)
            ->get(['menu_batch_id', 'reason', 'quantity']);

        $units = $this->units($batches);

        $rows = [];
        foreach ($batches as $batch) {
            $produced = Quantity::of($batch->quantity_produced);
            $soldQty = Quantity::of($sold[$batch->id] ?? 0);
            $off = Quantity::of($batch->written_off_quantity ?? 0);
            $left = Quantity::of($batch->quantity_left);
            $variance = $produced->minus($soldQty)->minus($off)->minus($left);

            $rows[] = [
                'id' => (int) $batch->id,
                'uuid' => $batch->uuid,
                'business_date' => BusinessDay::of($branch, $batch->started_at),
                'product_id' => (int) $batch->product_id,
                'product_name' => $batch->product?->name,
                'product_variant_id' => $batch->product_variant_id !== null ? (int) $batch->product_variant_id : null,
                'variant_name' => $batch->variant?->name,
                'unit' => $units[(int) $batch->id],
                'label' => $batch->label,
                'started_at' => $batch->started_at->toJSON(),
                'closed_at' => $batch->closed_at?->toJSON(),
                'is_open' => $batch->closed_at === null,
                'produced' => $produced->toNumber(),
                'sold' => $soldQty->toNumber(),
                'written_off' => $off->toNumber(),
                'left' => $left->toNumber(),
                'oversold' => Quantity::of($batch->oversold_quantity ?? 0)->toNumber(),
                'variance' => $variance->toNumber(),
                'sold_percent' => $this->percent($soldQty, $produced),
                'written_off_percent' => $this->percent($off, $produced),
            ];
        }

        return [
            'branch_id' => (int) $branch->id,
            'from' => $from,
            'to' => $to,
            'batches' => $rows,
            'days' => $this->days($rows),
            'write_offs' => $this->byReason($writeOffs),
            'in_sets' => $this->inSets($branch, $start, $end),
        ];
    }

    /**
     * Per business day and dish: the batches added up.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function days(array $rows): array
    {
        /** @var array<string, array{business_date: string, product_id: int, product_name: string|null, unit: string, batches: int, produced: Quantity, sold: Quantity, written_off: Quantity, left: Quantity, variance: Quantity}> $days */
        $days = [];
        foreach ($rows as $row) {
            $key = $row['business_date'] . '|' . $row['product_id'] . '|' . $row['unit'];
            $day = $days[$key] ?? [
                'business_date' => $row['business_date'],
                'product_id' => $row['product_id'],
                'product_name' => $row['product_name'],
                'unit' => $row['unit'],
                'batches' => 0,
                'produced' => Quantity::zero(),
                'sold' => Quantity::zero(),
                'written_off' => Quantity::zero(),
                'left' => Quantity::zero(),
                'variance' => Quantity::zero(),
            ];
            $day['batches']++;
            foreach (['produced', 'sold', 'written_off', 'left', 'variance'] as $field) {
                $day[$field] = $day[$field]->plus($row[$field]);
            }
            $days[$key] = $day;
        }

        $list = [];
        foreach ($days as $day) {
            $list[] = [
                'business_date' => $day['business_date'],
                'product_id' => $day['product_id'],
                'product_name' => $day['product_name'],
                'unit' => $day['unit'],
                'batches' => $day['batches'],
                'produced' => $day['produced']->toNumber(),
                'sold' => $day['sold']->toNumber(),
                'written_off' => $day['written_off']->toNumber(),
                'left' => $day['left']->toNumber(),
                'variance' => $day['variance']->toNumber(),
                'sold_percent' => $this->percent($day['sold'], $day['produced']),
                'written_off_percent' => $this->percent($day['written_off'], $day['produced']),
            ];
        }
        usort($list, fn (array $a, array $b) => [$b['business_date'], $a['product_name']] <=> [$a['business_date'], $b['product_name']]);

        return $list;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \stdClass>  $writeOffs
     * @return list<array{reason: string, label: string, count: int, quantity: int|float}>
     */
    private function byReason($writeOffs): array
    {
        $list = [];
        foreach ($writeOffs->groupBy('reason') as $reason => $items) {
            $sum = Quantity::zero();
            foreach ($items as $item) {
                $sum = $sum->plus($item->quantity);
            }
            $list[] = [
                'reason' => (string) $reason,
                'label' => WriteOffReason::tryFrom((string) $reason)?->label() ?? (string) $reason,
                'count' => $items->count(),
                'quantity' => $sum->toNumber(),
            ];
        }
        usort($list, fn (array $a, array $b) => $a['reason'] <=> $b['reason']);

        return $list;
    }

    /**
     * Portions of dishes sold inside kompleks lines of the branch in the period (one row per business day and dish).
     *
     * @return list<array{business_date: string, product_id: int, name: string, unit: string, quantity: int|float}>
     */
    private function inSets(Branch $branch, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $items = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.branch_id', $branch->id)
            ->whereNotNull('order_items.set_parts')
            ->whereNull('order_items.deleted_at')
            ->where('order_items.kitchen_status', '!=', 'void')
            ->whereNotIn('orders.status', ['canceled', 'rejected'])
            ->where('orders.created_at', '>=', $start)
            ->where('orders.created_at', '<', $end)
            ->get(['order_items.quantity', 'order_items.set_parts', 'orders.created_at']);

        $totals = [];
        foreach ($items as $item) {
            $parts = json_decode((string) $item->set_parts, true);
            if (!is_array($parts)) {
                continue;
            }
            $date = BusinessDay::of($branch, CarbonImmutable::parse($item->created_at, 'UTC'));
            foreach ($parts as $part) {
                $key = $date . '|' . (int) ($part['product_id'] ?? 0) . '|' . (string) ($part['unit'] ?? 'piece');
                $totals[$key] ??= ['business_date' => $date, 'product_id' => (int) ($part['product_id'] ?? 0), 'name' => (string) ($part['name'] ?? ''), 'unit' => (string) ($part['unit'] ?? 'piece'), 'quantity' => Quantity::zero()];
                $totals[$key]['quantity'] = $totals[$key]['quantity']->plus(Quantity::of($part['quantity'] ?? 1)->times(Quantity::of($item->quantity)));
            }
        }

        $list = array_map(fn (array $row) => ['quantity' => $row['quantity']->toNumber()] + $row, array_values($totals));
        usort($list, fn (array $a, array $b) => [$b['business_date'], $a['name']] <=> [$a['business_date'], $b['name']]);

        return $list;
    }

    /**
     * The unit of each batch: its variant's, else its product's first variant.
     *
     * @param  \Illuminate\Support\Collection<int, MenuBatch>  $batches
     * @return array<int, string>
     */
    private function units($batches): array
    {
        $productIds = $batches->whereNull('product_variant_id')->pluck('product_id')->unique()->values()->all();
        $first = $productIds === [] ? collect() : ProductVariant::withTrashed()->whereIn('product_id', $productIds)->orderBy('id')->get(['id', 'product_id', 'unit'])->unique('product_id')->keyBy('product_id');

        $units = [];
        foreach ($batches as $batch) {
            $units[(int) $batch->id] = $batch->variant?->unit->value ?? $first->get($batch->product_id)?->unit->value ?? 'piece';
        }

        return $units;
    }

    private function percent(Quantity $part, Quantity $whole): ?string
    {
        if (!$whole->isPositive()) {
            return null;
        }

        return number_format((float) $part->toString() / (float) $whole->toString() * 100, 1, '.', '');
    }
}
