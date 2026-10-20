<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Channel pricing and visibility (P1-21): a variant's own price on one channel (telegram, mobile, pos_hall, ...)
     * and whether a product or a variant is hidden there. No row: the variant's price, visible.
     */
    public function up(): void
    {
        Schema::create('channel_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // null: the row is about the whole product (hidden only, no price)
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('channel', 16); // App\Enums\OrderChannel
            $table->decimal('price', 12, 2)->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->index(['store_id', 'channel']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX channel_prices_subject_channel ON channel_prices (product_id, COALESCE(product_variant_id, 0), channel)');
        } else {
            Schema::table('channel_prices', fn (Blueprint $table) => $table->unique(['product_id', 'product_variant_id', 'channel']));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_prices');
    }
};
