<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6-22 (C1): service charge variants on a service fee row (App\Domain\Pos\Billing\ServiceChargeRules):
 *  - `service_charge_rules` (jsonb, null: none): `by_order_type` ({hall|table_service|takeaway: percent}), the
 *    large-group rule (`large_group_min_guests` guests or more: `large_group_percent`) and the restaurant cover
 *    (`cover_amount` per guest, charged as the place fee line);
 *  - `service_charge_notice`: the owner's own text for the notice on the bill (null: worked out from the rules).
 * A stolovaya stays at 0% whatever the rules say (VM 75).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_fee_settings', function (Blueprint $table) {
            $table->jsonb('service_charge_rules')->nullable();
            $table->string('service_charge_notice', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('service_fee_settings', function (Blueprint $table) {
            $table->dropColumn(['service_charge_rules', 'service_charge_notice']);
        });
    }
};
