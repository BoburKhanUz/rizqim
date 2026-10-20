<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P1-23: one monthly subscription invoice per store and month (unique store_id + period), posted to the store's
     * debt ledger (ledger_transaction_id) and, when voided, reversed there (void_transaction_id).
     */
    public function up(): void
    {
        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('number', 32)->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('store_subscription_id')->nullable()->constrained('store_subscriptions')->nullOnDelete();
            $table->foreignId('tariff_plan_id')->nullable()->constrained('tariff_plans')->nullOnDelete();
            $table->string('period', 7); // YYYY-MM, Asia/Tashkent
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->unsignedInteger('terminals_count');
            $table->unsignedInteger('included_terminals');
            $table->decimal('base_fee', 12, 2);
            $table->decimal('fee_per_terminal', 12, 2);
            $table->jsonb('lines');
            $table->decimal('amount', 12, 2);
            $table->string('status', 16); // App\Enums\SubscriptionInvoiceStatus
            $table->date('due_date');
            $table->timestamp('issued_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 500)->nullable();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('store_transactions')->nullOnDelete();
            $table->foreignId('void_transaction_id')->nullable()->constrained('store_transactions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'period']);
            $table->index(['period', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_invoices');
    }
};
