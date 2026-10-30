<?php

namespace App\Domain\Pos\TerminalFlow;

use App\Domain\Pos\Contracts\PosTender;
use App\Models\Payment;
use App\Models\PosRefund;
use App\Models\PosShift;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * The card money Rizqim recorded in a shift (P6-16, D5 d): what the branch's bank terminal should show for the same
 * period. Card payments of bills, card booking deposits and nasiya repayments taken on the shift's till during it
 * (the same rows the X/Z report counts), less card refunds of bills and card deposit refunds made on the shift.
 * `transactions` are the money-in rows with the terminal's transaction (RRN) number, to match an imported bank
 * report against.
 *
 * P6-22: a card tip is charged on the terminal with the bill it was given with: its payment's transaction is the bill
 * share plus the tip (as taken; a manager's later correction does not change what the terminal charged), and
 * `breakdown.tips` sums them.
 */
final class ShiftCardTotals
{
    public const BREAKDOWN = 'array{payments: string, deposits: string, repayments: string, refunds: string, deposit_refunds: string, tips: string}';

    /**
     * @return array{total: Money, count: int, breakdown: array{payments: string, deposits: string, repayments: string, refunds: string, deposit_refunds: string, tips: string}, transactions: list<array{rrn: string|null, amount: string, kind: string}>}
     */
    public function of(PosShift $shift): array
    {
        $from = $shift->opened_at;
        $to = $shift->closed_at ?? now();
        $card = PosTender::CARD->value;

        $transactions = [];

        $payments = Payment::where('shift_id', $shift->id)->where('provider', $card)->whereIn('status', ['paid', 'refunded'])
            ->orderBy('id')->get(['id', 'amount', 'transaction_id']);
        $tips = DB::table('pos_tips')->whereIn('payment_id', $payments->pluck('id'))->where('kind', 'tip')->where('method', $card)
            ->groupBy('payment_id')->selectRaw('payment_id, sum(amount) as amount')->pluck('amount', 'payment_id');
        $tipTotal = Money::zero();
        foreach ($payments as $payment) {
            $tip = Money::of(is_numeric($tips[$payment->id] ?? null) ? (string) $tips[$payment->id] : '0');
            $tipTotal = $tipTotal->plus($tip);
            $transactions[] = ['rrn' => $this->rrn($payment->getAttribute('transaction_id')), 'amount' => Money::of($payment->amount)->plus($tip)->toString(), 'kind' => 'payment'];
        }

        $deposits = $shift->device_id === null ? collect() : DB::table('booking_payments')
            ->where('device_id', $shift->device_id)->where('tender', $card)->whereBetween('created_at', [$from, $to])
            ->orderBy('id')->get(['amount', 'transaction_id']);
        foreach ($deposits as $deposit) {
            $transactions[] = ['rrn' => $this->rrn($deposit->transaction_id), 'amount' => Money::of((string) $deposit->amount)->toString(), 'kind' => 'deposit'];
        }

        // A repayment lowers the debt: its entry amount is negative (P3-17)
        $repayments = $shift->device_id === null ? collect() : DB::table('customer_account_entries')
            ->where('device_id', $shift->device_id)->where('type', 'repayment')->where('tender', $card)->whereBetween('created_at', [$from, $to])
            ->orderBy('id')->get(['amount', 'transaction_id']);
        foreach ($repayments as $repayment) {
            $transactions[] = ['rrn' => $this->rrn($repayment->transaction_id), 'amount' => Money::of((string) $repayment->amount)->abs()->toString(), 'kind' => 'repayment'];
        }

        $refunds = Money::sum('0', ...PosRefund::where('shift_id', $shift->id)->where('method', $card)->pluck('amount')->all());
        $depositRefunds = Money::sum('0', ...DB::table('booking_payments')->where('refunded_shift_id', $shift->id)->where('tender', $card)
            ->where('refunded_amount', '>', 0)->pluck('refunded_amount')->map(fn ($amount) => (string) $amount)->all());

        $sumOf = fn (string $kind) => Money::sum('0', ...array_column(array_filter($transactions, fn (array $row) => $row['kind'] === $kind), 'amount'));
        $in = Money::sum('0', ...array_column($transactions, 'amount'));

        return [
            'total' => $in->minus($refunds)->minus($depositRefunds),
            'count' => count($transactions),
            'breakdown' => [
                'payments' => $sumOf('payment')->toString(),
                'deposits' => $sumOf('deposit')->toString(),
                'repayments' => $sumOf('repayment')->toString(),
                'refunds' => $refunds->toString(),
                'deposit_refunds' => $depositRefunds->toString(),
                // P6-22: card tips inside the payments' transactions
                'tips' => $tipTotal->toString(),
            ],
            'transactions' => $transactions,
        ];
    }

    /**
     * Review P6 C#M2: the card money of several shifts together (the shifts on one shared bank terminal): totals,
     * counts and the breakdown summed, the transactions of all of them.
     *
     * @param  iterable<PosShift>  $shifts
     * @return array{total: Money, count: int, breakdown: array{payments: string, deposits: string, repayments: string, refunds: string, deposit_refunds: string, tips: string}, transactions: list<array{rrn: string|null, amount: string, kind: string}>}
     */
    public function ofShifts(iterable $shifts): array
    {
        $total = Money::zero();
        $count = 0;
        $breakdown = ['payments' => Money::zero(), 'deposits' => Money::zero(), 'repayments' => Money::zero(), 'refunds' => Money::zero(), 'deposit_refunds' => Money::zero(), 'tips' => Money::zero()];
        $transactions = [];
        foreach ($shifts as $shift) {
            $one = $this->of($shift);
            $total = $total->plus($one['total']);
            $count += $one['count'];
            foreach ($one['breakdown'] as $key => $amount) {
                $breakdown[$key] = $breakdown[$key]->plus($amount);
            }
            array_push($transactions, ...$one['transactions']);
        }

        return [
            'total' => $total,
            'count' => $count,
            'breakdown' => array_map(fn (Money $amount) => $amount->toString(), $breakdown),
            'transactions' => $transactions,
        ];
    }

    /**
     * A transaction number as matched: trimmed, without leading zeros (a slip prints "000123456789", a cashier may
     * type "123456789"); "offline" (a payment synced without one) and empty are none.
     */
    public static function normalizeRrn(mixed $value): ?string
    {
        $rrn = strtoupper(trim(is_scalar($value) ? (string) $value : ''));
        if ($rrn === '' || $rrn === 'OFFLINE') {
            return null;
        }
        $stripped = ltrim($rrn, '0');

        return $stripped === '' ? '0' : $stripped;
    }

    private function rrn(mixed $value): ?string
    {
        $rrn = trim(is_scalar($value) ? (string) $value : '');

        return $rrn === '' ? null : $rrn;
    }
}
