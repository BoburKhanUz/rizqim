<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Review W2 (four eyes on voids): voiding an unpaid invoice of config('finance.four_eyes_threshold') (1 000 000
     * so'm) or more takes two members. The first member's request is kept here (the invoice stays `issued`, the ledger
     * is untouched); a different member's void completes it (voided_by).
     */
    public function up(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->foreignId('void_requested_by')->nullable()->after('void_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('void_requested_at')->nullable()->after('void_requested_by');
            $table->string('void_request_reason', 500)->nullable()->after('void_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('void_requested_by');
            $table->dropColumn(['void_requested_at', 'void_request_reason']);
        });
    }
};
