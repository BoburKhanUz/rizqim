<?php

namespace App\Domain\Pos\Shifts;

use App\Domain\Pos\Billing\BillStatus;
use App\Domain\Pos\Contracts\PosTender;
use App\Models\CashMovement;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Models\PosRefund;
use App\Models\PosShift;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The X report of a shift (and, frozen at closing, its Z report; POS_SPEC 2.3, 2.13, research R21, R22): money taken
 * by tender and card type, by channel and by waiter (with the service charge they brought in), discounts, voided
 * lines, refunds, booking deposits taken and applied, sales on credit and debt repayments, the cash drawer's
 * movements and the cash expected in it. Amounts are decimal strings.
 *
 * What belongs to the shift: payments, refunds, cash movements and bills paid with its id; voided lines of its branch
 * during its time; deposits and repayments (P3-17 tables) taken on its terminal during its time; deposits refunded
 * on it (booking_payments.refunded_shift_id; cash ones were paid from its drawer). Cash deposits and cash repayments count toward the expected
 * cash, cash deposit refunds against it: the booking and account tables stay the one record of that money (nothing
 * of it is copied into cash_movements, so it can never be counted twice).
 */
final class ShiftReport
{
    private const COUNT_AMOUNT = 'array{count: int, amount: string}';

    /** One drawer movement, as the X/Z report and POST /pos/shifts/{uuid}/cash-movements show it. */
    public const MOVEMENT = 'array{uuid: string, type: string, category: string|null, amount: string, note: string|null, employee: array{id: int, name: string|null}|null, user: array{id: int, name: string|null}|null, approved_by: int|null, approver?: array{id: int, name: string|null}|null, created_at: string|null}';

    /** The fields of the X report (ShiftPresenter::SUMMARY adds the Z report's own). */
    public const REPORT_FIELDS = 'shift_uuid: string, generated_at: string, period: array{from: string, to: string|null}, sales: array{bills: int, received: string, refunded: string, net: string, bills_total: string, subtotal: string, discount: string, room_fee: string, min_spend_topup: string, service_charge: string, place_fee: string, average_bill: string}, by_tender: list<array{tender: string, count: int, amount: string}>, cards: list<array{card_type: string, count: int, amount: string}>, by_channel: list<array{channel: string, orders: int, amount: string}>, by_waiter: list<array{waiter_id: int|null, name: string|null, orders: int, bills: int, amount: string, service_charge: string}>, discounts: ' . self::COUNT_AMOUNT . ', voids: ' . self::COUNT_AMOUNT . ', batch_writeoffs: ' . \App\Domain\Pos\Menu\ShiftWriteOffs::SHAPE . ', refunds: array{count: int, amount: string, by_method: list<array{method: string, count: int, amount: string}>}, deposits: array{taken: array{count: int, amount: string, cash: string}, applied: ' . self::COUNT_AMOUNT . ', refunded: array{count: int, amount: string, cash: string}}, credit: array{sales: ' . self::COUNT_AMOUNT . ', repayments: array{count: int, amount: string, cash: string}}, cash: array{opening: string, sales: string, refunds: string, cash_in: string, cash_out: string, wages: string, purchases: string, deposits: string, deposit_refunds: string, repayments: string, expected: string}, movements: list<' . self::MOVEMENT . '>';

    /** The X report (and, as kept at closing, the Z report's body: ShiftPresenter::SUMMARY). */
    public const REPORT = 'array{' . self::REPORT_FIELDS . '}';

