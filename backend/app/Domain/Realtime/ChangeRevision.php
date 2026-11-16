<?php

namespace App\Domain\Realtime;

use App\Models\Branch;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Cache\Repository;
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
 *   rolls back, which only means the token did not change for a write that did not happen).
 * - touchStore() also touches every branch of the store (their tills and KDS show the store's menu and stop-list).
 *   touchBranch() touches only the branch: the hooks call touchStore() themselves when a change is store-visible.
 *
 * A missing touch shows a stale screen; an extra touch only costs one full read. When in doubt, touch.
 */
#[Singleton]
final class ChangeRevision
{
    private const PREFIX = 'rev:';

    /** Seconds the store is treated as down after a failure, so a dead Redis costs one timeout, not one per call */
    private const DOWN_BACKOFF_SECONDS = 5;

    private float $downUntil = 0.0;

    /** @var array<string, true> scope keys waiting for the transaction to commit */
    private array $pending = [];

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

    public function touchBranch(int $branchId): void
    {
        $this->schedule('b:'.$branchId);
    }

    public function touchStore(int $storeId): void
    {
        $this->schedule('s:'.$storeId);
    }

    public function touchUser(int $userId): void
    {
        $this->schedule('u:'.$userId);
    }

    private function read(string $scope): string
    {
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

    private function schedule(string $scope): void
    {
        $this->pending[$scope] = true;

        // Runs at once outside a transaction, otherwise after the outermost commit. One callback per touch: a set left
        // over from a rolled-back transaction is flushed by the next commit (an extra touch, never a missing one).
        DB::afterCommit(fn () => $this->flush());
    }

    private function flush(): void
    {
        if ($this->pending === []) {
            return;
        }

        $scopes = array_keys($this->pending);
        $this->pending = [];

        // A store-visible change reaches every branch screen of the store
        $storeIds = [];
        foreach ($scopes as $scope) {
            if (str_starts_with($scope, 's:')) {
                $storeIds[] = (int) substr($scope, 2);
            }
        }
        if ($storeIds !== []) {
            try {
                foreach (Branch::query()->whereIn('store_id', $storeIds)->pluck('id') as $branchId) {
                    $scopes[] = 'b:'.$branchId;
                }
            } catch (Throwable $e) {
                Log::warning('ChangeRevision: could not list the branches of a touched store', ['error' => $e->getMessage()]);
            }
        }

        $store = $this->cache();
        if ($store === null) {
            return; // reads return fresh tokens while the store is down
        }

        try {
            foreach (array_unique($scopes) as $scope) {
                $key = self::PREFIX.$scope;
                // +1 per committed change, so a client holding N can tell "the next change" (N+1) from "I missed
                // something". A key that did not exist comes back as 1: start it from a random base instead, so a
                // token from before an expiry or a Redis flush can never come back.
                if ((int) $store->increment($key) <= 1) {
                    $store->put($key, (int) self::fresh(), $this->ttl());
                }
            }
        } catch (Throwable $e) {
            $this->markDown($e);
        }
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
