<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-37: a discount given at the till (POST /pos/orders/{uuid}/discount). `discount_kind` percent|amount (null: none),
 * `discount_value` what was typed (10 for 10 %, 50000.00 for 50 000 so'm), `discount_reason`, `discount_by` who gave
 * it. `orders.discount` stays the computed amount the money code reads (a percent is recomputed with every line
 * change, PosChargesCalculator).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('discount_kind', 16)->nullable();
            $table->decimal('discount_value', 12, 2)->nullable();
            $table->string('discount_reason', 255)->nullable();
            $table->foreignId('discount_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_by');
            $table->dropColumn(['discount_kind', 'discount_value', 'discount_reason']);
        });
    }
};
