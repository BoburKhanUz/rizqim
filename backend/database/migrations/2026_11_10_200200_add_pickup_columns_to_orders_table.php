<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * Review P6 C#L7: numbers given twice before (P3-18 took a till's proposal as it was) are renumbered first — the
     * oldest order of the day keeps the number, each later one gets the day's next number (logged) — so the index
     * can be created.
     */
    public function up(): void
    {
        $this->renumberDuplicates();

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

    /**
     * Gives every order sharing a branch, business day and queue number with an older one (soft-deleted orders
     * included: the index covers them) the next number of that day, oldest first.
     */
    public function renumberDuplicates(): void
    {
        $groups = DB::table('orders')
            ->select('branch_id', 'business_date', 'queue_number')
            ->whereNotNull('branch_id')
            ->whereNotNull('business_date')
            ->whereNotNull('queue_number')
            ->groupBy('branch_id', 'business_date', 'queue_number')
            ->havingRaw('count(*) > 1')
            ->orderBy('branch_id')->orderBy('business_date')->orderBy('queue_number')
            ->get();

        foreach ($groups as $group) {
            $day = fn () => DB::table('orders')->where('branch_id', $group->branch_id)->where('business_date', $group->business_date);
            $later = $day()->where('queue_number', $group->queue_number)
                ->orderBy('created_at')->orderBy('id')
                ->offset(1)
                ->pluck('id');

            foreach ($later as $id) {
                $number = (int) $day()->max('queue_number') + 1;
                DB::table('orders')->where('id', $id)->update(['queue_number' => $number]);
                Log::warning('orders.queue_number renumbered before its unique index', [
                    'order_id' => (int) $id,
                    'branch_id' => (int) $group->branch_id,
                    'business_date' => (string) $group->business_date,
                    'from' => (int) $group->queue_number,
                    'to' => $number,
                ]);
            }
        }
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
