<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P3-17: an order opened from a booking ("Keldi") points back at it (its deposits are the order's prepayment,
     * App\Domain\Pos\Booking\BookingAdvanceLedger); a pre-order for later (takeaway osh) carries when it is due.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('due_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booking_id');
            $table->dropColumn('due_at');
        });
    }
};
