<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-26 (C6): the branch's table booking rules, one row per branch (none: nothing changes, the choyxona's room
     * bookings keep their 5 hour default slot and no lead time). Slots by the kind of the place booked (a null slot
     * keeps the branch's `booking_slot_minutes`); the least time ahead a booking may be made and the furthest; an
     * optional deposit (the existing booking deposit and its advance receipt: `deposit_mode` off, flat per booking
     * or per guest, from a party of `deposit_from_guests`, and whether the booking waits for it to be confirmed).
     */
    public function up(): void
    {
        Schema::create('branch_booking_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('table_slot_minutes')->default(120);
            $table->unsignedSmallInteger('room_slot_minutes')->nullable();
            $table->unsignedSmallInteger('tapchan_slot_minutes')->nullable();
            $table->unsignedSmallInteger('table_min_advance_minutes')->default(0);
            $table->unsignedSmallInteger('room_min_advance_minutes')->default(0);
            $table->unsignedSmallInteger('tapchan_min_advance_minutes')->default(0);
            $table->unsignedSmallInteger('max_advance_days')->nullable();
            $table->string('deposit_mode', 16)->default('off'); // App\Domain\Pos\Booking\BookingDepositMode
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('deposit_from_guests')->default(1);
            $table->boolean('deposit_required_to_confirm')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_booking_rules');
    }
};
