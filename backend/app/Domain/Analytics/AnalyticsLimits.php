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
 * - Per user and day: an event budget (events_per_day), spent by the events actually stored, and a cap on the new
 *   session ids (sessions_per_day), both kept by EventIngestor in the limiter's cache store.
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
        $limits[] = Limit::perMinute((int) config('analytics.limits.requests_per_minute_ip', 600))->by(self::ipKey((string) $request->ip()));

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

    /** How many new session ids the user has started in the day (the counter of sessions_per_day) */
    public static function sessionsKey(int|string $userId): string
    {
        return 'analytics-sessions-day:user:' . $userId;
    }

    /** A session of the user that is within the day's cap (present = its batches are taken) */
    public static function sessionMarkerKey(int|string $userId, string $session): string
    {
        return 'analytics-session:user:' . $userId . ':' . $session;
    }

    /** The nil UUID is no session (an app without a session id generator would put everybody in it) */
    public const NIL_SESSION = '00000000-0000-0000-0000-000000000000';

    /**
     * The body's session_id when it is one (never the nil UUID), read only from a body within the size cap (nginx
     * refuses a bigger one before PHP; without it the controller answers 413 and the limiter does not use its session).
     */
    private static function sessionId(Request $request): ?string
    {
        if (strlen((string) $request->getContent()) > (int) config('analytics.limits.max_body_bytes', 32768)) {
            return null;
        }
        $session = $request->json('session_id');

        return is_string($session) && Str::isUuid($session) && $session !== self::NIL_SESSION ? strtolower($session) : null;
    }
}
