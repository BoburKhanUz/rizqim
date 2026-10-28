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
 * - referral bonus: 5 000 for each first delivered order of a customer they invited (LedgerService books the store's
 *   side as a commission row with the referral note), plus.
 *
 * The newest $limit entries; older history is not needed to read the balance (the balance itself is shown).
 */
class KeshbekHistory
{
    // LedgerService::REFERRAL_NOTE: the store_transactions note of a referral reward ("... for referred user #12")
    private const REFERRAL_NOTE = 'Referral cashback reward cost';

    private const REFERRAL_AMOUNT = '5000.00';

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
            $entries[] = $this->entry('spent', 'Buyurtma uchun to\'landi' . ($code ? " #{$code}" : ''), Money::of($payment->amount)->negated(), $order, $payment->created_at);

            $payload = is_array($payment->payload) ? $payment->payload : [];
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

        $rewards = DB::table('store_transactions')
            ->join('orders', 'orders.id', '=', 'store_transactions.order_id')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->where('users.referred_by', $user->id)
            ->where('store_transactions.notes', 'like', self::REFERRAL_NOTE . '%')
            ->orderByDesc('store_transactions.id')
            ->limit($limit)
            ->get(['store_transactions.created_at', 'users.name as friend', 'orders.uuid', 'orders.short_code']);

        foreach ($rewards as $reward) {
            $entries[] = $this->entry(
                'referral',
                "Do'st taklifi uchun bonus (" . $reward->friend . ')',
                Money::of(self::REFERRAL_AMOUNT),
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
