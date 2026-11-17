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
 * ChangeRevision) and an expiry, and carries two MACs. Both cover the request (path, query, locale, deploy salt), the
 * actor (who asks, their role, store, branch, acting branch, token; a device's branch, type, name and the till it
 * follows), the expiry and the scope names; the second also covers the tokens of those scopes as they were BEFORE the
 * body was read. A repeat request whose If-None-Match holds that ETag is answered `304 Not Modified` while the expiry
 * has not passed and every token is unchanged: the controller does not run, so a 304 costs the route's own middleware
 * (authentication, rate limit, permission/module gates) plus one revision round trip per scope kind, and no
 * main-table query. Anything else runs the controller as before. Bodies are never changed.
 *
 * Review M2 R2-1: nothing in If-None-Match reaches the revision store (Redis) unauthenticated. A candidate is looked
 * at only if it is one of the first two, at most MAX_TAG bytes, not expired, and its first MAC (no token in it) is
 * this request's: so it names exactly the scopes the server issued for this actor and URL. Its scopes must also be
 * ones this route can issue to this actor (admissible()). Only then are the tokens read, with the non-creating
 * peek reads in one round trip per kind: a token that does not exist is "no match", never a new key.
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
 *   (store:N, user:N); a store-wide list carries neither (its revision is many tokens: re-read on any event). Only to
 *   an actor entitled to follow that scope (review M2 R2-2): a branch to a device of that branch or a staff member of
 *   its store, a store to its staff and devices, a user scope to that user. A customer never gets a store token.
 * - Clients: send the last ETag in If-None-Match; on 304 keep the last body AND the moment it was received (timers in
 *   a body, such as `waiting_seconds` or `open_minutes`, count from that moment).
 * - A token that cannot be read (Redis down) is a fresh random value (ChangeRevision): the ETag never matches, every
 *   poll is a full read; never a stale 304. `conditional_get.enabled=false` turns all of it off.
 */
final class ConditionalGet
{
    private const VERSION = 'c2';

    /** More scopes than this (a very large store-wide list): no ETag, a plain 200 as before. */
    private const MAX_SCOPES = 64;

    /** If-None-Match candidates looked at (a client sends one; nginx or a proxy may add a weak copy) */
    private const MAX_CANDIDATES = 2;

    /** Longest tag looked at: MAX_SCOPES names of 20 digits, the expiry and both MACs fit */
    private const MAX_TAG = 1500;

    /** Characters of the names MAC (96 bits) and of the token MAC (132 bits) */
    private const NAMES_MAC = 16;

    private const TOKENS_MAC = 22;

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
     * depends() with a token the caller already read (before the data it covers), for example many store tokens read
     * in one round trip (ChangeRevision::stores). The first token declared for a scope wins.
     */
    public static function dependsAt(Request $request, string $scope, int $id, string $token): void
    {
        $letter = self::SCOPES[$scope] ?? null;
        if ($letter === null || $id <= 0 || $token === '') {
            throw new \InvalidArgumentException("Unknown change scope {$scope}:{$id}");
        }

        /** @var array<string, string> $scopes */
        $scopes = $request->attributes->get(self::ATTR_SCOPES, []);
        $scopes[$letter . $id] ??= $token;
        $request->attributes->set(self::ATTR_SCOPES, $scopes);
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

        $matched = $this->matching($request, $context, $resolver);
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
        // the next broadcast `rev` (N+1) from a missed one. A multi-scope body (a store-wide list) carries none, and
        // nobody learns the token of a scope they are not entitled to follow (R2-2: never a store's to a customer).
        if (count($scopes) === 1) {
            $key = (string) array_key_first($scopes);
            if ($this->entitled($request, $key[0], (int) substr($key, 1))) {
                $response->headers->set('X-Revision', $scopes[$key]);
                $response->headers->set('X-Revision-Scope', ['b' => 'branch', 's' => 'store', 'u' => 'user'][$key[0]] . ':' . substr($key, 1));
            }
        }

        return $response;
    }

    // --- 304 path ------------------------------------------------------------------------------------------------

    /**
     * The If-None-Match tag that is still current for this request, or null.
     */
    private function matching(Request $request, string $context, string $resolver): ?string
    {
        $header = $request->headers->get('If-None-Match');
        if ($header === null || $header === '' || strlen($header) > 4096) {
            return null;
        }

        // The first two candidates only: the rest of the header is never even split
        foreach (array_slice(explode(',', $header, self::MAX_CANDIDATES + 1), 0, self::MAX_CANDIDATES) as $candidate) {
            // nginx turns a strong ETag weak when it compresses the body: W/"..." is the same tag
            $tag = trim($candidate);
            if (str_starts_with($tag, 'W/')) {
                $tag = substr($tag, 2);
            }
            if (strlen($tag) <= self::MAX_TAG && $this->stillCurrent($request, $resolver, $tag, $context)) {
                return $tag;
            }
        }

        return null;
    }

