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
        Schema::table('stores', function (Blueprint $table) {
            $table->string('payment_account_type')->default('own_account')->after('status');
            $table->string('settlement_type')->default('manual')->after('payment_account_type');

            $table->boolean('accept_cash')->default(true)->after('settlement_type');
            $table->boolean('accept_click')->default(false)->after('accept_cash');
            $table->boolean('accept_payme')->default(false)->after('accept_click');

            $table->decimal('balance', 12, 2)->default(0)->after('commission_rate');
            $table->decimal('credit_limit', 12, 2)->default(-100000)->after('balance');
            $table->decimal('wallet_balance', 12, 2)->default(0)->after('credit_limit');
            $table->decimal('low_balance_threshold', 12, 2)->default(0)->after('wallet_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only the columns still there: rolling back 2026_06_07_110000_add_missing_wallet_balance_to_stores (which found
        // wallet_balance already added here) has dropped wallet_balance before this runs
        $columns = array_values(array_filter([
            'payment_account_type', 'settlement_type',
            'accept_cash', 'accept_click', 'accept_payme',
            'balance', 'credit_limit', 'wallet_balance', 'low_balance_threshold',
        ], fn (string $column) => Schema::hasColumn('stores', $column)));

        if ($columns !== []) {
            Schema::table('stores', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
