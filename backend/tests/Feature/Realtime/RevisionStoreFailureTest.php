<?php

namespace Tests\Feature\Realtime;

use App\Domain\Realtime\ChangeRevision;
use App\Models\Hall;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use ReflectionProperty;
use RuntimeException;
use Tests\Feature\PosQueue\PosQueueFixtures;
use Tests\TestCase;

/** An array store that refuses writes like Redis at `maxmemory` with `noeviction` (reads and DEL still answer) */
class RefusingRevisionStore extends ArrayStore
{
    public static bool $refuseWrites = false;

    public static bool $refuseDeletes = false;

    public function increment($key, $value = 1)
    {
        if (self::$refuseWrites) {
            throw new RuntimeException('OOM command not allowed when used memory > maxmemory');
        }

        return parent::increment($key, $value);
    }

    public function put($key, $value, $seconds)
    {
        if (self::$refuseWrites) {
            throw new RuntimeException('OOM command not allowed when used memory > maxmemory');
        }

        return parent::put($key, $value, $seconds);
    }

    public function forget($key)
    {
        if (self::$refuseDeletes) {
            throw new RuntimeException('connection refused');
        }

        return parent::forget($key);
    }
}

/**
 * Wave M2 review R1-2: a touch the revision store refuses must never leave the old token readable. Before the fix the
 * scopes were dropped and the failure only logged: after the 5 s back-off the old token came back and every ETag issued
 * before the write validated again (304 on stale data).
 */
class RevisionStoreFailureTest extends TestCase
{
    use PosQueueFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RefusingRevisionStore::$refuseWrites = false;
        RefusingRevisionStore::$refuseDeletes = false;
        Cache::extend('refusing', fn () => new Repository(new RefusingRevisionStore));
        config(['cache.stores.refusing' => ['driver' => 'refusing'], 'realtime.revision_store' => 'refusing']);
        app()->forgetInstance(ChangeRevision::class);
        $this->setUpPosQueue();
    }

    protected function tearDown(): void
    {
        RefusingRevisionStore::$refuseWrites = false;
        RefusingRevisionStore::$refuseDeletes = false;
        parent::tearDown();
    }

    private function newHall(): void
    {
        Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'name' => 'Yangi zal', 'sort_order' => 9]);
    }

    /** The next request (PHP-FPM builds a new container): no back-off, no in-process state */
    private function nextRequest(): ChangeRevision
    {
        app()->forgetInstance(ChangeRevision::class);

        return app(ChangeRevision::class);
    }

    public function test_a_refused_increment_deletes_the_token_so_an_old_etag_never_validates_again(): void
    {
        $first = $this->tillAs('GET', '/pos/floor')->assertOk();
        $etag = (string) $first->headers->get('ETag');
        $this->assertNotSame('', $etag);
        $old = app(ChangeRevision::class)->branch($this->branch->id);

        RefusingRevisionStore::$refuseWrites = true;
        $this->newHall();
        $this->assertNotSame($old, app(ChangeRevision::class)->branch($this->branch->id), 'in the back-off: a fresh token');

        // Past the back-off (a new request): the old token must be gone, not read back
        $this->assertNotSame($old, $this->nextRequest()->branch($this->branch->id));
        $this->withBearer($this->cashierToken)->withHeader('If-None-Match', $etag)->getJson('/api/v1/pos/floor')
            ->assertOk(); // never 304 on the floor from before the new hall

        // Writes accepted again: the next read issues one stable token
        RefusingRevisionStore::$refuseWrites = false;
        $revisions = $this->nextRequest();
        $this->assertSame($revisions->branch($this->branch->id), $revisions->branch($this->branch->id));
        $this->assertNotSame($old, $revisions->branch($this->branch->id));
    }

    public function test_when_even_the_delete_fails_the_scope_reads_fresh_until_it_is_settled(): void
    {
        $revisions = app(ChangeRevision::class);
        $old = $revisions->branch($this->branch->id);
        $otherOld = $revisions->branch($this->otherBranch->id);

        RefusingRevisionStore::$refuseWrites = true;
        RefusingRevisionStore::$refuseDeletes = true;
        $this->newHall();

        // The same process keeps the scope unsettled: fresh tokens, never the old one; peek says "none"
        $this->assertNotSame($old, $revisions->branch($this->branch->id));
        $this->assertNotSame($revisions->branch($this->branch->id), $revisions->branch($this->branch->id));
        $this->assertNull($revisions->peekBranch($this->branch->id));

        // The store answers again (past the back-off): the next read deletes the key, then one new token holds
        RefusingRevisionStore::$refuseWrites = false;
        RefusingRevisionStore::$refuseDeletes = false;
        (new ReflectionProperty(ChangeRevision::class, 'downUntil'))->setValue($revisions, 0.0);
        $settled = $revisions->branch($this->branch->id);
        $this->assertNotSame($old, $settled);
        $this->assertSame($settled, $revisions->branch($this->branch->id));
        $this->assertSame($settled, $this->nextRequest()->branch($this->branch->id), 'the key is settled in the store too');
        $this->assertSame($otherOld, $this->nextRequest()->branch($this->otherBranch->id), 'an untouched scope keeps its token');
    }

    /**
     * The reviewer's proof with a real Redis at `maxmemory` + `noeviction` (opt-in: REALTIME_TEST_REDIS=host:port, a
     * scratch server; this test sets its maxmemory).
     */
    public function test_a_real_redis_at_maxmemory_never_brings_the_old_token_back(): void
    {
        $target = (string) env('REALTIME_TEST_REDIS', '');
        if (!preg_match('/^([\w.-]+):(\d+)$/', $target, $m)) {
            $this->markTestSkipped('needs REALTIME_TEST_REDIS=host:port (a scratch Redis server)');
        }
        config([
            'database.redis.cache.host' => $m[1], 'database.redis.cache.port' => (int) $m[2], 'database.redis.cache.password' => null,
            'database.redis.cache.database' => 1, 'cache.stores.revisions_test' => ['driver' => 'redis', 'connection' => 'cache'],
            'realtime.revision_store' => 'revisions_test',
        ]);
        app()->forgetInstance('redis');
        Redis::clearResolvedInstances();
        $redis = Redis::connection('cache');
        $redis->command('flushdb');
        $this->nextRequest();

        $first = $this->tillAs('GET', '/pos/floor')->assertOk();
        $etag = (string) $first->headers->get('ETag');
        $old = app(ChangeRevision::class)->branch($this->branch->id);

        $redis->command('config', ['set', 'maxmemory-policy', 'noeviction']);
        $redis->command('config', ['set', 'maxmemory', '1']);
        try {
            $refused = false;
            try {
                $redis->command('set', ['f1:filler', 'x']);
            } catch (\Throwable) {
                $refused = true;
            }
            $this->assertTrue($refused, 'Redis accepted a write at maxmemory 1: the test proves nothing');

            $this->newHall();

            // Past the back-off: GET still answers under OOM, the key must not
            $this->assertNotSame($old, $this->nextRequest()->branch($this->branch->id));
            $this->withBearer($this->cashierToken)->withHeader('If-None-Match', $etag)->getJson('/api/v1/pos/floor')->assertOk();
        } finally {
            $redis->command('config', ['set', 'maxmemory', '0']);
        }

        $revisions = $this->nextRequest();
        $this->assertSame($revisions->branch($this->branch->id), $revisions->branch($this->branch->id));
        $this->assertCount(count((array) $first->json('data.halls')) + 1, (array) $this->tillAs('GET', '/pos/floor')->assertOk()->json('data.halls'));
    }
}
