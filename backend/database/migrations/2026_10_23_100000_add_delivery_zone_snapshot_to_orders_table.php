<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-32: a delivery order priced from a delivery zone keeps what the zone said at checkout (which zone, its name, its
 * price and minimum order), so the order's history stays right when the owner later edits or deletes the zone. The fee
 * actually charged stays in `delivery_price`. All null on pickup, hall orders and stores without zones (radius rule).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('delivery_zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();
            $table->string('delivery_zone_name')->nullable();
            // The zone's own price; `delivery_price` is 0 when its free-delivery threshold was reached
            $table->decimal('delivery_zone_price', 12, 2)->nullable();
            $table->decimal('delivery_min_order_amount', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('delivery_zone_id');
            $table->dropColumn(['delivery_zone_name', 'delivery_zone_price', 'delivery_min_order_amount']);
        });
    }
};
