<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-31 Tizim → Viloyatlar va tumanlar: the order the team gives regions and districts (the Mini App's address form
     * and the admin pickers list them by it, then by name). Every existing row starts at 0, so the order stays
     * alphabetical until somebody changes it.
     */
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
        });

        Schema::table('districts', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            $table->index(['region_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('districts', function (Blueprint $table) {
            $table->dropIndex(['region_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
