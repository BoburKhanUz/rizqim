<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P6-32 (B2): quick modifier hints for the cashier's tile. A group says where its options are shown
     * (`display_mode`, App\Enums\ModifierDisplayMode: dialog = the modifier window as before, inline = chips beside
     * the menu tile, size = a one-tap size selector) and an option may be the group's preselected one (`is_default`).
     * Hints only: the order always carries the options chosen explicitly, and a branch without the `quick_modifiers`
     * module is served the old behaviour (dialog, nothing preselected).
     */
    public function up(): void
    {
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->string('display_mode', 16)->default('dialog')->after('is_required');
        });

        Schema::table('modifiers', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('max_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('modifiers', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropColumn('display_mode');
        });
    }
};
