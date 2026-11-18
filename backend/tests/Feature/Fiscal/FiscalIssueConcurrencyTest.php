<?php

namespace Tests\Feature\Fiscal;

use App\Domain\Fiscal\FiscalException;
use App\Domain\Fiscal\FiscalReceiptLock;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Models\AuditLog;
use App\Models\FiscalReceipt;
use App\Models\Store;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Feature\FiscalFixtures;
use Tests\TestCase;

/**
 * Wave M3 under real concurrency (forked processes, each with its own connection, as PosBookingConcurrencyTest):
 * two requests with the same receipt key, one at 23:59:59 on the last day of a month and one at 00:00:01 on the next
 * (their rows would land in different partitions, where the partition-local UNIQUE cannot see each other), give one
 * receipt; the same inside a month; the advisory lock alone (fiscal_receipt_keys' trigger switched off) is enough; a
 * waiter gives up after lock_timeout instead of hanging; two refunds of one sale are checked one after the other.
 * Fixtures are committed and removed in tearDown.
 */
class FiscalIssueConcurrencyTest extends TestCase
{
    use FiscalFixtures;
    use \Tests\Feature\AuditLogCleanup;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('pcntl_fork') || DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Needs pcntl and PostgreSQL.');
        }

        $this->artisan('migrate', ['--force' => true]);
        $this->store = $this->makeStore('Fiskal poyga');
        $this->fiscalMode($this->store);
        $this->dir = sys_get_temp_dir() . '/rizqim-fiscal-' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $this->asSchemaOwner('ALTER TABLE fiscal_receipts ENABLE TRIGGER fiscal_receipt_keys_insert');
        if (isset($this->store)) {
            $this->withoutAuditLogGuard(fn () => AuditLog::where('store_id', $this->store->id)->delete());
            // The store's receipts go with it (ON DELETE CASCADE); their keys have no foreign key
            DB::table('fiscal_receipt_keys')->where('store_id', $this->store->id)->delete();
            Store::withTrashed()->whereKey($this->store->id)->forceDelete();
        }
        if (isset($this->dir) && is_dir($this->dir)) {
            array_map('unlink', glob($this->dir . '/*') ?: []);
            rmdir($this->dir);
        }

        parent::tearDown();
    }

    private function key(string $key): string
    {
        return FiscalReceiptLock::name((int) $this->store->id, $key);
    }

    /**
     * $count children run $work at the same moment: the parent holds the advisory lock of $lockName until they are
     * all waiting on it, then lets go.
     *
     * @return list<string>
     */
    private function race(int $count, callable $work, ?string $lockName): array
    {
        $pids = [];
        DB::beginTransaction();
        if ($lockName !== null) {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$lockName]);
        }

        for ($i = 0; $i < $count; $i++) {
            $pid = pcntl_fork();
            $this->assertNotSame(-1, $pid, 'fork failed');

            if ($pid === 0) {
                static $inherited = [];
                try {
                    $inherited[] = DB::connection()->getPdo();
                    DB::purge();
                    $result = $work($i);
                } catch (FiscalException $e) {
                    $result = $e->reason;
                } catch (QueryException $e) {
                    $result = 'sqlstate:' . ($e->errorInfo[0] ?? $e->getCode());
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

    /**
     * @return list<string>
     */
    private function issueAcrossMidnight(string $key): array
    {
        return $this->race(2, function (int $i) use ($key) {
            $this->travelTo([0 => '2026-11-30 23:59:59', 1 => '2026-12-01 00:00:01'][$i]);

            return $this->issuer()->issue($this->document(key: $key, withBranch: false))->receiptUuid;
        }, $this->key($key));
    }

    private function assertOneReceipt(string $key, array $results): void
    {
        $this->assertCount(1, array_unique($results), 'both requests answer with the same receipt: ' . implode(', ', $results));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $results[0]);
        $this->assertSame([$results[0]], FiscalReceipt::where('store_id', $this->store->id)->where('idempotency_key', $key)->pluck('uuid')->all());
    }

    public function test_the_same_key_at_2359_59_and_0000_01_gives_one_receipt(): void
    {
        $results = $this->issueAcrossMidnight('pos-bill:midnight:sale');

        $this->assertOneReceipt('pos-bill:midnight:sale', $results);
        $this->assertSame(1, DB::table('fiscal_receipt_keys')->where('store_id', $this->store->id)->count());
    }

    public function test_the_advisory_lock_alone_keeps_one_receipt_across_midnight(): void
    {
        // Without the database guard: only FiscalService's lock and lookup stand between the two requests
        $this->asSchemaOwner('ALTER TABLE fiscal_receipts DISABLE TRIGGER fiscal_receipt_keys_insert');

        $results = $this->issueAcrossMidnight('pos-bill:lock-only:sale');

        $this->assertOneReceipt('pos-bill:lock-only:sale', $results);
    }

    public function test_the_same_key_twice_in_one_month_gives_one_receipt(): void
    {
        $results = $this->race(3, function () {
            $this->travelTo('2026-11-17 12:00:00');

            return $this->issuer()->issue($this->document(key: 'pos-bill:same:sale', withBranch: false))->receiptUuid;
        }, $this->key('pos-bill:same:sale'));

        $this->assertOneReceipt('pos-bill:same:sale', $results);
    }

    public function test_a_retry_waits_for_a_long_till_transaction_but_never_longer_than_the_lock_timeout(): void
    {
        $results = $this->race(3, function (int $i) {
            if ($i === 0) {
                // The till: issues, then keeps its transaction open (more work) for 1.5 s
                return DB::transaction(function () {
                    $uuid = $this->issuer()->issue($this->document(key: 'pos-bill:long:sale', withBranch: false))->receiptUuid;
                    usleep(1_500_000);

                    return $uuid;
                });
            }
            usleep(200_000);
            // Child 1 waits up to 5 s (gets the till's receipt), child 2 only 300 ms (gives up, retried later)
            config(['fiscal.idempotency.lock_timeout_ms' => $i === 1 ? 5000 : 300]);
            $started = microtime(true);
            try {
                return $this->issuer()->issue($this->document(key: 'pos-bill:long:sale', withBranch: false))->receiptUuid;
            } catch (QueryException $e) {
                // The offline sync treats it as transient: the operation stays queued and comes again
                return 'sqlstate:' . ($e->errorInfo[0] ?? '') . ':' . (int) ((microtime(true) - $started) * 1000)
                    . (\App\Domain\Pos\Sync\TransientDatabaseError::is($e) ? ':transient' : ':final');
            }
        }, null);

        $this->assertSame($results[0], $results[1], 'the patient retry gets the same receipt');
        $this->assertMatchesRegularExpression('/^sqlstate:55P03:\d+:transient$/', $results[2]);
        $waited = (int) explode(':', $results[2])[2];
        $this->assertGreaterThanOrEqual(250, $waited);
        $this->assertLessThan(1200, $waited, 'gave up after lock_timeout, not after the till transaction');
        $this->assertSame(1, FiscalReceipt::where('store_id', $this->store->id)->where('idempotency_key', 'pos-bill:long:sale')->count());
    }

    public function test_two_refunds_of_one_sale_at_once_never_give_back_more_than_it_was(): void
    {
        $sale = $this->issuer()->issue($this->document(lines: [$this->line('100000')], withBranch: false));
        $this->assertTrue($sale->isIssued());
        $original = FiscalReceipt::where('uuid', $sale->receiptUuid)->sole();

        // The first refund's transaction is still open (the till does more work) when the second one is checked
        $results = $this->race(2, function (int $i) use ($sale) {
            $refund = fn () => $this->issuer()->issue($this->document(FiscalReceiptType::REFUND, [$this->line('70000')], key: "refund-{$i}", original: $sale->receiptUuid, withBranch: false))->status;
            if ($i === 0) {
                return DB::transaction(function () use ($refund) {
                    $status = $refund();
                    usleep(800_000);

                    return $status === 'pending' ? 'issued' : $status;
                });
            }
            usleep(200_000);

            return $refund();
        }, null);

        sort($results);
        $this->assertSame(['issued', FiscalException::ORIGINAL_RECEIPT], $results);
        $this->assertSame('70000.00', (string) FiscalReceipt::where('original_receipt_id', $original->id)->sum('total'));
    }

    public function test_issue_outside_a_transaction_opens_its_own_and_no_lock_outlives_it(): void
    {
        $this->assertSame(0, DB::transactionLevel());
        $result = $this->issuer()->issue($this->document(key: 'no-transaction', withBranch: false));

        $this->assertTrue($result->isIssued());
        $this->assertSame(0, (int) DB::selectOne("SELECT count(*) AS n FROM pg_locks WHERE locktype = 'advisory' AND pid = pg_backend_pid()")->n);
        $this->expectException(LogicException::class);
        FiscalReceiptLock::acquire((int) $this->store->id, 'no-transaction');
    }
}
