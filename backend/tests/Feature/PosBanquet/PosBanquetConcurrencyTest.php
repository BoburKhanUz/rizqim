<?php

namespace Tests\Feature\PosBanquet;

use App\Models\AuditLog;
use App\Models\BanquetEvent;
use App\Models\Booking;
use App\Models\Category;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P6-51 under real concurrency (forked processes, each with its own connection, as PosBookingConcurrencyTest): four
 * managers holding the same hall for the same evening at once get one event, the others 409 `slot_taken`. Fixtures
 * are committed and removed in tearDown.
 */
class PosBanquetConcurrencyTest extends TestCase
{
    use PosBanquetFixtures;
    use \Tests\Feature\AuditLogCleanup;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('pcntl_fork') || DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Needs pcntl and PostgreSQL.');
        }

        $this->artisan('migrate', ['--force' => true]);
        $this->setUpBanquets();
        $this->dir = sys_get_temp_dir() . '/rizqim-banquets-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (isset($this->store)) {
            $storeIds = [$this->store->id, $this->foreignStore->id];
            $userIds = User::whereIn('store_id', $storeIds)->pluck('id')->push($this->customer->id)->all();

            $this->withoutAuditLogGuard(fn () => AuditLog::whereIn('store_id', $storeIds)->orWhereIn('user_id', $userIds)->delete());
            IdempotencyKey::where('actor_type', 'user')->whereIn('actor_id', $userIds)->delete();
            BanquetEvent::whereIn('store_id', $storeIds)->delete();
            DB::table('banquet_settings')->whereIn('store_id', $storeIds)->delete();
            DB::table('banquet_extras')->whereIn('store_id', $storeIds)->delete();
            DB::table('banquet_packages')->whereIn('store_id', $storeIds)->delete();
            DB::table('banquet_hall_rates')->whereIn('store_id', $storeIds)->delete();
            Booking::whereIn('store_id', $storeIds)->delete();
            Order::withTrashed()->whereIn('store_id', $storeIds)->forceDelete();
            $categoryId = $this->product->category_id;
            Store::withTrashed()->whereIn('id', $storeIds)->forceDelete();
            User::whereIn('id', $userIds)->forceDelete();
            Category::whereKey($categoryId)->forceDelete();
        }

        if (isset($this->dir) && is_dir($this->dir)) {
            array_map('unlink', glob($this->dir . '/*') ?: []);
            rmdir($this->dir);
        }

        parent::tearDown();
    }

    public function test_four_managers_holding_one_hall_at_once_get_one_event(): void
    {
        $at = microtime(true) + 0.4;

        $results = $this->race(4, function (int $i) use ($at) {
            time_sleep_until(max($at, microtime(true) + 0.001));
            $response = $this->hold(['customer_name' => "Oila {$i}"]);

            return $response->getStatusCode() === 409 ? (string) $response->json('data.reason') : (string) $response->getStatusCode();
        });

        sort($results);
        $this->assertSame(['201', 'slot_taken', 'slot_taken', 'slot_taken'], $results);
        $this->assertSame(1, BanquetEvent::where('branch_id', $this->branch->id)->count());
    }

    /**
     * Forks `$count` children that each run `$work` on a connection of their own once the parent lets go of the branch
     * row it locked; returns what each returned.
     *
     * @param  callable(int): string  $work
     * @return list<string>
     */
    private function race(int $count, callable $work): array
    {
        $pids = [];
        DB::beginTransaction();
        \App\Models\Branch::whereKey($this->branch->id)->lockForUpdate()->first();

        for ($i = 0; $i < $count; $i++) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'fork failed');

            if ($pid === 0) {
                static $inherited = [];
                try {
                    $inherited[] = DB::connection()->getPdo();
                    DB::purge();
                    $this->app['auth']->forgetGuards();
                    $result = $work($i);
                } catch (\Throwable $e) {
                    $result = 'error: ' . $e->getMessage();
                }
                file_put_contents("{$this->dir}/result-{$i}", $result);
                DB::disconnect();
                posix_kill(getmypid(), SIGKILL);
                exit(0);
            }

            $pids[] = $pid;
        }

        usleep(700_000);
        DB::commit();

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        return array_map(fn (int $i) => (string) @file_get_contents("{$this->dir}/result-{$i}"), range(0, $count - 1));
    }
}
