<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-21 (docs/BILLING.md):
     * - kind monthly | yearly | upgrade (App\Enums\InvoiceKind) and sequence: one monthly and one yearly invoice per
     *   store and month, upgrades numbered 1, 2... within the month (unique store + period + kind + sequence, in place
     *   of store + period).
     * - paid_amount: how much of the invoice the ledger has paid (top-ups, keshbek and payout netting pay the oldest
     *   invoices first); amount - paid_amount of every invoice not void is the store's subscription_debt.
     */
    public function up(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->string('kind', 16)->default('monthly')->after('period');
            $table->unsignedSmallInteger('sequence')->default(1)->after('kind');
            $table->decimal('paid_amount', 12, 2)->default(0)->after('amount');

            $table->dropUnique(['store_id', 'period']);
            $table->unique(['store_id', 'period', 'kind', 'sequence']);
            $table->index(['store_id', 'status', 'due_date']);
        });

        // An invoice already paid (P1-23: the store owed nothing, or marked by hand) is paid in full
        \Illuminate\Support\Facades\DB::table('subscription_invoices')->where('status', 'paid')->update(['paid_amount' => \Illuminate\Support\Facades\DB::raw('amount')]);
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'status', 'due_date']);
            $table->dropUnique(['store_id', 'period', 'kind', 'sequence']);
            $table->unique(['store_id', 'period']);
            $table->dropColumn(['kind', 'sequence', 'paid_amount']);
        });
    }
};
