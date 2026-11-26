<?php

namespace App\Services\Auth;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payment\OrderMerchantAccount;
use App\Services\Payment\Paylov\PaylovPayments;
use App\Support\Tenancy\StoreScope;
use Illuminate\Support\Facades\Cache;

class ReferralService
{
    public const PENDING_TTL_DAYS = 30;

    /**
     * Referrer user id from a deep-link payload (rizqim_<id>, ref_<id>, referrer_<id>), or null when it is
     * not a referral, the referrer does not exist, or it is the same Telegram account.
     */
    public function referrerIdFromParam(?string $param, string $telegramId): ?int
    {
        if (!$param || !(
            str_starts_with(strtolower($param), 'ref_') ||
            str_starts_with(strtolower($param), 'referrer_') ||
            str_starts_with(strtolower($param), 'rizqim_')
        )) {
            return null;
        }

        $parts = explode('_', $param);

        return $this->validReferrerId(end($parts), $telegramId);
    }

    /**
     * Parks a referrer for a Telegram account that has no User row yet (bot /start referral links).
     */
    public function storePendingReferral(string $telegramId, int $referrerId): void
    {
        Cache::put($this->pendingKey($telegramId), $referrerId, now()->addDays(self::PENDING_TTL_DAYS));
    }

    /**
     * Consumes (once) the parked referrer for this Telegram account and returns it if still valid.
     */
    public function pullPendingReferrerId(string $telegramId): ?int
    {
        return $this->validReferrerId(Cache::pull($this->pendingKey($telegramId)), $telegramId);
    }

    /**
     * Consumes the parked referrer and assigns it only if the user has no referrer yet (never overwrites).
     */
    public function applyPendingReferral(User $user, string $telegramId): void
    {
        $referrerId = $this->pullPendingReferrerId($telegramId);

        if ($referrerId === null || $user->referred_by !== null || $referrerId === $user->id) {
            return;
        }

        // referred_by is deliberately not mass assignable, so no profile update can ever rewrite it
        $user->referred_by = $referrerId;
        $user->save();
    }

    public function validReferrerId(mixed $candidate, string $telegramId): ?int
    {
        $candidate = is_int($candidate) ? (string) $candidate : $candidate;

        if (!is_string($candidate) || !ctype_digit($candidate)) {
            return null;
        }

        $referrer = User::find((int) $candidate);

        // An unclaimed employee invite row (telegram_id temp_…) signs in nowhere: a bonus credited to it could never
        // be spent, so it is no referrer. Staff are (they may invite friends); docs/OPERATIONS.md 31-bo'lim
        if ($referrer === null || str_starts_with((string) $referrer->telegram_id, 'temp_')) {
            return null;
        }

        return (string) $referrer->telegram_id !== $telegramId ? $referrer->id : null;
    }

    public function pendingKey(string $telegramId): string
    {
        return "referral:pending:{$telegramId}";
    }

    /** The referral bonus: ball credited to the referrer, paid by the platform out of its commission income. */
    public const BONUS = 5000;

    /** The smallest order total that earns the referral bonus (so'm). */
    public const MIN_ORDER = 50000;

    /**
     * Owner's rule B: at most this many referral bonuses per referrer per calendar month (Asia/Tashkent, the monthly
     * revenue report's boundary), granted in the month and not taken back (monthlyBonusesOf). Change it here (docs/OPERATIONS.md 31-bo'lim); not configurable.
     */
    public const MONTHLY_CAP_PER_REFERRER = 10;

    /**
     * The whole eligibility rule of an order for the referral bonus, in one place (owner decision 3 and rules A, B):
     * a delivered order of at least MIN_ORDER whose payment the platform verified (paymentVerifiedByPlatform) and whose
     * booked commission covers the bonus (commissionCoversBonus). An order that does not qualify neither earns the
     * bonus nor uses the referral up. Whether the referral is still open for the customer and whether the referrer's
     * monthly cap allows another bonus are decided under the row locks (LedgerService::grantReferralReward).
     */
    public static function qualifiesForReferralBonus(Order $order): bool
    {
        return self::whyNotQualifying($order) === null;
    }

    /**
     * Why the order does not qualify (qualifiesForReferralBonus), or null when it does: `below_minimum`,
     * `unverified` (paymentVerifiedByPlatform) or `commission_below_bonus` (commissionCoversBonus). The delivered
     * status is the caller's to check.
     */
    public static function whyNotQualifying(Order $order): ?string
    {
        return match (true) {
            \App\Support\Money::of($order->total_price)->isLessThan(self::MIN_ORDER) => 'below_minimum',
            !self::paymentVerifiedByPlatform($order) => 'unverified',
            !self::commissionCoversBonus($order) => 'commission_below_bonus',
            default => null,
        };
    }

    /**
     * Owner's rule A: the bonus never exceeds what the platform earns on the order: its commission as booked
     * (orders.commission_amount, the snapshot taken at checkout and charged at delivery) is at least BONUS. A 0 %
     * channel never qualifies; neither does an order without a commission snapshot.
     */
    public static function commissionCoversBonus(Order $order): bool
    {
        return isset($order->commission_amount) && \App\Support\Money::of($order->commission_amount)->isGreaterThanOrEqualTo(self::BONUS);
    }

