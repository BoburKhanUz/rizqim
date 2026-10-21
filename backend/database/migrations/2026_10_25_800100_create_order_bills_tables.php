<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-18: an order's bills (schyot, POS_SPEC 2.9, 10.2): one, or several when the bill is split equally or by items.
 * Each carries its share of the lines (`order_bill_items`, fractional quantities too) and of the discount and the
 * charges (room fee, minimum spend top-up, service charge, place fee), so every split bill is closed by its own sale
 * receipt. A bill is paid only when its receipt is issued (`fiscal_status`); until then the order is "fiskal kutmoqda".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_bills', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number'); // 1, 2, 3... within the order (voided bills keep theirs)
            $table->string('status', 16)->default('open'); // open | printed | paid | void
            $table->string('split_mode', 16)->default('single'); // single | equal | items
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('room_fee_amount', 12, 2)->default(0);
            $table->decimal('min_spend_topup_amount', 12, 2)->default(0);
            $table->decimal('service_charge_amount', 12, 2)->default(0);
            $table->decimal('place_fee_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->unsignedSmallInteger('guests_share')->nullable(); // guests this bill is for ("kishi boshiga")
            $table->string('basis', 64); // fingerprint of the order when the bill was made (a changed order: re-bill)
            $table->unsignedSmallInteger('printed_count')->default(0);
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete(); // the shift it was paid in
            $table->uuid('fiscal_receipt_uuid')->nullable();
            $table->string('fiscal_status', 16)->nullable(); // pending | issued | failed (the receipt's)
            $table->string('fiscal_error', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'number']);
            $table->index(['shift_id', 'status']);
            $table->index(['branch_id', 'fiscal_status']);
        });

        Schema::create('order_bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained('order_bills')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 3); // the bill's share of the line (0.5 kg of 1.5 kg)
            $table->decimal('amount', 12, 2); // its share of the line total
            $table->decimal('discount_amount', 12, 2)->default(0); // its share of the order discount
            $table->timestamps();

            $table->unique(['bill_id', 'order_item_id']);
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_bill_items');
        Schema::dropIfExists('order_bills');
    }
};
