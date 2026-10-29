<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Kompleks" (P6-15, stolovaya): a menu product sold at one fixed price as one order line (and one fiscal line) whose
     * parts the kitchen sees listed. The set's price is its product's variant price; the parts are dishes (variants).
     * A sold line keeps the parts it had (order_items.set_parts) like it keeps its modifiers.
     */
    public function up(): void
    {
        Schema::create('daily_sets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('daily_set_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_set_id')->constrained('daily_sets')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('quantity', 8, 3)->default(1);
            $table->string('label', 60)->nullable(); // "Birinchi", "Salat", "Non"
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('daily_set_id');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->jsonb('set_parts')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('set_parts');
        });
        Schema::dropIfExists('daily_set_items');
        Schema::dropIfExists('daily_sets');
    }
};
