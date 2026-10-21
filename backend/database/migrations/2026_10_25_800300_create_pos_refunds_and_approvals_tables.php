<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-18: money given back from a paid POS bill within the shift (`pos_refunds`, POS_SPEC 2.10: reason, which
 * payment, who approved, the refund receipt) and the one-time manager approvals of a terminal (`pos_approvals`,
 * POS_SPEC 2.2: a manager's PIN grants one permission on one terminal for 60 seconds; only the token's hash is kept).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained('order_bills')->nullOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 16); // cash | card | qr (the refunded payment's tender)
            $table->string('reason', 500);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('fiscal_receipt_uuid')->nullable();
            $table->string('fiscal_status', 16)->nullable();
            $table->string('fiscal_error', 500)->nullable();
            $table->timestamps();

            $table->index('payment_id');
            $table->index(['shift_id', 'method']);
        });

        Schema::create('pos_approvals', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique(); // sha256 of the one-time token
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 64);
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete(); // who entered their PIN
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete(); // whose session asked
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_approvals');
        Schema::dropIfExists('pos_refunds');
    }
};
