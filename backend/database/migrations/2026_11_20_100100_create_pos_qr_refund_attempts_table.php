<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Review P6W4 C2: a refund of a provider-confirmed QR payment in two phases (App\Domain\Pos\QrPayments\QrRefunds).
 * The attempt is committed (`requested`, the shift lock released) before Click/Payme/UzQR is asked, outside any
 * transaction; then it is finalised (`refunded`: the PosRefund it became, `refund_id`, same uuid), `failed` (the
 * provider refused: no refund) or `unknown` (no answer: the retry and the minute job ask the provider's status).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_qr_refund_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qr_payment_id')->constrained('pos_qr_payments')->cascadeOnDelete();
            $table->unsignedBigInteger('payment_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('bill_id');
            $table->unsignedBigInteger('shift_id');
            $table->decimal('amount', 14, 2);
            $table->string('reason', 500);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('status', 16)->default('requested');
            $table->string('message', 500)->nullable();
            $table->string('reference', 100)->nullable();
            $table->unsignedBigInteger('refund_id')->nullable();
            $table->unsignedSmallInteger('provider_calls')->default(0);
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'status']);
            $table->index(['status', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_qr_refund_attempts');
    }
};
