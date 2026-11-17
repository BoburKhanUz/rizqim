<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    // The backfill commits in batches (a big table is never locked for the whole copy)
    public $withinTransaction = false;

    private const BATCH = 20000;

    /**
     * Wave M3, stage A2 (docs/scale/M3_DESIGN.md section 4): fiscal_receipts is partitioned by month, so its uniques
     * are partition-local and the database alone no longer stops a second receipt under the same (store,
     * idempotency key) or the same uuid in another month. fiscal_receipt_keys is a small, NOT partitioned, never
     * archived table with the global uniques: a BEFORE INSERT trigger on fiscal_receipts writes one row per receipt in
     * the same transaction, so ANY insert (the model, the query builder, raw SQL, a future code path that forgets
     * FiscalService's advisory lock) of a used key or uuid fails with a unique violation. A BEFORE UPDATE trigger
     * refuses changing a receipt's id, store, key or uuid and keeps receipt_created_at in step with created_at.
     *
     * The keys of receipts in a detached (archived) month stay here: a key is never reused, and FiscalService tells an
     * archived original receipt from a missing one.
     *
     * Note for db:partitions:convert --rollback (PartitionConverter): the plain table it swaps back has its own global
     * uniques but not this trigger; converting again must re-create the trigger (run this migration's up() body).
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::transaction(function () {
            DB::statement("SET LOCAL lock_timeout = '5s'");
            DB::statement('CREATE TABLE IF NOT EXISTS fiscal_receipt_keys (
                store_id bigint NOT NULL,
                idempotency_key varchar(128) NOT NULL,
                uuid uuid NOT NULL,
                receipt_id bigint NOT NULL,
                receipt_created_at timestamp(0) without time zone NOT NULL,
                CONSTRAINT fiscal_receipt_keys_pkey PRIMARY KEY (store_id, idempotency_key),
                CONSTRAINT fiscal_receipt_keys_uuid_unique UNIQUE (uuid)
            )');
            DB::statement("COMMENT ON TABLE fiscal_receipt_keys IS 'Global uniqueness of fiscal_receipts (partitioned): written by trigger fiscal_receipt_keys_insert, never archived'");

            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION fiscal_receipt_keys_insert() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    INSERT INTO fiscal_receipt_keys (store_id, idempotency_key, uuid, receipt_id, receipt_created_at)
                    VALUES (NEW.store_id, NEW.idempotency_key, NEW.uuid, NEW.id, NEW.created_at);
                    RETURN NEW;
                END
                $$;

                CREATE OR REPLACE FUNCTION fiscal_receipt_keys_update() RETURNS trigger LANGUAGE plpgsql AS $$
                BEGIN
                    IF NEW.id IS DISTINCT FROM OLD.id OR NEW.store_id IS DISTINCT FROM OLD.store_id
                        OR NEW.idempotency_key IS DISTINCT FROM OLD.idempotency_key OR NEW.uuid IS DISTINCT FROM OLD.uuid THEN
                        RAISE EXCEPTION 'fiscal_receipts: id, store_id, idempotency_key and uuid of a fiscal receipt never change (receipt %)', OLD.id
                            USING ERRCODE = 'integrity_constraint_violation';
                    END IF;
                    IF NEW.created_at IS DISTINCT FROM OLD.created_at THEN
                        UPDATE fiscal_receipt_keys SET receipt_created_at = NEW.created_at
                        WHERE store_id = OLD.store_id AND idempotency_key = OLD.idempotency_key;
                    END IF;
                    RETURN NEW;
                END
                $$;
            SQL);
            // On the partitioned parent: cloned to every partition, also to months attached later. A row moved to
            // another month by an UPDATE of created_at fires BEFORE INSERT on the destination and is refused.
            DB::statement('DROP TRIGGER IF EXISTS fiscal_receipt_keys_insert ON fiscal_receipts');
            DB::statement('CREATE TRIGGER fiscal_receipt_keys_insert BEFORE INSERT ON fiscal_receipts FOR EACH ROW EXECUTE FUNCTION fiscal_receipt_keys_insert()');
            DB::statement('DROP TRIGGER IF EXISTS fiscal_receipt_keys_update ON fiscal_receipts');
            DB::statement('CREATE TRIGGER fiscal_receipt_keys_update BEFORE UPDATE OF id, store_id, idempotency_key, uuid, created_at ON fiscal_receipts FOR EACH ROW EXECUTE FUNCTION fiscal_receipt_keys_update()');
        });

        // Receipts written before the trigger: in id order, one commit per batch, set-based (rows inserted meanwhile
        // already have their key; a re-run inserts nothing). The earliest receipt of a key keeps it; a later duplicate
        // (impossible before M3, the uniques were global) is only reported, never deleted: a fiscal receipt is a legal
        // document. Each batch answers with the receipts that got no key row and do not already own one.
        $last = 0;
        $duplicates = [];
        while (($upper = DB::selectOne('SELECT max(id) AS id FROM (SELECT id FROM fiscal_receipts WHERE id > ? ORDER BY id LIMIT ' . self::BATCH . ') b', [$last])->id) !== null) {
            $duplicates = array_merge($duplicates, DB::transaction(fn () => DB::select(
                'WITH inserted AS (
                    INSERT INTO fiscal_receipt_keys (store_id, idempotency_key, uuid, receipt_id, receipt_created_at)
                    SELECT store_id, idempotency_key, uuid, id, created_at FROM fiscal_receipts WHERE id > ? AND id <= ? ORDER BY id
                    ON CONFLICT DO NOTHING RETURNING receipt_id
                )
                SELECT r.id, r.store_id, r.idempotency_key, r.uuid FROM fiscal_receipts r
                WHERE r.id > ? AND r.id <= ? AND r.id NOT IN (SELECT receipt_id FROM inserted)
                  AND NOT EXISTS (SELECT 1 FROM fiscal_receipt_keys k WHERE k.store_id = r.store_id AND k.idempotency_key = r.idempotency_key AND k.receipt_id = r.id)',
                [$last, $upper, $last, $upper],
            )));
            $last = (int) $upper;
        }

        if ($duplicates !== []) {
            Log::critical('fiscal_receipt_keys: receipts sharing a key or uuid with an earlier receipt (duplicates, kept, check by hand)', ['count' => count($duplicates), 'receipts' => array_slice($duplicates, 0, 100)]);
            fwrite(STDERR, 'DIQQAT: ' . count($duplicates) . " ta fiskal chek boshqa chek bilan bir xil kalit yoki uuid ga ega (takror). Ular o'chirilmadi; ro'yxat jurnalda (laravel.log), qo'lda tekshiring.\n");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::transaction(function () {
            DB::statement("SET LOCAL lock_timeout = '5s'");
            DB::statement('DROP TRIGGER IF EXISTS fiscal_receipt_keys_insert ON fiscal_receipts');
            DB::statement('DROP TRIGGER IF EXISTS fiscal_receipt_keys_update ON fiscal_receipts');
            DB::statement('DROP FUNCTION IF EXISTS fiscal_receipt_keys_insert()');
            DB::statement('DROP FUNCTION IF EXISTS fiscal_receipt_keys_update()');
            DB::statement('DROP TABLE IF EXISTS fiscal_receipt_keys');
        });
    }
};
