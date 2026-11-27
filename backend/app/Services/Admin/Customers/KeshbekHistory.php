<?php

namespace App\Services\Admin\Customers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * P4-31: a customer's keshbek movements for the user card. There is no keshbek ledger table: users.balance is the
 * truth, and its movements are read back from where they were booked —
 *
 * - spent: a keshbek payment of one of their orders (payments.provider = keshbek), minus;
 * - returned: the parts approved refund requests gave back (payload.refunded_to_balance_parts) and what a cancellation
 *   gave back (payload.refunded_to_balance; after partial refunds only the rest, refunded_to_balance_by_requests set),
 *   plus;
 * - referral bonus: 5 000 for the qualifying delivered order of each customer they invited (LedgerService: a
 *   platform-paid REFERRAL_BONUS row, or before owner decision 3's addition a store-charged commission row with the
 *   referral note; both are read), plus; and a bonus taken back on the full refund of that order (a negative
 *   REFERRAL_BONUS row), minus, or at an invite merge (referralMergeClawbackNote: labelled as such, not a refund);
 * - transfer (owner decision A): a balance moved to them from an invite row merged into their account (KeshbekTransfer,
 *   a debt minus); the earlier bonuses of the friends that moved with it went to the invite row and are inside the
 *   transfer, so they are not listed again.
 *
 * A keshbek payment written at the refund of a legacy order whose debit left none (owner decision B,
 * backfilled_without_debit_record) is spent at the order's placing (debited_at).
 *
 * The newest $limit entries; older history is not needed to read the balance (the balance itself is shown).
 */
class KeshbekHistory
{

    /**
     * @return array{balance: string, entries: list<array{kind: string, label: string, amount: string, order: array{uuid: string, short_code: string|null}|null, at: string|null}>}
     */
    public function for(User $user, int $limit = 30): array
    {
        $entries = [];

        $payments = Payment::query()
            ->whereIn('order_id', Order::query()->select('id')->where('user_id', $user->id))
            ->where('provider', 'keshbek')
            ->with('order:id,uuid,short_code')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'order_id', 'amount', 'status', 'payload', 'created_at']);

        foreach ($payments as $payment) {
            $order = $payment->order ? ['uuid' => (string) $payment->order->uuid, 'short_code' => $payment->order->short_code] : null;
            $code = $order['short_code'] ?? null;
            $payload = is_array($payment->payload) ? $payment->payload : [];
            // Owner decision B: a row written at the refund of a legacy order whose debit left none was spent when the
            // order was placed (debited_at)
            $spentAt = ($payload['backfilled_without_debit_record'] ?? null) === true ? ($this->time($payload['debited_at'] ?? null) ?? $payment->created_at) : $payment->created_at;
            $entries[] = $this->entry('spent', 'Buyurtma uchun to\'landi' . ($code ? " #{$code}" : ''), Money::of($payment->amount)->negated(), $order, $spentAt);

            $parts = is_array($payload['refunded_to_balance_parts'] ?? null) ? $payload['refunded_to_balance_parts'] : [];
            foreach ($parts as $part) {
                if (is_array($part) && isset($part['amount'])) {
                    $entries[] = $this->entry('refunded', "Qisman qaytarildi" . ($code ? " #{$code}" : ''), Money::of($part['amount']), $order, $this->time($part['refunded_at'] ?? null));
                }
            }
            // A cancellation's give-back: the whole payment, or after partial refunds only what was left of it. A
            // request that returned the last part also writes refunded_to_balance (the whole amount), already counted
            // by its parts
            $cancelled = array_key_exists('refunded_to_balance_by_requests', $payload) || $parts === [];
            if ($cancelled && isset($payload['refunded_to_balance']) && Money::of($payload['refunded_to_balance'])->isPositive()) {
                $entries[] = $this->entry('refunded', "Bekor qilingan buyurtma qaytarildi" . ($code ? " #{$code}" : ''), Money::of($payload['refunded_to_balance']), $order, $this->time($payload['refunded_at'] ?? null) ?? $payment->created_at);
            }
        }

        // Owner decision A: balances moved to this account (an invite row merged into it); the friends that moved with
        // one had earned their earlier bonuses for that row, so those are inside the transfer, not shown again below
        $transfers = \App\Models\KeshbekTransfer::query()->where('to_user_id', $user->id)
            ->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get(['amount', 'moved_friend_ids', 'created_at']);
        $movedBefore = [];
        foreach ($transfers as $transfer) {
            $entries[] = $this->entry('transfer', "Taklif hisobidan ko'chirildi", Money::of((string) $transfer->amount), null, $transfer->created_at);
            foreach ((array) $transfer->moved_friend_ids as $friendId) {
                $at = $transfer->created_at;
                if ($at !== null && (!isset($movedBefore[(int) $friendId]) || $at->greaterThan($movedBefore[(int) $friendId]))) {
                    $movedBefore[(int) $friendId] = $at;
                }
            }
        }

        $rewards = DB::table('store_transactions')
            ->join('orders', 'orders.id', '=', 'store_transactions.order_id')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->where('users.referred_by', $user->id)
            // Both shapes: platform-paid (REFERRAL_BONUS) and the legacy store-charged rows (LedgerService)
            ->where(fn ($query) => \App\Services\Finance\LedgerService::whereAnyReferralReward($query))
            ->orderByDesc('store_transactions.id')
            ->limit($limit)
            ->get(['store_transactions.created_at', 'store_transactions.amount', 'store_transactions.notes', 'users.id as friend_id', 'users.name as friend', 'orders.uuid', 'orders.short_code']);

        foreach ($rewards as $reward) {
            $movedAt = $movedBefore[(int) $reward->friend_id] ?? null;
            if ($movedAt !== null && ($at = $this->time($reward->created_at)) !== null && $at->lessThanOrEqualTo($movedAt)) {
                continue;
            }
            $entries[] = $this->entry(
                'referral',
                // Review finding 1: a negative row is the bonus taken back on the full refund of the order that earned it,
                // or (review M2) at an invite merge, when the friend's own account took in the invite row that earned it
                match (true) {
                    !Money::of((string) $reward->amount)->isNegative() => "Do'st taklifi uchun bonus (" . $reward->friend . ')',
                    \App\Services\Finance\LedgerService::isReferralMergeClawbackNote($reward->notes) => 'Taklif hisobi birlashtirilgani uchun bonus qaytarib olindi (' . $reward->friend . ", #{$reward->short_code})",
                    default => "Do'st taklifi bonusi qaytarib olindi (" . $reward->friend . ", #{$reward->short_code} qaytarildi)",
                },
                Money::of((string) $reward->amount),
                null,
                $this->time($reward->created_at),
            );
        }

        usort($entries, fn (array $a, array $b) => strcmp((string) $b['at'], (string) $a['at']));

        return [
            'balance' => CustomerFormat::money($user->balance ?? 0),
            'entries' => array_slice($entries, 0, $limit),
        ];
    }

    /**
     * @param  array{uuid: string, short_code: string|null}|null  $order
     * @return array{kind: string, label: string, amount: string, order: array{uuid: string, short_code: string|null}|null, at: string|null}
     */
    private function entry(string $kind, string $label, Money $amount, ?array $order, ?\Carbon\CarbonInterface $at): array
    {
        return ['kind' => $kind, 'label' => $label, 'amount' => $amount->toString(), 'order' => $order, 'at' => CustomerFormat::iso($at)];
    }

    private function time(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }
        if (!is_string($value) || $value === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
