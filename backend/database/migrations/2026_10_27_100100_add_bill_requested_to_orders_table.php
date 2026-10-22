<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-26: a waiter asked the till for the bill of this POS hall order (POST /pos/orders/{uuid}/request-bill): when and
 * who. The floor map shows "Schyot so'raldi" until a bill is printed or the order closes. Null: not requested.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('bill_requested_at')->nullable();
            $table->foreignId('bill_requested_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bill_requested_by');
            $table->dropColumn('bill_requested_at');
        });
    }
};
