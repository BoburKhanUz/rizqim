<?php

namespace App\Domain\Marketplace;

use Closure;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The marketplace documents' cache: stale-while-revalidate (Cache::flexible) with one builder per document.
 *
 * - A document lives under "<family>:<version>" (the version is made of revision tokens). A new version is a new key;
 *   building it drops the family's previous version at once, so a store whose menu changes on every order keeps one
 *   copy, not one per order until the TTL.
 * - A warm document inside its fresh time is returned as is (one cache read).
 * - Between fresh and stale it is returned once more and rebuilt after the response, under flexible's own lock.
 * - A cold document (first read, new version, expired) is built by one request holding a lock; the requests that
 *   arrive meanwhile wait up to `lock_wait_seconds` for it and then read it; if the lock is not freed in time they
 *   build it themselves (more load, never an error).
 * - Documents are stored compressed (zlib level 1 of the PHP serialization: a 60-dish menu is about 210 KB as is,
 *   10 KB compressed); on a store with a text column (the `database` store) the compressed form is base64 (a third
 *   larger, about 13 KB), so PostgreSQL accepts it. A copy that cannot be read is a miss, rebuilt, never an error.
 * - When the cache store fails, the document is built from the database for this request (slower, never wrong) and
 *   the store is skipped for a few seconds, so a dead Redis costs one timeout, not one per document.
 */
final class ReadModelCache
{
    private const DOWN_BACKOFF_SECONDS = 5;

    /** Bump when the shape of a document changes (a deploy's config:cache does the same by itself) */
    private const SCHEMA = 'm2.2';

    private float $downUntil = 0.0;

    /** remember() calls in progress: a document callback that runs while one is, is a cold build, not a deferred refresh */
    private int $depth = 0;

    /**
     * @template T
     *
     * @param  string  $kind  a key of config('marketplace.ttl')
     * @param  Closure(): T  $build
     * @return T
     */
    public function remember(string $family, string $version, string $kind, Closure $build): mixed
    {
        $store = $this->store();
        if ($store === null) {
            return $build();
        }

        $key = $family.':'.$this->deploy().':'.$version;
        $ttl = $this->ttl($kind);
        $text = !self::binarySafe($store);
        $encoded = function () use ($store, $family, $key, $build, $ttl, $text) {
            if ($this->depth === 0) {
                return self::encode($build(), $text); // deferred refresh: already under flexible's lock
            }

            // Cold path (synchronous, inside flexible): one builder; the previous version is dropped
            $blob = $this->buildLocked($store, $key, fn () => self::encode($build(), $text));
            $this->replaceVersion($store, $family, $key, $ttl[1]);

            return $blob;
        };

        $this->depth++;
        try {
            $blob = $store->flexible($key, $ttl, $encoded, ['seconds' => $this->lockSeconds()]);
        } catch (Throwable $e) {
            $this->markDown($e);

            return $build();
        } finally {
            $this->depth--;
        }

        try {
            return self::decode($blob);
        } catch (\UnexpectedValueException $e) {
            // A copy this code cannot read (another format, a damaged value) is a miss, never an error
            $this->forgetUnreadable($store, $key, $e);

            return $build();
        }
    }

    /**
     * Overwrite a document in place with a corrected copy (a few entries re-read), keeping its age: it is still
     * refreshed when its fresh time runs out and is gone when its stale time does, as if it had not been patched.
     */
    public function replace(string $family, string $version, string $kind, mixed $value): void
    {
        $store = $this->store();
        if ($store === null) {
            return;
        }

        $key = $family.':'.$this->deploy().':'.$version;
        try {
            $created = $store->get('illuminate:cache:flexible:created:'.$key);
            $left = is_numeric($created) ? (int) $created + $this->ttl($kind)[1] - now()->getTimestamp() : 0;
            if ($left > 0) {
                $store->put($key, self::encode($value, !self::binarySafe($store)), $left);
            }
        } catch (Throwable $e) {
            $this->markDown($e);
        }
    }

    /**
     * remember(), then: when $current says the document is out of date, rebuild it once (one builder under a lock;
     * the others wait for it and take its copy when that one is current) and store it as a new document.
     *
     * @template T
     *
     * @param  Closure(): T  $build
     * @param  Closure(T): bool  $current
     * @return T
     */
    public function rememberCurrent(string $family, string $version, string $kind, Closure $build, Closure $current): mixed
    {
        $value = $this->remember($family, $version, $kind, $build);
        if ($current($value)) {
            return $value;
        }

        $store = $this->store();
        if ($store === null) {
            return $build();
        }

        $key = $family.':'.$this->deploy().':'.$version;
        $built = null;
        $text = !self::binarySafe($store);
        try {
            $blob = $this->buildLocked($store, $key, function () use ($build, &$built, $text) {
                $built = $build();

                return self::encode($built, $text);
            }, function (string $existing) use ($current) {
                try {
                    return $current(self::decode($existing));
                } catch (\UnexpectedValueException) {
                    return false; // unreadable: rebuilt
                }
            });
            if ($built === null) {
                return self::decode($blob); // another request rebuilt it meanwhile
            }
            $ttl = $this->ttl($kind);
            $store->putMany([$key => $blob, 'illuminate:cache:flexible:created:'.$key => now()->getTimestamp()], $ttl[1]);
        } catch (Throwable $e) {
            $this->markDown($e);

            return $built ?? $build();
        }

        return $built;
    }

