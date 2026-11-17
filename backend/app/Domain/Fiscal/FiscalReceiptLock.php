<?php

namespace App\Domain\Fiscal;

use App\Models\FiscalReceipt;
use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Wave M3 (docs/scale/M3_DESIGN.md section 4): fiscal_receipts is partitioned by month and its uniques are
 * partition-local, so "one (store, idempotency key) = one receipt" is kept by
 *  1. this lock: FiscalService takes a transaction-scoped advisory lock on the key, looks for the receipt, and only
 *     then inserts, all in one transaction (PgBouncer transaction pooling safe: nothing outlives the transaction);
 *  2. the model: FiscalReceipt refuses to be created (creating hook) outside that locked scope, so a new code path
 *     that forgets the lock fails loudly in its first test;
 *  3. the database: fiscal_receipt_keys (global UNIQUE (store_id, idempotency_key) and UNIQUE (uuid)), written by a
 *     trigger, refuses a duplicate from ANY insert, also raw SQL.
 *
 * The lock lives until the OUTER transaction ends (a savepoint that is released keeps it; one that is rolled back
 * releases it). A till transaction that issues a receipt and then does more work holds the key for that time; a
 * concurrent request with the same key waits at most config('fiscal.idempotency.lock_timeout_ms', 5000) and then
 * fails with SQLSTATE 55P03 (lock_not_available), which the offline sync treats as transient (the operation comes
 * again) and the bill flow as "fiskal chek so'ralmadi" (retried). A till therefore never hangs on it.
 */
final class FiscalReceiptLock
{
    /** @var array<string, int> lock names whose insert is running now (nesting count) */
    private static array $writing = [];

    private static int $bypass = 0;

    public static function name(int $storeId, string $idempotencyKey): string
    {
        return "fiscal:{$storeId}:{$idempotencyKey}";
    }

    /**
     * The advisory lock on (store, key), inside the current transaction (the caller opens one). Uncontended: one
     * statement. Contended: waits under a local lock_timeout, restored afterwards.
     */
    public static function acquire(int $storeId, string $idempotencyKey): void
    {
        self::lock(self::name($storeId, $idempotencyKey));
    }

    /**
     * The refunds of one receipt, one at a time ("never more than is left of it" sees every other refund).
     */
    public static function acquireRefundsOf(int $originalReceiptId): void
    {
        self::lock("fiscal-refunds:{$originalReceiptId}");
    }

    private static function lock(string $name): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('FiscalReceiptLock needs an open transaction (the advisory lock is transaction-scoped).');
        }
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if ((bool) DB::selectOne('SELECT pg_try_advisory_xact_lock(hashtextextended(?, 0)) AS ok', [$name])->ok) {
            return;
        }

        // SET LOCAL only (never a session setting: PgBouncer transaction pooling, TransactionPoolingSafetyTest): it
        // ends with the transaction at the latest, and is put back right after the wait
        $timeout = max(100, (int) config('fiscal.idempotency.lock_timeout_ms', 5000));
        $previous = (string) DB::selectOne("SELECT current_setting('lock_timeout') AS t")->t;
        DB::statement("SET LOCAL lock_timeout = '{$timeout}ms'");
        // On a timeout (SQLSTATE 55P03) the caller's savepoint (DB::transaction) is rolled back, which restores
        // lock_timeout too
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$name]);
        DB::statement('SET LOCAL lock_timeout = ' . (preg_match('/^\d+(us|ms|s|min|h|d)?$/', $previous) ? "'{$previous}'" : 'DEFAULT'));
    }

    /**
     * Runs the insert of the receipt under (store, key): the model's creating hook lets exactly this one through.
     *
     * @template T
     *
     * @param  Closure(): T  $insert
     * @return T
     */
    public static function writing(int $storeId, string $idempotencyKey, Closure $insert): mixed
    {
        $name = self::name($storeId, $idempotencyKey);
        self::$writing[$name] = (self::$writing[$name] ?? 0) + 1;
        try {
            return $insert();
        } finally {
            if (--self::$writing[$name] <= 0) {
                unset(self::$writing[$name]);
            }
        }
    }

    /**
     * The model's creating hook: a receipt is created only by FiscalService, under its lock.
     */
    public static function assertMayCreate(FiscalReceipt $receipt): void
    {
        if (self::$bypass > 0) {
            return;
        }

        $name = self::name((int) $receipt->store_id, (string) $receipt->idempotency_key);
        if (!isset(self::$writing[$name]) || DB::transactionLevel() === 0) {
            throw new LogicException(
                'A fiscal receipt is created only through App\Domain\Fiscal\FiscalIssuer (FiscalService: advisory lock on the '
                . 'store and idempotency key, then the insert). Tests that need a bare row: FiscalReceiptLock::outsideIssuerForTests().'
            );
        }
    }

    /**
     * Test fixtures only: creates receipt rows without the issuer (the database still refuses a duplicate key or
     * uuid through fiscal_receipt_keys). Refused outside the test suite.
     *
     * @template T
     *
     * @param  Closure(): T  $create
     * @return T
     */
    public static function outsideIssuerForTests(Closure $create): mixed
    {
        if (!app()->runningUnitTests()) {
            throw new LogicException('FiscalReceiptLock::outsideIssuerForTests() is for the test suite only.');
        }

        self::$bypass++;
        try {
            return $create();
        } finally {
            self::$bypass--;
        }
    }
}
