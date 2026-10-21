<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P3-12: a hall's own service charge and place fee (R6: VIP rooms another percent, a self-service zone 0%). A row with
 * `hall_id` overrides the branch's row in that hall (App\Services\Order\DiningFeeCalculator). The scope index now
 * covers the hall.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_fee_settings', function (Blueprint $table) {
            $table->foreignId('hall_id')->nullable()->constrained('halls')->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS service_fee_settings_scope');
            DB::statement('CREATE UNIQUE INDEX service_fee_settings_scope ON service_fee_settings (store_id, COALESCE(branch_id, 0), COALESCE(hall_id, 0))');
        }
    }

    public function down(): void
    {
        DB::table('service_fee_settings')->whereNotNull('hall_id')->delete();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS service_fee_settings_scope');
            DB::statement('CREATE UNIQUE INDEX service_fee_settings_scope ON service_fee_settings (store_id, COALESCE(branch_id, 0))');
        }

        Schema::table('service_fee_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hall_id');
        });
    }
};
