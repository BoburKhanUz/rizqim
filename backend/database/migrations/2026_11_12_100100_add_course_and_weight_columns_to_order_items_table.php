<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-21 / P6-25 (wave 2 "Restoran va kafe"), on the POS lines:
     *  - `course_no`: the course a line belongs to (1, 2, 3...; null: no courses). A later course is held until the
     *    waiter or the till sends it ("2-kursni yuborish", POST /pos/orders/{uuid}/courses/{n}/send);
     *  - "weight later" (B3): a dish chosen now and weighed afterwards. `weight_status` `awaiting` (the line carries the
     *    estimate; no bill is made or printed while it waits) → `weighed` (the real kg, who weighed it and when);
     *    `weight_basis` is the product's rule at the time of sale: `raw` (weighed before cooking) or `cooked`.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('course_no')->nullable();
            $table->string('weight_status', 16)->nullable();
            $table->string('weight_basis', 8)->nullable();
            $table->foreignId('weighed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('weighed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('weighed_by');
            $table->dropColumn(['course_no', 'weight_status', 'weight_basis', 'weighed_at']);
        });
    }
};
