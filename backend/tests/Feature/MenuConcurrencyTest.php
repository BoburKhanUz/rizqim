<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\IdempotencyKey;
use App\Models\MenuBatch;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P1-21 under real concurrency: forked processes, each with its own database connection, place orders at the same
 * moment. A batch never sells more than is left (and never goes negative), and duplicate checkouts with one
 * Idempotency-Key create one order. The fixtures are committed (no RefreshDatabase: the children must see them) and
 * removed again in tearDown.
 */
class MenuConcurrencyTest extends TestCase
{
    use OrderLifecycleFixtures;
    use MenuFixtures;

    private string $dir;

    /** @var list<User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('pcntl_fork') || DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Needs pcntl and PostgreSQL.');
        }

        $this->artisan('migrate', ['--force' => true]);
        $this->setUpLifecycleStore();
        $this->dir = sys_get_temp_dir() . '/rizqim-concurrency-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (isset($this->store)) {
            $userIds = User::where('store_id', $this->store->id)->pluck('id')
                ->merge(collect($this->users)->pluck('id'))->push($this->customer->id)->unique()->all();
            $orderIds = Order::withTrashed()->where('store_id', $this->store->id)->pluck('id')->all();

            NotificationLog::whereIn('order_id', $orderIds)->orWhereIn('user_id', $userIds)->delete();
            AuditLog::where('store_id', $this->store->id)->orWhereIn('user_id', $userIds)->delete();
            IdempotencyKey::where('actor_type', 'user')->whereIn('actor_id', $userIds)->delete();
            Order::withTrashed()->whereIn('id', $orderIds)->forceDelete();
            $categoryId = $this->product->category_id;
            $this->store->forceDelete();
            User::whereIn('id', $userIds)->forceDelete();
            Category::whereKey($categoryId)->forceDelete();
        }

        if (isset($this->dir) && is_dir($this->dir)) {
            array_map('unlink', glob($this->dir . '/*') ?: []);
            rmdir($this->dir);
        }

        parent::tearDown();
    }

    public function test_concurrent_orders_never_take_more_than_the_batch_has(): void
    {
        $osh = $this->makeProduct('Osh', ['batch_tracked' => true]);
        // Two variants: their stock rows are locked separately, so only the batch row stands between the buyers
        $portion = $this->makeVariant($osh, '55000', ['name' => '1 porsiya']);
        $small = $this->makeVariant($osh, '45000', ['name' => '1 porsiya (b)']);
        $batch = MenuBatch::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'product_id' => $osh->id,
            'quantity_produced' => '4', 'quantity_left' => '4', 'started_at' => now(),
        ]);

        $buyers = [];
        for ($i = 0; $i < 7; $i++) {
            $buyers[] = $this->users[] = $this->makeCustomer();
        }

        $statuses = $this->race($buyers, fn (User $buyer, int $i) => $this->order(
            [['variant_id' => ($i % 2 ? $small : $portion)->uuid, 'quantity' => 1]], [], $buyer,
        ), fn () => MenuBatch::whereKey($batch->id)->lockForUpdate()->first());

        sort($statuses);
        $this->assertSame([201, 201, 201, 201, 422, 422, 422], $statuses);
        $this->assertSame('0.000', $batch->fresh()->getRawOriginal('quantity_left'));
        $this->assertSame(4, Order::where('store_id', $this->store->id)->count());
        $this->assertSame('4.000', (string) DB::table('order_items')->where('batch_id', $batch->id)->sum('batch_quantity'));
    }

    public function test_concurrent_duplicates_with_one_idempotency_key_create_one_order(): void
    {
        $customer = $this->users[] = $this->makeCustomer();
        $headers = ['Idempotency-Key' => 'double-tap-' . uniqid()];
        $items = [['variant_id' => $this->variant->uuid, 'quantity' => 1]];

        // The parent holds the variant's row: the first request stays in progress while the others arrive
        $statuses = $this->race(array_fill(0, 4, $customer), fn (User $buyer) => $this->order($items, [], $buyer, $headers),
            fn () => ProductVariant::whereKey($this->variant->id)->lockForUpdate()->first(), true);

        $this->assertSame([201, 201, 201, 201], $statuses);
        $this->assertSame(1, Order::where('store_id', $this->store->id)->count());
        $this->assertSame(9, $this->variant->fresh()->stock);

        $bodies = array_map(fn (int $i) => json_decode((string) file_get_contents("{$this->dir}/body-{$i}"), true), range(0, 3));
        $this->assertCount(1, array_unique(array_map(fn (array $body) => $body['data']['order_uuid'], $bodies)));
        $this->assertSame(3, count(array_filter(range(0, 3), fn (int $i) => file_get_contents("{$this->dir}/replayed-{$i}") === 'true')));
    }

    public function test_concurrent_stops_of_one_item_make_one_entry(): void
    {
        // P1-34 review: staff stopping the same item at the same instant get one entry and no 500 (updateOrCreate's
        // createOrFirst retries the unique violation inside a savepoint)
        $body = ['type' => 'variant', 'id' => $this->variant->id, 'branch_id' => $this->branch->id, 'reason' => 'tugadi'];
        // Every child sends at the same instant
        $at = microtime(true) + 0.4;

        $statuses = $this->race(array_fill(0, 6, $this->owner), function (User $owner) use ($body, $at) {
            time_sleep_until(max($at, microtime(true) + 0.001));

            return $this->actingAs($owner, 'sanctum')->postJson('/api/v1/owner/stop-list', $body);
        }, fn () => null);

        $this->assertSame([201, 201, 201, 201, 201, 201], $statuses);
        $this->assertSame(1, \App\Models\StopListEntry::where('branch_id', $this->branch->id)->count());
    }

    /**
     * Forks one child per actor; each waits for the row the parent holds locked, then sends its request at the same
     * moment as the others. Returns the HTTP statuses.
     *
     * @param  list<User>  $actors
     * @param  callable(User, int): \Illuminate\Testing\TestResponse  $request
     * @param  callable(): mixed  $hold  locks a row inside the parent's transaction
     * @return list<int>
     */
    private function race(array $actors, callable $request, callable $hold, bool $holdLonger = false): array
    {
        $pids = [];
        DB::beginTransaction();
        $hold();

        foreach ($actors as $i => $actor) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'fork failed');

