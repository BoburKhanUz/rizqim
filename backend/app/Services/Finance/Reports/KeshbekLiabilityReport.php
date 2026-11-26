<?php

namespace App\Services\Finance\Reports;

use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Services\Finance\LedgerService;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Moliya → Oylik hisobot → Keshbek: the keshbek (users.balance) the customers hold — the platform's liability — and its
 * movements in one Asia/Tashkent month (MonthlyRevenueReport::monthBounds), read back from where each balance change
 * is booked. There is no keshbek ledger table (docs/BILLING.md "Keshbek majburiyati"):
 *
 * - issued: platform-paid referral bonuses granted — store_transactions of type referral_bonus with amount > 0, written
 *   with the +5 000 by LedgerService::grantReferralReward —, by created_at;
 * - clawed_back: their clawbacks — the same type, amount < 0 (LedgerService::clawBackReferralReward) —, by created_at;
 * - legacy_store_paid: the bonuses stores paid before owner decision 3's addition (type commission, notes starting with
 *   LedgerService::REFERRAL_NOTE). They credited the balance as well, so they are a movement, but never the platform's
 *   cost: apart from `issued`;
 * - spent: keshbek payments (payments.provider = keshbek), by created_at. CreateOrderAction writes the row `paid` in the
 *   transaction that debits the balance (it sets no paid_at), so created_at is when the keshbek was spent. Every such
 *   row counts, whatever its status now: a later refund does not undo the debit, it is counted under `returned`;
 * - returned: what refunds gave back to the balance, from the keshbek payment's payload (no other record exists): each
 *   part of an approved refund request (refunded_to_balance_parts[].amount, at its refunded_at;
 *   RefundRequestService::spread) and the give-back of a cancellation or full refund (refunded_to_balance, at
 *   refunded_at; OrderStatusService::refundKeshbek) — the latter only when it is not the whole-amount marker that a
 *   request returning the last part also writes (the KeshbekHistory rule: refunded_to_balance_by_requests set, or no
 *   parts). A missing refunded_at falls back to the payment's created_at, as KeshbekHistory does.
 *
 * No other code in app/ writes users.balance (an account merge requires a zero balance; staff, admin and privacy paths
 * never touch it). A change made around these paths — a hand edit in the database, or a legacy keshbek order without
 * a keshbek payment row that is then cancelled or refunded (refundKeshbek gives the whole total back and writes
 * nothing; `unrecorded.keshbek_orders_without_payment` counts such orders) — has no record: it shows in the
 * reconciliation's `difference`, which is reported, never hidden.
 *
 * Reconciliation: opening (every movement before the month, from a zero start) + issued − clawed_back +
 * legacy_store_paid − spent + returned = closing (the month-end total from movements); closing + the movements after
 * the month = expected_now; live = SUM(users.balance) now; difference = live − expected_now (0 while every balance
 * change is recorded). `outstanding` (positive balances = the platform's liability, negative ones = customers' debts
 * after a clawback) is a snapshot of now (`as_of`), not of the month end: the month-end total is `closing`.
 *
 * Read-only, as the login role: an admin request is not tenant-bound (like MonthlyRevenueReport).
 */
