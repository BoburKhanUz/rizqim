<?php

namespace Tests\Feature\Realtime;

use App\Domain\Realtime\ChangeRevision;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wave M2 review R2-3: a write to platform reference data (RevisionMap::ALL_STORES) costs a constant number of SQL
 * statements and Redis round trips however many stores there are, and a long transaction of such writes holds one
 * pending flag, not a closure per touch. Before the fix one Category::create at 1 000 stores x 2 branches cost
 * 2 006 SQL statements and 6 003 Redis commands, and 300 creates in a transaction held 244 MB.
 */
class RevisionFlushCostTest extends TestCase
{
    use RefreshDatabase;

    private function seedStores(int $count): void
    {
        $stores = [];
        for ($i = 1; $i <= $count; $i++) {
            $stores[] = ['uuid' => (string) Str::uuid(), 'name' => "S{$i}", 'slug' => "s{$i}-" . Str::random(4)];
        }
        foreach (array_chunk($stores, 500) as $chunk) {
            DB::table('stores')->insert($chunk);
        }
        $branches = [];
        foreach (DB::table('stores')->pluck('id') as $id) {
            $branches[] = ['store_id' => $id, 'name' => 'A'];
            $branches[] = ['store_id' => $id, 'name' => 'B'];
        }
        foreach (array_chunk($branches, 500) as $chunk) {
            DB::table('branches')->insert($chunk);
        }
    }

    /** @return int the SQL statements $write runs */
    private function statements(callable $write): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $write();
        $this->app['events']->forget(\Illuminate\Database\Events\QueryExecuted::class);

        return $count;
    }

    public function test_an_all_stores_write_costs_the_same_sql_at_ten_and_at_a_thousand_stores(): void
    {
        $this->seedStores(10);
        Category::create(['uuid' => (string) Str::uuid(), 'name' => 'Nol', 'slug' => 'nol']); // warms the schema cache
        $small = $this->statements(fn () => Category::create(['uuid' => (string) Str::uuid(), 'name' => 'Bir', 'slug' => 'bir']));

        $this->seedStores(990);
        $revisions = app(ChangeRevision::class);
        $storeIds = DB::table('stores')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $branchIds = DB::table('branches')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertCount(1000, $storeIds);
        $before = [
            's' => array_map(fn (int $id) => $revisions->store($id), $storeIds),
            'b' => array_map(fn (int $id) => $revisions->branch($id), $branchIds),
        ];

        $large = $this->statements(fn () => Category::create(['uuid' => (string) Str::uuid(), 'name' => 'Ikki', 'slug' => 'ikki']));

        // Category::create is two writes (the sort_order shift and the insert), each its own commit here: +2
        foreach ($storeIds as $i => $id) {
            $before['s'][$i] = (string) ((int) $before['s'][$i] + 1);
        }
        foreach ($branchIds as $i => $id) {
            $before['b'][$i] = (string) ((int) $before['b'][$i] + 1);
        }

        $this->assertSame($small, $large, 'the SQL of one ALL_STORES write must not grow with the number of stores');
        $this->assertLessThanOrEqual(6, $large);

        // Every store and branch token moved by exactly 1 per commit (the client contract)
        foreach ($storeIds as $i => $id) {
            $this->assertSame((string) ((int) $before['s'][$i] + 1), $revisions->store($id));
        }
        foreach ($branchIds as $i => $id) {
            $this->assertSame((string) ((int) $before['b'][$i] + 1), $revisions->branch($id));
        }
    }

    public function test_many_all_stores_writes_in_one_transaction_hold_bounded_memory_and_touch_once(): void
    {
        $this->seedStores(200);
        $revisions = app(ChangeRevision::class);
        $storeId = (int) DB::table('stores')->value('id');
        $before = $revisions->store($storeId);

        $memory = memory_get_usage();
        $held = 0;
        $statements = $this->statements(function () use (&$held, $memory) {
            DB::transaction(function () use (&$held, $memory) {
                for ($i = 0; $i < 300; $i++) {
                    Category::create(['uuid' => (string) Str::uuid(), 'name' => "T{$i}", 'slug' => "t{$i}"]);
                }
                $held = memory_get_usage() - $memory;
            });
        });

        $this->assertLessThan(8 * 1024 * 1024, $held, 'memory held before the commit');
        $this->assertSame((string) ((int) $before + 1), $revisions->store($storeId), 'one commit: +1');
        $this->assertLessThan(300 * 4, $statements, 'no per-write store listing');
    }

    /**
     * With a real Redis (opt-in: REALTIME_TEST_REDIS=host:port, a scratch server; never a shared one): one ALL_STORES
     * write at 1 000 stores is a handful of Redis round trips.
     */
    public function test_an_all_stores_write_is_a_few_redis_round_trips(): void
    {
        $this->useScratchRedis();
        $this->seedStores(1000);
        app()->forgetInstance(ChangeRevision::class);

        $commands = [];
        Event::listen(CommandExecuted::class, function (CommandExecuted $event) use (&$commands) {
            $commands[] = $event->command;
        });
        Category::create(['uuid' => (string) Str::uuid(), 'name' => 'Redis', 'slug' => 'redis']);
        Event::forget(CommandExecuted::class);

        // Category::create is two writes (the sort_order shift and the insert): one script call each; the rest is the
        // marketplace's own global revision
        $evals = array_filter($commands, fn (string $command) => strtolower($command) === 'eval');
        $this->assertCount(2, $evals, 'Redis commands: ' . implode(' ', $commands));
        $this->assertLessThanOrEqual(6, count($commands), 'Redis commands: ' . implode(' ', $commands));

        $revisions = app(ChangeRevision::class);
        $storeId = (int) DB::table('stores')->max('id');
        $token = $revisions->store($storeId);
        DB::transaction(fn () => Category::create(['uuid' => (string) Str::uuid(), 'name' => 'Redis 2', 'slug' => 'redis-2']));
        $this->assertSame((string) ((int) $token + 1), $revisions->store($storeId), 'one commit: +1');
    }

    private function useScratchRedis(): void
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
        Redis::enableEvents();
        Redis::connection('cache')->command('flushdb');
    }
}
