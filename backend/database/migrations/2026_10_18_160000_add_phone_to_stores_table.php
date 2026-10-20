<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The store's own contact number. The creation forms and the settings pages already asked for it, but without a
     * column the model dropped it without a word.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
