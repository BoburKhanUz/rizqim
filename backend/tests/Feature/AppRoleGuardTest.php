<?php

namespace Tests\Feature;

use App\Support\Database\OwnerConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * M4: the guard of the CI job tests-app-role. With DB_OWNER_USERNAME set the suite must really run as the application
 * role: not the owner, not a superuser, owning no table, not a member of the owner role. A job misconfigured to run as
 * the owner would otherwise prove nothing and stay green.
 */
class AppRoleGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_with_split_roles_the_tests_connect_as_a_dml_only_role(): void
    {
        $owner = OwnerConnection::username();
        if ($owner === null) {
            $this->markTestSkipped('One role (DB_OWNER_USERNAME not set): nothing to guard.');
        }

        $row = DB::selectOne(
            "SELECT current_user AS role, r.rolsuper AS super, pg_has_role(current_user, ?, 'MEMBER') AS member,
                    (SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = current_schema() AND c.relowner = r.oid) AS owned
               FROM pg_roles r WHERE r.rolname = current_user",
            [$owner],
        );
        $this->assertNotSame($owner, $row->role, 'DB_USERNAME is the owner');
        $this->assertFalse((bool) $row->super, "{$row->role} is a superuser");
        $this->assertFalse((bool) $row->member, "{$row->role} can SET ROLE to {$owner}");
        $this->assertSame(0, (int) $row->owned, "{$row->role} owns tables");
    }
}
