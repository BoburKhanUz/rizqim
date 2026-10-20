<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The column holds what the store owes the platform (positive = debt), not money it holds; the name said otherwise.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->renameColumn('balance', 'debt_balance');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->renameColumn('debt_balance', 'balance');
        });
    }
};
