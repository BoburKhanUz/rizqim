<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-12: a POS order's place and people (hall, table/room/tapchan, waiter, the terminal that opened it), the
 * optimistic lock every POS mutation checks (`version`, 409 when stale), and two more charge lines next to
 * service_charge_amount and place_fee_amount: the room fee (room tariff by day type) and the minimum spend top-up
 * (docs/POS_SPEC.md §10.3, R2–R4). All nullable or defaulted: existing orders read as before (no table, version 0,
 * no room fee).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('hall_id')->nullable()->constrained('halls')->nullOnDelete();
            $table->foreignId('table_id')->nullable()->constrained('dining_tables')->nullOnDelete();
            $table->foreignId('waiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->unsignedInteger('version')->default(0);
            $table->decimal('room_fee_amount', 12, 2)->default(0);
            $table->decimal('min_spend_topup_amount', 12, 2)->default(0);

            $table->index(['table_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['table_id', 'status']);
            $table->dropConstrainedForeignId('hall_id');
            $table->dropConstrainedForeignId('table_id');
            $table->dropConstrainedForeignId('waiter_id');
            $table->dropConstrainedForeignId('device_id');
            $table->dropColumn(['version', 'room_fee_amount', 'min_spend_topup_amount']);
        });
    }
};
