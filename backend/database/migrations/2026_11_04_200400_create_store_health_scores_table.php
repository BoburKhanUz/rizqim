<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-22: the stores' health (good / attention / churn_risk), one row per store, refreshed hourly by
     * RefreshStoreHealthJob and on every 360° card view (App\Services\Admin\Stores\StoreHealthCalculator). The list,
     * the at-risk filter and the attention source read it instead of scanning orders per request.
     */
    public function up(): void
    {
        Schema::create('store_health_scores', function (Blueprint $table) {
            $table->foreignId('store_id')->primary()->constrained('stores')->cascadeOnDelete();
            $table->string('level', 16); // good | attention | churn_risk
            $table->unsignedSmallInteger('score'); // 0–100
            $table->jsonb('reasons'); // [{key, level, points, label}]
            $table->jsonb('metrics'); // the numbers the reasons came from
            $table->timestamp('computed_at');

            $table->index(['level', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_health_scores');
    }
};
