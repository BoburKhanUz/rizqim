<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreRating;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Wave M4 H1 under real concurrency: two customers rate the same store at the same moment (forked processes, each with
 * its own connection). Both ratings are counted: the aggregate is recomputed under the store's row lock, so neither
 * recomputation misses the other's row. The fixtures are committed (the children must see them) and removed in tearDown.
 */
class StoreRatingConcurrencyTest extends TestCase
{
    use OrderLifecycleFixtures;

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
        $this->dir = sys_get_temp_dir() . '/rizqim-rating-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (isset($this->store)) {
            $userIds = User::where('store_id', $this->store->id)->pluck('id')
                ->merge(collect($this->users)->pluck('id'))->push($this->customer->id)->unique()->all();
            StoreRating::where('store_id', $this->store->id)->delete();
            Order::withTrashed()->where('store_id', $this->store->id)->forceDelete();
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

    public function test_two_ratings_at_once_are_both_counted(): void
    {
        $raters = [];
        foreach ([5, 3] as $stars) {
            $customer = $this->users[] = $this->makeCustomer();
            $raters[] = [$customer, $this->makeOrder(OrderStatus::DELIVERED, ['user_id' => $customer->id]), $stars];
        }

        // The parent holds the store row with a lock that lets the rating rows in (their foreign key takes KEY SHARE)
        // but keeps any write of the store's aggregate waiting until both requests are under way
        $pids = [];
        DB::beginTransaction();
        Store::whereKey($this->store->id)->lock('for no key update')->first();

        foreach ($raters as $i => [$customer, $order, $stars]) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'fork failed');
            if ($pid === 0) {
                $this->runChild($i, fn () => $this->actingAs($customer, 'sanctum')
                    ->postJson("/api/v1/stores/{$this->store->id}/rate", ['rating' => $stars, 'order_id' => $order->id]));
            }
            $pids[] = $pid;
        }

        usleep(700_000);
        DB::commit();
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $statuses = array_map(fn (int $i) => (int) @file_get_contents("{$this->dir}/status-{$i}"), array_keys($raters));
        $this->assertSame([200, 200], $statuses, (string) @file_get_contents("{$this->dir}/error-0") . @file_get_contents("{$this->dir}/error-1"));
        $this->assertSame(2, StoreRating::where('store_id', $this->store->id)->count());
        $store = $this->store->fresh();
        $this->assertSame(2, (int) $store->review_count);
        $this->assertEquals(4.0, (float) $store->rating);
    }

    /**
     * In the child: a connection of its own, the request, the status written to a file, and an exit that skips
     * PHPUnit's shutdown (MenuConcurrencyTest::runChild).
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
        } catch (\Throwable $e) {
            file_put_contents("{$this->dir}/status-{$i}", '0');
            file_put_contents("{$this->dir}/error-{$i}", (string) $e);
        }

        DB::disconnect();
        posix_kill(getmypid(), SIGKILL);
        exit(0);
    }
}
