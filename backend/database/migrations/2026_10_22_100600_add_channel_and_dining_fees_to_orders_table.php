<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every order says which channel it came from (existing ones were all placed in the Telegram Mini App, so the
     * default backfills them), may carry the channel's own order number (aggregators, POS sync; unique per store and
     * channel), and is ready for the POS hall: service charge and place fee lines, guests, hall and table.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('channel', 16)->default('telegram'); // App\Enums\OrderChannel
            $table->string('external_id', 100)->nullable();
            $table->decimal('service_charge_amount', 12, 2)->default(0);
            $table->decimal('place_fee_amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('guests_count')->nullable();
            // Free-form for now; the POS phase adds halls and tables as their own records
            $table->string('hall_ref', 100)->nullable();
            $table->string('table_ref', 100)->nullable();

            $table->unique(['store_id', 'channel', 'external_id']); // NULL external ids never collide
        });

        // Explicit for any row the default did not reach (e.g. a driver that does not backfill defaults)
        DB::table('orders')->whereNull('channel')->update(['channel' => 'telegram']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['store_id', 'channel', 'external_id']);
            $table->dropColumn([
                'channel', 'external_id', 'service_charge_amount', 'place_fee_amount', 'guests_count', 'hall_ref', 'table_ref',
            ]);
        });
    }
};
