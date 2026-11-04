<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6 local bridge (docs/POS_SPEC.md §5.5): an operation the branch's hub relayed for another device of the branch (a
 * waiter's phone, a KDS) keeps that device in `origin_device_id`; `device_id` stays the hub that sent it (its queue,
 * its `seq`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sync_operations', function (Blueprint $table) {
            $table->foreignId('origin_device_id')->nullable()->after('device_id')->constrained('devices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_sync_operations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_device_id');
        });
    }
};
