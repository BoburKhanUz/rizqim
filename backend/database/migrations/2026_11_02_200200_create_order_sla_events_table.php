<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-14: when a marketplace order's SLA stage (accept, prepare, deliver) crossed into `warning` (80% of the limit)
     * or `late`, written by the every-minute SLA watcher (App\Services\Admin\Orders\OrderSlaWatcher). The unique key
     * makes each transition happen once per order, stage and state, so ops.order.updated is broadcast once; the order
     * card's SLA timeline reads it too.
     */
    public function up(): void
    {
        Schema::create('order_sla_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('stage', 16);  // accept | prepare | deliver
            $table->string('state', 16);  // warning | late
            $table->unsignedSmallInteger('limit_minutes');
            $table->timestamp('crossed_at'); // when the threshold was crossed (stage start + 80% / 100% of the limit)
            $table->timestamp('created_at')->useCurrent(); // when the watcher saw it

            $table->unique(['order_id', 'stage', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_sla_events');
    }
};
