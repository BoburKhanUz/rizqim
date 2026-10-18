<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropForeign(['district_id']);
            $table->dropColumn(['region_id', 'district_id']);
        });
    }
};
