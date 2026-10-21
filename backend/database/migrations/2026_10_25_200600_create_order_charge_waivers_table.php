<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-12: a charge line taken off one order (R6, R20: the guest objected to the service charge, the manager waived the
 * room fee or the minimum spend), with the reason and who approved it. Restoring the charge deletes the row (both are
 * in the audit log). The order's amount columns already reflect waivers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_charge_waivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('charge', 24); // service_charge, place_fee, room_fee, min_spend_topup
            $table->string('reason', 255);
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_id', 'charge']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_charge_waivers');
    }
};
