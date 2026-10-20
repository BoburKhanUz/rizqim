<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P1-21 modifiers: a store's modifier groups ("Sous", "Qo'shimcha go'sht", "Hajm"), their modifiers with a price
     * delta, and which products offer which groups (with per-product min/max/required/sort overrides). docs/MENU.md
     */
    public function up(): void
    {
        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->jsonb('name_i18n')->nullable(); // {"uz": "...", "ru": "..."}; `name` is the default
            $table->unsignedSmallInteger('min_select')->default(0);
            $table->unsignedSmallInteger('max_select')->nullable(); // null: any number
            $table->boolean('is_required')->default(false); // at least one (min_select is raised to 1)
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'sort_order']);
        });

        Schema::create('modifiers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->jsonb('name_i18n')->nullable();
            $table->decimal('price_delta', 12, 2)->default(0);
            $table->unsignedSmallInteger('max_quantity')->default(1); // how many times one item may take it
            // The stock this modifier draws on (e.g. "qazi" sold separately too); kept for ingredient stock later
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['modifier_group_id', 'sort_order']);
        });

        Schema::create('product_modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            // Overrides of the group's own rules for this product; null = the group's
            $table->unsignedSmallInteger('min_select')->nullable();
            $table->unsignedSmallInteger('max_select')->nullable();
            $table->boolean('is_required')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'modifier_group_id']);
            $table->index('modifier_group_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_modifier_groups');
        Schema::dropIfExists('modifiers');
        Schema::dropIfExists('modifier_groups');
    }
};
