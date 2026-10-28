<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-23 (netting): a ledger row names what it closed or reopened. On the debt ledger (store_transactions) a row
     * that paid (or, reversed, reopened) a subscription invoice carries `subscription_invoice_id`; rows of a payout's
     * netting, on both ledgers, carry `payout_request_id`. Older rows have neither (a debt payoff without an invoice
     * is commission debt).
     */
    public function up(): void
    {
        Schema::table('store_transactions', function (Blueprint $table) {
            $table->foreignId('subscription_invoice_id')->nullable()->after('order_id')->constrained('subscription_invoices')->nullOnDelete();
            $table->foreignId('payout_request_id')->nullable()->after('subscription_invoice_id')->constrained('payout_requests')->nullOnDelete();
        });

        Schema::table('store_wallet_transactions', function (Blueprint $table) {
            $table->foreignId('payout_request_id')->nullable()->after('order_id')->constrained('payout_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('store_wallet_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_request_id');
        });

        Schema::table('store_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_request_id');
            $table->dropConstrainedForeignId('subscription_invoice_id');
        });
    }
};
