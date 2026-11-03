<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-34 (B8): the packaging of an order. `packaging_amount` is a charge like the service charge (added after the
     * discount, never discounted); `packaging_lines` the lines it is made of as they were worked out (rule, name, MXIK,
     * quantity, price, amount, removed with a reason), so the bill, the receipt and the fiscal lines read one snapshot.
     * A bill carries its share of the amount (order_bills.packaging_amount, OrderBill::CHARGES).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('packaging_amount', 12, 2)->default(0);
            $table->jsonb('packaging_lines')->nullable();
        });
        Schema::table('order_bills', function (Blueprint $table) {
            $table->decimal('packaging_amount', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('order_bills', function (Blueprint $table) {
            $table->dropColumn('packaging_amount');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['packaging_amount', 'packaging_lines']);
        });
    }
};
