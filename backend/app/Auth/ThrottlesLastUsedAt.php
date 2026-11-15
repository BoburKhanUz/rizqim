<?php

namespace App\Auth;

use Carbon\CarbonInterface;

/**
 * Wave M1: Sanctum writes personal_access_tokens.last_used_at on every authenticated request, which was one UPDATE per
 * request in the load test (polls included). For a guard that uses this trait the write happens at most once per token
 * per `sanctum.last_used_at_throttle_minutes` (SANCTUM_LAST_USED_AT_THROTTLE_MINUTES, default 5; 0 = every request).
 *
 * Readers of last_used_at only need minutes, not seconds: the admin "last seen" columns (UserCardPresenter), store
 * health activity (StoreHealthCalculator, days). The admin guard keeps its own one-minute rule (its idle timeout reads
 * the column) and does not use this trait.
 */
trait ThrottlesLastUsedAt
{
    /**
     * @param  \Laravel\Sanctum\PersonalAccessToken  $accessToken
     */
    protected function updateLastUsedAt($accessToken)
    {
        if (!self::lastUsedAtIsDue($accessToken->last_used_at)) {
            return;
        }

        parent::updateLastUsedAt($accessToken);
    }

    public static function lastUsedAtIsDue(?CarbonInterface $lastUsedAt): bool
    {
        $minutes = max(0, (int) config('sanctum.last_used_at_throttle_minutes', 5));

        return $minutes === 0 || $lastUsedAt === null || $lastUsedAt->lte(now()->subMinutes($minutes));
    }
}
