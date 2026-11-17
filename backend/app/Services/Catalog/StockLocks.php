<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;

/**
 * The one lock order for stock (review M2: SQLSTATE 40P01 between a Telegram order and a till sale of the same dishes).
 * A transaction that takes or gives back stock, or writes order lines, calls this BEFORE its first order_items insert
 * or stock write and before any other product_variants / menu_batches lock:
 *
 *  1. every product_variants row it may touch, in ONE statement in ascending id: the lines' variants and, for a combo,
 *     every variant its slots can put on a kitchen part line (the part line's foreign key takes FOR KEY SHARE on that
 *     variant, which waits on another order's FOR UPDATE: locking the parts later, in line order, was the cycle);
 *  2. then the branch's open menu_batches of the lines' products, in ascending id.
 *
 * The callers' own lock statements that follow (the same rows) return at once, so their prices, checks and errors are
 * unchanged. Rows are only locked, never written here.
 *
 * Review M2 (LR-1): stock rows are locked FOR NO KEY UPDATE (mode()), here and in every caller's own lock of them. Two
 * stock lockers still exclude each other (and a DELETE or soft delete still waits), but a foreign key's FOR KEY SHARE
 * (an order line, a combo part line, a kazan write-off row referencing the variant or batch) no longer waits on them,
 * so such an insert, in whatever order, cannot close a cycle with a sale. Every statement relying on these locks only
 * writes non-key columns (stock, quantity_left, sold_out_at...). Other databases: a plain FOR UPDATE.
 */
final class StockLocks
{
    /** The lock of a stock row, for ->lock(): FOR NO KEY UPDATE on PostgreSQL, FOR UPDATE elsewhere */
    public static function mode(): string|bool
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'for no key update' : true;
    }

    /**
     * @param  list<string>  $uuids  the lines' variant uuids
     * @param  list<int>  $variantIds  variants known by id (a cancel's lines)
     * @param  list<int>  $batchIds  batches known by id (a cancel's lines)
     */
    public function lock(?int $branchId, array $uuids = [], array $variantIds = [], array $batchIds = []): void
    {
        $uuids = array_values(array_unique(array_filter(array_map('strval', $uuids))));
        $variantIds = array_values(array_unique(array_filter(array_map('intval', $variantIds))));

        $productIds = [];
        if ($uuids !== [] || $variantIds !== []) {
            $rows = DB::table('product_variants')
                ->where(function ($query) use ($uuids, $variantIds) {
                    if ($uuids !== []) {
                        $query->orWhereIn('uuid', $uuids);
                    }
                    if ($variantIds !== []) {
                        $query->orWhereIn('id', $variantIds);
                    }
                })
                ->get(['id', 'product_id']);
            $productIds = $rows->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

            $partIds = $productIds === [] ? collect() : DB::table('combo_slot_options')
                ->join('combo_slots', 'combo_slots.id', '=', 'combo_slot_options.combo_slot_id')
                ->join('combos', 'combos.id', '=', 'combo_slots.combo_id')
                ->whereIn('combos.product_id', $productIds)
                ->pluck('combo_slot_options.product_variant_id');

            $ids = $rows->pluck('id')->merge($partIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            if ($ids !== []) {
                DB::table('product_variants')->whereIn('id', $ids)->orderBy('id')->lock(self::mode())->pluck('id');
            }
        }

        $batchIds = array_values(array_unique(array_filter(array_map('intval', $batchIds))));
        if ($batchIds !== [] || ($branchId !== null && $productIds !== [])) {
            DB::table('menu_batches')
                ->where(function ($query) use ($branchId, $productIds, $batchIds) {
                    if ($branchId !== null && $productIds !== []) {
                        $query->orWhere(fn ($open) => $open->where('branch_id', $branchId)->whereIn('product_id', $productIds)->whereNull('closed_at'));
                    }
                    if ($batchIds !== []) {
                        $query->orWhereIn('id', $batchIds);
                    }
                })
                ->orderBy('id')
                ->lock(self::mode())
                ->pluck('id');
        }
    }
}
