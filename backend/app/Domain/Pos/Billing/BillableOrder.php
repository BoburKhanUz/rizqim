<?php

namespace App\Domain\Pos\Billing;

use App\Models\Order;
use App\Models\OrderBill;
use App\Models\OrderBillItem;
use App\Models\OrderItem;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Collection;

/**
 * What of an order is still to be billed: its live lines (not voided) and charges, less what the bills already being
 * paid (a payment taken, or paid) carry. Money in tiyin, quantities in thousandths, so every split is exact
 * (Allocation). The order discount left is shared over the lines left in proportion to their amounts.
 *
 * The `basis` fingerprint covers the whole order (lines, quantities, amounts, discount, charges): a bill made from one
 * basis is not paid against another (the order changed after the bill was printed: bill it again).
 */
final class BillableOrder
{
    /**
     * @param  array<int, OrderItem>  $items  live lines by id
     * @param  array<int, array{quantity: int, amount: int, discount: int}>  $remaining  per line id, what is left
     * @param  array<string, int>  $charges  charge => tiyin left
     */
    private function __construct(
        public readonly Order $order,
        public readonly array $items,
        public readonly array $remaining,
        public readonly array $charges,
        public readonly int $discount,
        public readonly string $basis,
    ) {}

    /**
     * @throws PosActionException when the bills being paid no longer fit the order (a paid line voided, a charge
     *                            lowered below what a bill carries)
     */
    public static function of(Order $order): self
    {
        $items = self::liveItems($order);

        $taken = OrderBill::query()
            ->where('order_id', $order->id)
            ->where('status', '!=', BillStatus::VOID->value)
            ->where(fn ($query) => $query->where('status', BillStatus::PAID->value)
                ->orWhereExists(fn ($payments) => $payments->selectRaw('1')->from('payments')->whereColumn('payments.bill_id', 'order_bills.id')))
            ->get();

        $takenShares = OrderBillItem::query()
            ->whereIn('bill_id', $taken->pluck('id'))
            ->get()
            ->groupBy('order_item_id');

        $remaining = [];
        $amounts = [];
        foreach ($items as $id => $item) {
            /** @var Collection<int, OrderBillItem> $shares */
            $shares = $takenShares->get($id, collect());
            $quantity = self::thousandths($item->getRawOriginal('quantity')) - $shares->sum(fn (OrderBillItem $share) => self::thousandths($share->quantity));
            $amount = Money::of($item->total_price)->toTiyin() - $shares->sum(fn (OrderBillItem $share) => Money::of($share->amount)->toTiyin());
            $remaining[$id] = ['quantity' => $quantity, 'amount' => $amount, 'discount' => 0];
            $amounts[$id] = $amount;
        }

        // A bill being paid that carries a line which is gone now (voided after it was billed)
        foreach ($takenShares->keys() as $itemId) {
            if (!isset($items[(int) $itemId])) {
                throw self::outOfDate();
            }
        }

        $charges = [];
        foreach (OrderBill::CHARGES as $charge => $column) {
            $charges[$charge] = Money::of($order->getAttribute($column))->toTiyin()
                - $taken->sum(fn (OrderBill $bill) => Money::of($bill->getAttribute($column))->toTiyin());
        }
        $discount = Money::of($order->discount)->toTiyin() - $taken->sum(fn (OrderBill $bill) => Money::of($bill->discount)->toTiyin());

        foreach ([...array_column($remaining, 'quantity'), ...$amounts, ...array_values($charges), $discount] as $left) {
            if ($left < 0) {
                throw self::outOfDate();
            }
        }
        if ($discount > array_sum($amounts)) {
            throw PosActionException::conflict('discount_exceeds_lines', "Chegirma qolgan taomlar summasidan katta. Chegirmani tekshiring.");
        }

        $shares = Allocation::proportional($discount, array_values($amounts));
        foreach (array_keys($remaining) as $k => $id) {
            $remaining[$id]['discount'] = $shares[$k];
        }

        return new self($order, $items, $remaining, $charges, $discount, self::basisOf($order, $items));
    }

    /**
     * The order's lines a bill may carry: not soft-deleted, not voided.
     *
     * @return array<int, OrderItem>
     */
    public static function liveItems(Order $order): array
    {
        return OrderItem::query()
            ->where('order_id', $order->id)
            ->where('kitchen_status', '!=', 'void')
            ->whereNull('voided_at')
            ->orderBy('id')
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * The fingerprint of what a bill of this order is made from.
     */
    public static function fingerprint(Order $order): string
    {
        return self::basisOf($order, self::liveItems($order));
    }

    /**
     * Tiyin left to bill: lines less their discount, plus charges.
     */
    public function total(): int
    {
        return array_sum(array_column($this->remaining, 'amount')) - $this->discount + array_sum($this->charges);
    }

    public function isEmpty(): bool
    {
        return $this->total() === 0
            && array_sum(array_column($this->remaining, 'quantity')) === 0
            && array_sum(array_column($this->remaining, 'amount')) === 0;
    }

    public static function thousandths(mixed $quantity): int
    {
        return (int) Quantity::of(is_numeric($quantity) ? $quantity : 0)->toBigDecimal()->withPointMovedRight(Quantity::SCALE)->toInt();
    }

    public static function quantityOf(int $thousandths): Quantity
    {
        return Quantity::of(\Brick\Math\BigDecimal::ofUnscaledValue($thousandths, Quantity::SCALE));
    }

    public static function moneyOf(int $tiyin): Money
    {
        return Money::fromTiyin($tiyin);
    }

    /**
     * @param  array<int, OrderItem>  $items
     */
    private static function basisOf(Order $order, array $items): string
    {
        $parts = [];
        foreach ($items as $id => $item) {
            $parts[] = [$id, Quantity::of($item->getRawOriginal('quantity') ?? 0)->toString(), Money::of($item->total_price)->toString()];
        }
        $charges = [];
        foreach (OrderBill::CHARGES as $column) {
            $charges[] = Money::of($order->getAttribute($column))->toString();
        }

        return hash('sha256', json_encode([$parts, Money::of($order->discount)->toString(), $charges]) ?: '');
    }

    private static function outOfDate(): PosActionException
    {
        return PosActionException::conflict(
            'bills_out_of_date',
            "To'lov boshlangan schyot buyurtmaga mos emas (taom o'chirilgan yoki xizmat haqi o'zgargan). Menejerga murojaat qiling.",
        );
    }
}
