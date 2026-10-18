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
        Schema::table('stores', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->default(10.00)->after('name')->comment('Percentage of commission taken by marketplace');
            $table->string('logo')->nullable()->after('address');
            $table->string('banner')->nullable()->after('logo');
            $table->text('description')->nullable()->after('banner');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['commission_rate', 'logo', 'banner', 'description']);
        });
    }
};
