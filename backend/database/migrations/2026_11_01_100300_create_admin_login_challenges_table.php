<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-11: the step between a correct password and an admin session (POST /admin/auth/login): valid 5 minutes,
     * spent by the first session it opens. Only the SHA-256 of the token is stored. A 2FA set-up keeps the new secret
     * here (encrypted) until the first code confirms it, so an unconfirmed set-up never replaces the account's secret.
     */
    public function up(): void
    {
        Schema::create('admin_login_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('purpose', 16); // otp | setup
            $table->text('pending_secret')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_login_challenges');
    }
};
