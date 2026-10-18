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
        if (!Schema::hasColumn('stores', 'min_payout_reserve')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->decimal('min_payout_reserve', 12, 2)->default(100000.00);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('stores', 'min_payout_reserve')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn('min_payout_reserve');
            });
        }
    }
};
