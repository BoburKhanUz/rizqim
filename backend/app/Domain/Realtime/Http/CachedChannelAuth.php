<?php

namespace App\Domain\Realtime\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Wave M2: POST /api/broadcasting/auth without PHP's token lookup and channel query for a subscription it granted a
 * moment ago. After a Reverb restart every till, KDS and board re-subscribes at once; with this, each repeat costs one
 * Redis read and an HMAC.
 *
 * Remembered: the fact that this bearer token was allowed this channel (sha256 of the two, never the token), for
 * `realtime.auth_cache_seconds` (60). The signature itself is made fresh for each socket id, exactly as Pusher's
 * authorizeChannel() does. Only the per-scope private channels (pos-branch, branch, store, user, courier); the RIZQIM
 * team's channel and anything else always go through the full check, and a refusal is never remembered.
 * Safety: every remembered grant is dropped at once (after the commit) when an access row changes: a token deleted, a
 * terminal revoked, a user's role/store/branch changed, a permission override (RevisionQueryBuilder calls forgetGrants()
 * for AUTH_TABLES). The TTL bounds anything else (a socket that is already open keeps its channel until it closes either
 * way: Reverb never re-checks).
 */
final class CachedChannelAuth
{
    private const CHANNEL = '/^private-(pos-branch|branch|store|user|courier)\.\d+$/';

    private const SOCKET = '/^\d+\.\d+$/';

    private const EPOCH_KEY = 'realtime:auth:epoch';

    /** Tables whose update or delete can take a channel away: a write drops every remembered grant */
    public const AUTH_TABLES = ['personal_access_tokens', 'devices', 'users', 'role_permission_overrides', 'branches'];

    /**
     * For these AUTH_TABLES only an update of one of the listed columns can take a channel away (a customer's balance
     * or a terminal's heartbeat does not); the others drop the grants on any update but the listed quiet columns.
     */
    public const AUTH_COLUMNS = [
        'users' => ['role', 'store_id', 'branch_id', 'blocked_at', 'deleted_at', 'platform_role', 'platform_disabled_at'],
        'devices' => ['revoked_at', 'store_id', 'branch_id', 'user_id', 'type'],
        'branches' => ['store_id'],
    ];

    public const AUTH_QUIET_COLUMNS = [
        'personal_access_tokens' => ['last_used_at', 'updated_at'],
    ];

    public static function forgetGrants(): void
    {
        DB::afterCommit(static function (): void {
            try {
                Cache::store((string) config('realtime.revision_store', 'redis'))->forever(self::EPOCH_KEY, bin2hex(random_bytes(8)));
            } catch (Throwable) {
                // the TTL still bounds the grants
            }
        });
    }

    public function handle(Request $request, Closure $next): Response
    {
        $ttl = (int) config('realtime.auth_cache_seconds', 60);
        $token = $request->bearerToken();
        $channel = (string) $request->input('channel_name', '');
        $socket = (string) $request->input('socket_id', '');
        $key = (string) config('broadcasting.connections.reverb.key', '');
        $secret = (string) config('broadcasting.connections.reverb.secret', '');

        if ($ttl <= 0 || $token === null || $token === '' || config('broadcasting.default') !== 'reverb' || $key === '' || $secret === ''
            || preg_match(self::CHANNEL, $channel) !== 1 || preg_match(self::SOCKET, $socket) !== 1 || $request->has('callback')) {
            return $next($request);
        }

        try {
            $store = Cache::store((string) config('realtime.revision_store', 'redis'));
            $cacheKey = 'realtime:auth:' . hash('sha256', $token . "\n" . $channel . "\n" . (string) $store->get(self::EPOCH_KEY, ''));
            $grant = $store->get($cacheKey);
        } catch (Throwable) {
            return $next($request);
        }

        if (is_array($grant) && isset($grant['limit'], $grant['max'], $grant['decay'])) {
            // The same bucket as throttle:broadcast-auth (the full path), so a cached repeat is limited exactly as before
            if (RateLimiter::tooManyAttempts((string) $grant['limit'], (int) $grant['max'])) {
                throw new ThrottleRequestsException('Too Many Attempts.');
            }
            RateLimiter::hit((string) $grant['limit'], (int) $grant['decay']);

            return response()->json(['auth' => $key . ':' . hash_hmac('sha256', $socket . ':' . $channel, $secret)]);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 200) {
            try {
                $limiter = RateLimiter::limiter('broadcast-auth');
                $limit = $limiter !== null ? $limiter($request) : null;
                if ($limit instanceof \Illuminate\Cache\RateLimiting\Limit) {
                    $store->put($cacheKey, ['limit' => md5('broadcast-auth' . $limit->key), 'max' => $limit->maxAttempts, 'decay' => $limit->decaySeconds], $ttl);
                }
            } catch (Throwable) {
                // only the next subscribe pays the full check
            }
        }

        return $response;
    }
}
