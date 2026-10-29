<?php

namespace App\Domain\Pos\Billing;

use App\Domain\Pos\Contracts\AdvanceLedger;
use App\Domain\Pos\Contracts\CustomerAccountLedger;
use App\Domain\Pos\Contracts\PosTender;
use App\Domain\Pos\Shifts\ShiftService;
use App\Enums\Permission;
use App\Events\Broadcast\BillChangedBroadcast;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Models\PosShift;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Taking payment for a bill (pos.take_payment, POS_SPEC 2.9, R17, R18, R24, L3, L9): one or more tenders per request
 * and per bill, until the bill is covered.
 *
 *  - cash: `amount` is what goes to the bill, `tendered_amount` what the guest handed over; the change is worked out;
 *  - card (a separate bank terminal): card type and the terminal's transaction id are required; P6-16 (D5 a): the
 *    transaction id is the terminal's RRN in "faqat terminal" mode, and the card's last four digits are optional;
 *  - qr: a static or dynamic QR confirmed by the cashier (`reference`: the provider's id, optional);
 *  - prepayment: the booking's deposit (AdvanceLedger::apply), at most what is available, once per bill;
 *  - account (nasiya): the customer account is charged (CustomerAccountLedger::charge), approved by someone holding
 *    customer_accounts.manage (the cashier themselves, or a manager's approval token), once per bill.
 *
 * Nothing may go over what the bill still owes. Every payment lands in the shift (cash in its drawer). When the bill
 * is covered its sale receipt is asked for (BillFiscalizer); the bill is paid, and the order closed once all bills
 * are, only when the receipt is issued. P3-31: a payment a till took offline comes through here at sync, with the
 * device's time of payment (`offline_paid_at`).
 */
final class PaymentService
{
    public const CARD_TYPES = ['uzcard', 'humo', 'visa', 'mastercard', 'unionpay', 'mir', 'other'];

    public function __construct(
        private readonly ShiftService $shifts,
        private readonly AdvanceLedger $advances,
        private readonly CustomerAccountLedger $accounts,
        private readonly ManagerApprovalService $approvals,
        private readonly BillFiscalizer $fiscalizer,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<array{tender: string, amount: string, tendered_amount?: string|null, card_type?: string|null, transaction_id?: string|null, card_last4?: string|null, reference?: string|null, account_uuid?: string|null}>  $tenders
     *
     * @throws PosActionException
     */
    public function pay(User $user, OrderBill $bill, array $tenders, ?string $approvalToken, ?string $shiftUuid, ?int $version, ?Device $device, ?Closure $applied = null, ?\DateTimeInterface $offlinePaidAt = null): OrderBill
    {
        $order = Order::findOrFail($bill->order_id);
        $shift = $this->shifts->forAction($user, $shiftUuid, (int) $order->branch_id);
        $normalized = $this->normalize($tenders);

        // A line that cannot be receipted (no MXIK...) refuses the payment before any money is taken (422)
        if ($bill->status->isPayable()) {
            $this->fiscalizer->lines($bill);
        }

        [$bill, $covered] = DB::transaction(function () use ($user, $bill, $normalized, $approvalToken, $shift, $version, $device, $applied, $offlinePaidAt) {
            // Review P3-35: the shift's row first (the Z and refunds lock it too, then the orders): a shift closed
            // meanwhile takes no more money, and a closing Z never misses a payment made at the same moment
            $shift = PosShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();
            if (!$shift->isOpen()) {
                throw PosActionException::conflict('shift_closed', 'Smena yopilgan.');
            }
            $order = BillService::lockOpenOrder(Order::findOrFail($bill->order_id), $version);
            $bill = OrderBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();

            if ($bill->status === BillStatus::PAID) {
                throw PosActionException::conflict('bill_paid', "Schyot to'langan.");
            }
            if ($bill->status === BillStatus::VOID) {
                throw PosActionException::conflict('bill_void', "Bu schyot bekor qilingan: buyurtmaning joriy schyotini oling.");
            }

            $existing = Payment::where('bill_id', $bill->id)->where('status', 'paid')->get();
            $paid = Money::sum(...$existing->pluck('amount')->all());
            $total = Money::of($bill->total);
            if ($paid->isGreaterThanOrEqualTo($total)) {
                throw PosActionException::conflict('bill_awaiting_fiscal', "Schyot to'liq to'langan, fiskal chek kutilmoqda. Chekni qayta yuboring.");
            }
            if ($existing->isEmpty() && $bill->basis !== BillableOrder::fingerprint($order)) {
                throw BillService::orderChanged();
            }

            $due = $total->minus($paid);
            $sum = Money::sum(...array_column($normalized, 'amount'));
            if ($sum->isGreaterThan($due)) {
                throw PosActionException::invalid('overpayment', "To'lov schyotdagi qarzdan ko'p: qoldiq {$due->toString()} so'm. Naqd uchun ortig'ini \"berilgan summa\"ga yozing, qaytim hisoblanadi.", ['due' => $due->toString()]);
            }

            foreach ([PosTender::PREPAYMENT, PosTender::ACCOUNT] as $once) {
                $count = count(array_filter($normalized, fn (array $tender) => $tender['tender'] === $once));
                if ($count > 1 || ($count === 1 && $existing->contains('provider', $once->value))) {
                    throw PosActionException::conflict('tender_once_per_bill', $once === PosTender::PREPAYMENT
                        ? "Avans bitta schyotga bir marta ayiriladi."
                        : "Bitta schyot nasiyaga bir marta yoziladi.");
                }
            }

            $created = [];
            foreach ($normalized as $tender) {
                $payload = null;
                if ($tender['tender'] === PosTender::PREPAYMENT) {
                    $available = $this->advances->available($order);
                    if ($tender['amount']->isGreaterThan($available)) {
                        throw PosActionException::invalid('advance_insufficient', "Avans yetarli emas: mavjud {$available->toString()} so'm.", ['available' => $available->toString()]);
                    }
                    $this->advances->apply($order, $tender['amount'], $bill->uuid);
                }
                if ($tender['tender'] === PosTender::ACCOUNT) {
                    $approvedBy = $this->approvals->authorize($user, Permission::CUSTOMER_ACCOUNTS_MANAGE, $order, $approvalToken, $device) ?? $user->id;
                    $this->accounts->charge((string) $tender['account_uuid'], $tender['amount'], $bill->uuid, $approvedBy);
                    $payload = ['account_uuid' => $tender['account_uuid'], 'approved_by' => $approvedBy];
                }

                $payment = Payment::create([
                    'order_id' => $order->id,
                    'bill_id' => $bill->id,
                    'shift_id' => $shift->id,
                    'device_id' => $device?->id,
                    'received_by' => $user->id,
                    'provider' => $tender['tender']->value,
                    'amount' => $tender['amount']->toString(),
                    'status' => 'paid',
                    'paid_at' => now(),
                    'tendered_amount' => $tender['tendered']?->toString(),
                    'change_amount' => $tender['change']?->toString(),
                    'card_type' => $tender['card_type'],
                    'transaction_id' => $tender['transaction_id'],
                    'card_last4' => $tender['card_last4'],
                    'reference' => $tender['reference'],
                    'payload' => $payload,
                    // P3-31: money a till took offline, synced now (the sale receipt keeps when it was taken)
                    'offline_paid_at' => $offlinePaidAt,
                    'synced_at' => $offlinePaidAt !== null ? now() : null,
                ]);
                if ($tender['tender']->isCashDrawer()) {
                    $this->shifts->recordSale($shift, $payment, $user, $device);
                }
                $created[] = $payment;
            }

            $covered = $paid->plus($sum)->isGreaterThanOrEqualTo($total);
            if ($covered) {
                $bill->forceFill(['shift_id' => $shift->id, 'fiscal_status' => 'pending'])->save();
            }
            if ($order->getAttribute('shift_id') === null) {
                $order->forceFill(['shift_id' => $shift->id]);
            }
            OrderCloser::bumpVersion($order);
            $order->save();

            $this->audit->record('pos.payment', $order, [], [], [
                'bill' => $bill->uuid,
                'payments' => array_map(fn (Payment $payment) => array_filter([
                    'uuid' => $payment->uuid,
                    'tender' => $payment->provider,
                    'amount' => $payment->amount,
                    'change' => $payment->change_amount,
                    'card_type' => $payment->getAttribute('card_type'),
                    'card_last4' => $payment->getAttribute('card_last4'),
                ], fn ($value) => $value !== null), $created),
                'covered' => $covered,
            ], ['actor' => $user, 'device' => $device]);

            if ($applied) {
                $applied();
            }

            return [$bill, $covered];
        });

        event(new BillChangedBroadcast(Order::findOrFail($bill->order_id), $bill, 'payment'));

        return $covered ? $this->fiscalizer->issue($bill, $user) : $bill->refresh();
    }

    /**
     * @param  list<array<string, mixed>>  $tenders
     * @return list<array{tender: PosTender, amount: Money, tendered: Money|null, change: Money|null, card_type: string|null, transaction_id: string|null, card_last4: string|null, reference: string|null, account_uuid: string|null}>
     *
     * @throws PosActionException
     */
    private function normalize(array $tenders): array
    {
        if ($tenders === []) {
            throw PosActionException::invalid('tenders_required', "Kamida bitta to'lov turini kiriting.");
        }

        $normalized = [];
        foreach ($tenders as $i => $input) {
            $tender = PosTender::tryFrom((string) ($input['tender'] ?? ''));
            if ($tender === null) {
                throw PosActionException::invalid('tender_invalid', "To'lov turi noto'g'ri.", ['index' => $i]);
            }
            $amount = Money::of((string) ($input['amount'] ?? '0'));
            if (!$amount->isPositive()) {
                throw PosActionException::invalid('amount_invalid', "To'lov summasi musbat bo'lishi kerak.", ['index' => $i]);
            }

            $tendered = null;
            $change = null;
            if ($tender === PosTender::CASH && filled($input['tendered_amount'] ?? null)) {
                $tendered = Money::of((string) $input['tendered_amount']);
                if ($tendered->isLessThan($amount)) {
                    throw PosActionException::invalid('tendered_too_small', "Berilgan naqd summa to'lov summasidan kam.", ['index' => $i]);
                }
                $change = $tendered->minus($amount);
            }

            $cardType = null;
            $transactionId = null;
            $last4 = null;
            if ($tender === PosTender::CARD) {
                $cardType = strtolower(trim((string) ($input['card_type'] ?? '')));
                $transactionId = trim((string) ($input['transaction_id'] ?? ''));
                if (!in_array($cardType, self::CARD_TYPES, true)) {
                    throw PosActionException::invalid('card_type_required', "Karta turini tanlang (Uzcard, Humo, Visa...).", ['index' => $i]);
                }
                if ($transactionId === '') {
                    throw PosActionException::invalid('transaction_id_required', "Bank terminali chekidagi tranzaksiya raqamini kiriting.", ['index' => $i]);
                }
                // P6-16: optional, exactly the four digits the slip shows
                $last4 = trim((string) ($input['card_last4'] ?? ''));
                if ($last4 === '') {
                    $last4 = null;
                } elseif (!preg_match('/^\d{4}$/', $last4)) {
                    throw PosActionException::invalid('card_last4_invalid', "Kartaning oxirgi 4 raqamini kiriting (yoki bo'sh qoldiring).", ['index' => $i]);
                }
            }

            $account = null;
            if ($tender === PosTender::ACCOUNT) {
                $account = trim((string) ($input['account_uuid'] ?? ''));
                if ($account === '') {
                    throw PosActionException::invalid('account_required', 'Nasiya hisobini tanlang.', ['index' => $i]);
                }
            }

            $normalized[] = [
                'tender' => $tender,
                'amount' => $amount,
                'tendered' => $tendered,
                'change' => $change,
                'card_type' => $cardType,
                'transaction_id' => $transactionId,
                'card_last4' => $last4,
                'reference' => filled($input['reference'] ?? null) ? mb_substr(trim((string) $input['reference']), 0, 100) : null,
                'account_uuid' => $account,
            ];
        }

        return $normalized;
    }
}
