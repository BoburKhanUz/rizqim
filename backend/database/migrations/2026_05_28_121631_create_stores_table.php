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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('timezone')->default('Asia/Tashkent');
            $table->string('currency')->default('UZS');
            $table->string('language')->default('uz');
            $table->string('theme_color')->default('#000000');
            $table->string('telegram_bot_token')->nullable();
            $table->string('telegram_bot_username')->nullable();
            $table->string('telegram_admin_chat_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
