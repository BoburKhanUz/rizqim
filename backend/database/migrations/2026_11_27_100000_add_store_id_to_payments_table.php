<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The backfill commits per batch, and every DDL step takes its lock in a short transaction of its own
    public $withinTransaction = false;

    private const BATCH = 20000;

    // A DDL step that cannot get its lock in this time fails (the migration is run again) instead of queueing every
    // payment insert behind it
    private const LOCK_TIMEOUT = '2s';

    // How long the backfill waits for a payment row a till holds locked (a refund, a Payme perform in progress)
    private const ROW_LOCK_TIMEOUT = '5s';

    /**
     * Wave M4 stage 2 (docs/scale/M4_DESIGN.md decision A): payments carry their store, so the tenant scope and the
     * stage 3 row-level-security policy can filter them directly (refunds address a payment by uuid, webhooks by the
     * provider's transaction id).
     *
     * The store of a payment is its parent's: orders.store_id, or, for a store's Payme top-up (order_id NULL,
     * payload.account.order_id = "topup_<uuid>"), store_topup_requests.store_id. That parent is the source of truth:
     * - the application sets store_id explicitly at every creation site (the model holds the right value without a
     *   re-read, and a writer never depends on the trigger alone);
     * - BEFORE INSERT and BEFORE UPDATE OF order_id/store_id triggers set it from the parent for every other writer
     *   (old code during a deploy, raw SQL, the load-test kit) and overwrite a value that differs from the parent's
     *   (with a WARNING in the server log): no payment insert fails because of this column, and the column always
     *   equals the parent's store. No code path changes orders.store_id or re-parents a payment.
     *
     * Steps (each one short; re-running the migration resumes where it stopped):
     * 1. nullable column (metadata only), functions, triggers: brief ACCESS EXCLUSIVE, under lock_timeout;
     * 2. backfill in id ranges of 20 000 (about 0.7 s each on 1 M rows under load), one commit each; rows a till
     *    holds locked are skipped (SKIP LOCKED: a batch never waits on a till's row, so it can never deadlock one) and
     *    then taken one by one, each waiting for its till (at most 5 s);
     * 3. foreign key to stores and CHECK (store_id IS NOT NULL), both NOT VALID (brief ACCESS EXCLUSIVE), VALIDATE
     *    (SHARE UPDATE EXCLUSIVE: inserts and updates go on), SET NOT NULL (brief ACCESS EXCLUSIVE, no scan: it uses
     *    the validated check), the check dropped.
     * A payment whose parent store cannot be found is reported (laravel.log and stderr), never guessed: the column then
     * stays nullable (only the foreign key is added) and the deploy goes on (a failing migration would put the backend
     * container into a restart loop). No writer creates such a row (every creation site has an order or a top-up
     * request).
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            if (!Schema::hasColumn('payments', 'store_id')) {
                Schema::table('payments', fn (Blueprint $table) => $table->foreignId('store_id')->nullable()->constrained()->cascadeOnDelete());
            }

            return;
        }

        $this->locked(function () {
            // Checked first: a re-run takes no ACCESS EXCLUSIVE lock for a column that is already there
            if (!Schema::hasColumn('payments', 'store_id')) {
                DB::statement('ALTER TABLE payments ADD COLUMN store_id bigint');
            }
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION payments_parent_store(p_order_id bigint, p_payload jsonb) RETURNS bigint
                LANGUAGE plpgsql STABLE AS $$
                DECLARE
                    reference text;
                    parent bigint;
                BEGIN
                    IF p_order_id IS NOT NULL THEN
                        SELECT o.store_id INTO parent FROM orders o WHERE o.id = p_order_id;
                        RETURN parent;
                    END IF;
                    -- A store's Payme top-up (PaymeWebhookController::findTarget): the reference is checked before the
                    -- cast, so a malformed one resolves to NULL instead of failing the insert
                    reference := p_payload -> 'account' ->> 'order_id';
                    IF reference ~* '^topup_[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$' THEN
                        SELECT t.store_id INTO parent FROM store_topup_requests t WHERE t.uuid = substr(reference, 7)::uuid;
                    END IF;
                    RETURN parent;
                END
                $$;

                CREATE OR REPLACE FUNCTION payments_store_id() RETURNS trigger LANGUAGE plpgsql AS $$
                DECLARE
                    parent bigint := payments_parent_store(NEW.order_id, NEW.payload);
                BEGIN
                    IF parent IS NOT NULL AND NEW.store_id IS DISTINCT FROM parent THEN
                        IF NEW.store_id IS NOT NULL THEN
                            RAISE WARNING 'payments: store_id % replaced by % (the store of order %, payment %)', NEW.store_id, parent, NEW.order_id, NEW.id;
                        END IF;
                        NEW.store_id := parent;
                    END IF;
                    RETURN NEW;
                END
                $$;

                CREATE OR REPLACE TRIGGER payments_store_id_insert BEFORE INSERT ON payments
                    FOR EACH ROW EXECUTE FUNCTION payments_store_id();
                CREATE OR REPLACE TRIGGER payments_store_id_update BEFORE UPDATE OF order_id, store_id ON payments
                    FOR EACH ROW WHEN (NEW.order_id IS DISTINCT FROM OLD.order_id OR NEW.store_id IS DISTINCT FROM OLD.store_id)
                    EXECUTE FUNCTION payments_store_id();
            SQL);
        });

        if (!$this->isNullable()) {
            return; // already complete
        }

        $missing = $this->backfill();

        // After the backfill: its updates would otherwise each take a KEY SHARE lock on the store row for the check
        $this->locked(function () use ($missing) {
            if (!$this->constraintExists('payments_store_id_foreign')) {
                DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_store_id_foreign FOREIGN KEY (store_id) REFERENCES stores (id) ON DELETE CASCADE NOT VALID');
            }
            if ($missing === [] && !$this->constraintExists('payments_store_id_not_null')) {
                DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_store_id_not_null CHECK (store_id IS NOT NULL) NOT VALID');
            }
        });
        $this->locked(fn () => DB::statement('ALTER TABLE payments VALIDATE CONSTRAINT payments_store_id_foreign'));

        if ($missing !== []) {
            Log::critical('payments.store_id: payments whose store could not be found (no order, no top-up request); the column stays nullable', ['count' => count($missing), 'payments' => array_slice($missing, 0, 100)]);
            fwrite(STDERR, 'DIQQAT: ' . count($missing) . " ta to'lovning do'koni topilmadi (buyurtma ham, to'ldirish so'rovi ham yo'q). payments.store_id NULL bo'lishi mumkin bo'lib qoldi; ro'yxat jurnalda (laravel.log), qo'lda tekshiring.\n");

            return;
        }

        $this->locked(fn () => DB::statement('ALTER TABLE payments VALIDATE CONSTRAINT payments_store_id_not_null'));
        $this->locked(function () {
            DB::statement('ALTER TABLE payments ALTER COLUMN store_id SET NOT NULL');
            DB::statement('ALTER TABLE payments DROP CONSTRAINT payments_store_id_not_null');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            if (Schema::hasColumn('payments', 'store_id')) {
                Schema::table('payments', function (Blueprint $table) {
                    $table->dropForeign(['store_id']);
                    $table->dropColumn('store_id');
                });
            }

            return;
        }

        $this->locked(function () {
            DB::statement('DROP TRIGGER IF EXISTS payments_store_id_insert ON payments');
            DB::statement('DROP TRIGGER IF EXISTS payments_store_id_update ON payments');
            DB::statement('DROP FUNCTION IF EXISTS payments_store_id()');
            DB::statement('DROP FUNCTION IF EXISTS payments_parent_store(bigint, jsonb)');
            // Its foreign key and check go with it
            DB::statement('ALTER TABLE payments DROP COLUMN IF EXISTS store_id');
        });
    }

    /**
     * The rows still without a store, in id ranges fixed at the start (rows inserted later are filled by the trigger),
     * skipping the rows a till holds locked; then each of those alone, in a transaction of its own that waits for the
     * till (holding no other row, it cannot deadlock one). Returns the ids left without a store: no parent store.
     *
     * @return list<int>
     */
    private function backfill(): array
    {
        $bounds = DB::selectOne('SELECT min(id) AS lo, max(id) AS hi FROM payments WHERE store_id IS NULL');
        if ($bounds->lo === null) {
            return [];
        }

        for ($from = (int) $bounds->lo - 1; $from < (int) $bounds->hi; $from += self::BATCH) {
            DB::transaction(fn () => DB::update(
                'UPDATE payments p SET store_id = payments_parent_store(p.order_id, p.payload)
                 WHERE p.id IN (SELECT id FROM payments WHERE id > ? AND id <= ? AND store_id IS NULL FOR UPDATE SKIP LOCKED)',
                [$from, $from + self::BATCH],
            ));
        }

        $skipped = DB::select('SELECT id FROM payments WHERE store_id IS NULL AND id <= ? AND payments_parent_store(order_id, payload) IS NOT NULL ORDER BY id', [(int) $bounds->hi]);
        foreach ($skipped as $row) {
            $this->locked(fn () => DB::update('UPDATE payments SET store_id = payments_parent_store(order_id, payload) WHERE id = ? AND store_id IS NULL', [$row->id]), self::ROW_LOCK_TIMEOUT);
        }

        return array_map('intval', DB::table('payments')->whereNull('store_id')->where('id', '<=', (int) $bounds->hi)->orderBy('id')->pluck('id')->all());
    }

    private function locked(callable $step, string $timeout = self::LOCK_TIMEOUT): void
    {
        DB::transaction(function () use ($step, $timeout) {
            DB::statement("SET LOCAL lock_timeout = '{$timeout}'");
            $step();
        });
    }

    private function constraintExists(string $name): bool
    {
        return DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = 'payments'::regclass AND conname = ?) AS e", [$name])->e;
    }

    private function isNullable(): bool
    {
        return DB::selectOne("SELECT NOT attnotnull AS n FROM pg_attribute WHERE attrelid = 'payments'::regclass AND attname = 'store_id'")->n;
    }
};
