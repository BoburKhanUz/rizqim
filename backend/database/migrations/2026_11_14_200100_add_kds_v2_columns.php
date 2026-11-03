<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-33 (KDS v2, docs/POS_SPEC.md "KDS v2"):
     *  - `kitchen_stations.target_seconds`: the station's time target for a line (sent → ready); on an `expo` station the
     *    assembly target (the last station ready → assembled). Null: no target;
     *  - `products.kds_target_seconds`: a dish's own target, over its station's (null: the station's);
     *  - `orders.assembled_at` / `assembled_by`: the expo station bumped the order assembled (module `kds_expo`); null
     *    while it waits for assembly, cleared when a line goes back to the kitchen or a new line is bumped;
     *  - an index on `order_items.sent_at` for the prep time report (lines by the time they reached the kitchen).
     */
    public function up(): void
    {
        Schema::table('kitchen_stations', function (Blueprint $table) {
            $table->unsignedInteger('target_seconds')->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('kds_target_seconds')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('assembled_at')->nullable();
            $table->foreignId('assembled_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->index('sent_at', 'order_items_sent_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_sent_at_index');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assembled_by');
            $table->dropColumn('assembled_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('kds_target_seconds');
        });

        Schema::table('kitchen_stations', function (Blueprint $table) {
            $table->dropColumn('target_seconds');
        });
    }
};
