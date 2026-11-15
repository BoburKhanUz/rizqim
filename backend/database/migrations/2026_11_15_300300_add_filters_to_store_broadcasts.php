<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Review R1-2 (wave M1): the filters of an `all` ommaviy e'lon (`statuses`, `region_id`), kept on the broadcast so a
     * broadcast whose job failed before it listed its recipients can be resumed with the same audience. Null on the
     * broadcasts sent before this column (their filters are unknown: such a broadcast is not re-listed).
     */
    public function up(): void
    {
        Schema::table('store_broadcasts', function (Blueprint $table) {
            $table->jsonb('filters')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('store_broadcasts', function (Blueprint $table) {
            $table->dropColumn('filters');
        });
    }
};
