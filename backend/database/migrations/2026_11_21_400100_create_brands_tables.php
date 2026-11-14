<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6-55 (wave 5 "Banket va dark kitchen", module `multi_brand`; App\Domain\Pos\Brands): several brands sold from one
 * kitchen.
 *
 * - `brands`: a store's brand (name, short code for the kitchen ticket and the label, colour, optional logo url,
 *   active). An inactive brand's dishes are hidden on every channel.
 * - `branch_brands`: a branch switches a brand off (no row: on). Its dishes are stopped there through the
 *   availability pipeline (MenuAvailability), so checkout refuses them like a stop-list entry.
 * - `brand_channels`: a brand on one sales channel: hidden there (a brand sold only on some channels) and/or a price
 *   adjustment in percent for its dishes on that channel (a variant's own channel price still wins; ChannelMenu).
 * - `products.brand_id`: the dish's brand (null: the house brand, as before).
 * - `order_items.brand_id` + `brand` (snapshot {id, name, code, color}): the brand a line was sold under, set when the
 *   line is created (BrandsServiceProvider), shown on kitchen tickets and labels, grouped by the channel report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('short_code', 8);
            $table->string('color', 7)->default('#64748B');
            $table->string('logo_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['store_id', 'short_code']);
            $table->index(['store_id', 'is_active']);
        });

        Schema::create('branch_brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'brand_id']);
        });

        Schema::create('brand_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 32); // App\Enums\OrderChannel
            $table->boolean('is_hidden')->default(false);
            $table->decimal('price_percent', 6, 2)->nullable(); // +15.00: 15 % dearer on this channel; null: no change
            $table->timestamps();

            $table->unique(['brand_id', 'channel']);
            $table->index(['store_id', 'channel']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('category_id')->constrained('brands')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->json('brand')->nullable(); // snapshot {id, name, code, color}
            $table->index('brand_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['brand_id']);
            $table->dropConstrainedForeignId('brand_id');
            $table->dropColumn('brand');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });
        Schema::dropIfExists('brand_channels');
        Schema::dropIfExists('branch_brands');
        Schema::dropIfExists('brands');
    }
};
