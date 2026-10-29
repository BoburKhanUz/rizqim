<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-24 (C4): a bill made by the guest split (`split_mode` by_guest) is one guest's: `seat_no` is that guest's
     * number (order_items.seat_no). Null on every other bill.
     */
    public function up(): void
    {
        Schema::table('order_bills', function (Blueprint $table) {
            $table->unsignedSmallInteger('seat_no')->nullable()->after('guests_share');
        });
    }

    public function down(): void
    {
        Schema::table('order_bills', function (Blueprint $table) {
            $table->dropColumn('seat_no');
        });
    }
};
