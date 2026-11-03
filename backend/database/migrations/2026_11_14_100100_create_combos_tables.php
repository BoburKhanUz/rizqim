<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-31 "Kombo" (fast food, dark kitchen; module `combo`, docs/MENU.md "Kombo"): a menu product (its price, MXIK code,
     * channel prices and daily menu work as for any dish) whose buyer picks its parts from ordered slots ("Asosiy",
     * "Ichimlik", "Sous"). A slot has min/max picks (min 0: optional) and options; an option is a dish variant with an
     * optional modifier preset ("katta, mol go'shtli"), a price delta (0 or more, added to the combo's price), a
     * default flag and an optional receipt label. The kompleks (daily_sets, P6-15) stays as it is: a fixed set, no slots.
     */
    public function up(): void
    {
        Schema::create('combos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'is_active']);
        });

        Schema::create('combo_slots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('combo_id')->constrained('combos')->cascadeOnDelete();
            $table->string('name', 60); // "Asosiy", "Ichimlik", "Sous"
            $table->unsignedSmallInteger('min_picks')->default(1); // 0: optional
            $table->unsignedSmallInteger('max_picks')->default(1);
            // The buyer may change the part's own modifiers (their deltas are added); else the option's preset is sold
            $table->boolean('allow_modifiers')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('combo_id');
        });

        Schema::create('combo_slot_options', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('combo_slot_id')->constrained('combo_slots')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1); // pieces of the dish in one combo
            $table->decimal('price_delta', 12, 2)->default(0);
            $table->jsonb('modifiers')->nullable(); // preset: [{modifier_id, quantity}]
            $table->boolean('is_default')->default(false);
            $table->string('label', 80)->nullable(); // receipt text, e.g. "Ichimlik — 0,4 l Pepsi"
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('combo_slot_id');
            $table->index('product_variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_slot_options');
        Schema::dropIfExists('combo_slots');
        Schema::dropIfExists('combos');
    }
};
