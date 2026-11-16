<?php

namespace App\Domain\Marketplace;

use App\Domain\Realtime\ChangeRevision;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The revisions the marketplace documents are keyed by.
 *
 * - store(id): ChangeRevision::store (the shared contract): one store's card, branches, reviews and menu.
 * - directory(): any store card or branch of any store (name, logo, hours, open flag, location, radius...). The area
 *   documents (which stores reach a geo cell, their cards) are keyed by it.
 * - global(): what every store's documents share: categories, promo banners, the platform's payment-provider switches.
 *
 * Same rules as ChangeRevision: tokens live in `realtime.revision_store`, a missing token is replaced by a fresh one
 * (one rebuild), a store that is down gives a fresh token on every read (always rebuilt, never stale), a touch is
 * applied after the outermost transaction commits.
 */
final class MarketplaceRevision
{
    private const PREFIX = 'rev:mkt:';

    private const DOWN_BACKOFF_SECONDS = 5;

    private float $downUntil = 0.0;

    public function store(int $storeId): string
    {
        return app(ChangeRevision::class)->store($storeId);
    }

    public function directory(): string
    {
        return $this->read('dir');
    }

    public function global(): string
    {
        return $this->read('global');
    }

    public function touchDirectory(): void
    {
        $this->schedule('dir');
    }

    public function touchGlobal(): void
    {
        $this->schedule('global');
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
            if (is_string($token) && $token !== '') {
                return $token;
            }
            $candidate = self::fresh();
            if ($store->add($key, $candidate, $this->ttl())) {
                return $candidate;
            }
            $token = $store->get($key);

            return is_string($token) && $token !== '' ? $token : $candidate;
        } catch (Throwable $e) {
            $this->markDown($e);

            return self::fresh();
        }
    }

    private function schedule(string $scope): void
    {
        DB::afterCommit(function () use ($scope) {
            $store = $this->cache();
            if ($store === null) {
                return;
            }
            try {
                $store->put(self::PREFIX.$scope, self::fresh(), $this->ttl());
            } catch (Throwable $e) {
                $this->markDown($e);
            }
        });
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
        Log::warning('MarketplaceRevision: revision store unavailable, documents are rebuilt', ['error' => $e->getMessage()]);
    }

    private function ttl(): int
    {
        return max(60, (int) config('realtime.revision_ttl_seconds', 7 * 24 * 3600));
    }

    private static function fresh(): string
    {
        return bin2hex(random_bytes(8));
    }
}
