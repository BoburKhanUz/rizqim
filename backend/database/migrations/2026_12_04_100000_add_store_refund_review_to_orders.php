<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // CREATE/DROP INDEX CONCURRENTLY cannot run inside a transaction
    public $withinTransaction = false;

    private const INDEX = 'orders_store_refund_review_at_index';

    /**
     * Wave P (forged provider cancels): since when a provider refund of a delivered STORE-owned order (a Payme
     * CancelTransaction signed with the store's own key) waits for a manager's decision before RIZQIM's commission is
     * reversed (App\Services\Payment\StoreRefundReview). Null: nothing waits. The partial index keeps the Bugun
     * "Diqqat talab qiladi" lookup off the table.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'store_refund_review_at')) {
            Schema::table('orders', fn (Blueprint $table) => $table->timestamp('store_refund_review_at')->nullable());
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS ' . self::INDEX . ' ON orders (store_refund_review_at) WHERE store_refund_review_at IS NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS ' . self::INDEX);
        }

        if (Schema::hasColumn('orders', 'store_refund_review_at')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('store_refund_review_at'));
        }
    }
};