    /**
     * @return array<string, mixed>
     */
    public function build(PosShift $shift): array
    {
        $from = $shift->opened_at;
        $to = $shift->closed_at ?? now();

        $payments = Payment::query()
            ->where('payments.shift_id', $shift->id)
            ->whereIn('payments.status', ['paid', 'refunded'])
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->get(['payments.id', 'payments.provider', 'payments.amount', 'payments.card_type', 'payments.order_id', 'orders.channel', 'orders.waiter_id']);

        $byTender = [];
        foreach (PosTender::cases() as $tender) {
            $rows = $payments->where('provider', $tender->value);
            $byTender[] = ['tender' => $tender->value, 'count' => $rows->count(), 'amount' => $this->sum($rows->pluck('amount'))];
        }

        $cards = $payments->where('provider', PosTender::CARD->value)->groupBy(fn ($row) => (string) ($row->card_type ?? 'other'))
            ->map(fn ($rows, $type) => ['card_type' => (string) $type, 'count' => $rows->count(), 'amount' => $this->sum($rows->pluck('amount'))])
            ->sortKeys()->values()->all();

        $byChannel = $payments->groupBy(fn (Payment $row) => (string) $row->getRawOriginal('channel'))
            ->map(fn ($rows, $channel) => ['channel' => (string) $channel, 'orders' => $rows->pluck('order_id')->unique()->count(), 'amount' => $this->sum($rows->pluck('amount'))])
            ->sortKeys()->values()->all();

        $bills = OrderBill::query()
            ->where('order_bills.shift_id', $shift->id)
            ->where('order_bills.status', BillStatus::PAID->value)
            ->join('orders', 'orders.id', '=', 'order_bills.order_id')
            ->get(['order_bills.*', 'orders.waiter_id']);

        $waiterIds = $payments->map(fn (Payment $row) => $row->getAttribute('waiter_id'))->merge($bills->map(fn (OrderBill $bill) => $bill->getAttribute('waiter_id')))->filter()->unique()->values();
        $names = User::whereIn('id', $waiterIds)->pluck('name', 'id');
        $byWaiter = [];
        foreach ($payments->groupBy(fn (Payment $row) => (int) $row->getAttribute('waiter_id')) as $waiterId => $rows) {
            $waiterBills = $bills->filter(fn (OrderBill $bill) => (int) $bill->getAttribute('waiter_id') === (int) $waiterId);
            $byWaiter[] = [
                'waiter_id' => $waiterId ?: null,
                'name' => $waiterId ? ($names[$waiterId] ?? null) : null,
                'orders' => $rows->pluck('order_id')->unique()->count(),
                'bills' => $waiterBills->count(),
                'amount' => $this->sum($rows->pluck('amount')),
                'service_charge' => $this->sum($waiterBills->pluck('service_charge_amount')),
            ];
        }
        usort($byWaiter, fn ($a, $b) => [$a['waiter_id'] === null, $a['waiter_id']] <=> [$b['waiter_id'] === null, $b['waiter_id']]);

        $voids = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.branch_id', $shift->branch_id)
            ->whereNotNull('order_items.voided_at')
            ->whereBetween('order_items.voided_at', [$from, $to])
            ->selectRaw('count(*) as count, coalesce(sum(order_items.total_price), 0) as amount')
            ->first();

        $refunds = PosRefund::where('shift_id', $shift->id)->get(['method', 'amount']);
        $refundsByMethod = $refunds->groupBy('method')
            ->map(fn ($rows, $method) => ['method' => (string) $method, 'count' => $rows->count(), 'amount' => $this->sum($rows->pluck('amount'))])
            ->sortKeys()->values()->all();

        $deposits = $this->deposits($shift, $from, $to);
        $repayments = $this->repayments($shift, $from, $to);

        $movements = CashMovement::where('shift_id', $shift->id)->orderBy('id')->get();
        $cashOf = fn (CashMovementType $type) => $this->sum($movements->where('type', $type)->pluck('amount'));

        $expected = Money::of($shift->opening_cash);
        foreach ($movements as $movement) {
            if ($movement->type !== CashMovementType::OPENING) {
                $expected = $movement->type->sign() > 0 ? $expected->plus($movement->amount) : $expected->minus($movement->amount);
            }
        }
        $expected = $expected->plus($deposits['taken_cash'])->plus($repayments['cash'])->minus($deposits['refunded_cash']);

        $employees = User::whereIn('id', $movements->pluck('employee_id')->merge($movements->pluck('user_id'))->merge($movements->pluck('approved_by'))->filter()->unique())->pluck('name', 'id');

        $received = $this->sum($payments->pluck('amount'));
        $refunded = $this->sum($refunds->pluck('amount'));

        return [
            'shift_uuid' => $shift->uuid,
            'generated_at' => now()->toIso8601String(),
            'period' => ['from' => $from->toIso8601String(), 'to' => $shift->closed_at?->toIso8601String()],
            'sales' => [
                'bills' => $bills->count(),
                'received' => $received,
                'refunded' => $refunded,
                'net' => Money::of($received)->minus($refunded)->toString(),
                'bills_total' => $this->sum($bills->pluck('total')),
                'subtotal' => $this->sum($bills->pluck('subtotal')),
                'discount' => $this->sum($bills->pluck('discount')),
                'room_fee' => $this->sum($bills->pluck('room_fee_amount')),
                'min_spend_topup' => $this->sum($bills->pluck('min_spend_topup_amount')),
                'service_charge' => $this->sum($bills->pluck('service_charge_amount')),
                'place_fee' => $this->sum($bills->pluck('place_fee_amount')),
                'average_bill' => $bills->count() > 0
                    ? Money::fromTiyin(intdiv(Money::of($this->sum($bills->pluck('total')))->toTiyin(), $bills->count()))->toString()
                    : '0.00',
            ],
            'by_tender' => $byTender,
            'cards' => $cards,
            'by_channel' => $byChannel,
            'by_waiter' => $byWaiter,
            'discounts' => [
                'count' => $bills->filter(fn ($bill) => Money::of($bill->discount)->isPositive())->count(),
                'amount' => $this->sum($bills->pluck('discount')),
            ],
            'voids' => ['count' => (int) ($voids->count ?? 0), 'amount' => Money::of($voids->amount ?? 0)->toString()],
            // P6-15: portions of a batch written off at this terminal during the shift (BatchService::writeOff)
            'batch_writeoffs' => app(\App\Domain\Pos\Menu\ShiftWriteOffs::class)->forShift($shift),
            'refunds' => ['count' => $refunds->count(), 'amount' => $refunded, 'by_method' => $refundsByMethod],
            'deposits' => [
                'taken' => ['count' => $deposits['count'], 'amount' => $deposits['amount'], 'cash' => $deposits['taken_cash']],
                'applied' => [
                    'count' => $payments->where('provider', PosTender::PREPAYMENT->value)->count(),
                    'amount' => $this->sum($payments->where('provider', PosTender::PREPAYMENT->value)->pluck('amount')),
                ],
                'refunded' => ['count' => $deposits['refunded_count'], 'amount' => $deposits['refunded'], 'cash' => $deposits['refunded_cash']],
            ],
            'credit' => [
                'sales' => [
                    'count' => $payments->where('provider', PosTender::ACCOUNT->value)->count(),
                    'amount' => $this->sum($payments->where('provider', PosTender::ACCOUNT->value)->pluck('amount')),
                ],
                'repayments' => ['count' => $repayments['count'], 'amount' => $repayments['amount'], 'cash' => $repayments['cash']],
            ],
            'cash' => [
                'opening' => Money::of($shift->opening_cash)->toString(),
                'sales' => $cashOf(CashMovementType::SALE),
                'refunds' => $cashOf(CashMovementType::REFUND),
                'cash_in' => $cashOf(CashMovementType::CASH_IN),
                'cash_out' => $cashOf(CashMovementType::CASH_OUT),
                'wages' => $cashOf(CashMovementType::WAGE),
                'purchases' => $cashOf(CashMovementType::PURCHASE),
                'deposits' => $deposits['taken_cash'],
                'deposit_refunds' => $deposits['refunded_cash'],
                'repayments' => $repayments['cash'],
                'expected' => $expected->toString(),
            ],
            'movements' => $movements->map(fn (CashMovement $movement) => [
                'uuid' => $movement->uuid,
                'type' => $movement->type->value,
                'category' => $movement->category,
                'amount' => Money::of($movement->amount)->toString(),
                'note' => $movement->note,
                'employee' => $movement->employee_id ? ['id' => (int) $movement->employee_id, 'name' => $employees[$movement->employee_id] ?? null] : null,
                'user' => $movement->user_id ? ['id' => (int) $movement->user_id, 'name' => $employees[$movement->user_id] ?? null] : null,
                'approved_by' => $movement->approved_by !== null ? (int) $movement->approved_by : null,
                // P3-26: the approving manager as {id, name}, like employee and user
                'approver' => $movement->approved_by !== null ? ['id' => (int) $movement->approved_by, 'name' => $employees[$movement->approved_by] ?? null] : null,
                'created_at' => $movement->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * The cash the drawer should hold now (X) or held at closing (Z).
     */
    public function expectedCash(PosShift $shift): Money
    {
        return Money::of($this->build($shift)['cash']['expected']);
    }

    /**
     * Booking deposits (P3-17's booking_payments) taken on this terminal during the shift, and refunded on this
     * shift (cash ones from its drawer).
     *
     * @return array{count: int, amount: string, taken_cash: string, refunded_count: int, refunded: string, refunded_cash: string}
     */
    private function deposits(PosShift $shift, Carbon $from, Carbon $to): array
    {
        $taken = $shift->device_id === null ? collect() : DB::table('booking_payments')
            ->where('device_id', $shift->device_id)
            ->whereBetween('created_at', [$from, $to])
            ->get(['amount', 'tender']);

        $refunded = DB::table('booking_payments')
            ->where('refunded_shift_id', $shift->id)
            ->where('refunded_amount', '>', 0)
            ->get(['refunded_amount', 'tender']);

        return [
            'count' => $taken->count(),
            'amount' => $this->sum($taken->pluck('amount')),
            'taken_cash' => $this->sum($taken->where('tender', 'cash')->pluck('amount')),
            'refunded_count' => $refunded->count(),
            'refunded' => $this->sum($refunded->pluck('refunded_amount')),
            'refunded_cash' => $this->sum($refunded->where('tender', 'cash')->pluck('refunded_amount')),
        ];
    }

    /**
     * Nasiya repayments (P3-17's customer_account_entries) taken on this terminal during the shift.
     *
     * @return array{count: int, amount: string, cash: string}
     */
    private function repayments(PosShift $shift, Carbon $from, Carbon $to): array
    {
        $rows = $shift->device_id === null ? collect() : DB::table('customer_account_entries')
            ->where('device_id', $shift->device_id)
            ->where('type', 'repayment')
            ->whereBetween('created_at', [$from, $to])
            ->get(['amount', 'tender']);

        // A repayment lowers the debt: its entry amount is negative (P3-17); the money taken is its absolute value
        $taken = fn ($rows) => $rows->pluck('amount')->map(fn ($amount) => Money::of(is_numeric($amount) ? (string) $amount : 0)->abs()->toString());

        return [
            'count' => $rows->count(),
            'amount' => $this->sum($taken($rows)),
            'cash' => $this->sum($taken($rows->where('tender', 'cash'))),
        ];
    }

    /**
     * @param  iterable<mixed>  $amounts
     */
    private function sum(iterable $amounts): string
    {
        $sum = Money::zero();
        foreach ($amounts as $amount) {
            $sum = $sum->plus(Money::of(is_numeric($amount) ? (string) $amount : 0));
        }

        return $sum->toString();
    }
}
