<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a status history row copied from order_status_logs keeps the log's id (see the next migration). A migration
     * of its own, so ADD COLUMN's exclusive lock on order_status_histories is released at once instead of being held
     * while the logs are copied (checkout and every status change write to this table).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('order_status_histories', 'order_status_log_id')) {
            Schema::table('order_status_histories', function (Blueprint $table) {
                $table->unsignedBigInteger('order_status_log_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_status_histories', 'order_status_log_id')) {
            Schema::table('order_status_histories', function (Blueprint $table) {
                $table->dropColumn('order_status_log_id');
            });
        }
    }
};
