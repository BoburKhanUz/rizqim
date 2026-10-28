<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P4 review F2: until when a team member without 2FA may set it up after the right password
     * (App\Services\Admin\TwoFactorService::setupAllowed). Opened for 24 hours by accepting an invite and by a 2FA reset;
     * closed when 2FA is turned on. Outside it the right password alone is refused (`setup_not_allowed`), so a guessed
     * password can no longer enroll the guesser's authenticator.
     *
     * Existing members without 2FA (e.g. the old super_admins moved to the team by 2026_11_01_100100) get no window
     * here on purpose: they are re-enrolled with `php artisan rizqim:admin:invite {email}` (docs/OPERATIONS.md §10).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('two_factor_setup_allowed_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_setup_allowed_until');
        });
    }
};
