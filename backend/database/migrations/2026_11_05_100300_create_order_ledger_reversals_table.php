<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-23: what each reversal of a delivered order's ledger booking took back (LedgerService::reverseDeliveredOrder,
     * reverseDeliveredOrderShare). A part refunded through a refund request reverses its share pro-rata (one row per
     * request, unique); the order's refund, or parts that add up to everything paid, reverse the rest (`is_full`, at
     * most one per order). `components` — the amounts this reversal booked, per what delivery booked, so the next one
     * only takes the difference and nothing is ever reversed twice.
     */
    public function up(): void
    {
        Schema::create('order_ledger_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('order_refund_request_id')->nullable()->constrained('order_refund_requests')->nullOnDelete();
            $table->boolean('is_full')->default(false);
            $table->decimal('refunded_total', 12, 2)->nullable(); // what was given back of the order after this part
            $table->decimal('paid_total', 12, 2)->nullable();
            $table->jsonb('components');
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['order_refund_request_id']);
            $table->index(['order_id']);
        });

        DB::statement('CREATE UNIQUE INDEX order_ledger_reversals_full_unique ON order_ledger_reversals (order_id) WHERE is_full');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_ledger_reversals');
    }
};
