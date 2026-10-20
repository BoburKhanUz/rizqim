<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Batch / kazan stock (P1-21): what a branch cooked of a product (or one variant of it) and how much is left.
     * Orders take from quantity_left atomically and cancellations give it back; at 0 the item is "tugadi" on that
     * branch until a new batch opens. At most one open batch per branch and product/variant.
     */
    public function up(): void
    {
        Schema::create('menu_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // null: the batch serves every variant of the product, each using its batch_consumption per unit
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('label', 100)->nullable(); // "2-qozon"
            $table->decimal('quantity_produced', 12, 3);
            $table->decimal('quantity_left', 12, 3);
            $table->timestamp('started_at');
            $table->timestamp('sold_out_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'closed_at']);
            $table->index(['store_id', 'started_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE menu_batches ADD CONSTRAINT menu_batches_left_non_negative CHECK (quantity_left >= 0)');
            DB::statement('CREATE UNIQUE INDEX menu_batches_one_open ON menu_batches (branch_id, product_id, COALESCE(product_variant_id, 0)) WHERE closed_at IS NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_batches');
    }
};
