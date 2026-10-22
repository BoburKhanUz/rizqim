<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P3-26: a product the till and the waiter phone offer as a one-tap button ("tez qo'shish": choy, non, ...), listed in
 * GET /pos/bootstrap `quick_items` together with the branch's per-guest ("choy-non") items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('quick_add')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('quick_add');
        });
    }
};
