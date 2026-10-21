<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3 integration fix (R20): when the owner was told that this POS hall order has been open longer than the branch's
 * `alerts.open_room_hours` (pos:alert-long-open), so each order is reported once. Null: not reported (yet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('long_open_alerted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('long_open_alerted_at');
        });
    }
};
