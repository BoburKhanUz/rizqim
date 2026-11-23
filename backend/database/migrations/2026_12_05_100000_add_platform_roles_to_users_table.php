<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wave P: a RIZQIM team member holds a set of platform roles (App\Enums\PlatformRole values, a jsonb list in
     * PlatformRole::cases() order); their admin permissions are the union of the roles' permissions.
     *
     * users.platform_role stays as the member's primary role (the first of the set, so superadmin whenever the set
     * holds it): the many "is a team member" / "is a superadmin" filters and old admin app builds keep working, while
     * every permission and team rule reads the set (App\Models\User::platformRoles). The User model writes both
     * together; the CHECK below refuses any write around it that would let them drift apart.
     *
     * Every member's single role becomes a one-role set.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->jsonb('platform_roles')->nullable();
        });

        if (DB::getDriverName() !== 'pgsql') {
            DB::table('users')->whereNotNull('platform_role')->orderBy('id')->each(function (object $user) {
                DB::table('users')->where('id', $user->id)->update(['platform_roles' => json_encode([$user->platform_role])]);
            });

            return;
        }

        DB::statement('UPDATE users SET platform_roles = jsonb_build_array(platform_role) WHERE platform_role IS NOT NULL');

        // Both columns or neither; a non-empty list whose first entry is the primary role; superadmin in the set is the
        // primary role (so SQL filters on platform_role = 'superadmin' find every superadmin)
        DB::statement(<<<'SQL'
            ALTER TABLE users ADD CONSTRAINT users_platform_roles_check CHECK (
                (platform_roles IS NULL AND platform_role IS NULL)
                OR (
                    platform_roles IS NOT NULL AND platform_role IS NOT NULL
                    AND jsonb_typeof(platform_roles) = 'array'
                    AND jsonb_array_length(platform_roles) > 0
                    AND platform_roles -> 0 = to_jsonb(platform_role::text)
                    AND (platform_role = 'superadmin' OR NOT platform_roles @> '["superadmin"]'::jsonb)
                )
            )
            SQL);
    }

    /**
     * Back to one role per member: platform_role (the primary role) already holds it; a member's other roles are
     * dropped.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_platform_roles_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('platform_roles');
        });
    }
};
