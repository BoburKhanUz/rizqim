<?php

use App\Support\ConcurrentIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The backfill commits per batch, the index is built CONCURRENTLY, and every DDL step takes its lock in a short
    // transaction of its own
    public $withinTransaction = false;

    private const BATCH = 20000;

    // How long the backfill waits for a payment row a till holds locked (a refund, a Payme perform in progress)
    private const ROW_LOCK_TIMEOUT = '5s';

    private const OLD = 'payments_provider_provider_transaction_id_unique';

    private const INDEX = 'payments_provider_scope_transaction_unique';

    /**
     * Security review F5: a provider's transaction id is only meaningful within the merchant account that issued it (the
     * platform's Click service / Payme cashbox / Paylov account, or a restaurant's own), but `payments` made
     * (provider, provider_transaction_id) unique across all of them. A store that collects into its own account knows the
     * key that signs its callbacks, so it could record a guessed future Click id N on its own order; Click's real Complete
     * with N for another merchant's order then found "a payment of another order" and was answered -9, and Click gave the
     * customer's money back. Payme ids of another cashbox were an existence oracle in the same way (F4).
     *
     * `payments.merchant_scope` names that account: `platform`, or `store:<store id>` (an order paid into its store's own
     * account, payment_owner other than PLATFORM, the rule of App\Services\Payment\OrderMerchantAccount::isStore; a
     * payment without an order is a top-up, the platform's). It is derived from the payment's order in the database
     * (payments_merchant_scope(), SECURITY DEFINER like payments_parent_store, so row-level security never hides the
     * order), by BEFORE INSERT / UPDATE OF order_id triggers, for every writer: the application sets the same value
     * itself (App\Models\Payment::merchantScopeOf), and old code during a deploy, raw SQL or the load-test kit get it from
     * the database. The identity becomes unique per (provider, merchant_scope, provider_transaction_id).
     *
     * Steps (each one short; re-running the migration resumes where it stopped), as 2026_11_27_100000:
     * 1. nullable column (metadata only, brief ACCESS EXCLUSIVE), function (no lock), triggers (brief SHARE ROW
     *    EXCLUSIVE); every table lock in a transaction of its own, waiting at most min(500 ms, deadlock_timeout / 2);
     * 2. backfill in id ranges of 20 000, one commit each; rows a till holds locked are skipped (SKIP LOCKED) and then
     *    taken one by one, each waiting for its till (at most 5 s);
     * 3. CHECK (merchant_scope IS NOT NULL) NOT VALID (brief ACCESS EXCLUSIVE), VALIDATEd (SHARE UPDATE EXCLUSIVE:
     *    writes go on), SET NOT NULL (no scan), the check dropped;
     * 4. the new unique index built CONCURRENTLY (it holds: the old constraint is stricter); an INVALID leftover of an
     *    interrupted build is rebuilt;
     * 5. the old unique constraint dropped (brief ACCESS EXCLUSIVE). Until then the old, stricter rule still holds.
     * payments is not partitioned. Touches no cache.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            if (!Schema::hasColumn('payments', 'merchant_scope')) {
                Schema::table('payments', function (Blueprint $table) {
                    $table->string('merchant_scope', 40)->default('platform');
                });
                Schema::table('payments', function (Blueprint $table) {
                    $table->dropUnique(self::OLD);
                    $table->unique(['provider', 'merchant_scope', 'provider_transaction_id'], self::INDEX);
                });
            }

            return;
        }

        if (!Schema::hasColumn('payments', 'merchant_scope')) {
            $this->locked(fn () => DB::statement('ALTER TABLE payments ADD COLUMN merchant_scope varchar(40)'));
        }
        // No table lock; relations schema-qualified and a fixed search_path, as payments_parent_store (stage 3)
        $schema = '"' . str_replace('"', '""', (string) DB::selectOne('SELECT current_schema() AS s')->s) . '"';
        DB::unprepared(str_replace('{schema}', $schema, <<<'SQL'
            CREATE OR REPLACE FUNCTION {schema}.payments_merchant_scope(p_order_id bigint) RETURNS varchar
            LANGUAGE sql STABLE SECURITY DEFINER SET search_path = pg_catalog, pg_temp AS $$
                SELECT COALESCE(
                    (SELECT CASE WHEN o.payment_owner IS DISTINCT FROM 'PLATFORM' THEN 'store:' || o.store_id ELSE 'platform' END
                       FROM {schema}.orders o WHERE o.id = p_order_id),
                    'platform')
            $$;

            CREATE OR REPLACE FUNCTION {schema}.payments_set_merchant_scope() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                scope varchar := {schema}.payments_merchant_scope(NEW.order_id);
            BEGIN
                IF NEW.merchant_scope IS DISTINCT FROM scope THEN
                    IF NEW.merchant_scope IS NOT NULL THEN
                        RAISE WARNING 'payments: merchant_scope % replaced by % (order %, payment %)', NEW.merchant_scope, scope, NEW.order_id, NEW.id;
                    END IF;
                    NEW.merchant_scope := scope;
                END IF;
                RETURN NEW;
            END
            $$;
        SQL));
        // Both take SHARE ROW EXCLUSIVE. The update trigger skips a NULL being filled (the backfill sets the same value)
        $this->locked(fn () => DB::unprepared(<<<'SQL'
            CREATE OR REPLACE TRIGGER payments_merchant_scope_insert BEFORE INSERT ON payments
                FOR EACH ROW EXECUTE FUNCTION payments_set_merchant_scope();
            CREATE OR REPLACE TRIGGER payments_merchant_scope_update BEFORE UPDATE OF order_id, merchant_scope ON payments
                FOR EACH ROW WHEN (NEW.order_id IS DISTINCT FROM OLD.order_id OR (OLD.merchant_scope IS NOT NULL AND NEW.merchant_scope IS DISTINCT FROM OLD.merchant_scope))
                EXECUTE FUNCTION payments_set_merchant_scope();
        SQL));

        if ($this->isNullable()) {
            $this->backfill();

            if (!$this->constraintExists('payments_merchant_scope_not_null')) {
                $this->locked(fn () => DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_merchant_scope_not_null CHECK (merchant_scope IS NOT NULL) NOT VALID'));
            }
            $this->locked(fn () => DB::statement('ALTER TABLE payments VALIDATE CONSTRAINT payments_merchant_scope_not_null'));
            $this->locked(function () {
                DB::statement('ALTER TABLE payments ALTER COLUMN merchant_scope SET NOT NULL');
                DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_merchant_scope_not_null');
            });
        }

        if (ConcurrentIndex::isInvalid(self::INDEX)) {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . self::INDEX);
        }
        DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS ' . self::INDEX . ' ON payments (provider, merchant_scope, provider_transaction_id)');

        if ($this->constraintExists(self::OLD)) {
            $this->locked(fn () => DB::statement('ALTER TABLE payments DROP CONSTRAINT ' . self::OLD));
        }
    }

    /**
     * Refused (nothing changed) while one transaction id is recorded in two merchant accounts: the old rule cannot hold
     * them both, and which one to drop is a decision about money, never a rollback's.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            if (Schema::hasColumn('payments', 'merchant_scope')) {
                Schema::table('payments', function (Blueprint $table) {
                    $table->dropUnique(self::INDEX);
                    $table->unique(['provider', 'provider_transaction_id'], self::OLD);
                });
                Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('merchant_scope'));
            }

            return;
        }

        if (!$this->constraintExists(self::OLD)) {
            $shared = DB::selectOne('SELECT count(*) AS n FROM (SELECT 1 FROM payments WHERE provider_transaction_id IS NOT NULL GROUP BY provider, provider_transaction_id HAVING count(*) > 1) d')->n;
            if ((int) $shared > 0) {
                throw new RuntimeException("payments: {$shared} ta tranzaksiya raqami bir nechta merchant hisobida yozilgan; eski (provider, provider_transaction_id) cheklovini qaytarib bo'lmaydi. Hech narsa o'zgartirilmadi.");
            }
            if (ConcurrentIndex::isInvalid(self::OLD)) {
                DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . self::OLD);
            }
            DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS ' . self::OLD . ' ON payments (provider, provider_transaction_id)');
            $this->locked(fn () => DB::statement('ALTER TABLE payments ADD CONSTRAINT ' . self::OLD . ' UNIQUE USING INDEX ' . self::OLD));
        }
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . self::INDEX);
        $this->locked(function () {
            DB::statement('LOCK TABLE payments IN ACCESS EXCLUSIVE MODE');
            DB::statement('DROP TRIGGER IF EXISTS payments_merchant_scope_insert ON payments');
            DB::statement('DROP TRIGGER IF EXISTS payments_merchant_scope_update ON payments');
            DB::statement('DROP FUNCTION IF EXISTS payments_set_merchant_scope()');
            DB::statement('DROP FUNCTION IF EXISTS payments_merchant_scope(bigint)');
            DB::statement('ALTER TABLE payments DROP COLUMN IF EXISTS merchant_scope');
        });
    }

    private function backfill(): void
    {
        $bounds = DB::selectOne('SELECT min(id) AS lo, max(id) AS hi FROM payments WHERE merchant_scope IS NULL');
        if ($bounds->lo === null) {
            return;
        }

        for ($from = (int) $bounds->lo - 1; $from < (int) $bounds->hi; $from += self::BATCH) {
            DB::transaction(fn () => DB::update(
                'UPDATE payments p SET merchant_scope = payments_merchant_scope(p.order_id)
                 WHERE p.id IN (SELECT id FROM payments WHERE id > ? AND id <= ? AND merchant_scope IS NULL FOR UPDATE SKIP LOCKED)',
                [$from, $from + self::BATCH],
            ));
        }

        foreach (DB::select('SELECT id FROM payments WHERE merchant_scope IS NULL AND id <= ? ORDER BY id', [(int) $bounds->hi]) as $row) {
            $this->locked(fn () => DB::update('UPDATE payments SET merchant_scope = payments_merchant_scope(order_id) WHERE id = ? AND merchant_scope IS NULL', [$row->id]), self::ROW_LOCK_TIMEOUT);
        }
    }

    /**
     * $step in a transaction of its own, waiting at most $timeout for a lock (default: half of deadlock_timeout, at
     * most 500 ms).
     */
    private function locked(callable $step, ?string $timeout = null): void
    {
        $timeout ??= $this->lockTimeout();
        DB::transaction(function () use ($step, $timeout) {
            DB::statement("SET LOCAL lock_timeout = '{$timeout}'");
            $step();
        });
    }

    private function lockTimeout(): string
    {
        $deadlock = (int) DB::selectOne("SELECT setting::int AS ms FROM pg_settings WHERE name = 'deadlock_timeout'")->ms;

        return max(10, min(500, intdiv($deadlock, 2))) . 'ms';
    }

    private function constraintExists(string $name): bool
    {
        return DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'payments'::regclass AND conname = ?) AS e", [$name])->e;
    }

    private function isNullable(): bool
    {
        return DB::selectOne("SELECT NOT attnotnull AS n FROM pg_attribute WHERE attrelid = 'payments'::regclass AND attname = 'merchant_scope'")->n;
    }
};
