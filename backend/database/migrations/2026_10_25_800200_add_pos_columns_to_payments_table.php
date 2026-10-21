<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-18: POS payments (POS_SPEC 10.3): the bill and shift they belong to, the terminal and person that took them,
 * cash tendered and change, the card's type and the bank terminal's transaction id (required for card), a free
 * reference. `provider` takes the POS tenders (App\Domain\Pos\Contracts\PosTender). Nullable: online payments keep
 * every new column null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('bill_id')->nullable()->constrained('order_bills')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('pos_shifts')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('tendered_amount', 12, 2)->nullable();
            $table->decimal('change_amount', 12, 2)->nullable();
            $table->string('card_type', 16)->nullable();
            $table->string('transaction_id', 64)->nullable();
            $table->string('reference', 100)->nullable();

            $table->index('bill_id');
            $table->index(['shift_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['bill_id']);
            $table->dropIndex(['shift_id', 'provider']);
            $table->dropConstrainedForeignId('bill_id');
            $table->dropConstrainedForeignId('shift_id');
            $table->dropConstrainedForeignId('device_id');
            $table->dropConstrainedForeignId('received_by');
            $table->dropColumn(['tendered_amount', 'change_amount', 'card_type', 'transaction_id', 'reference']);
        });
    }
};
