<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2-11: the kitchen ticket per line (docs/API.md, docs/POS_SPEC.md §2.8): a cook bumps each line "ready" on the
 * kitchen display (App\Enums\KitchenItemStatus) and the time is kept. A constant default, so PostgreSQL adds the column
 * without rewriting the table; older lines read as `pending` (kitchen screens only list orders still being cooked).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('kitchen_status', 16)->default('pending');
            $table->timestamp('ready_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['kitchen_status', 'ready_at']);
        });
    }
};
