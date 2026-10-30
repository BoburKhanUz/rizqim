<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-34 (module `scheduled_pickup`): a counter or Telegram pickup order for a later time. `pickup_at`: when the
     * customer comes; `kitchen_fire_at`: when the kitchen gets it (pickup_at − the prep time); `kitchen_fired_at`: when
     * it did (the order's lines were held until then; null while it waits). The minute job (pos:fire-scheduled-pickups)
     * reads the index.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('pickup_at')->nullable();
            $table->timestamp('kitchen_fire_at')->nullable();
            $table->timestamp('kitchen_fired_at')->nullable();
            $table->index(['kitchen_fire_at', 'kitchen_fired_at'], 'orders_kitchen_fire_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_kitchen_fire_index');
            $table->dropColumn(['pickup_at', 'kitchen_fire_at', 'kitchen_fired_at']);
        });
    }
};
