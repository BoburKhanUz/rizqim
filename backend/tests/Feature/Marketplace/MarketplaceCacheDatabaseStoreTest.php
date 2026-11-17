<?php

namespace Tests\Feature\Marketplace;

use App\Domain\Marketplace\ReadModelCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The read model on a cache store with a text column (the `database` store, PostgreSQL): its compressed documents
 * are stored as base64 there (a raw deflate stream is not UTF-8 and the column refuses it), so the read model really
 * caches on that store instead of logging a warning, backing off and building every document from the database.
 * Binary-safe stores (Redis, array) keep the raw form; a copy in the old form, or one that cannot be read at all, is
 * read or rebuilt, never an error.
 */
class MarketplaceCacheDatabaseStoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A document whose deflate stream has bytes that are not UTF-8 and no NUL byte (which Laravel's database store
     * would base64 by itself): the case PostgreSQL refused.
     *
     * @return array<string, mixed>
     */
    private function awkwardDocument(): array
    {
        for ($i = 0; $i < 10000; $i++) {
            $document = ['menu' => str_repeat('Osh palov ', 20) . $i, 'n' => $i, 'price' => (string) (30000 + $i)];
            $raw = (string) gzdeflate(serialize($document), 1);
            if (!str_contains($raw, "\0") && !mb_check_encoding($raw, 'UTF-8')) {
                return $document;
            }
        }
        $this->fail('no awkward document found');
    }

    public function test_documents_are_cached_on_the_database_store(): void
    {
        config(['marketplace.cache_store' => 'database']);
        Log::spy();
        $cache = app(ReadModelCache::class);
        $document = $this->awkwardDocument();
        $builds = 0;
        $build = function () use (&$builds, $document) {
            $builds++;

            return $document;
        };

        $this->assertSame($document, $cache->remember('mkt:test', 'a', 'menu', $build));
        $this->assertSame($document, $cache->remember('mkt:test', 'a', 'menu', $build));
        $this->assertSame(1, $builds, 'the second read is the cached copy');

        // rememberCurrent's rebuild (putMany) is stored too
        $this->assertSame($document, $cache->rememberCurrent('mkt:test', 'a', 'menu', $build, fn (array $value) => $builds > 1));
        $this->assertSame(2, $builds);
        $this->assertSame($document, $cache->remember('mkt:test', 'a', 'menu', $build));
        $this->assertSame(2, $builds);

        Log::shouldNotHaveReceived('warning');

        // The row holds the text form
        $key = 'mkt:test:' . $cache->deploy() . ':a';
        $stored = Cache::store('database')->get($key);
        $this->assertIsString($stored);
        $this->assertSame('b', $stored[0]);
        $this->assertTrue(mb_check_encoding((string) DB::table('cache')->where('key', 'like', '%' . $key)->value('value'), 'UTF-8'));
    }

    public function test_binary_safe_stores_keep_the_raw_form(): void
    {
        $document = $this->awkwardDocument();
        $cache = app(ReadModelCache::class);
        $this->assertTrue(ReadModelCache::binarySafe(Cache::store('array')));
        $this->assertFalse(ReadModelCache::binarySafe(Cache::store('database')));

        $cache->remember('mkt:test', 'r', 'menu', fn () => $document);
        $stored = Cache::store('array')->get('mkt:test:' . $cache->deploy() . ':r');
        $this->assertSame('z' . gzdeflate(serialize($document), 1), $stored);
        // The text form is the same stream in base64: a third larger
        $this->assertSame(strlen(base64_encode(substr($stored, 1))), strlen(ReadModelCache::encode($document, true)) - 1);
    }

    public function test_an_old_form_copy_is_read_and_an_unreadable_one_is_rebuilt(): void
    {
        $document = ['menu' => 'Osh', 'n' => 1];
        foreach (['array', 'database'] as $name) {
            config(['marketplace.cache_store' => $name]);
            $cache = new ReadModelCache();
            $store = Cache::store($name);
            $key = fn (string $version) => 'mkt:old:' . $cache->deploy() . ':' . $version;
            $put = function (string $version, string $blob) use ($store, $key) {
                $store->putMany([$key($version) => $blob, 'illuminate:cache:flexible:created:' . $key($version) => now()->getTimestamp()], 3600);
            };
            $fail = fn () => $this->fail("{$name}: a readable copy is not rebuilt");

            // What the previous code stored: z + raw deflate (on the database store only when Laravel could keep it), s + serialize
            $raw = 'z' . gzdeflate(serialize($document), 1);
            if ($name === 'array' || str_contains($raw, "\0") || mb_check_encoding($raw, 'UTF-8')) {
                $put('z', $raw);
                $this->assertSame($document, $cache->remember('mkt:old', 'z', 'menu', $fail));
            }
            $put('s', 's' . serialize($document));
            $this->assertSame($document, $cache->remember('mkt:old', 's', 'menu', $fail));

            // A copy nobody can read: rebuilt (and stored again), no error
            foreach (['bad-base64' => 'b%%%', 'bad-deflate' => 'bAAAA', 'unknown' => 'q123'] as $version => $blob) {
                $put($version, $blob);
                $this->assertSame(['rebuilt' => $version], $cache->remember('mkt:old', $version, 'menu', fn () => ['rebuilt' => $version]), "{$name}: {$version}");
                $this->assertSame(['rebuilt' => $version], $cache->rememberCurrent('mkt:old', $version, 'menu', fn () => ['rebuilt' => $version], fn () => true));
            }
        }
    }
}
