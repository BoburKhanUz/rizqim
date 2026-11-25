<?php

namespace Tests\Feature;

use App\Services\Payment\PaymeCashbox;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The digest repair of a Payme webhook (PaymeCashbox::remember) waits at most a second for a store row another
 * transaction holds, and its lock_timeout is transaction-scoped (SET LOCAL in a short transaction of its own: php-fpm
 * reaches Postgres through PgBouncer in transaction mode, tests/Unit/TransactionPoolingSafetyTest). So the setting
 * never outlives the repair on that connection, a blocked row makes the repair give up without failing the request,
 * and inside a caller's transaction (a savepoint) neither the setting nor a lock timeout changes that transaction.
 *
 * Runs without the wrapping transaction: the second connection must see the committed store row to lock it. The store
 * is deleted afterwards.
 */
class PaymeDigestRepairLockTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = [];

    private const BLOCKER = 'pgsql_payme_digest_blocker';

    private int $storeId;
    private string $key;
    private string $settings;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('lock_timeout is a PostgreSQL setting');
        }
        $this->key = 'repair-key-' . Str::random(24);
        $this->settings = Crypt::encryptString((string) json_encode(['payme' => ['enabled' => true, 'merchant_id' => 'pm-lock', 'secret_key' => $this->key]]));
        $this->storeId = (int) DB::table('stores')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'name' => 'Digest Lock Cafe',
            'slug' => 'digest-lock-' . Str::lower(Str::random(10)),
            'status' => 'active',
            'payment_settings' => $this->settings,
            'payme_key_digest' => PaymeCashbox::digest('old-key-of-the-store'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        config(['database.connections.' . self::BLOCKER => config('database.connections.' . DB::getDefaultConnection())]);
    }

    protected function tearDown(): void
    {
        DB::connection(self::BLOCKER)->disconnect();
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        DB::table('stores')->where('id', $this->storeId)->delete();
        parent::tearDown();
    }

    private function lockTimeout(): string
    {
        return (string) DB::selectOne('SHOW lock_timeout')->lock_timeout;
    }

    private function digest(): ?string
    {
        return DB::table('stores')->where('id', $this->storeId)->value('payme_key_digest');
    }

    /** Another transaction (a ledger write) holds the store row until the returned connection rolls back. */
    private function holdStoreRow(): Connection
    {
        $blocker = DB::connection(self::BLOCKER);
        $blocker->beginTransaction();
        $blocker->select('SELECT id FROM stores WHERE id = ? FOR UPDATE', [$this->storeId]);

        return $blocker;
    }

    public function test_the_repair_sets_its_lock_timeout_only_for_its_own_transaction(): void
    {
        $before = $this->lockTimeout();
        $this->assertNotSame('1s', $before);

        PaymeCashbox::remember($this->storeId, $this->key, $this->settings);

        $this->assertSame(PaymeCashbox::digest($this->key), $this->digest());
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame($before, $this->lockTimeout());
    }

    public function test_a_held_store_row_makes_the_repair_give_up_after_about_a_second_without_failing(): void
    {
        $before = $this->lockTimeout();
        $blocker = $this->holdStoreRow();
        try {
            $started = microtime(true);
            PaymeCashbox::remember($this->storeId, $this->key, $this->settings); // never throws
            $waited = microtime(true) - $started;
        } finally {
            $blocker->rollBack();
        }

        $this->assertGreaterThan(0.8, $waited);
        $this->assertLessThan(5, $waited, 'the repair queued behind the held row instead of giving up');
        $this->assertSame(PaymeCashbox::digest('old-key-of-the-store'), $this->digest()); // kept as it was: retried next call
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame($before, $this->lockTimeout());

        // Once the row is free the next call repairs it
        PaymeCashbox::remember($this->storeId, $this->key, $this->settings);
        $this->assertSame(PaymeCashbox::digest($this->key), $this->digest());
    }

    public function test_inside_a_callers_transaction_the_repair_leaves_that_transaction_as_it_was(): void
    {
        DB::beginTransaction();
        DB::statement("SET LOCAL lock_timeout = '7s'");

        PaymeCashbox::remember($this->storeId, $this->key, $this->settings);
        $this->assertSame(1, DB::transactionLevel());
        $this->assertSame('7s', $this->lockTimeout()); // not the repair's 1s for the rest of the caller's transaction
        $this->assertSame(PaymeCashbox::digest($this->key), $this->digest());
        DB::rollBack();
        $this->assertSame(PaymeCashbox::digest('old-key-of-the-store'), $this->digest()); // the caller's rollback undid it

        // A lock timeout in the savepoint is rolled back to it: the caller's transaction goes on
        $blocker = $this->holdStoreRow();
        DB::beginTransaction();
        DB::statement("SET LOCAL lock_timeout = '7s'");
        try {
            PaymeCashbox::remember($this->storeId, $this->key, $this->settings);
        } finally {
            $blocker->rollBack();
        }
        $this->assertSame(1, DB::transactionLevel());
        $this->assertSame('7s', $this->lockTimeout());
        $this->assertSame(1, (int) DB::selectOne('SELECT 1 AS one')->one); // not "current transaction is aborted"
        $this->assertSame(PaymeCashbox::digest('old-key-of-the-store'), $this->digest());
        DB::commit();
    }

    public function test_the_recompute_sets_its_lock_timeout_only_per_store_and_counts_a_held_row(): void
    {
        $before = $this->lockTimeout();
        $blocker = $this->holdStoreRow();
        try {
            [, , $failed] = PaymeCashbox::recomputeAll('200ms');
        } finally {
            $blocker->rollBack();
        }
        $this->assertGreaterThanOrEqual(1, $failed);
        $this->assertSame(PaymeCashbox::digest('old-key-of-the-store'), $this->digest());
        $this->assertSame($before, $this->lockTimeout());

        PaymeCashbox::recomputeAll('200ms');
        $this->assertSame(PaymeCashbox::digest($this->key), $this->digest());
        $this->assertSame($before, $this->lockTimeout());
    }

    public function test_a_lock_timeout_that_is_not_an_interval_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PaymeCashbox::recomputeAll("1s'; SELECT 1; --");
    }
}
