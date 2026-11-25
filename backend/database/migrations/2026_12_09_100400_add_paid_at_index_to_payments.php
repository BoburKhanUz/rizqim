<?php

use App\Support\ConcurrentIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // CREATE / DROP INDEX CONCURRENTLY cannot run inside a transaction
    public $withinTransaction = false;

    private const INDEX = 'payments_paid_at_index';

    /**
     * Review (query cost): SettlementRecoveryService::unbookedLatePayments (the settlement_failures check's `unmarked`
     * finding, on every monitoring run, and the Today page's settlement_unbooked group) looks only at payments paid
     * in the last 30 days. Without an index that window still read every payment (and every delivered order); this
     * partial index serves it: paid payments by paid time. paid_at is written once, with the paid status, so it costs
     * one index entry per paid payment. Built CONCURRENTLY (writes go on); an INVALID leftover of an interrupted
     * build is rebuilt. payments is not partitioned. Touches no cache.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (ConcurrentIndex::isInvalid(self::INDEX)) {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . self::INDEX);
        }
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS ' . self::INDEX . " ON payments (paid_at) WHERE status = 'paid'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . self::INDEX);
    }
};
