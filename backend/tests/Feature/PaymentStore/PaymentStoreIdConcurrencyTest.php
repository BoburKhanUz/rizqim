<?php

namespace Tests\Feature\PaymentStore;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use App\Support\Database\OwnerConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\AuditLogCleanup;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * M4 stage 2 under real concurrency (forked processes, each with its own connection, as the application role): the
 * payments.store_id migration runs while tills write. Rows written before the column are backfilled while one till
 * holds an old payment locked (a refund in progress) and another keeps inserting payments (old code without the
 * column, new code with it) and updating old ones, through the backfill, the constraint steps and VALIDATE. No till
 * write fails, a backfill batch never waits for the till's lock, and every row ends with its order's store. The
 * fixtures are committed (the children must see them) and removed in tearDown.
 */
class PaymentStoreIdConcurrencyTest extends TestCase
{
    use OrderLifecycleFixtures;
    use AuditLogCleanup;

    private const MIGRATION = 'migrations/2026_11_27_100000_add_store_id_to_payments_table.php';

    private const OLD_ROWS = 5000;

    private string $dir;

    private Store $other;

    /** @var list<Order> */
    private array $orders = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('pcntl_fork') || DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Needs pcntl and PostgreSQL.');
        }

        $this->artisan('migrate', ['--force' => true]);
        $this->setUpLifecycleStore();
        $this->other = $this->makeStore('Other Concurrent Store');
        $this->orders = [
            $this->makeOrder(OrderStatus::NEW),
            $this->makeOrder(OrderStatus::NEW, ['store_id' => $this->other->id, 'branch_id' => $this->makeBranch($this->other, 'Other Branch')->id]),
        ];
        $this->dir = sys_get_temp_dir() . '/rizqim-payment-store-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if (isset($this->store)) {
            // Whatever happened, the schema ends complete (the migration is re-runnable) and the fixtures are gone
            (require database_path(self::MIGRATION))->up();
            DB::table('payments')->whereIn('order_id', collect($this->orders)->pluck('id'))->delete();
            $userIds = User::whereIn('store_id', [$this->store->id, $this->other->id])->pluck('id')->push($this->customer->id)->all();
            Order::withTrashed()->whereIn('store_id', [$this->store->id, $this->other->id])->forceDelete();
            $categoryId = $this->product->category_id;
            $this->store->forceDelete();
            $this->other->forceDelete();
            User::whereIn('id', $userIds)->forceDelete();
            Category::whereKey($categoryId)->forceDelete();
        }

        if (isset($this->dir) && is_dir($this->dir)) {
            array_map('unlink', glob($this->dir . '/*') ?: []);
            rmdir($this->dir);
        }

        parent::tearDown();
    }

    // M4: the parent runs the migration (DDL) as the schema owner when the roles are split; the tills (children) switch
    // back to the application role
    #[\Tests\Concerns\AsSchemaOwner]
    public function test_tills_keep_writing_while_the_migration_backfills_and_validates(): void
    {
        [$mine, $theirs] = $this->orders;
        $this->asBeforeTheBackfill($mine, $theirs);
        $locked = (int) DB::table('payments')->where('order_id', $mine->id)->whereNull('store_id')->orderBy('id')->skip(100)->value('id');
        $old = DB::table('payments')->whereIn('order_id', [$mine->id, $theirs->id])->whereNull('store_id')->pluck('id')->all();
        $this->assertCount(self::OLD_ROWS, $old);

        $pids = [];
        $pids[] = $this->fork(0, function () use ($locked) {
            // A refund in progress on an old payment: its row stays locked for 2 s, then it is written
            DB::transaction(function () use ($locked) {
                DB::table('payments')->where('id', $locked)->lockForUpdate()->first();
                file_put_contents("{$this->dir}/locked", '1');
                usleep(2_000_000);
                DB::table('payments')->where('id', $locked)->update(['status' => 'refunded', 'updated_at' => now()]);
            });
            file_put_contents("{$this->dir}/released", (string) microtime(true));
        });
        $pids[] = $this->fork(1, function () use ($mine, $theirs, $old) {
            // Tills taking payments (code from before M4 without the column, and the new code with it) and updating
            // old payments, until the migration is done
            $written = 0;
            $deadline = microtime(true) + 30;
            while (!file_exists("{$this->dir}/done") && microtime(true) < $deadline) {
                $order = $written % 2 === 0 ? $mine : $theirs;
                if ($written % 3 === 0) {
                    Payment::create(['order_id' => $order->id, 'store_id' => $order->store_id, 'provider' => 'cash', 'amount' => '1000', 'status' => 'paid']);
                } else {
                    DB::table('payments')->insert(['uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'provider' => 'cash', 'amount' => '1000.00', 'status' => 'paid', 'created_at' => now(), 'updated_at' => now()]);
                }
                DB::table('payments')->where('id', $old[$written % count($old)])->update(['updated_at' => now()]);
                $written++;
                if ($written === 1) {
                    file_put_contents("{$this->dir}/started", '1');
                }
            }
            file_put_contents("{$this->dir}/written", (string) $written);
        });

        $this->waitFor(['locked', 'started']);
        $rangeDone = null;
        DB::listen(function ($query) use (&$rangeDone) {
            if (str_contains($query->sql, 'FOR UPDATE SKIP LOCKED')) {
                $rangeDone = microtime(true);
            }
        });
        (require database_path(self::MIGRATION))->up();
        touch("{$this->dir}/done");
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $this->assertSame(['ok', 'ok'], [@file_get_contents("{$this->dir}/status-0"), @file_get_contents("{$this->dir}/status-1")], (string) @file_get_contents("{$this->dir}/error-0") . @file_get_contents("{$this->dir}/error-1"));
        $this->assertGreaterThan(0, (int) file_get_contents("{$this->dir}/written"));
        $this->assertNotNull($rangeDone);
        $this->assertLessThan((float) file_get_contents("{$this->dir}/released"), $rangeDone, 'a backfill batch waited for the row a till held locked');

        $this->assertFalse((bool) DB::selectOne("SELECT NOT attnotnull AS n FROM pg_attribute WHERE attrelid = 'payments'::regclass AND attname = 'store_id'")->n, 'payments.store_id is NOT NULL');
        $this->assertTrue((bool) DB::selectOne("SELECT convalidated AS v FROM pg_constraint WHERE conrelid = 'payments'::regclass AND conname = 'payments_store_id_foreign'")->v);
        $this->assertSame(['refunded', $this->store->id], [DB::table('payments')->where('id', $locked)->value('status'), (int) DB::table('payments')->where('id', $locked)->value('store_id')]);
        $this->assertSame(0, DB::table('payments')->join('orders', 'orders.id', '=', 'payments.order_id')->whereIn('payments.order_id', [$mine->id, $theirs->id])
            ->whereColumn('payments.store_id', '!=', 'orders.store_id')->count());
        $this->assertGreaterThan(self::OLD_ROWS, DB::table('payments')->whereIn('order_id', [$mine->id, $theirs->id])->count());
    }

    /**
     * The schema as a first run of the migration left it after its first step (column nullable, triggers in place, no
     * foreign key yet), with OLD_ROWS payments written before it (no store).
     */
    private function asBeforeTheBackfill(Order $mine, Order $theirs): void
    {
        $this->asSchemaOwner('ALTER TABLE payments DROP CONSTRAINT payments_store_id_foreign; ALTER TABLE payments ALTER COLUMN store_id DROP NOT NULL; ALTER TABLE payments DISABLE TRIGGER payments_store_id_insert');
        try {
            DB::table('payments')->insertUsing(['uuid', 'order_id', 'provider', 'amount', 'status', 'created_at', 'updated_at'], DB::query()->fromRaw('generate_series(1, ?) g', [self::OLD_ROWS])
                ->selectRaw("gen_random_uuid(), CASE WHEN g % 2 = 0 THEN ?::bigint ELSE ?::bigint END, 'cash', 1000, 'paid', now(), now()", [$mine->id, $theirs->id]));
        } finally {
            $this->asSchemaOwner('ALTER TABLE payments ENABLE TRIGGER payments_store_id_insert');
        }
    }

    private function fork(int $i, callable $work): int
    {
        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid, 'fork failed');
        if ($pid !== 0) {
            return $pid;
        }

        // In the child: a connection of its own, as the application role (a till), and an exit that skips PHPUnit's
        // shutdown (StoreRatingConcurrencyTest::runChild)
        static $inherited = [];
        try {
            foreach (array_keys(DB::getConnections()) as $name) {
                $inherited[] = DB::connection($name)->getPdo();
                DB::purge($name);
            }
            if (DB::getDefaultConnection() === OwnerConnection::NAME) {
                DB::setDefaultConnection('pgsql');
            }
            $work();
            file_put_contents("{$this->dir}/status-{$i}", 'ok');
        } catch (\Throwable $e) {
            file_put_contents("{$this->dir}/status-{$i}", 'failed');
            file_put_contents("{$this->dir}/error-{$i}", (string) $e);
        }

        DB::disconnect();
        posix_kill(getmypid(), SIGKILL);
        exit(0);
    }

    /**
     * @param  list<string>  $files
     */
    private function waitFor(array $files): void
    {
        $deadline = microtime(true) + 20;
        while (array_filter($files, fn (string $file) => !file_exists("{$this->dir}/{$file}")) !== []) {
            $this->assertLessThan($deadline, microtime(true), 'the tills did not start: ' . @file_get_contents("{$this->dir}/error-0") . @file_get_contents("{$this->dir}/error-1"));
            usleep(20_000);
        }
    }
}
