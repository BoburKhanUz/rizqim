<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-31: what an offline sale keeps (docs/POS_SPEC.md §5, §10.3):
 *  - orders: the device's local number (`K1-017`, unique in the store), when the device opened it, when it reached
 *    the server, and the flags a manager should see (`sync_flags`: offline price, stale menu, oversold, payment on a
 *    cancelled order, amount mismatch...);
 *  - order_items: the device's id of the line (later operations name it) and where its price came from (`online` or
 *    `offline_snapshot`);
 *  - payments: when the money was taken by the device's clock (the receipt keeps it) and when it reached the server;
 *  - menu_batches: what was sold offline beyond what the kazan held (its quantity never goes below zero);
 *  - devices: the local number prefix and what the device last said about its queue (the shift does not close while
 *    it has operations waiting);
 *  - fiscal_receipts: when the sale happened, when it is earlier than the receipt (an offline sale receipted at sync).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('local_number', 32)->nullable();
            $table->timestamp('offline_created_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->jsonb('sync_flags')->nullable();

            $table->unique(['store_id', 'local_number']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->uuid('client_line_id')->nullable();
            $table->string('price_source', 16)->nullable(); // online | offline_snapshot

            $table->index(['order_id', 'client_line_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('offline_paid_at')->nullable();
            $table->timestamp('synced_at')->nullable();
        });

        Schema::table('menu_batches', function (Blueprint $table) {
            $table->decimal('oversold_quantity', 12, 3)->default(0);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->string('sync_prefix', 8)->nullable();
            $table->unsignedBigInteger('sync_last_seq')->default(0);
            $table->unsignedInteger('sync_pending_ops')->default(0);
            $table->timestamp('sync_oldest_op_at')->nullable();
            $table->timestamp('sync_reported_at')->nullable();
            $table->integer('sync_clock_skew_seconds')->nullable();

            $table->unique(['store_id', 'sync_prefix']);
        });

        Schema::table('fiscal_receipts', function (Blueprint $table) {
            $table->timestamp('sold_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_receipts', fn (Blueprint $table) => $table->dropColumn('sold_at'));
        Schema::table('devices', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'sync_prefix']);
            $table->dropColumn(['sync_prefix', 'sync_last_seq', 'sync_pending_ops', 'sync_oldest_op_at', 'sync_reported_at', 'sync_clock_skew_seconds']);
        });
        Schema::table('menu_batches', fn (Blueprint $table) => $table->dropColumn('oversold_quantity'));
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['offline_paid_at', 'synced_at']));
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'client_line_id']);
            $table->dropColumn(['client_line_id', 'price_source']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'local_number']);
            $table->dropColumn(['local_number', 'offline_created_at', 'synced_at', 'sync_flags']);
        });
    }
};
