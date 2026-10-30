<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-25 (B3, B4), how a dish is sold in a kabobxona or baliqxona (PATCH /owner/products/{id}/pos-service):
     *  - `quick_multipliers`: the one-tap quantity buttons of a skewer or piece dish ([2, 3, 5, 10] → ×2, ×3, ×5, ×10;
     *    null: none), module `skewer_buttons`;
     *  - `wait_minutes`: how long the grill takes ("mangal ~15 daq"), shown at the till and on the kitchen ticket;
     *  - `weight_mode`: a dish weighed after it is chosen (module `weight_later`): `raw` or `cooked` (which weight the
     *    price is for); null: sold by the quantity typed, as before.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->jsonb('quick_multipliers')->nullable();
            $table->unsignedSmallInteger('wait_minutes')->nullable();
            $table->string('weight_mode', 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['quick_multipliers', 'wait_minutes', 'weight_mode']);
        });
    }
};
