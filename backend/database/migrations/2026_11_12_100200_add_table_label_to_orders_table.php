<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-25 (C7, kafe "stolga olib kelish"): a counter order paid at the till whose food is carried to the guest's table.
     * `table_label` is the number on the plate the guest took ("12", "A7"): free text, not a dining table of the floor
     * plan, shown on the kitchen ticket, the order and the bill.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('table_label', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('table_label');
        });
    }
};
