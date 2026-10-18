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
        if (!Schema::hasColumn('stores', 'wallet_balance')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->decimal('wallet_balance', 12, 2)->default(0)->after('credit_limit');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('stores', 'wallet_balance')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn('wallet_balance');
            });
        }
    }
};
