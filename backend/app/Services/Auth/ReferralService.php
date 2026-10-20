<?php

namespace App\Services\Auth;

use App\Models\User;
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
}
