<?php

namespace App\Services\Payment;

use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Security review F6 (and F2/F5): which Payme cashbox an HTTP Basic `Paycom:<key>` speaks for, without decrypting the
 * payment settings of every store on each (unauthenticated) request.
 *
 * Every store carries `payme_key_digest`, a keyed SHA-256 of its own Payme cashbox key (payment_settings.payme
 * .secret_key), kept by the Store model whenever payment_settings is saved, NONE when it has none, NULL for a row
 * written by code that predates the column or whose settings could not be read. Third review M1: the digest is an
 * index hint, never the source of truth. A key is matched through the index on that column and every candidate is
 * checked against its decrypted key in constant time, so a stale digest can never authorise anything; and a digest
 * left stale by code that does not keep it (a release before the column, after a code-only rollback, saving a store
 * whose owner changed the key) never drops a Payme call either:
 *  - stores whose digest is NULL are always checked too (none once the backfill has run);
 *  - a call about a transaction id also checks the stores that hold that id (storesWithKey's $transactionId: at most
 *    HOLDERS of them, found through the payments and pos_qr_payments indexes on (provider, provider_transaction_id),
 *    and only those whose digest is not this key's), so an unknown key costs at most that many decryptions and only for
 *    an id that exists — never a scan of every store;
 *  - an account method (CheckPerformTransaction, CreateTransaction) is authorised by its order's own key, and that
 *    store is then the request's (PaymeWebhookController::authorizeAccount, remember());
 *  - a store found that way has its digest repaired (remember()), and `php artisan payments:payme-digests` recomputes
 *    every digest (run at every deploy by the entrypoint, and by hand after a code rollback: docs/OPERATIONS.md).
 * GetStatement names neither an account nor an id: for a store whose digest is stale it is answered -32504 until one
 * of the calls above or the command has repaired it (Payme asks for the statement again; no money moves on it).
 *
 * The HMAC key is a fixed label, deliberately not APP_KEY: rotating APP_KEY (old keys kept in APP_PREVIOUS_KEYS for
 * the encrypted settings) must not silently orphan every store's cashbox. A Payme cashbox key is a random secret Payme
 * issues, so its digest gives nothing a guess could use; it is never serialized (Store::$hidden).
 */
final class PaymeCashbox
{
    /** The digest of a store without its own Payme key. */
    public const NONE = 'none';

    private const HMAC_KEY = 'rizqim:payme-cashbox-key:v1';

    public static function digest(?string $key): string
    {
        $key = $key === null ? '' : trim($key);

        return $key === '' ? self::NONE : hash_hmac('sha256', $key, self::HMAC_KEY);
    }

    /**
     * @param  array<string, mixed>|null  $paymentSettings
     */
    public static function digestOf(?array $paymentSettings): string
    {
        $config = $paymentSettings['payme'] ?? null;
        $value = is_array($config) ? ($config['secret_key'] ?? null) : null;

        return self::digest(is_scalar($value) ? (string) $value : null);
    }

    /**
     * Security review F2: serialises, until the caller's transaction ends, every CreateTransaction of this Payme
     * transaction id — an order's, a top-up's and a POS QR's alike — so the one that commits first owns the id and the
     * other sees it (PaymeWebhookController::createTransaction, PaymePosQrWebhook::create). Callers are inside a
     * transaction and take it before any row lock.
     */
    public static function lockTransaction(string $transactionId): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['payme-transaction:' . $transactionId]);
        }
    }

    /**
     * The ids of the stores whose own Payme cashbox key this is (one cashbox may serve several stores of a chain, each
     * saving the same key), in id order; [] for none. With $transactionId (a call about that id), the stores that hold
     * the id are checked too when their digest is not this key's (a stale digest, see the class comment).
     *
     * @return list<int>
     */
    public static function storesWithKey(string $key, ?string $transactionId = null): array
    {
        $key = trim($key);
        $digest = self::digest($key);
        if ($digest === self::NONE) {
            return [];
        }

        $ids = [];
        $candidates = Store::query()
            ->where(fn ($query) => $query->where('payme_key_digest', $digest)->orWhereNull('payme_key_digest'))
            ->whereNotNull('payment_settings')
            ->orderBy('id')
            ->get(['id', 'payment_settings', 'payme_key_digest']);
        foreach ($candidates as $store) {
            if (self::holdsKey($store, $key)) {
                $ids[] = (int) $store->id;
            }
        }

        if ($transactionId !== null && $transactionId !== '') {
            $holders = array_values(array_diff(self::holdersOf($transactionId), $ids));
            if ($holders !== []) {
                $stale = Store::query()->whereIn('id', $holders)
                    ->whereNotNull('payme_key_digest')->where('payme_key_digest', '!=', $digest)
                    ->whereNotNull('payment_settings')
                    ->orderBy('id')
                    ->get(['id', 'payment_settings', 'payme_key_digest']);
                foreach ($stale as $store) {
                    if (self::holdsKey($store, $key)) {
                        $ids[] = (int) $store->id;
                    }
                }
                sort($ids);
            }
        }

        return $ids;
    }

    /**
     * The store's decrypted key is this one (constant time); its digest is then repaired when it is not this key's.
     */
    private static function holdsKey(Store $store, string $key): bool
    {
        try {
            $own = \App\Domain\Pos\QrPayments\Providers\StoreMerchant::credential($store, 'payme', 'secret_key');
        } catch (\Throwable) {
            // Settings this install cannot decrypt (its digest is NULL: payments:payme-digests reports it): never the
            // reason every other store's Payme call fails
            return false;
        }
        if ($own === null || !hash_equals($own, $key)) {
            return false;
        }
        if ($store->getAttribute('payme_key_digest') !== self::digest($key)) {
            self::remember((int) $store->id, $key, $store->getRawOriginal('payment_settings'));
        }

        return true;
    }

    /** How many stores holding one transaction id are looked at, at most (an id is one per merchant account). */
    private const HOLDERS = 8;

    /**
     * The stores whose own cashbox holds this Payme transaction id: a STORE-owned payment's, or a POS QR's.
     *
     * @return list<int>
     */
    private static function holdersOf(string $transactionId): array
    {
        $payments = DB::table('payments')->where('provider', 'payme')->where('provider_transaction_id', $transactionId)
            ->where('merchant_scope', 'like', 'store:%')->limit(self::HOLDERS)->pluck('merchant_scope')
            ->map(fn ($scope) => (int) substr((string) $scope, 6));
        $qrs = DB::table('pos_qr_payments')->where('provider', \App\Domain\Pos\QrPayments\QrProvider::PAYME->value)
            ->where('provider_transaction_id', $transactionId)->limit(self::HOLDERS)->pluck('store_id')->map(fn ($id) => (int) $id);

        return array_slice(array_values(array_unique([...$payments->all(), ...$qrs->all()])), 0, self::HOLDERS);
    }

    /**
     * Third review M1: the store's own Payme key is $key (the caller has just checked it against the decrypted
     * settings): its digest is set to this key's when it is not. A single-row update of that column, waiting at most a
     * second for a store row a ledger transaction holds; when it cannot be written now the request is still answered
     * for the store (the caller has it), and the next call or `payments:payme-digests` writes it.
     *
     * Fourth review: $verifiedSettings is stores.payment_settings as stored (the ciphertext, Model::getRawOriginal) that
     * the key was checked against. The digest is written only while the row still holds those settings, as
     * recomputeAll() does: an owner who saved another key meanwhile had its digest written by the model hook, and a
     * late repair for the old key never overwrites it.
     */
    public static function remember(int $storeId, string $key, mixed $verifiedSettings): void
    {
        $digest = self::digest($key);
        if (!is_string($verifiedSettings) || $verifiedSettings === '') {
            return; // nothing verified to compare against: the next call or `payments:payme-digests` writes it
        }
        try {
            self::withLockTimeout('1s', function () use ($storeId, $digest, $verifiedSettings) {
                $repaired = DB::table('stores')->where('id', $storeId)
                    ->where('payment_settings', $verifiedSettings)
                    ->where(fn ($query) => $query->whereNull('payme_key_digest')->orWhere('payme_key_digest', '!=', $digest))
                    ->update(['payme_key_digest' => $digest]);
                if ($repaired > 0) {
                    \Illuminate\Support\Facades\Log::warning('stores.payme_key_digest was stale and has been repaired', ['store_id' => $storeId]);
                }
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('stores.payme_key_digest could not be repaired now', ['store_id' => $storeId, 'error' => get_class($e)]);
        }
    }

    /**
     * The digest of a stores.payment_settings value as stored (encrypted with APP_KEY, or plain JSON on an install that
     * never encrypted it), or null when it cannot be read (third review L3: left NULL, so it is still matched and
     * retried, never NONE).
     */
    public static function digestOfStored(mixed $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return self::NONE;
        }
        if (!is_string($stored)) {
            return null;
        }
        try {
            $plain = \Illuminate\Support\Facades\Crypt::decryptString($stored);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            $plain = $stored; // not encrypted (an install that never ran 2026_12_01_100000)
        }
        $settings = json_decode($plain, true);

        return is_array($settings) ? self::digestOf($settings) : null;
    }

    /**
     * Recomputes every store's digest from its settings (`payments:payme-digests`, and the migration's backfill):
     * [stores read, digests changed, stores left without a current digest (settings that could not be read, left NULL;
     * or a row a ledger transaction held, kept as it was)]. Store by store, each a
     * single-row update of that column only, waiting at most $lockTimeout for a row a ledger transaction holds.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function recomputeAll(string $lockTimeout = '5s'): array
    {
        self::assertLockTimeout($lockTimeout);
        $read = $changed = $failed = 0;
        DB::table('stores')->select(['id', 'payment_settings', 'payme_key_digest'])->orderBy('id')
            ->chunkById(200, function ($stores) use (&$read, &$changed, &$failed, $lockTimeout) {
                foreach ($stores as $store) {
                    $read++;
                    $digest = self::digestOfStored($store->payment_settings);
                    if ($digest === null) {
                        $failed++;
                        \Illuminate\Support\Facades\Log::warning('stores.payme_key_digest left NULL: the payment settings could not be read', ['store_id' => $store->id]);
                    }
                    if ($digest === $store->payme_key_digest) {
                        continue;
                    }
                    try {
                        self::withLockTimeout($lockTimeout, function () use ($store, $digest, &$changed) {
                            // Only if the settings are still the ones read (a save meanwhile wrote its own digest)
                            $changed += DB::table('stores')->where('id', $store->id)
                                ->where('payment_settings', $store->payment_settings)
                                ->update(['payme_key_digest' => $digest]);
                        });
                    } catch (\Throwable $e) {
                        $failed += $digest === null ? 0 : 1;
                        \Illuminate\Support\Facades\Log::warning('stores.payme_key_digest not recomputed for a store now', ['store_id' => $store->id, 'error' => get_class($e)]);
                    }
                }
            });

        return [$read, $changed, $failed];
    }

    /**
     * $work in a short transaction of its own that waits at most $lockTimeout for a lock (SET LOCAL: PgBouncer runs
     * php-fpm in transaction mode, so a session setting would leak to the next request on that server connection;
     * tests/Unit/TransactionPoolingSafetyTest). Whatever $work throws is rethrown after the rollback.
     *
     * Inside a caller's transaction (the migration's backfill when it runs in one, a test's RefreshDatabase) this is a
     * savepoint, and two things differ: a SET LOCAL made in a savepoint that is released lasts until the caller's
     * transaction ends, so the caller's lock_timeout is put back before the release; and a failure (a lock timeout, a
     * deadlock) is always rolled back to the savepoint, so the caller's transaction is left usable. Laravel's nested
     * DB::transaction() does not do the latter for a deadlock (it rethrows a DeadlockException without rolling back
     * to the savepoint, leaving the caller's transaction aborted), hence the savepoint handled here.
     *
     * Only DB::statement() on the hooked connection, like every other SET LOCAL in app/ (ConcurrentIndex,
     * PartitionManager::withLockRetry, FiscalReceiptLock): never the raw PDO, which RevisionMapCoverageTest refuses
     * outside its allowlist. The interval is written into the statement as a literal (SET takes no bindings), which
     * is why it must be a plain interval (lockTimeoutLiteral). The update in $work is a query-builder write.
     */
    private static function withLockTimeout(string $lockTimeout, \Closure $work): mixed
    {
        self::assertLockTimeout($lockTimeout);
        $db = DB::connection();
        if ($db->getDriverName() !== 'pgsql') {
            return $db->transaction(fn () => $work());
        }
        if ($db->transactionLevel() === 0) {
            return $db->transaction(function () use ($db, $lockTimeout, $work) {
                $db->statement('SET LOCAL lock_timeout = ' . self::lockTimeoutLiteral($lockTimeout));

                return $work();
            });
        }

        // The caller's own value (current_setting prints it as a plain interval: `0`, `7s`, `1500ms`, `1min`)
        $before = self::lockTimeoutLiteral((string) $db->selectOne("SELECT current_setting('lock_timeout') AS value")->value);
        $db->statement('SAVEPOINT payme_cashbox_lock_timeout');
        try {
            $db->statement('SET LOCAL lock_timeout = ' . self::lockTimeoutLiteral($lockTimeout));
            $result = $work();
            $db->statement('SET LOCAL lock_timeout = ' . $before);
            $db->statement('RELEASE SAVEPOINT payme_cashbox_lock_timeout');

            return $result;
        } catch (\Throwable $e) {
            // Also undoes the SET LOCAL made in the savepoint
            $db->statement('ROLLBACK TO SAVEPOINT payme_cashbox_lock_timeout');
            $db->statement('RELEASE SAVEPOINT payme_cashbox_lock_timeout');

            throw $e;
        }
    }

    /** A lock_timeout is a plain interval (`5s`, `500ms`, `1min`, `0`): it is written into the SET statement. */
    private static function assertLockTimeout(string $lockTimeout): void
    {
        if (preg_match('/^[0-9]{1,9}(us|ms|s|min|h|d)?$/', $lockTimeout) !== 1) {
            throw new \InvalidArgumentException("Not a lock_timeout: {$lockTimeout}");
        }
    }

    /** The quoted SQL literal of a checked lock_timeout (digits and a unit only, so nothing to escape). */
    private static function lockTimeoutLiteral(string $lockTimeout): string
    {
        self::assertLockTimeout($lockTimeout);

        return "'{$lockTimeout}'";
    }
}
