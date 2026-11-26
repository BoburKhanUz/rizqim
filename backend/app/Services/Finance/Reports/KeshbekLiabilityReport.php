<?php

namespace App\Services\Finance\Reports;

use App\Enums\PaymentStatus;
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
 *   row counts, whatever its status now: a later refund does not undo the debit, it is counted under `returned`.
 *   Owner decision B: a row OrderStatusService::refundKeshbek wrote at the refund of a legacy order whose debit left
 *   none (payload backfilled_without_debit_record, debited_at = the order's placing) is spent at debited_at (an
 *   unreadable one: its created_at, counted as invalid) and returned at its refunded_at, like any cancellation;
 * - returned: what refunds gave back to the balance, from the keshbek payment's payload (no other record exists): each
 *   part of an approved refund request (refunded_to_balance_parts[].amount, at its refunded_at;
 *   RefundRequestService::spread) and the give-back of a cancellation or full refund (refunded_to_balance, at
 *   refunded_at; OrderStatusService::refundKeshbek) — the latter only when it is not the whole-amount marker that a
 *   request returning the last part also writes (the KeshbekHistory rule: refunded_to_balance_by_requests set, or no
 *   parts). A missing refunded_at falls back to the payment's created_at, as KeshbekHistory does; so does one the
 *   report cannot read (review M2: only ISO 8601 with an explicit offset or Z is read — an offset-less time's zone is
 *   unknown — and only plain decimals as amounts; a part whose amount is unreadable is not counted). Every rejected
 *   payload value is counted in `unrecorded.invalid_payload_values`, shown beside the difference; no value can fail
 *   the report.
 * - transferred (owner decision A): balances moved from one users row to another (keshbek_transfers, by created_at;
 *   a debt moved is negative): an unclaimed invite row's balance handed to the account that accepted the invite
 *   (User::mergeInviteInto). The same amount leaves one row and reaches another, so it nets to zero platform-wide and
 *   is not part of the reconciliation's movement; shown apart.
 *
 * No other code in app/ writes users.balance. Nor does any path drop a balance with the users row: an account merge
 * requires a zero balance and no referred friends (AccountLinkingService), and a users row that holds keshbek (a
 * non-zero balance or friends who name it as referrer, User::holdsKeshbek — any user can be a referrer, staff
 * included; unclaimed invite rows only in data from before ReferralService refused them) is never deleted: the owner
 * removing an employee takes them out of the store instead (OwnerEmployeeController::destroy), and an invite row
 * merged into an existing account first hands its balance (with the transfer record) and its friends to that account
 * (User::mergeInviteInto). A change made around these paths has no record and shows in the reconciliation's
 * `difference`, which is reported, never hidden: a hand edit in the database, or a legacy keshbek order without a
 * keshbek payment row (`unrecorded.keshbek_orders_without_payment` counts them). Such an order's debit left no record,
 * so while it stands the difference is −total; when it is cancelled or refunded, refundKeshbek gives the whole total
 * back and writes the missing payment row (owner decision B, above): the debit and the give-back are both recorded,
 * the difference returns to 0 and the order is no longer counted. One refunded before that change wrote nothing
 * either way: still counted, its difference 0.
 *
 * Reconciliation: opening (every movement before the month, from a zero start) + issued − clawed_back +
 * legacy_store_paid − spent + returned = closing (the month-end total from movements); closing + the movements after
 * the month = expected_now; live = SUM(users.balance) now; difference = live − expected_now (0 while every balance
 * change is recorded). `outstanding` (positive balances = the platform's liability, negative ones = customers' debts
 * after a clawback) is a snapshot of now (`as_of`), not of the month end: the month-end total is `closing`.
 *
 * Read-only, as the login role: an admin request is not tenant-bound (like MonthlyRevenueReport). One snapshot for
 * every query (build). The store_transactions sources are read in one pass through a partial index (LEDGER_ROWS), the
 * keshbek payments through the provider-leading index payments_provider_scope_transaction_unique.
 */
class KeshbekLiabilityReport
{
    /**
     * The keshbek rows of store_transactions, as SQL: the platform's referral bonuses and their clawbacks, and the
     * legacy store-paid bonuses. Literals, not bindings, and word for word the predicate of the partial index
     * store_transactions_keshbek_movements_index (migration 2026_12_11_100000), so the planner can prove the index
     * applies (KeshbekLiabilityReportTest::test_the_report_reads_through_its_indexes).
     */
    public const LEDGER_ROWS = "type = 'referral_bonus' OR (type = 'commission' AND notes LIKE 'Referral cashback reward cost%')";

    /**
     * A payload time the report reads: ISO 8601 with an explicit offset or Z (what toIso8601String writes). A time
     * without an offset is not read (in which zone it was written is unknown), nor an epoch, nor anything else. The
     * day is checked against the month apart (timestampOrNull), so no cast can fail.
     */
    private const TIMESTAMP = '^[1-9][0-9]{3}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])[T ]([01][0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9](\.[0-9]+)?)?(Z|[+-](0[0-9]|1[0-5])(:?[0-5][0-9])?)$';

