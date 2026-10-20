<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Service charge (xizmat haqi, %) and place fee (joy narxi) of a store, optionally overridden per branch. Charged
     * on dine-in channels only by default and never on Telegram orders (App\Services\Order\DiningFeeCalculator).
     */
    public function up(): void
    {
        Schema::create('service_fee_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete(); // null: the store's default
            $table->decimal('service_charge_percent', 5, 2)->default(0);
            $table->jsonb('service_charge_channels')->nullable(); // null: ["pos_hall"]
            $table->decimal('place_fee_amount', 12, 2)->default(0);
            $table->string('place_fee_mode', 16)->default('per_person'); // App\Enums\PlaceFeeMode
            $table->jsonb('place_fee_channels')->nullable(); // null: ["pos_hall"]
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX service_fee_settings_scope ON service_fee_settings (store_id, COALESCE(branch_id, 0))');
        } else {
            Schema::table('service_fee_settings', fn (Blueprint $table) => $table->unique(['store_id', 'branch_id']));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_fee_settings');
    }
};
