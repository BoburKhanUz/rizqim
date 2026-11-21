<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wave M5 S5-a: the owner is told once per till and per "minimum" declaration that the till is below the minimum
     * version after the grace period. This is the id of the pos_app_releases row (the minimum) the till was last told
     * about. A nullable bigint: no table rewrite, safe on a live database. No foreign key on purpose: a deleted
     * release must never touch a device row.
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedBigInteger('app_outdated_notified_release_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('app_outdated_notified_release_id');
        });
    }
};
