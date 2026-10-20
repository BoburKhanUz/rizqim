<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sold by weight (P1-21): a variant is sold per piece, kg or litre; `price` is per that unit. A measured unit has
     * a quantity step (0.1 kg) and a minimum; stock becomes a decimal so 0.5 kg can be taken from it. Products may be
     * cooked in batches (a kazan of plov) whose remaining portions decide availability (menu_batches).
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('unit', 8)->default('piece'); // App\Enums\MenuUnit
            $table->decimal('quantity_step', 8, 3)->nullable(); // measured units: e.g. 0.100
            $table->decimal('min_quantity', 8, 3)->nullable(); // measured units: e.g. 0.300
            // How much of the product's batch one unit uses: 1 portion, 0.7 for a "0.7 porsiya" variant
            $table->decimal('batch_consumption', 8, 3)->default(1);
        });

        // Whole numbers read back unchanged (10 → 10.000); the stock >= 0 check constraint stays
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('stock', 12, 3)->default(0)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            // Available on a branch only while it has an open batch with something left
            $table->boolean('batch_tracked')->default(false);
        });
    }

    public function down(): void
    {
        // An integer column would silently round 0.5 kg of stock (P1-34): refuse while a fraction is stored
        if (DB::table('product_variants')->whereRaw('stock <> FLOOR(stock)')->exists()) {
            throw new RuntimeException(
                "Rollback refused: product_variants.stock has fractional values (sold by weight). An integer column would "
                . "round them. Set those variants' stock to whole numbers (or delete them) first, then roll back again."
            );
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('batch_tracked');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->integer('stock')->default(0)->change();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['unit', 'quantity_step', 'min_quantity', 'batch_consumption']);
        });
    }
};
