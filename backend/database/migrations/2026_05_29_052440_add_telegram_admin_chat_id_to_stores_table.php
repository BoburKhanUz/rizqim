<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('stores', 'telegram_admin_chat_id')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->string('telegram_admin_chat_id')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('stores', 'telegram_admin_chat_id')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn('telegram_admin_chat_id');
            });
        }
    }
};
