<?php

namespace App\Domain\Pos\Billing;

use App\Domain\Pos\Contracts\AdvanceLedger;
use App\Domain\Pos\Contracts\CustomerAccountLedger;
use App\Domain\Pos\Contracts\PosTender;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Domain\Pos\Shifts\ShiftService;
use App\Domain\Pos\Tips\TipMethod;
use App\Domain\Pos\Tips\TipService;
use App\Domain\Pos\Tips\TipSource;
use App\Models\Branch;
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
 *
 * P6-22 (C2): a cash, card or QR tender may carry a tip (`tip_amount`) on top of what it pays of the bill; for cash
 * the guest may leave the change as the tip (`tip_from_change`: the tip is the change, or `tip_amount` of it).
 * A tip is never a payment and never on the receipt: it is a pos_tips row (TipService), to the waiter picked at
 * payment (`$tipStaffId`), else the order's waiter (personal mode), or the branch's pool. A tip needs the branch's
 * `tips` module (403 module_disabled before any money is taken); a payment synced from offline keeps its tip anyway.
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
        private readonly TipService $tips,
        private readonly PosModules $modules,
    ) {}

    /**
     * @param  list<array{tender: string, amount: string, tendered_amount?: string|null, card_type?: string|null, transaction_id?: string|null, card_last4?: string|null, reference?: string|null, account_uuid?: string|null, tip_amount?: string|null, tip_from_change?: string|bool|null}>  $tenders
     * @param  int|null  $tipStaffId  P6-22: the staff member the cashier gave the tips to (personal mode)
     *
     * @throws PosActionException
     */
    public function pay(User $user, OrderBill $bill, array $tenders, ?string $approvalToken, ?string $shiftUuid, ?int $version, ?Device $device, ?Closure $applied = null, ?\DateTimeInterface $offlinePaidAt = null, ?int $tipStaffId = null): OrderBill
    {
        $order = Order::findOrFail($bill->order_id);
        $shift = $this->shifts->forAction($user, $shiftUuid, (int) $order->branch_id);
        $normalized = $this->normalize($tenders);

        // P6-22: tips need the branch's module (online; money taken offline keeps its tip) and a staff member of it
        $tipped = array_filter($normalized, fn (array $tender) => $tender['tip'] !== null);
        if ($tipped !== []) {
            if ($offlinePaidAt === null) {
                $this->modules->assertEnabled(Branch::findOrFail((int) $order->branch_id), PosModule::TIPS);
            }
            if ($tipStaffId !== null && TipService::recipient((int) $order->store_id, (int) $order->branch_id, $tipStaffId) === null) {
                if ($offlinePaidAt === null) {
                    throw PosActionException::invalid('tip_staff_not_found', 'Choy puli beriladigan xodim topilmadi.');
                }
                $tipStaffId = null;
            }
        }

        // A line that cannot be receipted (no MXIK...) refuses the payment before any money is taken (422)
        if ($bill->status->isPayable()) {
            $this->fiscalizer->lines($bill);
        }

        [$bill, $covered] = DB::transaction(function () use ($user, $bill, $normalized, $approvalToken, $shift, $version, $device, $applied, $offlinePaidAt, $tipStaffId) {
            // Review P3-35: the shift's row first (the Z and refunds lock it too, then the orders): a shift closed
            // meanwhile takes no more money, and a closing Z never misses a payment made at the same moment
            $shift = PosShift::lockForMoney((int) $shift->id);
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
            $tips = [];
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
                if ($tender['tip'] !== null) {
                    $tips[] = $this->tips->record($order, $bill, $payment, $shift, $tender['tip_method'], $tender['tip'], $tender['tip_source'], $tipStaffId, $user, $device);
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
                // P6-22: tips taken with these payments (never part of the bill or the receipt)
                'tips' => array_map(fn (\App\Models\PosTip $tip) => ['uuid' => $tip->uuid, 'method' => $tip->method->value, 'amount' => $tip->amount, 'mode' => $tip->mode->value, 'staff_id' => $tip->staff_id], $tips),
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
     * @return list<array{tender: PosTender, amount: Money, tendered: Money|null, change: Money|null, card_type: string|null, transaction_id: string|null, card_last4: string|null, reference: string|null, account_uuid: string|null, tip: Money|null, tip_method: TipMethod, tip_source: TipSource}>
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

            // P6-22: a tip on top of the tender (cash, card, QR), or the change left as one (cash)
            $tip = filled($input['tip_amount'] ?? null) ? Money::of((string) $input['tip_amount']) : null;
            $fromChange = filter_var($input['tip_from_change'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $tipMethod = TipMethod::forTender($tender);
            if (($tip !== null || $fromChange) && $tipMethod === null) {
                throw PosActionException::invalid('tip_tender_invalid', "Choy puli faqat naqd, karta yoki QR bilan olinadi.", ['index' => $i]);
            }
            if ($tip !== null && !$tip->isPositive()) {
                throw PosActionException::invalid('tip_invalid', "Choy puli summasi musbat bo'lishi kerak.", ['index' => $i]);
            }
            if ($fromChange && ($tender !== PosTender::CASH || !filled($input['tendered_amount'] ?? null))) {
                throw PosActionException::invalid('tip_from_change_invalid', "Qaytimni choy puliga qoldirish uchun berilgan naqd summani kiriting.", ['index' => $i]);
            }

            $tendered = null;
            $change = null;
            if ($tender === PosTender::CASH && filled($input['tendered_amount'] ?? null)) {
                $tendered = Money::of((string) $input['tendered_amount']);
                if ($tendered->isLessThan($amount)) {
                    throw PosActionException::invalid('tendered_too_small', "Berilgan naqd summa to'lov summasidan kam.", ['index' => $i]);
                }
                $change = $tendered->minus($amount);
                if ($fromChange) {
                    $tip ??= $change;
                    if (!$tip->isPositive()) {
                        throw PosActionException::invalid('tip_invalid', "Qaytim yo'q: choy puliga qoldiradigan summa yo'q.", ['index' => $i]);
                    }
                }
                if ($tip !== null) {
                    if ($tip->isGreaterThan($change)) {
                        throw PosActionException::invalid('tendered_too_small', "Berilgan naqd summa to'lov va choy pulidan kam.", ['index' => $i]);
                    }
                    $change = $change->minus($tip);
                }
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
                'tip' => $tip,
                'tip_method' => $tipMethod ?? TipMethod::CASH,
                'tip_source' => $fromChange ? TipSource::CHANGE : TipSource::TENDER,
            ];
        }

        return $normalized;
    }
}
