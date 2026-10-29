<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-13 (A2): where a counter order with a queue number is for the customer waiting for it
     * (App\Domain\Pos\Orders\PickupQueue): `pickup_status` preparing → ready → handed (null: not in the pickup queue),
     * when it became ready, when it was last called and how many times, when it was handed over. Kept apart from
     * `orders.status`: a counter order paid first is closed (delivered) while the kitchen is still cooking it.
     *
     * The queue number becomes unique per branch and business day (it was only indexed): the server never gives one
     * number twice in a day, whether it numbers an order itself or takes the number a till printed from its block.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('pickup_status', 16)->nullable();
            $table->timestamp('pickup_ready_at')->nullable();
            $table->timestamp('pickup_called_at')->nullable();
            $table->unsignedSmallInteger('pickup_call_count')->default(0);
            $table->timestamp('pickup_handed_at')->nullable();

            $table->dropIndex(['branch_id', 'business_date', 'queue_number']);
            $table->unique(['branch_id', 'business_date', 'queue_number']);
            $table->index(['branch_id', 'business_date', 'pickup_status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'business_date', 'pickup_status']);
            $table->dropUnique(['branch_id', 'business_date', 'queue_number']);
            $table->index(['branch_id', 'business_date', 'queue_number']);
            $table->dropColumn(['pickup_status', 'pickup_ready_at', 'pickup_called_at', 'pickup_call_count', 'pickup_handed_at']);
        });
    }
};
