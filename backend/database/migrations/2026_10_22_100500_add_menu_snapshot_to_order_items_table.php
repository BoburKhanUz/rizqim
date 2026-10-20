<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Order lines keep what was sold as it was (P1-21): a decimal quantity (1.5 kg; whole numbers read back
     * unchanged), the unit, the chosen modifiers with their names and prices, and the batch the line was taken from
     * (so a cancellation gives it back there).
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('quantity', 10, 3)->default(1)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('unit', 8)->default('piece'); // App\Enums\MenuUnit at the time of the order
            // [{id, uuid, group_id, group_name, name, price_delta, quantity, total}], prices as decimal strings
            $table->jsonb('modifiers')->nullable();
            $table->decimal('modifiers_total', 12, 2)->default(0); // per piece (per line for a weighed item)
            $table->foreignId('batch_id')->nullable()->constrained('menu_batches')->nullOnDelete();
            $table->decimal('batch_quantity', 12, 3)->nullable(); // what the line took from the batch
        });
    }

    public function down(): void
    {
        // An integer column would silently round 1.5 kg sold (P1-34): order history is never rewritten, so refuse
        if (DB::table('order_items')->whereRaw('quantity <> FLOOR(quantity)')->exists()) {
            throw new RuntimeException(
                "Rollback refused: order_items.quantity has fractional values (items sold by weight). An integer column "
                . "would round them and rewrite order history. Keep this migration, or move those orders out first."
            );
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('batch_id');
            $table->dropColumn(['unit', 'modifiers', 'modifiers_total', 'batch_quantity']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->change();
        });
    }
};
