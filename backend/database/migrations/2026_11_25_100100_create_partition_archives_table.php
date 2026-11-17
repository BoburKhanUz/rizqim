<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wave M3 (docs/scale/M3_DESIGN.md section 5): the catalogue of journal months taken offline. One row per
     * partition, written by `php artisan db:partitions:archive` (detached, dropped) and by scripts/archive-partitions.sh
     * with plain psql (uploaded, verified, failed: sha256, storage_uri, bytes); scripts/archive-restore.sh --attach sets
     * held. Timestamps are UTC (scripts write `now() AT TIME ZONE 'UTC'`).
     *
     * status: detached (out of the live table, waiting for the dump), uploaded (dumped, sha256 known, copy only local
     * or not yet verified), verified (downloaded back from off-site storage, sha256 and pg_restore --list checked),
     * dropped (the table is gone from the database; the archive file stays forever for audit and fiscal), failed
     * (see error), held (attached again by hand; the archive job skips it).
     */
    public function up(): void
    {
        Schema::create('partition_archives', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 63);
            $table->string('partition_name', 63)->unique();
            $table->timestamp('range_from');
            $table->timestamp('range_to');
            $table->string('status', 16);
            $table->bigInteger('rows')->nullable();
            $table->bigInteger('min_id')->nullable();
            $table->bigInteger('max_id')->nullable();
            $table->bigInteger('bytes')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->text('storage_uri')->nullable();
            $table->timestamp('detached_at')->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('dropped_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'table_name']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE partition_archives ADD CONSTRAINT partition_archives_status_check CHECK (status IN ('detached', 'uploaded', 'verified', 'dropped', 'failed', 'held'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partition_archives');
    }
};
