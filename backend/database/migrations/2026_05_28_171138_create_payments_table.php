<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // click, payme, cash
            $table->string('provider_transaction_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending'); // pending, paid, failed, cancelled, expired
            $table->jsonb('payload')->nullable(); // auditing raw Click/Payme logs
            
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            // Composite unique to ensure idempotency across provider transaction retries
            $table->unique(['provider', 'provider_transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
