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
 * moment ago. After a Reverb restart every till, KDS and board re-subscribes at once; with this, each repeat costs two
 * Redis reads (the grant, then its epochs in one MGET) and an HMAC.
 *
 * Remembered: the fact that this bearer token was allowed this channel (sha256 of the two, never the token), for
 * `realtime.auth_cache_seconds` (60), with the token's expiry and the epochs of what the decision depended on. The
 * signature itself is made fresh for each socket id, exactly as Pusher's authorizeChannel() does. Only the per-scope
 * private channels (pos-branch, branch, store, user, courier); the RIZQIM team's channel and anything else always go
 * through the full check, and a refusal is never remembered.
 *
 * Safety (wave M2 review R2-4/R2-5): a remembered grant is used only while
 * - the token has not expired (its expires_at and Sanctum's `expiration`, checked on every cached use);
 * - none of its subjects' epochs moved: the token (t:id), the user or terminal it belongs to (u:id / d:id), the
 *   terminal a PIN session is bound to (d:id), the store (s:id: permission overrides), and the global epoch.
 *   RevisionQueryBuilder moves exactly the epochs of the rows a write to AUTH_TABLES changes (after the commit):
 *   a refreshed or deleted token drops only that token's grants, a role/branch change only that user's, a revoked
 *   terminal that terminal's (and its PIN sessions'), a permission override that store's; a branch moved to another
 *   store, or a write whose rows cannot be listed, moves the global epoch (every grant), as before.
 * - an epoch moved while the full check ran does not let its answer be remembered (the bump carries its time).
 * Nothing outside AUTH_TABLES decides a channel (routes/channels.php reads the token, the user, the terminal, the
 * branch and the store's permission overrides; no plan module, store status or subscription), so nothing else needs
 * to drop a grant; PermissionService's own override cache is the full path's, not this one's. The TTL bounds the rest
 * (a socket that is already open keeps its channel until it closes either way: Reverb never re-checks).
 */
final class CachedChannelAuth
{
    private const CHANNEL = '/^private-(pos-branch|branch|store|user|courier)\.\d+$/';

    private const SOCKET = '/^\d+\.\d+$/';

    private const EPOCH_KEY = 'realtime:auth:epoch';

    /** Per-subject epochs: realtime:auth:e:t:12 (token), u: (user), d: (terminal), s: (store) */
    private const SUBJECT_EPOCH = 'realtime:auth:e:';

    /** A subject epoch outlives every grant that could have recorded the value before it (grants live 60 s) */
    private const SUBJECT_EPOCH_TTL = 86400;

    /** More rows than this in one access write: the global epoch instead of one key per row */
    public const MAX_SUBJECTS = 500;

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

    /**
     * The epoch subject of a written row of each AUTH_TABLE: [prefix, column]. A table without one (branches: a branch
     * moved to another store) drops every grant.
     */
    public const SUBJECTS = [
        'personal_access_tokens' => ['t:', 'id'],
        'users' => ['u:', 'id'],
        'devices' => ['d:', 'id'],
        'role_permission_overrides' => ['s:', 'store_id'],
    ];

    public const AUTH_QUIET_COLUMNS = [
        'personal_access_tokens' => ['last_used_at', 'updated_at'],
    ];

    /** Drops every remembered grant (after the commit) */
    public static function forgetGrants(): void
    {
        DB::afterCommit(static function (): void {
            try {
                Cache::store((string) config('realtime.revision_store', 'redis'))->forever(self::EPOCH_KEY, self::epochValue());
            } catch (Throwable) {
                // the TTL still bounds the grants
            }
        });
    }

    /**
     * Drops the remembered grants that depended on these subjects (after the commit): 't:<token id>', 'u:<user id>',
     * 'd:<device id>', 's:<store id>'.
     *
     * @param  list<string>  $subjects
     */
    public static function forgetGrantsOf(array $subjects): void
    {
        $subjects = array_values(array_unique($subjects));
        if ($subjects === []) {
            return;
        }
        if (count($subjects) > self::MAX_SUBJECTS) {
            self::forgetGrants();

            return;
        }

        DB::afterCommit(static function () use ($subjects): void {
            try {
                $value = self::epochValue();
                Cache::store((string) config('realtime.revision_store', 'redis'))
                    ->putMany(array_fill_keys(array_map(fn (string $subject) => self::SUBJECT_EPOCH . $subject, $subjects), $value), self::SUBJECT_EPOCH_TTL);
            } catch (Throwable) {
                self::forgetGrants(); // cannot be targeted: drop them all (or the TTL bounds them)
            }
        });
    }

    /** "<unix time with microseconds>:<random>": unique, and tells when it moved */
    private static function epochValue(): string
    {
        return sprintf('%.6F', microtime(true)) . ':' . bin2hex(random_bytes(6));
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
            $cacheKey = 'realtime:auth:' . hash('sha256', $token . "\n" . $channel);
            $grant = $store->get($cacheKey);
            if (is_array($grant) && !$this->stillValid($store, $grant)) {
                $grant = null;
            }
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

        $startedAt = microtime(true);
        $response = $next($request);

        if ($response->getStatusCode() === 200) {
            try {
                $limiter = RateLimiter::limiter('broadcast-auth');
                $limit = $limiter !== null ? $limiter($request) : null;
                $subjects = self::subjectsOf($request);
                if ($limit instanceof \Illuminate\Cache\RateLimiting\Limit && $subjects !== null) {
                    $keys = [self::EPOCH_KEY, ...array_map(fn (string $subject) => self::SUBJECT_EPOCH . $subject, $subjects['subjects'])];
                    $epochs = [];
                    foreach ($store->many($keys) as $epochKey => $value) {
                        $value = is_string($value) ? $value : '';
                        // An epoch that moved while the full check ran (minus clock skew between hosts): do not remember
                        if ($value !== '' && (float) strtok($value, ':') >= $startedAt - 2.0) {
                            return $response;
                        }
                        $epochs[$epochKey] = $value;
                    }
                    $life = $ttl;
                    if ($subjects['expires'] !== null) {
                        $life = min($life, $subjects['expires'] - now()->getTimestamp());
                    }
                    if ($life > 0) {
                        $store->put($cacheKey, [
                            'limit' => md5('broadcast-auth' . $limit->key), 'max' => $limit->maxAttempts, 'decay' => $limit->decaySeconds,
                            'expires' => $subjects['expires'], 'epochs' => $epochs,
                        ], $life);
                    }
                }
            } catch (Throwable) {
                // only the next subscribe pays the full check
            }
        }

        return $response;
    }

    /** The token has not expired and no epoch the grant recorded has moved (one MGET) */
    private function stillValid(\Illuminate\Contracts\Cache\Repository $store, array $grant): bool
    {
        $epochs = $grant['epochs'] ?? null;
        if (!is_array($epochs) || !array_key_exists(self::EPOCH_KEY, $epochs)) {
            return false;
        }
        if (isset($grant['expires']) && (int) $grant['expires'] <= now()->getTimestamp()) {
            return false; // R2-5: an expired token is refused by the full path
        }
        $current = $store->many(array_keys($epochs));
        foreach ($epochs as $epochKey => $value) {
            $now = $current[$epochKey] ?? null;
            if ((is_string($now) ? $now : '') !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * What the granted decision depended on, and when the token stops being valid; null when the actor cannot be told
     * (nothing is remembered then).
     *
     * @return array{subjects: list<string>, expires: int|null}|null
     */
    private static function subjectsOf(Request $request): ?array
    {
        $actor = $request->user(); // the guard that signed the request in (Authenticate::shouldUse)
        if (!$actor instanceof \App\Models\User && !$actor instanceof \App\Models\Device) {
            return null;
        }
        // A personal access token (a session sign-in has none: the caller's try/catch then remembers nothing)
        $accessToken = $actor->currentAccessToken();

        $subjects = ['t:' . $accessToken->getKey(), ($actor instanceof \App\Models\Device ? 'd:' : 'u:') . $actor->getKey()];
        if (($deviceId = $accessToken->getAttribute('device_id')) !== null) {
            $subjects[] = 'd:' . (int) $deviceId;
        }
        if (($storeId = $actor->getAttribute('store_id')) !== null) {
            $subjects[] = 's:' . (int) $storeId;
        }

        // Sanctum's validity: expires_at, and `sanctum.expiration` minutes after creation
        $expires = $accessToken->expires_at?->getTimestamp();
        $minutes = (int) config('sanctum.expiration');
        if ($minutes > 0 && $accessToken->created_at !== null) {
            $byAge = $accessToken->created_at->getTimestamp() + $minutes * 60;
            $expires = $expires === null ? $byAge : min($expires, $byAge);
        }

        return ['subjects' => array_values(array_unique($subjects)), 'expires' => $expires];
    }
}
