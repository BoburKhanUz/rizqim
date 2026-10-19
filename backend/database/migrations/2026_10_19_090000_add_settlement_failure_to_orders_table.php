<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // CREATE/DROP INDEX CONCURRENTLY cannot run inside a transaction; each ADD COLUMN commits (and unlocks) on its own
    public $withinTransaction = false;

    private const INDEX = 'orders_settlement_failed_at_index';

    /**
     * A delivered order whose ledger booking failed (SettlementRecoveryService): when it first failed, the last error
     * and how many bookings failed so far. The partial index keeps RetryFailedSettlementsJob's lookup off the table.
     */
    public function up(): void
    {
        $columns = [
            'settlement_failed_at' => fn (Blueprint $table) => $table->timestamp('settlement_failed_at')->nullable(),
            'settlement_error' => fn (Blueprint $table) => $table->string('settlement_error', 500)->nullable(),
            'settlement_attempts' => fn (Blueprint $table) => $table->unsignedSmallInteger('settlement_attempts')->default(0),
        ];

        foreach ($columns as $column => $add) {
            if (!Schema::hasColumn('orders', $column)) {
                Schema::table('orders', $add);
            }
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS ' . self::INDEX . ' ON orders (settlement_failed_at) WHERE settlement_failed_at IS NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . self::INDEX);
        }

        foreach (['settlement_failed_at', 'settlement_error', 'settlement_attempts'] as $column) {
            if (Schema::hasColumn('orders', $column)) {
                Schema::table('orders', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
