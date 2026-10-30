<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-23/P6-24 (owner decision 30.10.2026): the waiter a bill was paid under. `waiter_id` is the order's waiter at
     * the moment the bill's payments cover it (PaymentService; BillFiscalizer for a bill paid without a payment), so a
     * table handed to another waiter later keeps the bills paid before the handover with the first one. Staff pay
     * attributes sales by it when the branch's `sales_attribution` is `at_payment` (the default). Null for a bill not
     * paid yet or of an order without a waiter. Paid bills made before this column get their order's waiter.
     */
    public function up(): void
    {
        Schema::table('order_bills', function (Blueprint $table) {
            $table->foreignId('waiter_id')->nullable()->after('seat_no')->constrained('users')->nullOnDelete();
        });

        DB::table('order_bills')
            ->where('status', 'paid')
            ->whereNull('waiter_id')
            ->update(['waiter_id' => DB::raw('(SELECT orders.waiter_id FROM orders WHERE orders.id = order_bills.order_id)')]);
    }

    public function down(): void
    {
        Schema::table('order_bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('waiter_id');
        });
    }
};
