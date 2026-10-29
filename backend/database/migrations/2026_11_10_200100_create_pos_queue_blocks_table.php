<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-13 (A2): the queue number ranges ("bloklar") a branch hands out within one business day
     * (App\Domain\Pos\Shifts\QueueNumbers). A till (`device_id`) takes a block of numbers from the server and numbers
     * its orders from it, online and offline, so two tills never print the same number even without internet; the
     * branch's own block (`device_id` null) numbers the Telegram pickup orders and the orders opened without a till.
     *  - `start_number`..`end_number`: the range (inclusive); blocks of one branch and day never overlap (they are
     *    cut one after another under the branch and day lock, and `start_number` is unique per branch and day);
     *  - `next_number`: the first number the server has not seen used from the block yet (a till's offline numbers
     *    move it forward when they sync).
     * Numbering restarts from 1 every business day per branch (POS_SPEC 2.3).
     */
    public function up(): void
    {
        Schema::create('pos_queue_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->date('business_date');
            $table->unsignedInteger('start_number');
            $table->unsignedInteger('end_number');
            $table->unsignedInteger('next_number');
            $table->timestamps();

            $table->unique(['branch_id', 'business_date', 'start_number']);
            $table->index(['branch_id', 'business_date', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_queue_blocks');
    }
};