            if ($pid === 0) {
                $this->runChild($i, fn () => $request($actor, $i));
            }

            $pids[] = $pid;
        }

        // Let every child reach the locked row (or the waiting key), then let them all go
        usleep($holdLonger ? 1_500_000 : 700_000);
        DB::commit();

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        return array_map(fn (int $i) => (int) @file_get_contents("{$this->dir}/status-{$i}"), array_keys($actors));
    }

    /**
     * In the child: a connection of its own (the parent's socket is kept alive but never used or closed here), the
     * request, the result written to files, and an exit that skips PHPUnit's shutdown.
     */
    private function runChild(int $i, callable $request): never
    {
        static $inherited = [];

        try {
            $inherited[] = DB::connection()->getPdo();
            DB::purge();
            $this->app['auth']->forgetGuards();

            $response = $request();
            file_put_contents("{$this->dir}/status-{$i}", (string) $response->getStatusCode());
            file_put_contents("{$this->dir}/body-{$i}", (string) $response->getContent());
            file_put_contents("{$this->dir}/replayed-{$i}", (string) $response->headers->get('Idempotent-Replayed'));
        } catch (\Throwable $e) {
            file_put_contents("{$this->dir}/status-{$i}", '0');
            file_put_contents("{$this->dir}/error-{$i}", (string) $e);
        }

        DB::disconnect();
        posix_kill(getmypid(), SIGKILL);
        exit(0);
    }
}
