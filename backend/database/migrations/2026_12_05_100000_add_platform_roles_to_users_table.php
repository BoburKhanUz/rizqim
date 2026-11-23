<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     *
     * Review 16: the CHECK also limits the set to known roles, each once, and ties users.role to it: super_admin exactly
     * when superadmin is in the set, and an account with a store-level role (owner, cashier, customer...) holds no
     * platform role. Rows where the two already disagreed are brought into line first, each logged: for a team account
     * the platform role decides users.role (super_admin with another platform role becomes platform, platform with
     * superadmin becomes super_admin); a store-level account keeps its store role and loses the platform role;
     * super_admin without a platform role becomes a superadmin (the P4-11 rule). users.role platform without any
     * platform role (an account that left the team out of step) stays allowed: it signs in nowhere.
     */
    private const ROLES = ['superadmin', 'operator', 'support', 'finance', 'sales', 'viewer'];

    public function up(): void
    {
        $this->alignRoles();

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

        $known = json_encode(self::ROLES);
        // No repeats: a set within the known roles holds each at most once exactly when its length is the number of
        // known roles it contains (a subquery is not allowed in a CHECK)
        $distinct = implode(' + ', array_map(fn (string $role) => "(platform_roles @> '[\"{$role}\"]'::jsonb)::int", self::ROLES));

        // Both columns or neither; a non-empty list of known roles, each once, whose first entry is the primary role;
        // superadmin in the set is the primary role (so SQL filters on platform_role = 'superadmin' find every
        // superadmin) and goes with users.role super_admin; a store-level users.role holds no set
        DB::statement(<<<SQL
            ALTER TABLE users ADD CONSTRAINT users_platform_roles_check CHECK (
                (platform_roles IS NULL AND platform_role IS NULL AND role <> 'super_admin')
                OR (
                    platform_roles IS NOT NULL AND platform_role IS NOT NULL
                    AND jsonb_typeof(platform_roles) = 'array'
                    AND jsonb_array_length(platform_roles) > 0
                    AND platform_roles <@ '{$known}'::jsonb
                    AND jsonb_array_length(platform_roles) = {$distinct}
                    AND platform_roles -> 0 = to_jsonb(platform_role::text)
                    AND (platform_role = 'superadmin' OR NOT platform_roles @> '["superadmin"]'::jsonb)
                    AND role IN ('super_admin', 'platform')
                    AND (role = 'super_admin') = (platform_roles @> '["superadmin"]'::jsonb)
                )
            )
            SQL);
    }

    /**
     * Back to one role per member: platform_role (the primary role) already holds it; a member's other roles are
     * dropped, and each member who loses one is logged (docs/OPERATIONS.md).
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'platform_roles')) {
            DB::table('users')->whereNotNull('platform_roles')->orderBy('id')->each(function (object $user) {
                $roles = json_decode((string) $user->platform_roles, true);
                $dropped = is_array($roles) ? array_values(array_diff($roles, [$user->platform_role])) : [];
                if ($dropped !== []) {
                    $this->report('platform_roles rollback: secondary roles dropped', [
                        'user_id' => (int) $user->id, 'kept' => $user->platform_role, 'dropped' => $dropped,
                    ]);
                }
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_platform_roles_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('platform_roles');
        });
    }

    /**
     * Rows whose users.role and platform_role disagree, brought into line before the CHECK (see the class comment).
     */
    private function alignRoles(): void
    {
        $fixes = [
            // users.role super_admin without a platform role: a superadmin (P4-11)
            [fn ($q) => $q->where('role', 'super_admin')->whereNull('platform_role'), ['platform_role' => 'superadmin']],
            // A team account: the platform role decides users.role
            [fn ($q) => $q->where('role', 'super_admin')->whereNotNull('platform_role')->where('platform_role', '<>', 'superadmin'), ['role' => 'platform']],
            [fn ($q) => $q->where('role', 'platform')->where('platform_role', 'superadmin'), ['role' => 'super_admin']],
            // A store-level account keeps its store role and holds no platform role
            [fn ($q) => $q->whereNotIn('role', ['super_admin', 'platform'])->whereNotNull('platform_role'), ['platform_role' => null]],
        ];

        foreach ($fixes as [$scope, $change]) {
            $scope(DB::table('users'))->orderBy('id')->get(['id', 'role', 'platform_role'])->each(function (object $user) use ($change) {
                $this->report('platform_roles migration: role and platform role brought into line', [
                    'user_id' => (int) $user->id,
                    'before' => ['role' => $user->role, 'platform_role' => $user->platform_role],
                    'after' => [...['role' => $user->role, 'platform_role' => $user->platform_role], ...$change],
                ]);
                DB::table('users')->where('id', $user->id)->update($change);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function report(string $message, array $context): void
    {
        Log::warning($message, $context);
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            fwrite(STDOUT, "  {$message}: " . json_encode($context) . PHP_EOL);
        }
    }
};
