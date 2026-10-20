<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1-24: delivery zones. The polygon is GeoJSON in jsonb (no PostGIS: a store has a handful of zones); the point-in-
 * polygon test runs in PHP (App\Domain\Delivery\ZoneResolver) after the indexed bounding box has narrowed the candidates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            // The branch that serves the zone; empty = the nearest open branch of the store
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            // GeoJSON Polygon / MultiPolygon geometry, [lng, lat] positions
            $table->jsonb('polygon');
            // Bounding box of the polygon, kept in step with it by the model
            $table->decimal('min_lat', 9, 6);
            $table->decimal('max_lat', 9, 6);
            $table->decimal('min_lng', 9, 6);
            $table->decimal('max_lng', 9, 6);
            // Approximate area, km2: the tie-break between zones of equal priority (smallest wins)
            $table->decimal('area_km2', 12, 4)->default(0);
            $table->decimal('delivery_price', 12, 2)->default(0);
            $table->decimal('min_order_amount', 12, 2)->default(0);
            $table->decimal('free_delivery_from', 12, 2)->nullable();
            $table->unsignedSmallInteger('eta_minutes')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['store_id', 'active', 'min_lat', 'max_lat', 'min_lng', 'max_lng'], 'delivery_zones_store_bbox_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
