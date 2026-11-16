<?php

namespace App\Http\Middleware;

use App\Domain\Realtime\ChangeRevision;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Support\RowId;
use Closure;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wave M2: conditional GETs for the polled endpoints (docs/API.md "Shartli so'rovlar (ETag)").
 *
 * A 200 answer carries `ETag` and `Cache-Control: private, no-cache`. The ETag is stateless and signed (HMAC with the
 * app key): it names the change scopes the body depends on (branch / store / customer, App\Domain\Realtime\
 * ChangeRevision) and an expiry, and its MAC covers the request (path, query, locale, deploy salt), the actor (who
 * asks, their role, store, branch, acting branch, token; a device's branch, type, name and
 * the till it follows) and the tokens of those scopes as they were BEFORE the body was read. A repeat request whose
 * If-None-Match holds that ETag is answered `304 Not Modified` while the expiry has not passed and every token is
 * unchanged: the controller does not run, so a 304 costs the route's own middleware (authentication, rate limit,
 * permission/module gates) plus one revision lookup per scope, and no main-table query. Anything else runs the
 * controller as before. Bodies are never changed.
 *
 * Usage (always the LAST middleware of the route, after auth and every permission gate, so a revoked token or a lost
 * permission is still refused on what would have been a 304):
 *
 *     Route::get('/pos/floor', ...)->middleware(['permission:orders.view', ConditionalGet::using('branch', 120)]);
 *
 * - `$resolver` names the scopes, resolved before the controller runs (so the token snapshot precedes every read):
 *   `branch` (the `branch_id` query parameter, else the caller's acting branch, a device's own branch; without either:
 *   the caller's store and all its branches), `order` (the branch of the `{uuid}` route order), `user` (the caller),
 *   `orders_feed` (GET /orders: a customer's own orders; an owner/manager the branch scope; a courier the store),
 *   `none` (the controller declares its scopes itself with depends()).
 * - `$maxAge` (seconds) bounds what changes with the clock alone (timers, "reserved soon", business-day rollover): an
 *   ETag never validates longer than that after its 200. A controller narrows it with validUntil() when it knows the
 *   next moment its body changes by itself (a scheduled kitchen time).
 * - Controllers may add scopes with depends() (call it BEFORE reading the data it covers), shorten the life with
 *   validUntil(), or opt a particular answer out with skip(). M2-3's customer GETs: ConditionalGet::using('none', N)
 *   on the route and ConditionalGet::depends($request, 'store', $storeId) in the controller.
 * - A 200 whose body has exactly one scope also carries `X-Revision: <token>` and `X-Revision-Scope: branch:12`
 *   (store:N, user:N); a store-wide list carries neither (its revision is many tokens: re-read on any event).
 * - Clients: send the last ETag in If-None-Match; on 304 keep the last body AND the moment it was received (timers in
 *   a body, such as `waiting_seconds` or `open_minutes`, count from that moment).
 * - A token that cannot be read (Redis down) is a fresh random value (ChangeRevision): the ETag never matches, every
 *   poll is a full read; never a stale 304. `conditional_get.enabled=false` turns all of it off.
 */
final class ConditionalGet
{
    private const VERSION = 'c1';

    /** More scopes than this (a very large store-wide list): no ETag, a plain 200 as before. */
    private const MAX_SCOPES = 64;

    private const ATTR_SCOPES = 'conditional_get.scopes';

    private const ATTR_UNTIL = 'conditional_get.until';

    private const ATTR_SKIP = 'conditional_get.skip';

    /** Scope letter in the ETag => ChangeRevision reader */
    private const SCOPES = ['branch' => 'b', 'store' => 's', 'user' => 'u'];

    public function __construct(
        private readonly ChangeRevision $revisions,
        private readonly PermissionService $permissions,
    ) {}

    /**
     * The middleware string for a route: ConditionalGet::using('branch', 120).
     */
    public static function using(string $resolver, int $maxAge): string
    {
        return self::class . ':' . $resolver . ',' . $maxAge;
    }

    /**
     * The body also depends on this scope ('branch', 'store' or 'user'). Reads its token now: call it before the data
     * it covers is read.
     */
    public static function depends(Request $request, string $scope, int $id): void
    {
        $letter = self::SCOPES[$scope] ?? null;
        if ($letter === null || $id <= 0) {
            throw new \InvalidArgumentException("Unknown change scope {$scope}:{$id}");
        }

        /** @var array<string, string> $scopes */
        $scopes = $request->attributes->get(self::ATTR_SCOPES, []);
        $key = $letter . $id;
        if (!isset($scopes[$key])) {
            $scopes[$key] = app(ChangeRevision::class)->{$scope}($id);
            $request->attributes->set(self::ATTR_SCOPES, $scopes);
        }
    }

    /**
     * The body changes by itself at this moment (with no write): its ETag stops validating then.
     */
    public static function validUntil(Request $request, DateTimeInterface $at): void
    {
        $current = $request->attributes->get(self::ATTR_UNTIL);
        $ts = $at->getTimestamp();
        if (!is_int($current) || $ts < $current) {
            $request->attributes->set(self::ATTR_UNTIL, $ts);
        }
    }

    /**
     * This answer gets no ETag (its body depends on something no change token covers).
     */
    public static function skip(Request $request): void
    {
        $request->attributes->set(self::ATTR_SKIP, true);
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $resolver = 'none', string $maxAge = '60'): Response
    {
        if (!config('conditional_get.enabled', true) || !in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $life = max(1, (int) $maxAge);
        $context = $this->context($request, $resolver, $life);

        $matched = $this->matching($request, $context);
        if ($matched !== null) {
            return $this->notModified($matched);
        }

        // Snapshot the tokens before the controller reads anything
        foreach ($this->resolve($request, $resolver) as [$scope, $id]) {
            self::depends($request, $scope, $id);
        }

        $response = $next($request);

        if ($response->getStatusCode() !== 200 || $response->headers->has('ETag') || $request->attributes->get(self::ATTR_SKIP) === true) {
            return $response;
        }

        /** @var array<string, string> $scopes */
        $scopes = $request->attributes->get(self::ATTR_SCOPES, []);
        if ($scopes === [] || count($scopes) > self::MAX_SCOPES) {
            return $response;
        }

        $now = now()->getTimestamp();
        $expires = $now + $life;
        $until = $request->attributes->get(self::ATTR_UNTIL);
        if (is_int($until)) {
            $expires = min($expires, $until);
        }
        if ($expires <= $now) {
            return $response;
        }

        ksort($scopes);
        $response->headers->set('ETag', $this->tag($context, $expires, $scopes));
        $response->headers->set('Cache-Control', 'private, no-cache');
        // A single-scope body also names its token (read before the body), so a client holding revision N can tell
        // the next broadcast `rev` (N+1) from a missed one. A multi-scope body (a store-wide list) carries none.
        if (count($scopes) === 1) {
            $key = (string) array_key_first($scopes);
            $response->headers->set('X-Revision', $scopes[$key]);
            $response->headers->set('X-Revision-Scope', ['b' => 'branch', 's' => 'store', 'u' => 'user'][$key[0]] . ':' . substr($key, 1));
        }

        return $response;
    }

    // --- 304 path ------------------------------------------------------------------------------------------------

    /**
     * The If-None-Match tag that is still current for this request, or null.
     */
    private function matching(Request $request, string $context): ?string
    {
        $header = $request->headers->get('If-None-Match');
        if ($header === null || $header === '' || strlen($header) > 4096) {
            return null;
        }

        foreach (explode(',', $header) as $candidate) {
            // nginx turns a strong ETag weak when it compresses the body: W/"..." is the same tag
            $tag = trim($candidate);
            if (str_starts_with($tag, 'W/')) {
                $tag = substr($tag, 2);
            }
            if ($this->stillCurrent($tag, $context)) {
                return $tag;
            }
        }

        return null;
    }

    private function stillCurrent(string $tag, string $context): bool
    {
        if (!preg_match('/^"' . self::VERSION . '\.([0-9a-z]{1,13})\.([bsu][0-9]{1,19}(?:-[bsu][0-9]{1,19})*)\.([A-Za-z0-9_-]{22})"$/', $tag, $m)) {
            return false;
        }

        $expires = (int) base_convert($m[1], 36, 10);
        if ($expires <= now()->getTimestamp()) {
            return false;
        }

        $keys = explode('-', $m[2]);
        if (count($keys) > self::MAX_SCOPES) {
            return false;
        }

        $readers = array_flip(self::SCOPES);
        $scopes = [];
        foreach ($keys as $key) {
            $id = (int) substr($key, 1);
            if ($id <= 0) {
                return false;
            }
            $scopes[$key] = $this->revisions->{$readers[$key[0]]}($id);
        }

        return hash_equals($this->tag($context, $expires, $scopes), $tag);
    }

    private function notModified(string $tag): Response
    {
        $response = new Response('', 304);
        $response->headers->set('ETag', $tag);
        $response->headers->set('Cache-Control', 'private, no-cache');

        return $response;
    }

    // --- the tag ---------------------------------------------------------------------------------------------------

    /**
     * @param  array<string, string>  $scopes  scope key (b12) => token
     */
    private function tag(string $context, int $expires, array $scopes): string
    {
        $exp = base_convert((string) $expires, 10, 36);
        $names = implode('-', array_keys($scopes));
        $payload = $context . "\n" . $exp . "\n" . $names . "\n" . implode("\n", $scopes);
        $mac = substr(rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $this->secret(), true)), '+/', '-_'), '='), 0, 22);

        return '"' . self::VERSION . '.' . $exp . '.' . $names . '.' . $mac . '"';
    }

    /**
     * Everything besides the tokens that the body depends on: the request, the actor, the deploy.
     */
    private function context(Request $request, string $resolver, int $life): string
    {
        $query = $request->query();
        self::sortDeep($query);

        $configFile = app()->getCachedConfigPath();
        $deployed = is_file($configFile) ? (string) filemtime($configFile) : '';

        return implode("\n", [
            self::VERSION,
            (string) config('conditional_get.salt', ''),
            $deployed,
            $resolver . ':' . $life,
            '/' . ltrim($request->path(), '/'),
            http_build_query($query),
            app()->getLocale(),
            $this->actor($request),
        ]);
    }

    /**
     * Who asks, and everything about them a body may depend on (all of it already loaded by authentication).
     */
    private function actor(Request $request): string
    {
        $actor = $request->user();

        if ($actor instanceof User) {
            /** @var mixed $token a TransientToken for a session sign-in (the web panel) */
            $token = $actor->currentAccessToken();
            /** @var mixed $role */
            $role = $actor->role;
            $acting = $actor->store_id !== null ? $this->permissions->actingBranchId($actor) : null;

            return implode('|', [
                'user',
                (string) $actor->getKey(),
                $role instanceof UserRole ? $role->value : '',
                (string) $actor->store_id,
                (string) $actor->branch_id,
                $token instanceof PersonalAccessToken ? 't' . $token->getKey() : 'session',
                // The store's permission overrides are not folded in: a change of them (role_permission_overrides)
                // touches every branch of the store (RevisionMap STORE_BRANCHES), and every staff ETag depends on a
                // branch of its store; the permission middleware runs before the conditional check anyway
                $acting === null ? '*' : (string) $acting,
            ]);
        }

        if ($actor instanceof Device) {
            return implode('|', [
                'device',
                (string) $actor->getKey(),
                (string) $actor->store_id,
                (string) $actor->branch_id,
                $actor->type->value,
                (string) $actor->name,
                (string) $actor->display_for_device_id,
            ]);
        }

        return 'guest';
    }

    private function secret(): string
    {
        return 'conditional-get|' . (string) config('app.key');
    }

    /**
     * @param  array<mixed>  $value
     */
    private static function sortDeep(array &$value): void
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                self::sortDeep($item);
            }
        }
    }

    // --- scope resolvers (200 path only) -----------------------------------------------------------------------------

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function resolve(Request $request, string $resolver): array
    {
        return match ($resolver) {
            'branch' => $this->branchScopes($request),
            'order' => $this->orderScopes($request),
            'user' => $request->user() instanceof User ? [['user', (int) $request->user()->getKey()]] : [],
            'orders_feed' => $this->ordersFeedScopes($request),
            default => [],
        };
    }

    /**
     * The request's branch (`branch_id`, else the caller's acting branch, a device's own branch); for a store-wide
     * caller without `branch_id`: the store and every branch of it (a store-wide list).
     *
     * @return list<array{0: string, 1: int}>
     */
    private function branchScopes(Request $request, bool $branchParam = true): array
    {
        $actor = $request->user();

        if ($actor instanceof Device) {
            return $actor->branch_id !== null ? [['branch', (int) $actor->branch_id]] : [];
        }
        if (!$actor instanceof User || $actor->store_id === null) {
            return [];
        }

        $id = $branchParam ? RowId::parse($request->query('branch_id')) : null;
        if ($id === null) {
            $acting = $this->permissions->actingBranchId($actor);
            if ($acting === PermissionService::NO_BRANCH) {
                return [];
            }
            $id = $acting;
        }

        return $id !== null ? [['branch', $id]] : $this->storeWide((int) $actor->store_id);
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function storeWide(int $storeId): array
    {
        // The branch list is cached under the store's token: a branch added or removed (the branches table) moves the
        // store token (RevisionMap STORE), so a stale list is never used, and no query runs on a warm cache
        $token = $this->revisions->store($storeId);
        $list = fn () => Branch::query()->where('store_id', $storeId)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        try {
            // The revision store (Redis), never the database cache store
            $ids = Cache::store((string) config('realtime.revision_store', 'redis'))
                ->remember('conditional_get:branches:' . $storeId . ':' . $token, 3600, $list);
        } catch (\Throwable) {
            $ids = $list();
        }

        $scopes = [['store', $storeId]];
        foreach ((array) $ids as $branchId) {
            $scopes[] = ['branch', (int) $branchId];
        }

        return $scopes;
    }

    /**
     * The branch of the `{uuid}` order (its store for an order without a branch).
     *
     * @return list<array{0: string, 1: int}>
     */
    private function orderScopes(Request $request): array
    {
        $uuid = $request->route('uuid');
        if (!is_string($uuid) || !Str::isUuid($uuid)) {
            return [];
        }

        $order = Order::query()->where('uuid', $uuid)->first(['id', 'store_id', 'branch_id']);
        if ($order === null) {
            return [];
        }

        return $order->branch_id !== null ? [['branch', (int) $order->branch_id]] : $this->storeWide((int) $order->store_id);
    }

    /**
     * GET /orders by role (OrderQueryService::scopedTo): an owner or manager sees their branch (or store), a courier
     * the store's orders assigned to them, anyone else their own orders as a customer.
     *
     * @return list<array{0: string, 1: int}>
     */
    private function ordersFeedScopes(Request $request): array
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            return [];
        }

        return match (true) {
            in_array($actor->role, [UserRole::OWNER, UserRole::MANAGER], true) => $this->branchScopes($request, false),
            $actor->role === UserRole::COURIER && $actor->store_id !== null => [['user', (int) $actor->getKey()], ...$this->storeWide((int) $actor->store_id)],
            default => [['user', (int) $actor->getKey()]],
        };
    }
}
