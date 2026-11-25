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

        return $referrer && (string) $referrer->telegram_id !== $telegramId ? $referrer->id : null;
    }

    public function pendingKey(string $telegramId): string
    {
        return "referral:pending:{$telegramId}";
    }

    /**
     * Owner decision 3: whether the platform itself verified the payment of this order — the ONE rule the referral
     * bonus (LedgerService::grantReferralReward) is granted by. Only such an order earns the bonus, and only such an
     * order uses the referral up; any other leaves it waiting for a later one.
     *
     *  - an online payment into the platform's own merchant account (payment_owner PLATFORM: Click, Payme, Paylov,
     *    Uzum, Paynet, and keshbek, which the platform takes from its own balance ledger), paid: its callbacks are
     *    authenticated with the platform's keys;
     *  - a Paylov payment into the store's own account settled on Paylov's read-back (payload.paylov.state
     *    `confirmed`, never one refused at perform: that money was not the order's): RIZQIM read it from Paylov.
     *
     * Never: cash (nobody can confirm it was paid), and Click / Payme / Uzum / Paynet into the store's own account (a
     * store knows the key its own callbacks are signed with, so it can report a payment that never happened).
     *
     * Keshbek (payment_owner PLATFORM) counts: it is taken from the platform's own balance ledger, nothing a store can
     * report. A paid order refunded later still counts (`refunded` payment status): it was paid when it earned the bonus.
     *
     * This is the only place the rule lives: LedgerService (grant, "first verified delivery") and the admin customer card
     * (UserCardPresenter::referralBonus) only call it. To also grant the bonus for cash orders (owner's choice,
     * docs/OPERATIONS.md 31-bo'lim), add this as the method's first line:
     *     if ($order->payment_method === 'cash') { return true; }
     *
     * Reads the order's payments of its own store (other stores' orders: the caller runs it as the login role).
     */
    public static function paymentVerifiedByPlatform(Order $order): bool
    {
        if (!in_array($order->payment_status, [PaymentStatus::PAID, PaymentStatus::REFUNDED], true) || $order->payment_method === 'cash') {
            return false;
        }

        if (!OrderMerchantAccount::isStore($order)) {
            return true;
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
