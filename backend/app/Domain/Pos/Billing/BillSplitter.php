<?php

namespace App\Domain\Pos\Billing;

use App\Models\Order;
use App\Models\OrderBill;
use App\Models\OrderBillItem;

/**
 * Turns what is left to bill of an order (BillableOrder) into bills (POS_SPEC 2.9, R17):
 *
 *  - `single`: one bill for all of it;
 *  - `equal` into N: every bill's total is the same, the remainder tiyin on the first bill; every line's quantity and
 *    amount and every charge is shared out among the bills so each bill can carry its own sale receipt;
 *  - `items`: the cashier puts (parts of) lines on each bill, fractions too (0.5 kg of 1.5 kg); each line's amount
 *    and discount follow its quantity shares, the charges and the order discount follow the bills' amounts.
 *
 * All shares are exact to the tiyin (Allocation): bills add up to the order, lines and charges are fully shared out.
 */
final class BillSplitter
{
    public const MAX_PARTS = 30;

    /**
     * Bill plans (not saved): per bill its lines [item id => [quantity, amount, discount]] and charges.
     *
     * @param  string  $mode  single | equal | items
     * @param  list<array{items: list<array{order_item_id: int, quantity: string}>, guests?: int|null}>  $billsSpec  for `items`
     * @return list<array{lines: array<int, array{quantity: int, amount: int, discount: int}>, charges: array<string, int>, guests: int|null}>
     *
     * @throws PosActionException
     */
    public function plan(BillableOrder $billable, string $mode, int $parts = 1, array $billsSpec = []): array
    {
        if ($billable->isEmpty()) {
            throw PosActionException::conflict('nothing_to_bill', "Buyurtmada schyotga chiqadigan narsa qolmagan.");
        }

        return match ($mode) {
            'single' => $this->equal($billable, 1),
            'equal' => $this->equal($billable, $parts),
            'items' => $this->byItems($billable, $billsSpec),
            default => throw PosActionException::invalid('split_mode_invalid', "Bo'lish turi noto'g'ri."),
        };
    }

    /**
     * Saves the plans as bills of the order (numbers after the existing ones).
     *
     * @param  list<array{lines: array<int, array{quantity: int, amount: int, discount: int}>, charges: array<string, int>, guests: int|null}>  $plans
     * @return list<OrderBill>
     */
    public function save(Order $order, string $mode, array $plans, string $basis, ?int $userId): array
    {
        $number = (int) OrderBill::where('order_id', $order->id)->max('number');
        $bills = [];

        foreach ($plans as $plan) {
            $subtotal = array_sum(array_column($plan['lines'], 'amount'));
            $discount = array_sum(array_column($plan['lines'], 'discount'));
            $attributes = [
                'store_id' => $order->store_id,
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'number' => ++$number,
                'status' => BillStatus::OPEN->value,
                'split_mode' => $mode,
                'subtotal' => BillableOrder::moneyOf($subtotal)->toString(),
                'discount' => BillableOrder::moneyOf($discount)->toString(),
                'total' => BillableOrder::moneyOf($subtotal - $discount + array_sum($plan['charges']))->toString(),
                'guests_share' => $plan['guests'],
                'basis' => $basis,
                'created_by' => $userId,
            ];
            foreach (OrderBill::CHARGES as $charge => $column) {
                $attributes[$column] = BillableOrder::moneyOf($plan['charges'][$charge] ?? 0)->toString();
            }

            $bill = OrderBill::create($attributes);
            foreach ($plan['lines'] as $itemId => $line) {
                if ($line['quantity'] === 0 && $line['amount'] === 0) {
                    continue;
                }
                OrderBillItem::create([
                    'bill_id' => $bill->id,
                    'order_item_id' => $itemId,
                    'quantity' => BillableOrder::quantityOf($line['quantity'])->toString(),
                    'amount' => BillableOrder::moneyOf($line['amount'])->toString(),
                    'discount_amount' => BillableOrder::moneyOf($line['discount'])->toString(),
                ]);
            }
            $bills[] = $bill;
        }

        return $bills;
    }

