<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-23 (owner decision 30.10.2026): whose sales a paid bill is when its table was handed to another waiter —
     * `at_payment` (the waiter at payment time, order_bills.waiter_id; the default, the same as tips) or
     * `current_waiter` (the order's waiter when the statement is computed). App\Domain\Pos\StaffPay\SalesAttribution.
     */
    public function up(): void
    {
        Schema::table('staff_pay_settings', function (Blueprint $table) {
            $table->string('sales_attribution', 16)->default('at_payment')->after('period');
        });
    }

    public function down(): void
    {
        Schema::table('staff_pay_settings', function (Blueprint $table) {
            $table->dropColumn('sales_attribution');
        });
    }
};
