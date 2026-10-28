<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4-11: the RIZQIM team (App\Enums\PlatformRole) and its sign-in to the admin API: email + password + TOTP.
     * The TOTP secret and the hashed recovery codes are encrypted at rest (casts on User) and shared with the old
     * Filament panels' app authentication (same columns), so both ask for the same code. Existing super admins become
     * platform superadmins; they set up 2FA on their next sign-in.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('platform_role', 16)->nullable()->index();
            $table->timestamp('platform_disabled_at')->nullable();
            $table->timestamp('platform_last_login_at')->nullable();
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            // The 30 s time step of the last accepted TOTP code: an older or the same code is refused (replay)
            $table->unsignedBigInteger('two_factor_last_timestep')->nullable();
        });

        DB::table('users')->where('role', 'super_admin')->whereNull('platform_role')->update(['platform_role' => 'superadmin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['platform_role']);
            $table->dropColumn([
                'platform_role', 'platform_disabled_at', 'platform_last_login_at', 'app_authentication_secret',
                'app_authentication_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_timestep',
            ]);
        });
    }
};