    /**
     * A small value (a store id, a reachability flag) kept for a fixed time; null from the cache counts as missing.
     *
     * @template T
     *
     * @param  Closure(): T  $build
     * @return T
     */
    public function short(string $key, int $seconds, Closure $build): mixed
    {
        $store = $this->store();
        if ($store === null) {
            return $build();
        }
        try {
            return $store->remember($key, $seconds, $build);
        } catch (Throwable $e) {
            $this->markDown($e);

            return $build();
        }
    }

    public function store(): ?Repository
    {
        if ($this->downUntil > microtime(true)) {
            return null;
        }

        try {
            $name = config('marketplace.cache_store');
            $store = Cache::store(is_string($name) && $name !== '' ? $name : null);

            return $store instanceof Repository ? $store : null;
        } catch (Throwable $e) {
            $this->markDown($e);

            return null;
        }
    }

    /**
     * The stored form of a document: `z` + the raw deflate stream (binary: Redis, Memcached, file, array), or with
     * $text `b` + its base64 (a store that keeps values in a text column: the `database` store, whose PostgreSQL column
     * rejects bytes that are not UTF-8). `s` + the plain serialization when deflate fails.
     */
    public static function encode(mixed $value, bool $text = false): string
    {
        $compressed = gzdeflate(serialize($value), 1);
        if ($compressed === false) {
            return 's'.serialize($value);
        }

        return $text ? 'b'.base64_encode($compressed) : 'z'.$compressed;
    }

    /**
     * Whether the store keeps any bytes as they are. The `database` store writes the value into a text column, and
     * Laravel only base64-encodes it there when it holds a NUL byte: a deflate stream without one but with other
     * non-UTF-8 bytes is refused by PostgreSQL.
     */
    public static function binarySafe(Repository $store): bool
    {
        return !$store->getStore() instanceof DatabaseStore;
    }

    /**
     * A document from its stored form (anything else: as it is).
     */
    public static function decode(mixed $blob): mixed
    {
        if (!is_string($blob) || $blob === '') {
            return $blob;
        }
        $body = match ($blob[0]) {
            'z' => @gzinflate(substr($blob, 1)),
            'b' => ($raw = base64_decode(substr($blob, 1), true)) === false ? false : @gzinflate($raw),
            's' => substr($blob, 1),
            default => false,
        };
        if ($body === false) {
            throw new \UnexpectedValueException('Marketplace read model: an unreadable document');
        }

        return unserialize($body, ['allowed_classes' => false]);
    }

    /**
     * @param  Closure(): string  $build
     * @param  (Closure(string): bool)|null  $usable  whether a copy found under the key may be returned (default: any)
     */
    private function buildLocked(Repository $store, string $key, Closure $build, ?Closure $usable = null): string
    {
        $locks = $store->getStore();
        if (!$locks instanceof LockProvider) {
            return $build();
        }

        try {
            return $locks->lock('mkt:lock:'.$key, $this->lockSeconds())->block(
                max(0, (int) config('marketplace.lock_wait_seconds', 3)),
                function () use ($store, $key, $build, $usable) {
                    // Someone else may have built it while this request waited for the lock
                    $existing = $store->get($key);

                    return is_string($existing) && ($usable === null || $usable($existing)) ? $existing : $build();
                },
            );
        } catch (LockTimeoutException) {
            return $build();
        }
    }

    /**
     * Remember $key as the family's current version and forget the one before it (a failure only leaves the old
     * copy to its TTL).
     */
    private function replaceVersion(Repository $store, string $family, string $key, int $ttl): void
    {
        try {
            $pointer = 'mkt:current:'.$family;
            $previous = $store->get($pointer);
            if (is_string($previous) && $previous !== $key) {
                $store->forget($previous);
                $store->forget('illuminate:cache:flexible:created:'.$previous);
            }
            $store->put($pointer, $key, $ttl);
        } catch (Throwable) {
            // best effort
        }
    }

    /**
     * Documents of an earlier deploy are never read: the code or the config (asset URLs, payment methods not
     * integrated yet) may have changed what they hold. The cached config file is rewritten at every container start.
     */
    public function deploy(): string
    {
        $file = app()->getCachedConfigPath();

        return substr(hash('xxh64', self::SCHEMA.'|'.(is_file($file) ? (string) filemtime($file) : '-')), 0, 8);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function ttl(string $kind): array
    {
        $ttl = (array) config('marketplace.ttl.'.$kind, [60, 300]);
        $fresh = max(1, (int) ($ttl[0] ?? 60));

        return [$fresh, max($fresh + 1, (int) ($ttl[1] ?? $fresh * 5))];
    }

    private function lockSeconds(): int
    {
        return max(1, (int) config('marketplace.lock_seconds', 10));
    }

    private function forgetUnreadable(Repository $store, string $key, Throwable $e): void
    {
        Log::warning('Marketplace read model: an unreadable cached document, rebuilt', ['key' => $key, 'error' => $e->getMessage()]);
        try {
            $store->forget($key);
            $store->forget('illuminate:cache:flexible:created:'.$key);
        } catch (Throwable) {
            // best effort: it expires with its TTL
        }
    }

    private function markDown(Throwable $e): void
    {
        $this->downUntil = microtime(true) + self::DOWN_BACKOFF_SECONDS;
        Log::warning('Marketplace read model: cache unavailable, reading from the database', ['error' => $e->getMessage()]);
    }
}
