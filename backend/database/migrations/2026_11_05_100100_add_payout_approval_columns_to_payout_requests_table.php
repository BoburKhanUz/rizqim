<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-23: the payout queue of the Boshqaruv markazi (docs/API.md "Moliya"). A payout of 1 000 000 so'm or more needs
     * two members: the first approval is kept here (status stays `pending`), a different member completes it. When it
     * is completed, the store's debt is netted first: `netted_amount` went to the debt (each invoice and the commission
     * debt its own ledger row, `netting` lists them), `transferred_amount` to the card.
     */
    public function up(): void
    {
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->foreignId('first_approved_by')->nullable()->after('processed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('first_approved_at')->nullable()->after('first_approved_by');
            $table->string('first_approval_note', 500)->nullable()->after('first_approved_at');
            $table->decimal('netted_amount', 12, 2)->nullable()->after('first_approval_note');
            $table->decimal('transferred_amount', 12, 2)->nullable()->after('netted_amount');
            $table->jsonb('netting')->nullable()->after('transferred_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payout_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('first_approved_by');
            $table->dropColumn(['first_approved_at', 'first_approval_note', 'netted_amount', 'transferred_amount', 'netting']);
        });
    }
};
