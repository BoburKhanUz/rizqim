<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The one thing only order_status_logs recorded, so the history alone describes each transition.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('order_status_histories', 'from_status')) {
            Schema::table('order_status_histories', function (Blueprint $table) {
                $table->string('from_status')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_status_histories', 'from_status')) {
            Schema::table('order_status_histories', function (Blueprint $table) {
                $table->dropColumn('from_status');
            });
        }
    }
};
