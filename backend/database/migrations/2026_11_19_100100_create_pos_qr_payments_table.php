<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6-41/P6-42: a dynamic QR the till shows for a bill (Click, Payme or UzQR; App\Domain\Pos\QrPayments). One pending
 * request per bill (a new one cancels the old); the provider's callback confirms it (`paid`: the POS payment it made,
 * `payment_id`), the cashier cancels it, its time runs out (`expired`) or a refund reverses it (`refunded`).
 * `held_at`: the provider started taking the money (Click Prepare, Payme CreateTransaction): the expiry waits for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_qr_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained('order_bills')->cascadeOnDelete();
            $table->uuid('bill_uuid');
            $table->string('provider', 16);
            $table->string('driver', 32);
            $table->decimal('amount', 14, 2);
            $table->string('status', 16)->default('pending');
            $table->text('checkout_payload');
            $table->string('gateway_reference', 100)->nullable();
            $table->string('provider_transaction_id', 100)->nullable();
            $table->jsonb('provider_payload')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('held_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 64)->nullable();
            $table->decimal('refunded_amount', 14, 2)->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['bill_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index(['provider', 'provider_transaction_id']);
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_qr_payments');
    }
};
