<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-31: a sold combo is ONE priced line (`combo_parts`: the parts as sold, like `modifiers` and `set_parts` never
     * rewritten by later menu edits) with one zero-priced kitchen line per part (`combo_parent_id`): each part goes to
     * its own station and is bumped there; the combo line is ready when all its parts are. The kitchen lines are hidden
     * from every order, bill, receipt and report query (OrderItem's global scope), so money only ever sees the combo.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->jsonb('combo_parts')->nullable();
            $table->foreignId('combo_parent_id')->nullable()->constrained('order_items')->cascadeOnDelete();

            $table->index('combo_parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('combo_parent_id');
            $table->dropColumn('combo_parts');
        });
    }
};
