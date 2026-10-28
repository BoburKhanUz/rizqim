<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-23: manual adjustments of a store's debt or wallet (Moliya → Qo'lda tuzatish), with a reason and an optional
     * document. One of 1 000 000 so'm or more waits (`pending`) until a different member approves it (four eyes,
     * owner decision 28.10.2026); the ledger row is written only then (`applied`). Rejected ones keep their history.
     */
    public function up(): void
    {
        Schema::create('finance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('kind', 24); // App\Services\Finance\Adjustments\AdjustmentKind
            $table->decimal('amount', 12, 2);
            $table->string('reason', 500);
            $table->string('status', 16); // pending | applied | rejected
            $table->boolean('needs_second_approval')->default(false);
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->string('document_mime', 100)->nullable();
            $table->unsignedInteger('document_size')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('debt_transaction_id')->nullable()->constrained('store_transactions')->nullOnDelete();
            $table->foreignId('wallet_transaction_id')->nullable()->constrained('store_wallet_transactions')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['store_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_adjustments');
    }
};
