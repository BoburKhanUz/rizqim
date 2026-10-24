<?php

namespace App\Domain\Pos\Billing;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalException;
use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalLine;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Fiscal\FiscalPayment;
use App\Domain\Fiscal\FiscalPaymentForm;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Pos\Contracts\PosTender;
use App\Events\Broadcast\BillChangedBroadcast;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\OrderBillItem;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PosRefund;
use App\Models\Store;
use App\Models\User;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The receipts of POS bills (P3-18 on P3-11's FiscalIssuer and FiscalLineBuilder):
 *
 *  - a bill whose payments cover it gets a SALE receipt for its full total: its lines (its share of each order line,
 *    after its share of the discount) and its charges (room fee, minimum spend top-up, service charge, place fee);
 *  - the payments map one to one: cash, card (card type and the bank terminal's transaction id), QR as they are;
 *    the part paid from a booking deposit (PosTender::PREPAYMENT) as `prepayment: true` in the deposit's own form
 *    (the advance receipt was issued when it was taken: bo'nak → sotuv, research §5); the part closed on credit
 *    (PosTender::ACCOUNT, nasiya) as `credit: true` (not money received now; research §5: the sale receipt is for
 *    the full amount when the food is served, a CREDIT receipt follows each repayment, P3-17);
 *  - the bill becomes `paid` only once its receipt is `issued`; a pending or failed receipt leaves the order
 *    "fiskal kutmoqda" (retry: POST /pos/bills/{uuid}/fiscal-retry, which sends a failed receipt again — review
 *    P3-35, FiscalIssuer::reissue; a receipt the provider confirms later finishes the bill through the
 *    fiscal.receipt_updated event);
 *  - a refund gets a REFUND receipt naming the bill's sale receipt, its lines the refunded share of the bill's.
 *
 * Receipts are asked for outside any transaction (so the sandbox or a provider answers at once) and under a stable
 * idempotency key, so asking again returns the same receipt.
 */
final class BillFiscalizer
{
    public function __construct(
        private readonly FiscalIssuer $issuer,
        private readonly FiscalLineBuilder $builder,
        private readonly OrderCloser $closer,
    ) {}

    /**
     * The bill's receipt lines; throws FiscalException (422) when a line cannot be receipted (no MXIK...), which is
     * checked before any payment is taken.
     *
     * @return list<FiscalLine>
     */
    public function lines(OrderBill $bill): array
    {
        $parts = $this->parts($bill);

        return array_map(fn (array $part) => $part['line'](Money::fromTiyin($part['amount']), $part['quantity']), $parts);
    }

    /**
     * Asks for the bill's sale receipt when its payments cover it, and applies the answer.
     */
    public function issue(OrderBill $bill, ?User $actor = null): OrderBill
    {
        $bill = $bill->fresh() ?? $bill;
        if ($bill->status === BillStatus::PAID || $bill->status === BillStatus::VOID) {
            return $bill;
        }

        $payments = Payment::where('bill_id', $bill->id)->where('status', 'paid')->orderBy('id')->get();
        if (Money::sum(...$payments->pluck('amount')->all())->isLessThan($bill->total)) {
            return $bill;
        }

        try {
            $document = new FiscalDocument(
                type: FiscalReceiptType::SALE,
                storeId: (int) $bill->store_id,
                branchId: $bill->branch_id !== null ? (int) $bill->branch_id : null,
                deviceId: ($deviceId = $payments->last()?->getAttribute('device_id')) !== null ? (int) $deviceId : null,
                lines: $this->lines($bill),
                payments: $this->payments($bill, $payments->all()),
                total: Money::of($bill->total),
                idempotencyKey: "pos-bill:{$bill->uuid}:sale",
                subjectType: OrderBill::FISCAL_SUBJECT,
                subjectId: (int) $bill->id,
                // P3-31: a bill paid offline is receipted at sync; the receipt keeps when the money was taken
                soldAt: $payments->last()?->getAttribute('offline_paid_at'),
            );
            // A receipt the provider refused before is sent again (review P3-35: the till's retry, the order's or the
            // shift's close)
            $result = $this->issuer->reissue($document);
        } catch (Throwable $e) {
            if (!$e instanceof FiscalException) {
                report($e);
            }

            return $this->apply($bill, null, 'failed', $e instanceof FiscalException ? $e->getMessage() : "Fiskal chekni so'rab bo'lmadi.", $actor);
        }

        return $this->apply($bill, $result->receiptUuid, $result->status, $result->error, $actor);
    }

    /**
     * What the provider said about the bill's receipt (now, or later through fiscal.receipt_updated): `issued` pays
     * the bill and closes the order once every bill is paid. Safe to call twice.
     */
    public function apply(OrderBill $bill, ?string $receiptUuid, string $status, ?string $error = null, ?User $actor = null): OrderBill
    {
        $changed = DB::transaction(function () use (&$bill, $receiptUuid, $status, $error, $actor) {
            $order = Order::whereKey($bill->order_id)->lockForUpdate()->firstOrFail();
            $bill = OrderBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            if ($bill->status === BillStatus::PAID || $bill->status === BillStatus::VOID) {
                return false;
            }

            $bill->forceFill([
                'fiscal_receipt_uuid' => $receiptUuid ?? $bill->getAttribute('fiscal_receipt_uuid'),
                'fiscal_status' => $status,
                'fiscal_error' => $status === 'issued' ? null : ($error !== null ? mb_substr($error, 0, 500) : null),
            ]);
            if ($status === 'issued') {
                $bill->forceFill(['status' => BillStatus::PAID->value, 'paid_at' => now()]);
            }
            $bill->save();

            if ($status === 'issued') {
                $this->closer->closeIfSettled($order, $actor);
            }

            return true;
        });

        if ($changed) {
            $order = Order::find($bill->order_id);
            if ($order !== null) {
                event(new BillChangedBroadcast($order, $bill, 'fiscal'));
            }
        }

        return $bill;
    }

    /**
     * Asks for the refund's REFUND receipt (or again, after a failure) and records the answer.
     */
    public function issueRefund(PosRefund $refund): PosRefund
    {
        $refund = $refund->fresh() ?? $refund;
        if ($refund->getAttribute('fiscal_status') === 'issued') {
            return $refund;
        }

        $bill = OrderBill::find($refund->bill_id);
        try {
            if ($bill === null || $bill->getAttribute('fiscal_receipt_uuid') === null) {
                throw FiscalException::originalReceipt("Asl sotuv cheki topilmadi.");
            }
            $payment = Payment::findOrFail($refund->payment_id);
            $amount = Money::of($refund->amount);

            $result = $this->issuer->reissue(new FiscalDocument(
                type: FiscalReceiptType::REFUND,
                storeId: (int) $refund->store_id,
                branchId: $refund->branch_id !== null ? (int) $refund->branch_id : null,
                deviceId: $refund->device_id !== null ? (int) $refund->device_id : null,
                lines: $this->refundLines($bill, $amount),
                payments: [$this->refundPayment($bill, $payment, $amount)],
                total: $amount,
                idempotencyKey: "pos-refund:{$refund->uuid}",
                originalReceiptUuid: (string) $bill->getAttribute('fiscal_receipt_uuid'),
                subjectType: PosRefund::FISCAL_SUBJECT,
                subjectId: (int) $refund->id,
            ));
        } catch (Throwable $e) {
            if (!$e instanceof FiscalException) {
                report($e);
            }

            return $this->applyRefund($refund, null, 'failed', $e instanceof FiscalException ? $e->getMessage() : "Fiskal chekni so'rab bo'lmadi.");
        }

        return $this->applyRefund($refund, $result->receiptUuid, $result->status, $result->error);
    }

    public function applyRefund(PosRefund $refund, ?string $receiptUuid, string $status, ?string $error = null): PosRefund
    {
        $refund->forceFill([
            'fiscal_receipt_uuid' => $receiptUuid ?? $refund->getAttribute('fiscal_receipt_uuid'),
            'fiscal_status' => $status,
            'fiscal_error' => $status === 'issued' ? null : ($error !== null ? mb_substr($error, 0, 500) : null),
        ])->save();

        return $refund;
    }

    /**
     * The refunded share of the bill's lines: the amount spread over them in proportion (largest remainder), each
     * line's quantity in the same proportion (at least 0.001).
     *
     * @return list<FiscalLine>
     */
    private function refundLines(OrderBill $bill, Money $amount): array
    {
        $parts = $this->parts($bill);
        $shares = Allocation::proportional($amount->toTiyin(), array_column($parts, 'amount'));

        $lines = [];
        foreach ($parts as $k => $part) {
            if ($shares[$k] === 0) {
                continue;
            }
            $quantity = $part['amount'] > 0
                ? \Brick\Math\BigInteger::of(BillableOrder::thousandths($part['quantity']->toString()))
                    ->multipliedBy($shares[$k])->plus(intdiv($part['amount'], 2))->quotient($part['amount'])->toInt()
                : 0;
            $lines[] = $part['line'](Money::fromTiyin($shares[$k]), BillableOrder::quantityOf(max(1, $quantity)));
        }

        return $lines;
    }

    /**
     * The bill's receipt parts: each line share (amount after discount, quantity) and each charge, with a maker.
     *
     * @return list<array{amount: int, quantity: Quantity, line: \Closure(Money, Quantity): FiscalLine}>
     */
    private function parts(OrderBill $bill): array
    {
        $parts = [];
        $shares = OrderBillItem::where('bill_id', $bill->id)->orderBy('order_item_id')->get();
        $items = OrderItem::withTrashed()->whereIn('id', $shares->pluck('order_item_id'))->get()->keyBy('id');

        foreach ($shares as $share) {
            $item = $items->get($share->order_item_id);
            if (!$item instanceof OrderItem) {
                continue;
            }
            $amount = Money::of($share->amount)->minus($share->discount_amount);
            $quantity = BillableOrder::thousandths($share->quantity);
            if ($quantity === 0 && $amount->isZero()) {
                continue;
            }
            $parts[] = [
                'amount' => $amount->toTiyin(),
                'quantity' => BillableOrder::quantityOf(max(1, $quantity)),
                'line' => fn (Money $money, Quantity $qty) => $this->builder->forOrderItem($item, $qty, $money),
            ];
        }

        $store = Store::findOrFail($bill->store_id);
        foreach (OrderBill::CHARGES as $charge => $column) {
            $amount = Money::of($bill->getAttribute($column));
            if ($amount->isPositive()) {
                $parts[] = [
                    'amount' => $amount->toTiyin(),
                    'quantity' => Quantity::of(1),
                    'line' => fn (Money $money, Quantity $qty) => $this->builder->forCharge($store, $charge, $money),
                ];
            }
        }

        return $parts;
    }

    /**
     * @param  list<Payment>  $payments
     * @return list<FiscalPayment>
     */
    private function payments(OrderBill $bill, array $payments): array
    {
        $fiscal = [];
        foreach ($payments as $payment) {
            $tender = PosTender::tryFrom((string) $payment->provider);
            $amount = Money::of($payment->amount);
            $fiscal[] = match ($tender) {
                PosTender::CARD => new FiscalPayment(FiscalPaymentForm::CARD, $amount, cardType: $payment->getAttribute('card_type'), transactionId: $payment->getAttribute('transaction_id')),
                PosTender::QR => new FiscalPayment(FiscalPaymentForm::QR, $amount),
                PosTender::PREPAYMENT => new FiscalPayment($this->depositForm($bill), $amount, prepayment: true),
                // Nasiya: the full sale is receipted now, the money comes later with CREDIT receipts (P3-17); the
                // form is a placeholder until the provider says how an unpaid part is shown (T4)
                PosTender::ACCOUNT => new FiscalPayment(FiscalPaymentForm::CASH, $amount, credit: true),
                default => new FiscalPayment(FiscalPaymentForm::CASH, $amount),
            };
        }

        return $fiscal;
    }

    /**
     * The form the order's booking deposits were taken in (P3-17's booking_payments): the advance receipt's form, as
     * a sale paid by an advance shows it; cash when they were taken in more than one form or cannot be found.
     */
    private function depositForm(OrderBill $bill): FiscalPaymentForm
    {
        $order = Order::find($bill->order_id);
        $tenders = DB::table('booking_payments')
            ->where(fn ($query) => $query->where('order_id', $bill->order_id)
                ->when($order?->getAttribute('booking_id') !== null, fn ($query) => $query->orWhere('booking_id', $order?->getAttribute('booking_id'))))
            ->distinct()
            ->pluck('tender')
            ->all();

        return count($tenders) === 1 ? (FiscalPaymentForm::tryFrom((string) $tenders[0]) ?? FiscalPaymentForm::CASH) : FiscalPaymentForm::CASH;
    }

    /**
     * The refund receipt's payment: the refunded payment's form; review P3-35: a deposit part goes back to the advance
     * (`prepayment`, in the deposit's form), a nasiya part comes off the debt (`credit`): no money leaves the till.
     */
    private function refundPayment(OrderBill $bill, Payment $payment, Money $amount): FiscalPayment
    {
        return match (PosTender::tryFrom((string) $payment->provider)) {
            PosTender::PREPAYMENT => new FiscalPayment($this->depositForm($bill), $amount, prepayment: true),
            PosTender::ACCOUNT => new FiscalPayment(FiscalPaymentForm::CASH, $amount, credit: true),
            default => new FiscalPayment(
                form: $this->formOf((string) $payment->provider),
                amount: $amount,
                cardType: $payment->getAttribute('card_type'),
                transactionId: $payment->getAttribute('transaction_id'),
            ),
        };
    }

    private function formOf(string $tender): FiscalPaymentForm
    {
        return match ($tender) {
            PosTender::CARD->value => FiscalPaymentForm::CARD,
            PosTender::QR->value => FiscalPaymentForm::QR,
            default => FiscalPaymentForm::CASH,
        };
    }
}