    /**
     * Owner's rule B: how many referral bonuses the referrer was granted in the Asia/Tashkent calendar month of $at — a
     * gross limit on the grants booked in that month (review L1). A clawback frees a slot only in the month of the
     * grant it reverses: a grant booked in the month and taken back since (whenever) no longer counts there, while a
     * clawback of an earlier month's grant frees nothing now (counted by booking month, three October bonuses taken back
     * in November let November grant 13). An order earns the bonus at most once (a refunded order never again), so a
     * grant is reversed by the clawback row of its own order. Reads the referral_bonus rows of every store (the orders of
     * the customers the referrer invited, soft-deleted ones too): callers inside a store-bound transaction run it as
     * the login role (LedgerService::grantReferralReward). With $asOf: as the count stood then, from the grants and the
     * clawbacks booked up to that moment (LedgerService::grantMissedReferralBonus decides a lost booking as of its payment).
     */
    public static function monthlyBonusesOf(int $referrerId, \Carbon\CarbonInterface $at, ?\Carbon\CarbonInterface $asOf = null): int
    {
        [$start, $end] = \App\Services\Finance\Reports\MonthlyRevenueReport::monthBounds(
            \Illuminate\Support\Carbon::instance($at)->setTimezone(\App\Services\Finance\Reports\MonthlyRevenueReport::timezoneName())->format('Y-m')
        );
        $bonus = \App\Enums\StoreTransactionType::REFERRAL_BONUS->value;

        // Every store's rows: the cap is the referrer's across stores (the caller elevates past RLS inside a tenant transaction)
        return \App\Models\StoreTransaction::query()->withoutGlobalScope(StoreScope::class)
            ->where('type', $bonus)
            ->where('amount', '>', 0)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->when($asOf !== null, fn ($query) => $query->where('created_at', '<=', $asOf))
            // The invited customers' orders at every store, soft-deleted ones too (a deleted order's bonus still counts)
            ->whereIn('order_id', Order::query()->withoutGlobalScope(StoreScope::class)->withTrashed()->select('id')
                ->whereIn('user_id', User::query()->select('id')->where('referred_by', $referrerId)))
            // Not taken back
            ->whereNotExists(fn ($back) => $back->selectRaw('1')->from('store_transactions as back')
                ->whereColumn('back.order_id', 'store_transactions.order_id')
                ->where('back.type', $bonus)
                ->where('back.amount', '<', 0)
                ->when($asOf !== null, fn ($query) => $query->where('back.created_at', '<=', $asOf)))
            ->count();
    }

    /**
     * Owner decision 3: whether the platform itself verified the payment of this order — part of the rule the referral
     * bonus is granted by (qualifiesForReferralBonus). Only such an order can earn the bonus; any other leaves the
     * referral waiting for a later one.
     *
     *  - an online payment into the platform's own merchant account (payment_owner PLATFORM: Click, Payme, Paylov,
     *    Uzum, Paynet), paid: its callbacks are authenticated with the platform's keys;
     *  - a Paylov payment into the store's own account settled on Paylov's read-back (payload.paylov.state
     *    `confirmed`, never one refused at perform: that money was not the order's): RIZQIM read it from Paylov.
     *
     * Never: cash (nobody can confirm it was paid), Click / Payme / Uzum / Paynet into the store's own account (a store
     * knows the key its own callbacks are signed with, so it can report a payment that never happened), and keshbek
     * (review finding 2: keshbek only comes from this bonus, so a keshbek order earning it would chain bonuses — A
     * invites B, B pays with keshbek from inviting C, ...).
     *
     * A paid order refunded later still reads as verified (`refunded` payment status); a refunded order never earns the
     * bonus all the same (LedgerService::grantReferralReward wants it delivered and still paid).
     *
     * To also grant the bonus for cash orders (owner's choice, docs/OPERATIONS.md 31-bo'lim), add this as the method's
     * first line:
     *     if ($order->payment_method === 'cash') { return true; }
     *
     * Reads the order's payments of its own store (other stores' orders: the caller runs it as the login role).
     */
    public static function paymentVerifiedByPlatform(Order $order): bool
    {
        if (!in_array($order->payment_status, [PaymentStatus::PAID, PaymentStatus::REFUNDED], true)
            || in_array($order->payment_method, ['cash', 'keshbek'], true)) {
            return false;
        }

        if (!OrderMerchantAccount::isStore($order)) {
            // No keshbek share either (keshbek is never verified, whatever the order's method says)
            return !Payment::query()->withoutGlobalScope(StoreScope::class)->where('order_id', $order->id)->where('provider', 'keshbek')->exists();
        }

        // The order's own payments, whichever store the request is bound to (the caller decides whose orders it reads)
        return Payment::query()->withoutGlobalScope(StoreScope::class)
            ->where('order_id', $order->id)
            ->where('provider', 'paylov')
            ->whereIn('status', [PaymentStatus::PAID->value, PaymentStatus::REFUNDED->value])
            ->get(['id', 'provider', 'status', 'payload'])
            ->contains(function (Payment $payment) {
                $state = PaylovPayments::state($payment);

                return ($state['state'] ?? null) === PaylovPayments::STATE_CONFIRMED && ($state['refused'] ?? null) === null;
            });
    }
}
