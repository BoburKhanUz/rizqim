<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6-55 / B9 (module `ingredient_stoplist`; App\Domain\Pos\Ingredients): an ingredient stop-list, not recipes.
 *
 * - `ingredients`: a store's ingredient (name, optional unit for display).
 * - `product_ingredients`: which dishes use it; a `critical` link closes the dish when the ingredient is stopped on a
 *   branch (stop_list_entries.subject_type = ingredient), a non-critical one only marks it ("tovuqsiz").
 * - `channel_commissions`: the owner's commission percent per sales channel (aggregators: Yandex, Uzum take theirs)
 *   for the channel net revenue report; no row: the order's own RIZQIM commission (docs/BILLING.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredients', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('unit', 16)->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'name']);
        });

        Schema::create('product_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_critical')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'ingredient_id']);
            $table->index('ingredient_id');
        });

        Schema::create('channel_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 32); // App\Enums\OrderChannel
            $table->decimal('percent', 5, 2);
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_commissions');
        Schema::dropIfExists('product_ingredients');
        Schema::dropIfExists('ingredients');
    }
};
