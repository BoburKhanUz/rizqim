<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P3-11: every receipt the store issued (sale, refund, advance, credit), whatever registered it: a fiscal driver
     * (sandbox, null, later a virtual kassa provider) or a payment provider itself (Payme, Click: `external`). The
     * row is written before the provider is asked, so a sale is never lost: a receipt the provider has not confirmed
     * stays `pending` and is retried (RetryFiscalReceipt, fiscal:retry-pending) until `issued` or `failed`.
     */
    public function up(): void
    {
        Schema::create('fiscal_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16); // App\Domain\Fiscal\FiscalReceiptType
            $table->string('payment_form', 16); // App\Domain\Fiscal\FiscalPaymentForm
            $table->string('status', 16)->default('pending'); // pending | issued | failed
            $table->string('driver', 32); // sandbox | null | ... | external
            $table->string('provider', 32)->nullable(); // external receipts: payme | click
            $table->string('idempotency_key', 128);
            $table->string('subject_type', 64)->nullable(); // what the receipt is for: order, order_bill, booking_payment...
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('tax_regime', 16)->nullable(); // the store's regime when the receipt was issued
            $table->decimal('total', 12, 2);
            $table->decimal('vat_total', 12, 2)->default(0);
            $table->jsonb('lines'); // snapshot of the FiscalLines
            $table->jsonb('payments'); // snapshot of the FiscalPayments
            $table->foreignId('original_receipt_id')->nullable()->constrained('fiscal_receipts')->nullOnDelete();
            $table->boolean('non_fiscal')->default(false); // "Bu fiskal chek emas": null/sandbox drivers
            $table->string('fiscal_sign', 64)->nullable();
            $table->string('receipt_number', 64)->nullable();
            $table->string('qr_url', 1000)->nullable();
            $table->string('terminal_id', 64)->nullable(); // the provider's fiscal module / virtual kassa id
            $table->jsonb('provider_payload')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'idempotency_key']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['store_id', 'created_at']);
            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_receipts');
    }
};
