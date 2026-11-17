<?php

namespace App\Domain\Realtime;

use Illuminate\Cache\RedisStore;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Redis\Connections\PhpRedisClusterConnection;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connections\PredisClusterConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wave M2: one opaque change token per scope (a branch, a store, a customer). A token changes whenever anything a
 * screen of that scope shows may have changed, so a client (or the conditional-GET layer) that still holds the current
 * token knows it is up to date without reading the main tables.
 *
 * Tokens are decimal strings. Each committed change adds 1, so a client holding N that receives an event with
 * `rev` N+1 knows it missed nothing; any other value means "re-read". Clients compare tokens only for equality and
 * for "+1": the base is random and restarts (expiry, Redis flush, Redis down) jump to an unrelated value.
 *
 * Rules (docs/OPERATIONS.md, "Real-time"):
 * - Tokens live in the `realtime.revision_store` cache store (Redis in production), never in Postgres.
 * - A missing token (never issued, expired, Redis flushed) is replaced by a fresh random one: clients re-read once.
 * - When the store is unreachable every read returns a new random token: clients always re-read, never stale.
 * - A touch inside a database transaction is applied after the outermost transaction commits (and is dropped if it
 *   rolls back, which only means the token did not change for a write that did not happen). One after-commit callback
 *   per transaction, whatever the number of touches; the pending scopes are a de-duplicated set.
 * - A touch that cannot be applied never leaves the old token readable: when the increment is refused (Redis at
 *   `maxmemory`, a timeout) the keys are deleted (DEL is allowed even under `noeviction`), so the next read issues an
 *   unrelated token. If even the delete fails, the scopes stay "unsettled" in this process: every read of them returns
 *   a fresh random token (and peek returns null) until a later flush, read or the end of the request deletes them.
 *   What this cannot cover: a short-lived PHP-FPM request that ends while Redis refuses both the increment and the
 *   delete, after which Redis comes back with the old key intact; that token then lasts until the next touch of the
 *   scope or its TTL, and the clients' unconditional safety poll (60 s) and the ETag max ages bound the stale screen.
 * - touchStore() also touches every branch of the store (their tills and KDS show the store's menu and stop-list).
 *   touchStoreAlone() moves only the store token (marketplace-only data, RevisionMap::NARROW_COLUMNS), touchBranch()
 *   only the branch, touchStoreBranches() every branch of the store but not the store token, touchAllStores() every
 *   store and branch (platform reference data). Branch lists are resolved at the flush, in one query per flush, and
 *   the increments go to Redis in one script call per 5 000 keys.
 *
 * A missing touch shows a stale screen; an extra touch only costs one full read. When in doubt, touch.
 */
#[Singleton]
final class ChangeRevision
{
    private const PREFIX = 'rev:';

    /** Seconds the store is treated as down after a failure, so a dead Redis costs one timeout, not one per call */
    private const DOWN_BACKOFF_SECONDS = 5;

    /** Keys per Redis script call (a script blocks Redis while it runs: about 1 ms per 1 000 keys) */
    private const CHUNK = 5000;

    /**
     * +1 per key; a key that did not exist (INCR gives 1) starts from the random base passed for it instead, so a token
     * from before an expiry or a Redis flush can never come back. KEYS = the tokens, ARGV = one base per key, then the TTL.
     */
    private const INCREMENT_SCRIPT = <<<'LUA'
local ttl = tonumber(ARGV[#ARGV])
for i, key in ipairs(KEYS) do
  if redis.call('INCR', key) <= 1 then
    redis.call('SET', key, ARGV[i], 'EX', ttl)
  end
end
return #KEYS
LUA;

    private float $downUntil = 0.0;

    /** @var array<string, true> token scopes ('b:1', 's:2', 'u:3') waiting for the transaction to commit */
    private array $pending = [];

    /** @var array<int, true> stores whose every branch is touched at the flush */
    private array $pendingBranchesOf = [];

    private bool $pendingAllStores = false;

    /** An after-commit flush is registered for the open transaction (reset on a rollback, see transactionRolledBack()) */
    private bool $scheduled = false;

    /** @var array<string, true> scopes whose touch could not be applied nor their key deleted (see the class docblock) */
    private array $unsettled = [];

    private bool $settleAtTerminate = false;

    public function branch(int $branchId): string
    {
        return $this->read('b:'.$branchId);
    }

    public function store(int $storeId): string
    {
        return $this->read('s:'.$storeId);
    }

    public function user(int $userId): string
    {
        return $this->read('u:'.$userId);
    }

    /** The branch's current token, or null when none exists (never issued, expired, store down). Never creates a key. */
    public function peekBranch(int $branchId): ?string
    {
        return $this->peek('b:'.$branchId);
    }

    /** The store's current token, or null when none exists. Never creates a key. */
    public function peekStore(int $storeId): ?string
    {
        return $this->peek('s:'.$storeId);
    }

    /** The customer's current token, or null when none exists. Never creates a key. */
    public function peekUser(int $userId): ?string
    {
        return $this->peek('u:'.$userId);
    }

    /**
     * The current tokens of many stores in one round trip (MGET); a missing token is issued as store() would.
     *
     * @param  list<int>  $ids
     * @return array<int, string> id => token
     */
    public function stores(array $ids): array
    {
        $tokens = [];
        foreach ($this->peekMany('s:', $ids) as $id => $token) {
            $tokens[$id] = $token ?? $this->read('s:'.$id);
        }

        return $tokens;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string|null> id => token, or null when none exists; one round trip, never creates a key
     */
    public function peekStores(array $ids): array
    {
        return $this->peekMany('s:', $ids);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string|null> id => token, or null when none exists; one round trip, never creates a key
     */
    public function peekBranches(array $ids): array
    {
        return $this->peekMany('b:', $ids);
    }

    public function touchBranch(int $branchId): void
    {
        $this->pending['b:'.$branchId] = true;
        $this->schedule();
    }

    /** The store token and every branch of the store */
    public function touchStore(int $storeId): void
    {
        $this->pending['s:'.$storeId] = true;
        $this->pendingBranchesOf[$storeId] = true;
        $this->schedule();
    }

    /** The store token only: data no branch screen shows (RevisionMap::NARROW_COLUMNS) */
    public function touchStoreAlone(int $storeId): void
    {
        $this->pending['s:'.$storeId] = true;
        $this->schedule();
    }

    /** Every branch of the store, not the store token (store-wide POS configuration) */
    public function touchStoreBranches(int $storeId): void
    {
        $this->pendingBranchesOf[$storeId] = true;
        $this->schedule();
    }

    /** Every store and every branch (platform reference data): resolved once, at the flush */
    public function touchAllStores(): void
    {
        $this->pendingAllStores = true;
        $this->schedule();
    }

    public function touchUser(int $userId): void
    {
        $this->pending['u:'.$userId] = true;
        $this->schedule();
    }

    /** A transaction (or a savepoint) rolled back: its after-commit flush is gone, the next touch registers a new one */
    public function transactionRolledBack(): void
    {
        $this->scheduled = false;
    }

    private function read(string $scope): string
    {
        if ($this->unsettled !== [] && isset($this->unsettled[$scope])) {
            $this->settle();
            if (isset($this->unsettled[$scope])) {
                return self::fresh();
            }
        }

        $store = $this->cache();
        if ($store === null) {
            return self::fresh();
        }

        try {
            $key = self::PREFIX.$scope;
            $token = $store->get($key);
            if (is_numeric($token)) {
                return (string) $token;
            }

            // First read (or expired/flushed): issue one token that concurrent readers agree on
            $candidate = self::fresh();
            if ($store->add($key, (int) $candidate, $this->ttl())) {
                return $candidate;
            }

            $token = $store->get($key);

            return is_numeric($token) ? (string) $token : $candidate;
        } catch (Throwable $e) {
            $this->markDown($e);

            return self::fresh();
        }
    }

    private function peek(string $scope): ?string
    {
        if (isset($this->unsettled[$scope])) {
            return null;
        }

        $store = $this->cache();
        if ($store === null) {
            return null;
        }

        try {
            $token = $store->get(self::PREFIX.$scope);

            return is_numeric($token) ? (string) $token : null;
        } catch (Throwable $e) {
            $this->markDown($e);

            return null;
        }
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string|null>
     */
    private function peekMany(string $prefix, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $result = array_fill_keys($ids, null);
        if ($ids === []) {
            return $result;
        }

        $store = $this->cache();
        if ($store === null) {
            return $result;
        }

        try {
            $keys = array_map(fn (int $id): string => self::PREFIX.$prefix.$id, $ids);
            $values = $store->many($keys);
            foreach ($ids as $i => $id) {
                $token = $values[$keys[$i]] ?? null;
                $result[$id] = is_numeric($token) && !isset($this->unsettled[$prefix.$id]) ? (string) $token : null;
            }
        } catch (Throwable $e) {
            $this->markDown($e);
        }

        return $result;
    }

    private function schedule(): void
    {
        // Registered and still inside the transaction it was registered in (outside any transaction a callback that
        // has not run means that transaction ended without one: register again)
        if ($this->scheduled && DB::transactionLevel() > 0) {
            return;
        }
        $this->scheduled = true;

        // Runs at once outside a transaction, otherwise after the outermost commit. One callback per transaction (the
        // flag is cleared by the flush and by a rollback); a set left over from a rolled-back transaction is flushed by
        // the next commit (an extra touch, never a missing one).
        DB::afterCommit(fn () => $this->flush());
    }

    private function flush(): void
    {
        $this->scheduled = false;
        if ($this->unsettled !== []) {
            $this->settle();
        }
        if ($this->pending === [] && $this->pendingBranchesOf === [] && !$this->pendingAllStores) {
            return;
        }

        $scopes = $this->pending;
        $branchesOf = array_keys($this->pendingBranchesOf);
        $all = $this->pendingAllStores;
        $this->pending = [];
        $this->pendingBranchesOf = [];
        $this->pendingAllStores = false;

        // A store-visible change reaches every branch screen of the store: one query, however many stores
        try {
            if ($all) {
                $rows = DB::table('stores')->leftJoin('branches', 'branches.store_id', '=', 'stores.id')
                    ->select(['stores.id as store_id', 'branches.id as branch_id'])->get();
                foreach ($rows as $row) {
                    $scopes['s:'.$row->store_id] = true;
                    if ($row->branch_id !== null) {
                        $scopes['b:'.$row->branch_id] = true;
                    }
                }
            } elseif ($branchesOf !== []) {
                foreach (DB::table('branches')->whereIn('store_id', $branchesOf)->pluck('id') as $branchId) {
                    $scopes['b:'.$branchId] = true;
                }
            }
        } catch (Throwable $e) {
            Log::warning('ChangeRevision: could not list the branches of a touched store', ['error' => $e->getMessage()]);
        }

        $this->apply(array_keys($scopes));
    }

    /**
     * +1 on every scope; on failure the keys are deleted, and if that fails too they stay unsettled.
     *
     * @param  list<string>  $scopes
     */
    private function apply(array $scopes): void
    {
        if ($scopes === []) {
            return;
        }

        $store = $this->cache();
        if ($store === null) {
            $this->unsettle($scopes); // reads return fresh tokens meanwhile; the keys go at the next chance

            return;
        }

        $done = 0;
        try {
            $redis = $this->redis($store);
            foreach (array_chunk($scopes, self::CHUNK) as $chunk) {
                if ($redis !== null) {
                    $keys = array_map(fn (string $scope): string => $redis[1].self::PREFIX.$scope, $chunk);
                    $bases = array_map(fn (): string => self::fresh(), $chunk);
                    $arguments = [...$keys, ...$bases, (string) $this->ttl()];
                    $result = $redis[0] instanceof PhpRedisConnection
                        ? $redis[0]->eval(self::INCREMENT_SCRIPT, count($keys), ...$arguments)
                        : $redis[0]->command('eval', [self::INCREMENT_SCRIPT, count($keys), ...$arguments]);
                    if ($result === false) {
                        throw new \RuntimeException('the revision script was refused');
                    }
                } else {
                    foreach ($chunk as $scope) {
                        $key = self::PREFIX.$scope;
                        // +1 per committed change; a missing key (INCR gives 1) starts from a random base instead
                        if ((int) $store->increment($key) <= 1) {
                            $store->put($key, (int) self::fresh(), $this->ttl());
                        }
                    }
                }
                $done += count($chunk);
            }
        } catch (Throwable $e) {
            $left = array_slice($scopes, $done);
            if (!$this->discard($store, $left)) {
                $this->unsettle($left);
            }
            $this->markDown($e);
        }
    }

    /**
     * Deletes the keys so the old tokens can never validate again (the next read issues new ones).
     *
     * @param  list<string>  $scopes
     */
    private function discard(Repository $store, array $scopes): bool
    {
        try {
            $redis = $this->redis($store);
            foreach (array_chunk($scopes, self::CHUNK) as $chunk) {
                if ($redis !== null) {
                    $redis[0]->del(...array_map(fn (string $scope): string => $redis[1].self::PREFIX.$scope, $chunk));
                } else {
                    foreach ($chunk as $scope) {
                        $store->forget(self::PREFIX.$scope);
                    }
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param list<string> $scopes */
    private function unsettle(array $scopes): void
    {
        foreach ($scopes as $scope) {
            $this->unsettled[$scope] = true;
        }
        if (!$this->settleAtTerminate) {
            $this->settleAtTerminate = true;
            try {
                app()->terminating(fn () => $this->settle());
            } catch (Throwable) {
                // a later flush or read settles them
            }
        }
    }

    /** Deletes the keys of the unsettled scopes once the store answers again */
    private function settle(): void
    {
        if ($this->unsettled === []) {
            return;
        }
        $store = $this->cache();
        if ($store === null) {
            return;
        }
        if ($this->discard($store, array_keys($this->unsettled))) {
            $this->unsettled = [];
        }
    }

    /**
     * The Redis connection and key prefix of a Redis revision store (null for another driver or a cluster, whose keys
     * would span slots: they take the one-key-at-a-time path).
     *
     * @return array{0: \Illuminate\Redis\Connections\Connection, 1: string}|null
     */
    private function redis(Repository $store): ?array
    {
        $inner = $store->getStore();
        if (!$inner instanceof RedisStore) {
            return null;
        }
        $connection = $inner->connection();
        if ($connection instanceof PhpRedisClusterConnection || $connection instanceof PredisClusterConnection) {
            return null;
        }

        return [$connection, $inner->getPrefix()];
    }

    private function cache(): ?Repository
    {
        if ($this->downUntil > microtime(true)) {
            return null;
        }

        try {
            return Cache::store((string) config('realtime.revision_store', 'redis'));
        } catch (Throwable $e) {
            $this->markDown($e);

            return null;
        }
    }

    private function markDown(Throwable $e): void
    {
        $this->downUntil = microtime(true) + self::DOWN_BACKOFF_SECONDS;
        Log::warning('ChangeRevision: revision store unavailable, clients will re-read', ['error' => $e->getMessage()]);
    }

    private function ttl(): int
    {
        return max(60, (int) config('realtime.revision_ttl_seconds', 7 * 24 * 3600));
    }

    /** A random base far from 1 (and far below 2^53, so JavaScript clients can add 1 exactly) */
    private static function fresh(): string
    {
        return (string) random_int(1_000_000_000, 4_000_000_000_000_000);
    }
}
