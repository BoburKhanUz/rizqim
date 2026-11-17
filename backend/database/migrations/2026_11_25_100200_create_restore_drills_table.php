<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wave M3 (docs/scale/M3_DESIGN.md section 8): one row per restore drill, inserted into the live database by
     * scripts/restore-drill.sh with plain psql at the end of a run. Timestamps are UTC (`now() AT TIME ZONE 'UTC'`).
     * restore_seconds = restore + WAL replay + promotion (the RTO); data_loss_seconds = the loss window (RPO); checks
     * = the result of each check, e.g. {"marker": "ok", "counts": "ok", "amcheck": "ok"}. Monitoring's
     * restore_drill_stale rule reads the last row.
     */
    public function up(): void
    {
        Schema::create('restore_drills', function (Blueprint $table) {
            $table->id();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status', 16);
            $table->string('repo', 16)->nullable();
            $table->timestamp('target_time')->nullable();
            $table->timestamp('reached_time')->nullable();
            $table->integer('restore_seconds')->nullable();
            $table->integer('data_loss_seconds')->nullable();
            $table->string('backup_label', 64)->nullable();
            $table->jsonb('checks')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['status', 'started_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE restore_drills ADD CONSTRAINT restore_drills_status_check CHECK (status IN ('ok', 'failed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('restore_drills');
    }
};
