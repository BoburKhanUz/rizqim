<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P3-13: kitchen stations of a branch (osh/qozon, mangal, salat va bar, choy...) and the routing of dishes to them:
     * a route sends one product or one whole category to a station (POS_SPEC 2.8). A product route beats the
     * category route of its category (App\Domain\Pos\Setup\KitchenStationRouter). A product or a category is routed
     * to at most one station per branch.
     */
    public function up(): void
    {
        Schema::create('kitchen_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('kind', 16)->default('kitchen'); // App\Enums\KitchenStationKind
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_kds')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
        });

        Schema::create('kitchen_station_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained('kitchen_stations')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            // Nulls are distinct in a unique index: one route per category and one per product, per branch
            $table->unique(['branch_id', 'category_id']);
            $table->unique(['branch_id', 'product_id']);
            $table->index('station_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            // A route is for a category or for a product, never both and never neither
            DB::statement('ALTER TABLE kitchen_station_routes ADD CONSTRAINT kitchen_station_routes_one_subject CHECK ((category_id IS NULL) <> (product_id IS NULL))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_station_routes');
        Schema::dropIfExists('kitchen_stations');
    }
};
