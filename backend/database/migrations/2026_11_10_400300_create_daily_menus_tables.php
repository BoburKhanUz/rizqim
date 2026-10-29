<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Bugungi menyu" (P6-15, stolovaya): the dishes a branch cooks on a business day. A dish marked `daily_rotation`
     * is sold only while it is on the day's menu (a day without a menu row sells everything: the owner is only warned).
     * Dishes that are not rotating (bread, tea, drinks) are always on sale.
     */
    public function up(): void
    {
        Schema::create('daily_menus', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('menu_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'menu_date']);
        });

        Schema::create('daily_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_menu_id')->constrained('daily_menus')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['daily_menu_id', 'product_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('daily_rotation')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('daily_rotation');
        });
        Schema::dropIfExists('daily_menu_items');
        Schema::dropIfExists('daily_menus');
    }
};