    /**
     * @return list<array{lines: array<int, array{quantity: int, amount: int, discount: int}>, charges: array<string, int>, guests: int|null}>
     */
    private function equal(BillableOrder $billable, int $parts): array
    {
        if ($parts < 1 || $parts > self::MAX_PARTS) {
            throw PosActionException::invalid('parts_invalid', 'Schyotlar soni 1 dan ' . self::MAX_PARTS . ' gacha bo\'lishi kerak.');
        }
        $total = $billable->total();
        if ($parts > 1 && $total < $parts) {
            throw PosActionException::invalid('parts_invalid', "Summa shuncha schyotga bo'linmaydi.");
        }

        // Guests per bill for the "kishi boshiga" line: all of them on a single bill, an even share when they divide
        $guestsCount = (int) ($billable->order->guests_count ?? 0);
        $guests = $guestsCount > 0 && $guestsCount % $parts === 0 ? intdiv($guestsCount, $parts) : null;

        $itemIds = array_keys($billable->remaining);
        $chargeNames = array_keys($billable->charges);

        // Columns: each line's amount after its discount, then each charge; rows: the bills' equal totals
        $columns = [];
        foreach ($billable->remaining as $line) {
            $columns[] = $line['amount'] - $line['discount'];
        }
        foreach ($billable->charges as $amount) {
            $columns[] = $amount;
        }
        $cells = Allocation::matrix(Allocation::equal($total, $parts), $columns);

        $quantities = [];
        $discounts = [];
        foreach ($billable->remaining as $id => $line) {
            $quantities[$id] = Allocation::equal($line['quantity'], $parts);
            $discounts[$id] = Allocation::equal($line['discount'], $parts);
        }

        $plans = [];
        for ($b = 0; $b < $parts; $b++) {
            $lines = [];
            foreach ($itemIds as $k => $id) {
                $net = $cells[$b][$k];
                $lines[$id] = ['quantity' => $quantities[$id][$b], 'amount' => $net + $discounts[$id][$b], 'discount' => $discounts[$id][$b]];
            }
            $charges = [];
            foreach ($chargeNames as $k => $charge) {
                $charges[$charge] = $cells[$b][count($itemIds) + $k];
            }
            $plans[] = ['lines' => $lines, 'charges' => $charges, 'guests' => $guests];
        }

        return $plans;
    }

    /**
     * @param  list<array{items: list<array{order_item_id: int, quantity: string}>, guests?: int|null}>  $billsSpec
     * @return list<array{lines: array<int, array{quantity: int, amount: int, discount: int}>, charges: array<string, int>, guests: int|null}>
     */
    private function byItems(BillableOrder $billable, array $billsSpec): array
    {
        $count = count($billsSpec);
        if ($count < 1 || $count > self::MAX_PARTS) {
            throw PosActionException::invalid('parts_invalid', 'Schyotlar soni 1 dan ' . self::MAX_PARTS . ' gacha bo\'lishi kerak.');
        }

        // quantity[item][bill] in thousandths
        $quantity = [];
        foreach (array_keys($billable->remaining) as $id) {
            $quantity[$id] = array_fill(0, $count, 0);
        }
        foreach ($billsSpec as $b => $spec) {
            foreach ($spec['items'] as $entry) {
                $id = (int) $entry['order_item_id'];
                if (!isset($quantity[$id])) {
                    throw PosActionException::invalid('item_not_billable', "Taom qatori bu buyurtmada yo'q yoki o'chirilgan.", ['order_item_id' => $id]);
                }
                $share = BillableOrder::thousandths($entry['quantity']);
                if ($share <= 0) {
                    throw PosActionException::invalid('quantity_invalid', "Miqdor musbat bo'lishi kerak.", ['order_item_id' => $id]);
                }
                $quantity[$id][$b] += $share;
            }
        }

        foreach ($billable->remaining as $id => $line) {
            if (array_sum($quantity[$id]) !== $line['quantity']) {
                throw PosActionException::invalid('items_not_covered', "Har bir taom qatorining qolgan miqdori schyotlarga to'liq taqsimlanishi kerak.", [
                    'order_item_id' => $id,
                    'quantity_left' => BillableOrder::quantityOf($line['quantity'])->toNumber(),
                    'quantity_given' => BillableOrder::quantityOf(array_sum($quantity[$id]))->toNumber(),
                ]);
            }
        }

        $lines = array_fill(0, $count, []);
        $net = array_fill(0, $count, 0);
        foreach ($billable->remaining as $id => $line) {
            $amounts = Allocation::proportional($line['amount'], $quantity[$id]);
            $discounts = Allocation::proportional($line['discount'], $quantity[$id]);
            for ($b = 0; $b < $count; $b++) {
                $lines[$b][$id] = ['quantity' => $quantity[$id][$b], 'amount' => $amounts[$b], 'discount' => $discounts[$b]];
                $net[$b] += $amounts[$b] - $discounts[$b];
            }
        }

        $charges = array_fill(0, $count, []);
        foreach ($billable->charges as $charge => $amount) {
            foreach (Allocation::proportional($amount, $net) as $b => $share) {
                $charges[$b][$charge] = $share;
            }
        }

        $plans = [];
        foreach ($billsSpec as $b => $spec) {
            if (array_sum(array_column($lines[$b], 'quantity')) === 0) {
                throw PosActionException::invalid('bill_empty', "Har bir schyotda kamida bitta taom bo'lishi kerak.", ['bill' => $b + 1]);
            }
            $plans[] = ['lines' => $lines[$b], 'charges' => $charges[$b], 'guests' => isset($spec['guests']) ? (int) $spec['guests'] : null];
        }

        return $plans;
    }
}
