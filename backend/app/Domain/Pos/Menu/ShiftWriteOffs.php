<?php

namespace App\Domain\Pos\Menu;

use App\Models\MenuBatchWriteOff;
use App\Models\PosShift;
use App\Support\Quantity;

/**
 * The `batch_writeoffs` block of the X and Z reports (P6-15): the portions written off at this shift's terminal
 * (menu_batch_writeoffs.shift_id), one row per dish, unit and reason. Quantities are numbers in the batch's unit.
 */
final class ShiftWriteOffs
{
    /** The block's shape in ShiftReport::REPORT_FIELDS. */
    public const SHAPE = 'array{count: int, items: list<array{product_id: int, product_name: string|null, unit: string, reason: string, reason_label: string, quantity: int|float}>}';

    /**
     * @return array{count: int, items: list<array{product_id: int, product_name: string|null, unit: string, reason: string, reason_label: string, quantity: int|float}>}
     */
    public function forShift(PosShift $shift): array
    {
        $rows = MenuBatchWriteOff::query()
            ->where('shift_id', $shift->id)
            ->with('product:id,name')
            ->orderBy('id')
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $key = $row->product_id . '|' . $row->unit . '|' . $row->reason;
            $items[$key] ??= [
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product?->name,
                'unit' => $row->unit,
                'reason' => $row->reason,
                'reason_label' => WriteOffReason::tryFrom($row->reason)?->label() ?? $row->reason,
                'quantity' => Quantity::zero(),
            ];
            $items[$key]['quantity'] = $items[$key]['quantity']->plus($row->quantity);
        }

        return [
            'count' => $rows->count(),
            'items' => array_values(array_map(fn (array $item) => ['quantity' => $item['quantity']->toNumber()] + $item, $items)),
        ];
    }
}
