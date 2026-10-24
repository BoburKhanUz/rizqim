<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-38: when the guest was last sent the booking confirmation (App\Domain\Pos\Booking\BookingConfirmationService) and
 * how: `telegram` or `sms`. Null: never sent. Written when the send is queued, by hand or automatically on confirming.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->string('confirmation_channel', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['confirmation_sent_at', 'confirmation_channel']);
        });
    }
};
