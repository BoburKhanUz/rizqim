<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-15: small "this happened at" facts that processes outside the backend report. The backup service
     * (scripts/backup.sh) writes the `backup` marker after each verified dump, so Monitoring → Holat and the
     * backup_stale rule can say when the last backup was made.
     */
    public function up(): void
    {
        Schema::create('monitoring_markers', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->timestamp('recorded_at');
            $table->json('meta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_markers');
    }
};