    /** A payload amount the report reads: a plain decimal (no exponent, NaN, Infinity, blanks or empty string). */
    private const DECIMAL = '^-?[0-9]+(\.[0-9]+)?$';

    /**
     * Review M1: every query reads the same snapshot, in one REPEATABLE READ READ ONLY transaction: a balance change
     * committed while the report is being read (a bonus booked with its +5 000) is wholly in it or wholly out of it,
     * never in SUM(users.balance) alone (a false difference). SET TRANSACTION must be the transaction's first
     * statement, so it is sent only when this is the outermost one (level 1): inside a transaction somebody else opened
     * (the test suite's RefreshDatabase, a console command's) it is a savepoint, where PostgreSQL refuses it
     * ("must be called before any query"), and that transaction's own snapshot rules apply. An admin request is not
     * tenant-bound, so nothing runs between BEGIN and it (RevisionPostgresConnection takes the tenant role only for a
     * bound store; a SET LOCAL ROLE takes no snapshot, so even then it would still be first).
     *
     * @return array{period: string, timezone: string, as_of: string, issued: array{count: int, amount: Money}, clawed_back: array{count: int, amount: Money}, legacy_store_paid: array{count: int, amount: Money}, spent: array{count: int, amount: Money}, returned: array{count: int, amount: Money}, transferred: array{count: int, amount: Money}, outstanding: array{positive: Money, negative: Money, net: Money, holders: int, debtors: int}, reconciliation: array{opening: Money, movement: Money, closing: Money, after_period: Money, expected_now: Money, live: Money, difference: Money}, unrecorded: array{keshbek_orders_without_payment: int, invalid_payload_values: int}}
     */
    public function build(string $period): array
    {
        return DB::transaction(function () use ($period) {
            if (DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            }

            return $this->read($period);
        });
    }

