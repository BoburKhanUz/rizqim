<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P1-23: a store's tariff plan over time. At most one row per store is not cancelled (the current one); ended
     * rows stay as history. A plan change for the next month waits in next_tariff_plan_id until the month closes.
     */
    public function up(): void
    {
        Schema::create('store_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('tariff_plan_id')->constrained('tariff_plans')->restrictOnDelete();
            $table->string('status', 16); // App\Enums\SubscriptionStatus
            $table->timestamp('started_at');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->foreignId('next_tariff_plan_id')->nullable()->constrained('tariff_plans')->nullOnDelete();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['status', 'current_period_end']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX store_subscriptions_one_current ON store_subscriptions (store_id) WHERE status <> 'cancelled'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_subscriptions');
    }
};
