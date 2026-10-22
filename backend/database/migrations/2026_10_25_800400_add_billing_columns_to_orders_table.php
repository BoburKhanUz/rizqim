<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-18: a POS order's shift and business day (POS_SPEC 2.3, 10.3: reports and queue numbers follow the business
 * day, not the calendar day), its queue number within that day, when its first bill was printed (the order is locked:
 * voids and discounts need a manager from then on) and when it was closed (paid in full, receipts issued). Nullable:
 * online orders and existing rows keep them null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->date('business_date')->nullable();
            $table->unsignedInteger('queue_number')->nullable();
            $table->timestamp('bill_locked_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->index(['branch_id', 'business_date', 'queue_number']);
            $table->index(['shift_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'business_date', 'queue_number']);
            $table->dropIndex(['shift_id', 'status']);
            $table->dropConstrainedForeignId('shift_id');
            $table->dropColumn(['business_date', 'queue_number', 'bill_locked_at', 'closed_at']);
        });
    }
};