    /**
     * @return array{period: string, timezone: string, as_of: string, issued: array{count: int, amount: Money}, clawed_back: array{count: int, amount: Money}, legacy_store_paid: array{count: int, amount: Money}, spent: array{count: int, amount: Money}, returned: array{count: int, amount: Money}, transferred: array{count: int, amount: Money}, outstanding: array{positive: Money, negative: Money, net: Money, holders: int, debtors: int}, reconciliation: array{opening: Money, movement: Money, closing: Money, after_period: Money, expected_now: Money, live: Money, difference: Money}, unrecorded: array{keshbek_orders_without_payment: int, invalid_payload_values: int}}
     */
    private function read(string $period): array
    {
        [$start, $end] = MonthlyRevenueReport::monthBounds($period);

        // Review M3: the three store_transactions sources in one pass over the keshbek rows (the partial index)
        $ledger = new KeshbekBuckets($start, $end);
        $ledger->add('issued', 'created_at', "CASE WHEN type = 'referral_bonus' AND amount > 0 THEN amount END");
        $ledger->add('clawed', 'created_at', "CASE WHEN type = 'referral_bonus' AND amount < 0 THEN -amount END");
        $ledger->add('legacy', 'created_at', "CASE WHEN type = 'commission' THEN amount END");
        $ledgerRow = $ledger->select(DB::table('store_transactions')->whereRaw('(' . self::LEDGER_ROWS . ')'))->first();
        [$issued, $clawed, $legacy] = [$ledger->read($ledgerRow, 'issued'), $ledger->read($ledgerRow, 'clawed'), $ledger->read($ledgerRow, 'legacy')];

        // The keshbek payments in one pass: what was spent, and the give-back of a cancellation or full refund
        // (refunded_to_balance, unless it is the whole-amount marker a request returning the last part also writes:
        // counted only with refunded_to_balance_by_requests set, or without parts — the KeshbekHistory rule)
        $parts = "p.payload->'refunded_to_balance_parts'";
        $giveBack = "p.payload->>'refunded_to_balance'";
        $giveBackAt = "p.payload->>'refunded_at'";
        $marker = "(p.payload->'refunded_to_balance_by_requests' IS NOT NULL OR CASE WHEN jsonb_typeof({$parts}) = 'array' THEN jsonb_array_length({$parts}) = 0 ELSE true END)";
        // Owner decision B: a payment row refundKeshbek wrote for a legacy order whose debit left none
        // (backfilled_without_debit_record) was spent when the order was placed (debited_at), not when it was written
        $debitedAt = "p.payload->>'debited_at'";
        $spentAt = "CASE WHEN p.payload->>'backfilled_without_debit_record' = 'true' THEN COALESCE(" . self::timestampOrNull($debitedAt) . ', p.created_at) ELSE p.created_at END';
        $payments = new KeshbekBuckets($start, $end);
        $payments->add('spent', $spentAt, 'p.amount');
        $payments->add('cancelled', 'COALESCE(' . self::timestampOrNull($giveBackAt) . ', p.created_at)', 'CASE WHEN ' . self::decimalOrNull($giveBack) . " > 0 THEN CASE WHEN {$marker} THEN " . self::decimalOrNull($giveBack) . ' END END');
        $paymentRow = $payments->select(DB::table('payments as p')->where('p.provider', 'keshbek'), [
            'invalid' => 'COUNT(*) FILTER (WHERE ' . self::invalid($giveBack, self::decimalOrNull($giveBack)) . ') + COUNT(*) FILTER (WHERE ' . self::invalid($giveBackAt, self::timestampOrNull($giveBackAt)) . ')'
                . ' + COUNT(*) FILTER (WHERE ' . self::invalid($debitedAt, self::timestampOrNull($debitedAt)) . ')',
        ])->first();
        [$spent, $cancellations] = [$payments->read($paymentRow, 'spent'), $payments->read($paymentRow, 'cancelled')];

        // Each part of an approved refund request (RefundRequestService::spread), at its refunded_at; a part that is
        // not an object or whose amount is unreadable is not counted
        $partAmount = "part->>'amount'";
        $partAt = "part->>'refunded_at'";
        $requests = new KeshbekBuckets($start, $end);
        $requests->add('parts', 'COALESCE(' . self::timestampOrNull($partAt) . ', p.created_at)', self::decimalOrNull($partAmount));
        $partRow = $requests->select(
            DB::table('payments as p')
                ->crossJoin(DB::raw("LATERAL jsonb_array_elements(CASE WHEN jsonb_typeof({$parts}) = 'array' THEN {$parts} ELSE '[]'::jsonb END) AS part"))
                ->where('p.provider', 'keshbek'),
            ['invalid' => "COUNT(*) FILTER (WHERE jsonb_typeof(part) <> 'object' OR " . self::decimalOrNull($partAmount) . ' IS NULL) + COUNT(*) FILTER (WHERE ' . self::invalid($partAt, self::timestampOrNull($partAt)) . ')'],
        )->first();
        $requestParts = $requests->read($partRow, 'parts');

        // Owner decision A: balances moved between users rows (KeshbekTransfer), by created_at. Each moves the same
        // amount off one row and onto another: no part of the movement (net 0 platform-wide), shown apart
        $transfers = new KeshbekBuckets($start, $end);
        $transfers->add('transferred', 'created_at', 'amount');
        $transferred = $transfers->read($transfers->select(DB::table('keshbek_transfers'))->first(), 'transferred');

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

        // Keshbek orders whose debit left no payment row (older data, if any): while one stands its debit is unrecorded
        // (the difference is −total); refundKeshbek writes the missing row with its give-back (owner decision B), so
        // one refunded since is no longer counted. One refunded before that wrote nothing either way (net 0)
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
            'transferred' => $month($transferred),
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
            'unrecorded' => [
                'keshbek_orders_without_payment' => $withoutPayment,
                'invalid_payload_values' => (int) ($paymentRow->invalid ?? 0) + (int) ($partRow->invalid ?? 0),
            ],
        ];
    }

    /**
     * SQL: the payload text $text as a timestamp in the app's time zone (the zone created_at is written in), or NULL
     * when it is not one the report reads (TIMESTAMP; a day past the month's end, such as 30 February, too). Nested
     * CASEs, because PostgreSQL evaluates CASE branches in order but not the operands of AND: no cast ever sees a value
     * it would fail on.
     */
    private static function timestampOrNull(string $text): string
    {
        $lastDay = "extract(day from make_date(substr({$text}, 1, 4)::int, substr({$text}, 6, 2)::int, 1) + interval '1 month' - interval '1 day')";

        return "(CASE WHEN {$text} ~ '" . self::TIMESTAMP . "' THEN CASE WHEN substr({$text}, 9, 2)::int <= {$lastDay} "
            . "THEN ({$text})::timestamptz AT TIME ZONE " . self::appTimezoneLiteral() . ' END END)';
    }

    /** SQL: the payload text $text as a number, or NULL when it is not a plain decimal (DECIMAL). */
    private static function decimalOrNull(string $text): string
    {
        return "(CASE WHEN {$text} ~ '" . self::DECIMAL . "' THEN ({$text})::numeric END)";
    }

    /** SQL: $text is there but unreadable ($parsed, its timestampOrNull or decimalOrNull, is NULL). */
    private static function invalid(string $text, string $parsed): string
    {
        return "{$text} IS NOT NULL AND {$parsed} IS NULL";
    }

    /** config('app.timezone') as an SQL string literal; only a known zone name, so it is never anything else. */
    private static function appTimezoneLiteral(): string
    {
        $zone = (string) config('app.timezone');

        return "'" . (in_array($zone, \DateTimeZone::listIdentifiers(), true) ? $zone : 'UTC') . "'";
    }
}
