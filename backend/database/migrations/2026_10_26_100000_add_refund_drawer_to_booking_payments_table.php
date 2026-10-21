<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3 integration fix: where a booking deposit was refunded (BookingDepositService::refund): the terminal and the shift
 * open on it (a cash refund is paid from that shift's drawer). The shift's X/Z
 * reads booking_payments for deposits taken and refunded (ShiftReport): the deposit table stays the one record of
 * that money, nothing is copied into cash_movements. Nullable: refunds made before this and refunds from the web
 * panel (no drawer) keep null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->foreignId('refunded_device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->foreignId('refunded_shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();

            $table->index(['refunded_shift_id']);
        });
    }

    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->dropIndex(['refunded_shift_id']);
            $table->dropConstrainedForeignId('refunded_shift_id');
            $table->dropConstrainedForeignId('refunded_device_id');
        });
    }
};
