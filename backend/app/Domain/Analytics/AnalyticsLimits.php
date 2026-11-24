<?php

namespace App\Domain\Analytics;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Wave P, step 2 (docs/ANALYTICS_PLAN.md A.4): the limits of POST /api/v1/analytics/events (config analytics.limits).
 *
 * - Per request (the `analytics-events` rate limiter, AppServiceProvider): per user, per session and per IP address
 *   each a minute. The route's own limiter only: the shared `api` limit that the order and payment calls use is never
 *   spent by analytics.
 * - Per user and day: an event budget (events_per_day), spent by the events actually stored (EventIngestor).
 *
 * The address is never stored: its limiter key is an HMAC of it with the app key, so the key in Redis cannot be
 * turned back into the address by hashing every IPv4 address (the throttle middleware's own md5 of the key could).
 */
final class AnalyticsLimits
{
    /**
     * @return list<Limit>
     */
    public static function forRequest(Request $request): array
    {
        $limits = [];
        $user = $request->user();
        if ($user !== null) {
            $limits[] = Limit::perMinute((int) config('analytics.limits.requests_per_minute_user', 20))->by('user:' . $user->getAuthIdentifier());
        }
        $session = self::sessionId($request);
        if ($session !== null) {
            $limits[] = Limit::perMinute((int) config('analytics.limits.requests_per_minute_session', 20))->by('session:' . $session);
        }
        $limits[] = Limit::perMinute((int) config('analytics.limits.requests_per_minute_ip', 120))->by(self::ipKey((string) $request->ip()));

        return $limits;
    }

    public static function ipKey(string $ip): string
    {
        return 'ip:' . hash_hmac('sha256', 'analytics-ip|' . $ip, (string) config('app.key'));
    }

    public static function dailyKey(int|string $userId): string
    {
        return 'analytics-events-day:user:' . $userId;
    }

    /**
     * The body's session_id when it is one, read only from a body within the size cap (the controller refuses a
     * bigger one with 413 before reading it; the limiter runs first and must not parse it either).
     */
    private static function sessionId(Request $request): ?string
    {
        if (strlen((string) $request->getContent()) > (int) config('analytics.limits.max_body_bytes', 32768)) {
            return null;
        }
        $session = $request->json('session_id');

        return is_string($session) && Str::isUuid($session) ? strtolower($session) : null;
    }
}
