<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Review C1 (P6 wave 4): a promo code dropped by the charges recalculation (the dishes fell under its minimum) leaves a
 * plain note for the till and the waiter (`orders.promo_note`, shown as the order's `promo_note`); applying a code,
 * taking it off or a discount by hand clears it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('promo_note', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('promo_note');
        });
    }
};
