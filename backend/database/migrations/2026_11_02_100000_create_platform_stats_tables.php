<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-13: the Bugun page's aggregate tables (App\Services\Admin\Today, docs/API.md "Bugun"), refreshed by
     * App\Jobs\RefreshPlatformStatsJob and `php artisan admin:stats-backfill`; the page never scans every order.
     *
     * - platform_hourly_stats / platform_daily_stats: one row per Asia/Tashkent hour (or day), region (0: the branch has
     *   no region) and channel (an App\Enums\OrderChannel value, or `subscriptions` for paid subscription invoices).
     *   Money columns are exact decimals like the orders they come from.
     * - platform_stats_days: which days were computed and through which instant their hourly rows are complete;
     *   anything after `complete_through` is read live from orders.
     */
    public function up(): void
    {
        $metrics = function (Blueprint $table) {
            $table->unsignedBigInteger('region_id')->default(0);
            $table->string('channel', 16);
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('gmv', 16, 2)->default(0);
            $table->decimal('commission', 16, 2)->default(0);
            $table->unsignedInteger('canceled_count')->default(0);
            $table->unsignedInteger('refunded_count')->default(0);
            $table->unsignedInteger('invoices_paid_count')->default(0);
            $table->decimal('subscription_revenue', 16, 2)->default(0);
            $table->timestamp('computed_at');
        };

        Schema::create('platform_hourly_stats', function (Blueprint $table) use ($metrics) {
            $table->id();
            $table->date('date');
            $table->unsignedSmallInteger('hour'); // 0-23, Asia/Tashkent
            $metrics($table);
            $table->unique(['date', 'hour', 'region_id', 'channel']);
        });

        Schema::create('platform_daily_stats', function (Blueprint $table) use ($metrics) {
            $table->id();
            $table->date('date');
            $metrics($table);
            $table->unique(['date', 'region_id', 'channel']);
        });

        Schema::create('platform_stats_days', function (Blueprint $table) {
            $table->date('date')->primary();
            // Hourly rows of this day are final for hours ending at or before this instant (UTC wall time)
            $table->timestamp('complete_through');
            $table->timestamp('computed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_stats_days');
        Schema::dropIfExists('platform_daily_stats');
        Schema::dropIfExists('platform_hourly_stats');
    }
};
