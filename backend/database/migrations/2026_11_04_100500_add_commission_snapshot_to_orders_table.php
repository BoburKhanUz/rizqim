<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-21: the commission channel (App\Enums\CommissionChannel) and rate an order was charged at checkout, next to
     * the commission_amount snapshot. NULL for orders placed before Billing v2 (they keep their amount as it is).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('commission_channel', 20)->nullable()->after('commission_amount');
            $table->decimal('commission_rate', 5, 2)->nullable()->after('commission_channel');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['commission_channel', 'commission_rate']);
        });
    }
};
