<?php

namespace App\Domain\Fiscal;

use App\Domain\Pos\Billing\Allocation;
use App\Domain\Pos\Billing\BillableOrder;
use App\Models\OrderItem;
use App\Support\Money;
use App\Support\Quantity;

/**
 * P6-31, combo_fiscal_mode `split` (ComboFiscalMode): a combo line's receipt share as one share per part.
 *
 * Remainder rule (docs/MENU.md "Kombo", "Fiskal chek"): the amount (in tiyin) is split in proportion to the parts'
 * list prices in one combo (`combo_parts[].list_price`: the dish's channel price with its modifiers × its pieces), by
 * the largest remainder; leftover tiyin go one each to the largest fractions, ties to the earlier part (slot order).
 * All-zero list prices split equally, the remainder to the first part. So the part amounts always add up exactly to
 * the combo line's amount (after its discount share; the service charge is its own line and is not split). Each
 * part's quantity is its pieces × the share's combo count; its unit price is its share of the line before discounts.
 */
final class ComboFiscalSplit
{
    /**
     * @return list<array{item: OrderItem, amount: int, quantity: Quantity}>  a receipt line maker's input per part
     */
    public static function shares(OrderItem $item, int $amountTiyin, Quantity $quantity): array
    {
        $parts = array_values(array_filter($item->combo_parts ?? [], 'is_array'));
        if ($parts === []) {
            return [];
        }

        $weights = array_map(fn (array $part) => Money::of($part['list_price'] ?? 0)->toTiyin(), $parts);
        $amounts = Allocation::proportional(max(0, $amountTiyin), $weights);
        $before = Allocation::proportional(max(0, Money::of($item->total_price)->toTiyin()), $weights);
        $lineQuantity = Quantity::of($item->getRawOriginal('quantity') ?? $item->getAttributes()['quantity'] ?? 1);

        $shares = [];
        foreach ($parts as $k => $part) {
            $pieces = $quantity->times((int) ($part['quantity'] ?? 1));
            $fullPieces = $lineQuantity->times((int) ($part['quantity'] ?? 1));
            // The part as its own receipt line: its dish (MXIK), name, unit; total_price its share before discounts
            $line = new OrderItem([
                'order_id' => $item->order_id,
                'product_variant_id' => $part['variant_id'] ?? null,
                'product_name' => (string) ($part['name'] ?? ''),
                'variant_name' => $part['variant_name'] ?? null,
                'unit' => $part['unit'] ?? 'piece',
                'quantity' => $fullPieces->isPositive() ? $fullPieces->toString() : '1',
                'unit_price' => '0.00',
                'total_price' => Money::fromTiyin($before[$k])->toString(),
            ]);
            $shares[] = [
                'item' => $line,
                'amount' => $amounts[$k],
                'quantity' => $pieces->isPositive() ? $pieces : BillableOrder::quantityOf(1),
            ];
        }

        return $shares;
    }
}