class KeshbekLiabilityReport
{
    /**
     * @return array{period: string, timezone: string, as_of: string, issued: array{count: int, amount: Money}, clawed_back: array{count: int, amount: Money}, legacy_store_paid: array{count: int, amount: Money}, spent: array{count: int, amount: Money}, returned: array{count: int, amount: Money}, outstanding: array{positive: Money, negative: Money, net: Money, holders: int, debtors: int}, reconciliation: array{opening: Money, movement: Money, closing: Money, after_period: Money, expected_now: Money, live: Money, difference: Money}, unrecorded: array{keshbek_orders_without_payment: int}}
     */
    public function build(string $period): array
    {
        [$start, $end] = MonthlyRevenueReport::monthBounds($period);
        $bonus = StoreTransactionType::REFERRAL_BONUS->value;
        // A JSON timestamp (ISO 8601 with an offset) in the app's time zone, the zone created_at is written in
        $local = fn (string $json) => "(({$json})::timestamptz AT TIME ZONE " . self::appTimezoneLiteral() . ')';

        $issued = $this->buckets(DB::table('store_transactions')->where('type', $bonus)->where('amount', '>', 0), 'created_at', 'amount', $start, $end);
        $clawed = $this->buckets(DB::table('store_transactions')->where('type', $bonus)->where('amount', '<', 0), 'created_at', '-amount', $start, $end);
        $legacy = $this->buckets(
            DB::table('store_transactions')->where('type', StoreTransactionType::COMMISSION->value)->where('notes', 'like', LedgerService::REFERRAL_NOTE . '%'),
            'created_at',
            'amount',
            $start,
            $end,
        );
        $spent = $this->buckets(DB::table('payments')->where('provider', 'keshbek'), 'created_at', 'amount', $start, $end);

        $parts = "p.payload->'refunded_to_balance_parts'";
        $requestParts = $this->buckets(
            DB::table('payments as p')
                ->crossJoin(DB::raw("LATERAL jsonb_array_elements(CASE WHEN jsonb_typeof({$parts}) = 'array' THEN {$parts} ELSE '[]'::jsonb END) AS part"))
                ->where('p.provider', 'keshbek')
                ->whereRaw("jsonb_typeof(part) = 'object' AND part->>'amount' IS NOT NULL"),
            'COALESCE(' . $local("part->>'refunded_at'") . ', p.created_at)',
            "(part->>'amount')::numeric",
            $start,
            $end,
        );
        $cancellations = $this->buckets(
            DB::table('payments as p')
                ->where('p.provider', 'keshbek')
                ->whereRaw("p.payload->>'refunded_to_balance' IS NOT NULL AND (p.payload->>'refunded_to_balance')::numeric > 0")
                ->whereRaw("(p.payload->'refunded_to_balance_by_requests' IS NOT NULL OR CASE WHEN jsonb_typeof({$parts}) = 'array' THEN jsonb_array_length({$parts}) = 0 ELSE true END)"),
            'COALESCE(' . $local("p.payload->>'refunded_at'") . ', p.created_at)',
            "(p.payload->>'refunded_to_balance')::numeric",
            $start,
            $end,
        );
        $returned = [
            'before' => $requestParts['before']->plus($cancellations['before']),
            'within' => $requestParts['within']->plus($cancellations['within']),
            'count' => $requestParts['count'] + $cancellations['count'],
            'after' => $requestParts['after']->plus($cancellations['after']),
        ];

        // + issued − clawed_back + legacy − spent + returned, per bucket
        $net = fn (string $bucket): Money => $issued[$bucket]->minus($clawed[$bucket])->plus($legacy[$bucket])->minus($spent[$bucket])->plus($returned[$bucket]);
        $opening = $net('before');
        $movement = $net('within');
        $closing = $opening->plus($movement);
        $afterPeriod = $net('after');
        $expectedNow = $closing->plus($afterPeriod);

        $live = DB::table('users')->selectRaw(
            'COALESCE(SUM(CASE WHEN balance > 0 THEN balance END), 0) AS positive, COALESCE(SUM(CASE WHEN balance < 0 THEN balance END), 0) AS negative, '
            . 'COUNT(CASE WHEN balance > 0 THEN 1 END) AS holders, COUNT(CASE WHEN balance < 0 THEN 1 END) AS debtors'
        )->first();
        $positive = Money::of((string) ($live->positive ?? '0'));
        $negative = Money::of((string) ($live->negative ?? '0'));
        $liveTotal = $positive->plus($negative);

        // Keshbek orders whose debit left no payment row (older data, if any): refundKeshbek gives them back unrecorded
        $withoutPayment = DB::table('orders as o')
            ->where('o.payment_method', 'keshbek')
            ->whereIn('o.payment_status', [PaymentStatus::PAID->value, PaymentStatus::REFUNDED->value])
            ->whereNotExists(fn (Builder $query) => $query->from('payments as p')->whereColumn('p.order_id', 'o.id')->where('p.provider', 'keshbek'))
            ->count();

        $month = fn (array $source) => ['count' => $source['count'], 'amount' => $source['within']];

        return [
            'period' => $period,
            'timezone' => MonthlyRevenueReport::timezoneName(),
            'as_of' => Carbon::now()->toIso8601String(),
            'issued' => $month($issued),
            'clawed_back' => $month($clawed),
            'legacy_store_paid' => $month($legacy),
            'spent' => $month($spent),
            'returned' => $month($returned),
            'outstanding' => [
                'positive' => $positive,
                'negative' => $negative,
                'net' => $liveTotal,
                'holders' => (int) ($live->holders ?? 0),
                'debtors' => (int) ($live->debtors ?? 0),
            ],
            'reconciliation' => [
                'opening' => $opening,
                'movement' => $movement,
                'closing' => $closing,
                'after_period' => $afterPeriod,
                'expected_now' => $expectedNow,
                'live' => $liveTotal,
                'difference' => $liveTotal->minus($expectedNow),
            ],
            'unrecorded' => ['keshbek_orders_without_payment' => $withoutPayment],
        ];
    }

    /**
     * One source's amounts before the month, in it (and how many rows) and after it. $at is a timestamp in the app's
     * time zone; $amount the row's (positive) effect on the balances, both SQL expressions of the code above.
     *
     * @return array{before: Money, within: Money, count: int, after: Money}
     */
    private function buckets(Builder $query, string $at, string $amount, string $start, string $end): array
    {
        $row = $query->selectRaw(
            "COALESCE(SUM(CASE WHEN {$at} < ? THEN {$amount} END), 0) AS sum_before, "
            . "COALESCE(SUM(CASE WHEN {$at} >= ? AND {$at} < ? THEN {$amount} END), 0) AS sum_within, "
            . "COUNT(CASE WHEN {$at} >= ? AND {$at} < ? THEN 1 END) AS count_within, "
            . "COALESCE(SUM(CASE WHEN {$at} >= ? THEN {$amount} END), 0) AS sum_after",
            [$start, $start, $end, $start, $end, $end]
        )->first();

        return [
            'before' => Money::of((string) ($row->sum_before ?? '0')),
            'within' => Money::of((string) ($row->sum_within ?? '0')),
            'count' => (int) ($row->count_within ?? 0),
            'after' => Money::of((string) ($row->sum_after ?? '0')),
        ];
    }

    /** config('app.timezone') as an SQL string literal; only a known zone name, so it is never anything else. */
    private static function appTimezoneLiteral(): string
    {
        $zone = (string) config('app.timezone');

        return "'" . (in_array($zone, \DateTimeZone::listIdentifiers(), true) ? $zone : 'UTC') . "'";
    }
}
