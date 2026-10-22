<?php

namespace App\Domain\Pos\Billing;

use App\Domain\Pos\Contracts\AdvanceLedger;
use App\Models\Booking;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\OrderBillItem;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PosRefund;
use App\Support\Money;
use App\Support\Quantity;

/**
 * The JSON of bills, payments and refunds for the till (amounts as decimal strings, quantities as numbers). The card's
 * transaction id is shown to staff as typed (it is the bank terminal's receipt number, not card data).
 */
final class BillPresenter
{
    public const BILL = 'array{uuid: string, order_uuid: string, number: int, status: string, split_mode: string, subtotal: string, discount: string, room_fee_amount: string, min_spend_topup_amount: string, service_charge_amount: string, place_fee_amount: string, total: string, paid_amount: string, due_amount: string, guests_share: int|null, per_guest_amount: string|null, printed_count: int, printed_at: string|null, paid_at: string|null, fiscal: ' . PosFiscalReceipt::FISCAL . ', items: list<array{order_item_id: int, product_name: string, variant_name: string|null, unit: string, quantity: int|float, amount: string, discount_amount: string}>, payments: list<' . self::PAYMENT . '>, created_at: string|null}';

    public const PAYMENT = 'array{uuid: string, tender: string, amount: string, tendered_amount: string|null, change_amount: string|null, card_type: string|null, transaction_id: string|null, reference: string|null, status: string, paid_at: string|null}';

    public const REFUND = 'array{uuid: string, order_uuid: string|null, bill_uuid: string|null, payment_uuid: string|null, amount: string, method: string, reason: string, approved_by: int|null, fiscal: ' . PosFiscalReceipt::FISCAL . ', created_at: string|null}';

    public const ORDER = 'array{uuid: string, status: string, payment_status: string, version: int, queue_number: int|null, business_date: string|null, bill_locked_at: string|null, closed_at: string|null, total_price: string, billed_amount: string, paid_amount: string, unbilled_amount: string|null, fiscal_pending: bool, bills_out_of_date: bool, booking_uuid: string|null, deposit_available: string}';

