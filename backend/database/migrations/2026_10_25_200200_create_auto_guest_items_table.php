<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P3-12: the "choy-non" rule (R11): menu items a hall order gets per guest automatically, when it opens and when guests
 * join. A branch-wide rule (`hall_id` null) or a hall's own (which replaces the branch rule of the same item there;
 * an inactive hall rule switches it off in that hall).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_guest_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hall_id')->nullable()->constrained('halls')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->decimal('quantity_per_guest', 12, 3);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['branch_id', 'hall_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX auto_guest_items_scope ON auto_guest_items (branch_id, COALESCE(hall_id, 0), product_variant_id)');
            DB::statement('ALTER TABLE auto_guest_items ADD CONSTRAINT auto_guest_items_quantity_positive CHECK (quantity_per_guest > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_guest_items');
    }
};
