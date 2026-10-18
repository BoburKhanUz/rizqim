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
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'payment_account_type', 'settlement_type', 
                'accept_cash', 'accept_click', 'accept_payme', 
                'balance', 'credit_limit', 'wallet_balance', 'low_balance_threshold'
            ]);
        });
    }
};
