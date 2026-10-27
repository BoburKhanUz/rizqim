<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-11: an admin session is a Sanctum token row (name and only ability `admin`, App\Auth\AdminTokenGuard); these
     * describe it in "Sessiyalarim" and hold the step-up confirmation (POST /admin/auth/confirm, `admin.recent`).
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('reauthenticated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent', 'reauthenticated_at']);
        });
    }
};
