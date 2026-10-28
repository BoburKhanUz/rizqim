<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-21 (docs/BILLING.md):
     * - subscription_debt: the part of debt_balance that is unpaid subscription invoices. The credit limit that stops
     *   Mini App orders counts only the rest (debt_balance - subscription_debt, the commission debt). 0 for every
     *   store until `billing:migrate-plans` sorts the existing debt into the two parts, so nothing changes before it.
     * - commission_overrides: the store's own commission per channel ({"pickup": "3.00", ...}; admin, audited).
     * - delivery_courier: who delivers the store's Mini App delivery orders (own | fleet), which sets their commission.
     * - commission_rate is no longer required: NULL takes the channel rates; a value is a flat rate for every online
     *   channel, exactly as before (existing rows keep theirs).
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('subscription_debt', 12, 2)->default(0)->after('debt_balance');
            $table->jsonb('commission_overrides')->nullable()->after('commission_rate');
            $table->string('delivery_courier', 16)->default('own')->after('commission_overrides');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('stores')->whereNull('commission_rate')->update(['commission_rate' => 10]);

        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->default(10.00)->nullable(false)->change();
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['subscription_debt', 'commission_overrides', 'delivery_courier']);
        });
    }
};