    private function stillCurrent(Request $request, string $resolver, string $tag, string $context): bool
    {
        if (!preg_match('/^"' . self::VERSION . '\.([0-9a-z]{1,13})\.([bsu][0-9]{1,19}(?:-[bsu][0-9]{1,19})*)\.([A-Za-z0-9_-]{' . self::NAMES_MAC . '})\.([A-Za-z0-9_-]{' . self::TOKENS_MAC . '})"$/', $tag, $m)) {
            return false;
        }

        $expires = (int) base_convert($m[1], 36, 10);
        if ($expires <= now()->getTimestamp()) {
            return false;
        }

        // 1. Without the revision store: the names are the ones this server issued for this request and actor
        if (!hash_equals($this->namesMac($context, $m[1], $m[2]), $m[3])) {
            return false;
        }

        $keys = explode('-', $m[2]);
        if (count($keys) > self::MAX_SCOPES || count($keys) !== count(array_unique($keys))) {
            return false;
        }

        foreach ($keys as $key) {
            $id = (int) substr($key, 1);
            if ($id <= 0 || (string) $id !== substr($key, 1)) {
                return false;
            }
        }
        $of = fn (string $letter): array => array_values(array_map(
            fn (string $key) => (int) substr($key, 1),
            array_filter($keys, fn (string $key) => $key[0] === $letter),
        ));
        $ids = ['b' => $of('b'), 's' => $of('s'), 'u' => $of('u')];

        // 2. And ones this route can issue to this actor at all
        if (!$this->admissible($request, $resolver, $ids)) {
            return false;
        }

        // 3. The tokens, never creating one: a scope without a token is "changed"
        $tokens = [
            'b' => $ids['b'] === [] ? [] : $this->revisions->peekBranches($ids['b']),
            's' => $ids['s'] === [] ? [] : $this->revisions->peekStores($ids['s']),
            'u' => array_combine($ids['u'], array_map(fn (int $id) => $this->revisions->peekUser($id), $ids['u'])),
        ];
        $scopes = [];
        foreach ($keys as $key) {
            $token = $tokens[$key[0]][(int) substr($key, 1)] ?? null;
            if ($token === null) {
                return false;
            }
            $scopes[$key] = $token;
        }

        return hash_equals($this->tag($context, $expires, $scopes), $tag);
    }

    /**
     * Whether the scopes of a tag are ones this route issues to this actor (a cheap check on top of the names MAC):
     * a user scope is the caller's own; a device's branch and store are its own; a staff member's store is their own,
     * and on a single-branch read the branch is the one this request resolves to; a customer has no branch scope.
     *
     * @param  array{b: list<int>, s: list<int>, u: list<int>}  $ids
     */
    private function admissible(Request $request, string $resolver, array $ids): bool
    {
        $actor = $request->user();

        if ($actor instanceof Device) {
            return $ids['u'] === []
                && array_diff($ids['b'], [(int) $actor->branch_id]) === []
                && array_diff($ids['s'], [(int) $actor->store_id]) === [];
        }
        if (!$actor instanceof User) {
            return $ids['u'] === [] && $ids['b'] === [];
        }
        if (array_diff($ids['u'], [(int) $actor->getKey()]) !== []) {
            return false;
        }
        if ($resolver === 'user') {
            return $ids['b'] === [] && $ids['s'] === [];
        }
        if ($actor->store_id === null) {
            return $ids['b'] === []; // a customer: stores of the marketplace, their own scope
        }
        if ($resolver !== 'none' && array_diff($ids['s'], [(int) $actor->store_id]) !== []) {
            return false;
        }
        if ($resolver === 'branch' && $ids['s'] === []) {
            // A single-branch read: the branch this request resolves to (no query: the parameter or the acting branch)
            return array_diff($ids['b'], array_map(fn (array $scope) => $scope[1], $this->branchScopesWithoutStoreWide($request))) === [];
        }

        return true;
    }

    /**
     * The branch a `branch` read resolves to, without the store-wide expansion (which needs the store token).
     *
     * @return list<array{0: string, 1: int}>
     */
    private function branchScopesWithoutStoreWide(Request $request): array
    {
        $actor = $request->user();
        if (!$actor instanceof User || $actor->store_id === null) {
            return [];
        }
        $id = RowId::parse($request->query('branch_id'));
        if ($id === null) {
            $acting = $this->permissions->actingBranchId($actor);
            $id = $acting === PermissionService::NO_BRANCH ? null : $acting;
        }

        return $id !== null ? [['branch', (int) $id]] : [];
    }

    /**
     * Whether the actor may learn the raw token of a scope (X-Revision): R2-2.
     */
    private function entitled(Request $request, string $letter, int $id): bool
    {
        $actor = $request->user();

        if ($actor instanceof Device) {
            return match ($letter) {
                'b' => (int) $actor->branch_id === $id,
                's' => (int) $actor->store_id === $id,
                default => false,
            };
        }
        if (!$actor instanceof User) {
            return false;
        }

        return match ($letter) {
            'u' => (int) $actor->getKey() === $id,
            // Staff of the store; the branch resolver only issues a branch of the caller's store (or the route's own
            // permission gate refused the request before)
            'b' => $actor->store_id !== null && ((int) $actor->branch_id === $id
                || $this->permissions->actingBranchId($actor) === $id
                || Branch::query()->whereKey($id)->where('store_id', $actor->store_id)->exists()),
            's' => $actor->store_id !== null && (int) $actor->store_id === $id,
            default => false,
        };
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
        $mac = $this->mac('tokens', $context . "\n" . $exp . "\n" . $names . "\n" . implode("\n", $scopes), self::TOKENS_MAC);

        return '"' . self::VERSION . '.' . $exp . '.' . $names . '.' . $this->namesMac($context, $exp, $names) . '.' . $mac . '"';
    }

    /**
     * The MAC of what a tag names (no token): checked before the revision store is asked anything.
     */
    private function namesMac(string $context, string $exp, string $names): string
    {
        return $this->mac('names', $context . "\n" . $exp . "\n" . $names, self::NAMES_MAC);
    }

    private function mac(string $purpose, string $payload, int $length): string
    {
        return substr(rtrim(strtr(base64_encode(hash_hmac('sha256', $purpose . "\n" . $payload, $this->secret(), true)), '+/', '-_'), '='), 0, $length);
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
