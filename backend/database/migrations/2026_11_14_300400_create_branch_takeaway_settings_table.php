<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-34 (module `scheduled_pickup`): a branch's rules for orders picked up later ("Oldindan buyurtma"), one row per
     * branch (none: the defaults). `min_lead_minutes`: the soonest a pickup time may be from now; `slot_minutes`: the
     * step of the times offered; `max_days_ahead`: 0 = today's business day only; `prep_minutes`: how long before the
     * pickup time the kitchen gets the order when none of its dishes says more (products.wait_minutes).
     */
    public function up(): void
    {
        Schema::create('branch_takeaway_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('pickup_min_lead_minutes')->default(20);
            $table->unsignedSmallInteger('pickup_slot_minutes')->default(15);
            $table->unsignedSmallInteger('pickup_max_days_ahead')->default(1);
            $table->unsignedSmallInteger('pickup_prep_minutes')->default(15);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_takeaway_settings');
    }
};
