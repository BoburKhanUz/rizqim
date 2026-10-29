<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-31 Ko'rsatkichlar: "which stores were active at the start of each month" reads the last status change of every
     * store before a moment (App\Services\Admin\Metrics\StoreActivity). A foreign key makes no index in PostgreSQL, so
     * the history of one store was a scan of the whole table. The conversion funnel reads the stage moves of many
     * applications from the audit log by subject (model_type, model_id), which had no index either.
     */
    public function up(): void
    {
        Schema::table('store_status_logs', function (Blueprint $table) {
            $table->index(['store_id', 'id'], 'store_status_logs_store_id_id_index');
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['model_type', 'model_id'], 'audit_logs_model_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_model_index');
        });
        Schema::table('store_status_logs', function (Blueprint $table) {
            $table->dropIndex('store_status_logs_store_id_id_index');
        });
    }
};
