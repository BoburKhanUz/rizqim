<?php

namespace Tests\Feature\PosMenu;

use App\Domain\Pos\Menu\WriteOffReason;
use App\Models\MenuBatch;
use App\Services\Catalog\BatchService;
use App\Services\Catalog\StockLocks;
use App\Support\Quantity;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Feature\MenuFixtures;
use Tests\Feature\PosBilling\PosBillingFixtures;
use Tests\TestCase;

/**
 * Review M2 (LR-1): a kazan write-off locks its batch, then inserts the write-off row whose foreign key takes FOR KEY
 * SHARE on the batch's variant; a sale locks the variant, then the batch (StockLocks). With FOR UPDATE stock locks the
 * two deadlocked (40P01). Stock rows are now locked FOR NO KEY UPDATE, which a foreign key's KEY SHARE does not wait
 * on: the write-off goes through, the sale waits for it, then goes through.
 *
 * A real two-process test on committed rows (pcntl_fork, two connections): each side pauses holding its first lock until
 * the other holds its own, so the steps run in this order every time. Not in a test transaction, so the database is
 * rebuilt before and after (migrate:fresh): it leaves no rows behind. Skipped without pcntl/posix or on another driver.
 */
class WriteOffSaleLockTest extends TestCase
{
    // M4: does DDL itself (tests/Concerns/UsesOwnerConnection.php): runs as the schema owner when the roles are split
    use \Tests\Concerns\UsesOwnerConnection;
    use MenuFixtures;
    use PosBillingFixtures;

    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('pcntl_fork') || !function_exists('posix_kill')) {
            $this->markTestSkipped('pcntl/posix not available');
        }
        if (DB::connection()->getDriverName() !== 'pgsql' || !str_starts_with((string) DB::connection()->getDatabaseName(), 'testing')) {
            $this->markTestSkipped('needs a PostgreSQL test database');
        }
        $this->artisan('migrate:fresh', ['--force' => true]);
        $this->setUpPosBilling();
        $this->dir = sys_get_temp_dir() . '/writeoff-lock-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if ($this->dir !== '') {
            array_map('unlink', glob($this->dir . '/*') ?: []);
            @rmdir($this->dir);
            DB::purge();
            $this->artisan('migrate:fresh', ['--force' => true]);
        }
        parent::tearDown();
    }

    /** Signals $mine, then waits (at most 10 s) for $theirs */
    private function rendezvous(string $mine, string $theirs): void
    {
        touch("{$this->dir}/{$mine}");
        $until = microtime(true) + 10;
        while (!file_exists("{$this->dir}/{$theirs}") && microtime(true) < $until) {
            usleep(10000);
        }
    }

    private static function locks(string $sql, string $table): bool
    {
        return str_contains($sql, "from \"{$table}\"") && preg_match('/ for (no key )?update$/', trim($sql)) === 1;
    }

    public function test_a_write_off_and_a_sale_of_the_same_dish_at_once_both_go_through(): void
    {
        $osh = $this->makeProduct('Osh', ['batch_tracked' => true]);
        $portion = $this->makeVariant($osh, '55000', ['name' => '1 porsiya']);
        $batch = MenuBatch::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'product_id' => $osh->id,
            'product_variant_id' => $portion->id, 'quantity_produced' => '10', 'quantity_left' => '10', 'started_at' => now(),
        ]);
        $branchId = (int) $this->branch->id;
        $uuid = (string) $portion->uuid;

        DB::disconnect();
        $pid = pcntl_fork();
        if ($pid === 0) {
            // The sale: the variant, then (after the write-off holds the batch) the batch
            DB::purge();
            DB::statement("set lock_timeout = '8s'");
            $paused = false;
            DB::listen(function (QueryExecuted $query) use (&$paused) {
                if (!$paused && self::locks($query->sql, 'product_variants')) {
                    $paused = true;
                    $this->rendezvous('sale', 'writeoff');
                }
            });
            try {
                DB::transaction(fn () => app(StockLocks::class)->lock($branchId, [$uuid]));
                $result = 'ok';
            } catch (\Throwable $e) {
                $result = $e->getMessage();
            }
            file_put_contents("{$this->dir}/sale.result", $result);
            posix_kill(getmypid(), SIGKILL);
        }

        // The write-off: the batch, then (after the sale holds the variant) the write-off row
        DB::purge();
        DB::statement("set lock_timeout = '8s'");
        $paused = false;
        DB::listen(function (QueryExecuted $query) use (&$paused) {
            if (!$paused && self::locks($query->sql, 'menu_batches')) {
                $paused = true;
                $this->rendezvous('writeoff', 'sale');
            }
        });
        try {
            app(BatchService::class)->writeOff($batch->fresh(), Quantity::of('1'), WriteOffReason::UNSOLD, null, null, $this->owner);
            $writeOff = 'ok';
        } catch (\Throwable $e) {
            $writeOff = $e->getMessage();
        }
        pcntl_waitpid($pid, $status);
        $sale = (string) @file_get_contents("{$this->dir}/sale.result");

        $this->assertTrue(file_exists("{$this->dir}/sale") && file_exists("{$this->dir}/writeoff"), 'both sides held their first lock');
        $this->assertSame('ok', $writeOff, 'write-off: ' . mb_substr($writeOff, 0, 300));
        $this->assertSame('ok', $sale, 'sale: ' . mb_substr($sale, 0, 300));
        $this->assertSame('9.000', (string) MenuBatch::whereKey($batch->id)->value('quantity_left'));
    }
}
