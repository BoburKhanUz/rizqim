<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-16 (D5 d): the bank terminal's day total of a shift, entered at the Z (or after it) or imported (its
     * transactions, or the smart kassa provider's own total), compared with the card money Rizqim recorded in that
     * shift: card payments of bills, card booking deposits and nasiya repayments taken on the till, less card refunds
     * and card deposit refunds. `difference` = bank − Rizqim; above the branch's threshold the owner is alerted
     * (`alerted_at`). Imported transactions are matched to Rizqim's by their RRN (`unmatched`). One per shift; a new
     * entry replaces it (audited).
     */
    public function up(): void
    {
        Schema::create('bank_terminal_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->unique()->constrained('pos_shifts')->cascadeOnDelete();
            $table->date('business_date');
            $table->string('source', 16);
            $table->string('terminal_ref', 64)->nullable();
            $table->decimal('bank_total', 14, 2);
            $table->unsignedInteger('bank_count')->nullable();
            $table->jsonb('transactions')->nullable();
            $table->decimal('rizqim_total', 14, 2);
            $table->unsignedInteger('rizqim_count');
            $table->jsonb('rizqim_breakdown')->nullable();
            $table->decimal('difference', 14, 2);
            $table->decimal('threshold', 12, 2);
            $table->boolean('over_threshold');
            $table->jsonb('unmatched')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('alerted_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'business_date']);
            $table->index(['store_id', 'over_threshold']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_terminal_reports');
    }
};
