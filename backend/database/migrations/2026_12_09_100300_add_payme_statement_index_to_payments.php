<?php

use App\Support\ConcurrentIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // CREATE / DROP INDEX CONCURRENTLY cannot run inside a transaction
    public $withinTransaction = false;

    private const INDEX = 'payments_payme_statement_index';

    /**
     * Fourth review: since 2026_12_09_100100 the unique index on payments is (provider, provider_transaction_id,
     * merchant_scope), so Payme GetStatement (provider, merchant_scope IN (...), a range of Payme's `time` in the
     * payload) had no index path and read every Payme payment. This partial index serves it: the cashbox's merchant
     * accounts, then the time range. Its expression is PaymeWebhookController::STATEMENT_TIME (a literal here: a
     * migration never follows later code); at most 18 digits are cast, so no `time` a caller sends can make the insert
     * of a payment fail. Built CONCURRENTLY (writes go on); an INVALID leftover of an interrupted build is rebuilt.
     * payments is not partitioned. Touches no cache.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (ConcurrentIndex::isInvalid(self::INDEX)) {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . self::INDEX);
        }
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS ' . self::INDEX . " ON payments (merchant_scope, (CASE WHEN payload->>'time' ~ '^[0-9]{1,18}$' THEN CAST(payload->>'time' AS BIGINT) END)) WHERE provider = 'payme' AND provider_transaction_id IS NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . self::INDEX);
    }
};