    /**
     * @return array<string, mixed>
     */
    public function bill(OrderBill $bill, ?Order $order = null): array
    {
        $order ??= Order::find($bill->order_id);
        $payments = Payment::where('bill_id', $bill->id)->orderBy('id')->get();
        $paid = Money::sum(...$payments->where('status', 'paid')->pluck('amount')->all(), ...$payments->where('status', 'refunded')->pluck('amount')->all());
        $total = Money::of($bill->total);
        $shares = OrderBillItem::where('bill_id', $bill->id)->orderBy('order_item_id')->get();
        $items = OrderItem::withTrashed()->whereIn('id', $shares->pluck('order_item_id'))->get()->keyBy('id');
        $guests = $bill->getAttribute('guests_share');

        return [
            'uuid' => $bill->uuid,
            'order_uuid' => (string) $order?->uuid,
            'number' => (int) $bill->number,
            'status' => $bill->status->value,
            'split_mode' => (string) $bill->split_mode,
            'subtotal' => Money::of($bill->subtotal)->toString(),
            'discount' => Money::of($bill->discount)->toString(),
            'room_fee_amount' => Money::of($bill->room_fee_amount)->toString(),
            'min_spend_topup_amount' => Money::of($bill->min_spend_topup_amount)->toString(),
            'service_charge_amount' => Money::of($bill->service_charge_amount)->toString(),
            'place_fee_amount' => Money::of($bill->place_fee_amount)->toString(),
            'total' => $total->toString(),
            'paid_amount' => $paid->toString(),
            'due_amount' => Money::max(Money::zero(), $total->minus($paid))->toString(),
            'guests_share' => $guests !== null ? (int) $guests : null,
            // "Kishi boshiga" on the printed bill: whole so'm, rounded up so the guests never come short
            'per_guest_amount' => $guests ? Money::fromTiyin(intdiv($total->toTiyin() + (int) $guests * 100 - 1, (int) $guests * 100) * 100)->toString() : null,
            'printed_count' => (int) $bill->printed_count,
            'printed_at' => $bill->printed_at?->toIso8601String(),
            'paid_at' => $bill->paid_at?->toIso8601String(),
            // P3-26: with the receipt as registered (fiscal sign, number, QR, lines), for the till to print
            'fiscal' => PosFiscalReceipt::fiscal($bill->getAttribute('fiscal_status'), $bill->getAttribute('fiscal_receipt_uuid'), $bill->getAttribute('fiscal_error'), (int) $bill->getAttribute('store_id')),
            'items' => $shares->map(function (OrderBillItem $share) use ($items) {
                $item = $items->get($share->order_item_id);

                return [
                    'order_item_id' => (int) $share->order_item_id,
                    'product_name' => (string) ($item->product_name ?? ''),
                    'variant_name' => $item?->variant_name,
                    'unit' => (string) ($item?->unit->value ?? 'piece'),
                    'quantity' => Quantity::of($share->quantity)->toNumber(),
                    'amount' => Money::of($share->amount)->toString(),
                    'discount_amount' => Money::of($share->discount_amount)->toString(),
                ];
            })->values()->all(),
            'payments' => $payments->map(fn (Payment $payment) => $this->payment($payment))->values()->all(),
            'created_at' => $bill->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payment(Payment $payment): array
    {
        return [
            'uuid' => (string) $payment->uuid,
            'tender' => (string) $payment->provider,
            'amount' => Money::of($payment->amount)->toString(),
            'tendered_amount' => $payment->tendered_amount !== null ? Money::of($payment->tendered_amount)->toString() : null,
            'change_amount' => $payment->change_amount !== null ? Money::of($payment->change_amount)->toString() : null,
            'card_type' => $payment->getAttribute('card_type'),
            'transaction_id' => $payment->getAttribute('transaction_id'),
            'reference' => $payment->getAttribute('reference'),
            'status' => $payment->status->value,
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function refund(PosRefund $refund): array
    {
        return [
            'uuid' => (string) $refund->uuid,
            'order_uuid' => Order::whereKey($refund->order_id)->value('uuid'),
            'bill_uuid' => OrderBill::whereKey($refund->bill_id)->value('uuid'),
            'payment_uuid' => Payment::whereKey($refund->payment_id)->value('uuid'),
            'amount' => Money::of($refund->amount)->toString(),
            'method' => (string) $refund->method,
            'reason' => (string) $refund->reason,
            'approved_by' => $refund->approved_by !== null ? (int) $refund->approved_by : null,
            'fiscal' => PosFiscalReceipt::fiscal($refund->getAttribute('fiscal_status'), $refund->getAttribute('fiscal_receipt_uuid'), $refund->getAttribute('fiscal_error'), (int) $refund->getAttribute('store_id')),
            'created_at' => $refund->created_at?->toIso8601String(),
        ];
    }

    /**
     * The order's billing state: what is billed, paid and left, and whether a receipt is awaited ("fiskal kutmoqda").
     *
     * @return array<string, mixed>
     */
    public function order(Order $order): array
    {
        $bills = OrderBill::where('order_id', $order->id)->where('status', '!=', BillStatus::VOID->value)->get();
        $paid = Money::sum(...Payment::whereIn('bill_id', $bills->pluck('id'))->whereIn('status', ['paid', 'refunded'])->pluck('amount')->all());

        $unbilled = null;
        $outOfDate = false;
        try {
            $billable = BillableOrder::of($order);
            $taken = $bills->filter(fn (OrderBill $bill) => $bill->status === BillStatus::PAID || Payment::where('bill_id', $bill->id)->exists())->pluck('id');
            $open = $bills->whereNotIn('id', $taken->all());
            $unbilled = Money::fromTiyin($billable->total())->minus(Money::sum(...$open->pluck('total')->all()))->toString();
            $outOfDate = $open->contains(fn (OrderBill $bill) => $bill->basis !== $billable->basis);
        } catch (PosActionException) {
            $outOfDate = true;
        }

        return [
            'uuid' => (string) $order->uuid,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'version' => (int) $order->getAttribute('version'),
            'queue_number' => $order->queue_number,
            'business_date' => $order->business_date?->toDateString(),
            'bill_locked_at' => $order->bill_locked_at?->toIso8601String(),
            'closed_at' => $order->closed_at?->toIso8601String(),
            'total_price' => Money::of($order->total_price)->toString(),
            'billed_amount' => Money::sum(...$bills->pluck('total')->all())->toString(),
            'paid_amount' => $paid->toString(),
            'unbilled_amount' => $unbilled,
            'fiscal_pending' => $bills->contains(fn (OrderBill $bill) => $bill->status !== BillStatus::PAID && in_array($bill->getAttribute('fiscal_status'), ['pending', 'failed'], true)),
            'bills_out_of_date' => $outOfDate,
            // P3-26: the booking the order was opened from and what its deposits still hold (PosTender::PREPAYMENT)
            'booking_uuid' => self::bookingUuid($order),
            'deposit_available' => self::depositAvailable($order)->toString(),
        ];
    }

    /**
     * The uuid of the booking an order was opened from (orders.booking_id, P3-17); null for any other order.
     */
    public static function bookingUuid(Order $order): ?string
    {
        $bookingId = $order->getAttribute('booking_id');

        return $bookingId !== null ? Booking::whereKey((int) $bookingId)->value('uuid') : null;
    }

    /**
     * What the order's booking deposits still hold to pay its bills with (AdvanceLedger; zero without a booking).
     */
    public static function depositAvailable(Order $order): Money
    {
        if ($order->getAttribute('booking_id') === null || !app()->bound(AdvanceLedger::class)) {
            return Money::zero();
        }

        return app(AdvanceLedger::class)->available($order);
    }
}
