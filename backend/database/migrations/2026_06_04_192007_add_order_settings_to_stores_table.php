<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->time('open_time')->default('09:00:00')->after('is_open');
            $table->time('close_time')->default('23:00:00')->after('open_time');
            $table->decimal('min_order_amount', 12, 2)->default(0)->after('close_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['open_time', 'close_time', 'min_order_amount']);
        });
    }
};
