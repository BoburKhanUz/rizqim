<?php

namespace Tests\Feature;

use App\Enums\AdminPermission;
use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Admin\AdminAuthException;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\Customers\ComplaintInbox;
use App\Services\Admin\PlatformTeamService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Wave P: a team member holds a set of platform roles (users.platform_roles) and may do what any of them may (the
 * union). Every team rule keeps its meaning with sets: superadmin-only for superadmin and privileged roles, nobody
 * changes their own roles, the last superadmin stays, at least one role. users.platform_role stays as the primary role
 * (the first of the set in PlatformRole::cases() order) for old clients and SQL filters.
 */
class AdminMultiRoleTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;

    /** @var list<array<string, mixed>> */
    private array $warnings = [];

    /**
     * @param  list<PlatformRole>  $roles
     */
    private function makeMultiMember(array $roles, bool $twoFactor = true, array $attributes = []): User
    {
        return $this->makeMember($roles[0], $twoFactor, [...$attributes, 'platform_roles' => array_map(fn (PlatformRole $role) => $role->value, $roles)]);
    }

    public function test_a_two_role_member_gets_both_roles_permissions(): void
    {
        $member = $this->makeMultiMember([PlatformRole::OPERATOR, PlatformRole::SALES]);
        $permissions = app(AdminPermissionService::class)->permissionsFor($member);

        // Operator only, sales only, and both
        $this->assertContains(AdminPermission::ORDERS_INTERVENE, $permissions);
        $this->assertContains(AdminPermission::STORES_MANAGE, $permissions);
        $this->assertContains(AdminPermission::STORES_VIEW, $permissions);
        $this->assertNotContains(AdminPermission::TEAM_MANAGE, $permissions);
        $this->assertNotContains(AdminPermission::FINANCE_VIEW, $permissions);
        // In AdminPermission::cases() order, no duplicates
        $this->assertSame(array_values(array_filter(AdminPermission::cases(), fn ($p) => in_array($p, $permissions, true))), $permissions);

        $token = $this->signIn($member);
        $me = $this->adminJson('GET', '/me', $token)->assertOk()
            ->assertJsonPath('data.member.platform_roles', ['operator', 'sales'])
            ->assertJsonPath('data.member.platform_role', 'operator')
            ->assertJsonPath('data.platform_roles', ['operator', 'sales']);
        $this->assertContains('admin.orders.intervene', $me->json('data.permissions'));
        $this->assertContains('admin.stores.manage', $me->json('data.permissions'));

        // admin.can lets both roles' routes through: today.view (operator), stores.manage (sales: a validation error,
        // not forbidden), but not finance
        $this->adminJson('GET', '/today', $token)->assertOk();
        $this->adminJson('POST', '/store-applications', $token, [])->assertStatus(422);
        $this->adminJson('GET', '/team', $token)->assertForbidden()->assertJsonPath('code', 'forbidden');
    }

    public function test_overrides_apply_per_role_and_the_union_is_taken_after_them(): void
    {
        $superadmin = $this->makeMember();
        $member = $this->makeMultiMember([PlatformRole::OPERATOR, PlatformRole::SALES]);
        $service = app(AdminPermissionService::class);

        // Taking stores.view from sales leaves it with the operator role; taking it from both removes it
        $service->setOverride(PlatformRole::SALES, AdminPermission::STORES_VIEW, false, $superadmin);
        $this->assertTrue($service->has($member, AdminPermission::STORES_VIEW));
        $service->setOverride(PlatformRole::OPERATOR, AdminPermission::STORES_VIEW, false, $superadmin);
        $this->assertFalse($service->has($member, AdminPermission::STORES_VIEW));

        // Granting one role a permission reaches the member
        $service->setOverride(PlatformRole::SALES, AdminPermission::AUDIT_VIEW, true, $superadmin);
        $this->assertTrue($service->has($member, AdminPermission::AUDIT_VIEW));

        // Disabled: nothing at all
        $member->forceFill(['platform_disabled_at' => now()])->save();
        $this->assertSame([], $service->permissionsFor($member->refresh()));
    }

    public function test_superadmin_among_the_roles_is_everything_and_the_store_level_super_admin_role(): void
    {
        $member = $this->makeMultiMember([PlatformRole::VIEWER, PlatformRole::SUPERADMIN]);

        $this->assertSame([PlatformRole::SUPERADMIN, PlatformRole::VIEWER], $member->platformRoles());
        $this->assertSame(PlatformRole::SUPERADMIN, $member->platform_role);
        $this->assertTrue($member->isPlatformSuperadmin());
        $this->assertSame(UserRole::SUPER_ADMIN, $member->role);
        $this->assertSame(AdminPermission::cases(), app(AdminPermissionService::class)->permissionsFor($member));

        // Without superadmin it is a plain team account again
        $member->forceFill(['platform_roles' => ['viewer']])->save();
        $this->assertSame(UserRole::PLATFORM, $member->refresh()->role);
        $this->assertFalse($member->isPlatformSuperadmin());
        $this->assertSame(PlatformRole::VIEWER, $member->platform_role);
    }

    public function test_invite_and_edit_with_several_roles_and_the_team_list_shows_them_all(): void
    {
        $superadmin = $this->makeMember();
        $token = $this->confirmedSession($superadmin);

        $invite = $this->adminJson('POST', '/team', $token, ['name' => 'Jasur', 'email' => 'jasur@rizqim.test', 'platform_roles' => ['sales', 'operator']])
            ->assertCreated()
            ->assertJsonPath('data.member.platform_roles', ['operator', 'sales'])
            ->assertJsonPath('data.member.platform_role_labels', ['Operator', 'Savdo va ulash'])
            ->assertJsonPath('data.member.platform_role', 'operator');
        $member = User::findOrFail($invite->json('data.member.id'));
        $this->assertSame([PlatformRole::OPERATOR, PlatformRole::SALES], $member->platformRoles());
        $this->assertSame(UserRole::PLATFORM, $member->role);

        $list = collect($this->adminJson('GET', '/team', $token)->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(['operator', 'sales'], $list[$member->id]['platform_roles']);
        $this->assertSame(['superadmin'], $list[$superadmin->id]['platform_roles']);

        $this->stepUp($superadmin, $token);
        $this->adminJson('PATCH', "/team/{$member->id}", $token, ['platform_roles' => ['support', 'finance', 'viewer']])
            ->assertOk()->assertJsonPath('data.platform_roles', ['support', 'finance', 'viewer'])
            ->assertJsonPath('data.platform_role', 'support');

        // The legacy single field still works and replaces the whole set
        $this->adminJson('PATCH', "/team/{$member->id}", $token, ['platform_role' => 'sales'])
            ->assertOk()->assertJsonPath('data.platform_roles', ['sales']);
    }

    public function test_at_least_one_valid_role_is_required(): void
    {
        $superadmin = $this->makeMember();
        $member = $this->makeMultiMember([PlatformRole::OPERATOR, PlatformRole::SALES]);
        $token = $this->confirmedSession($superadmin);

        foreach ([[], ['nobody'], ['operator', 'operator'], 'operator', [null]] as $roles) {
            $this->adminJson('POST', '/team', $token, ['name' => 'Bo\'sh', 'email' => 'empty@rizqim.test', 'platform_roles' => $roles])
                ->assertStatus(422);
            $this->adminJson('PATCH', "/team/{$member->id}", $token, ['platform_roles' => $roles])->assertStatus(422);
        }
        // No role at all on an invite; both fields at once
        $this->adminJson('POST', '/team', $token, ['name' => 'Bo\'sh', 'email' => 'empty@rizqim.test'])->assertStatus(422);
        $this->adminJson('PATCH', "/team/{$member->id}", $token, ['platform_roles' => ['viewer'], 'platform_role' => 'support'])->assertStatus(422);

        $this->assertSame([PlatformRole::OPERATOR, PlatformRole::SALES], $member->refresh()->platformRoles());
        $this->assertSame(0, User::where('email', 'empty@rizqim.test')->count());

        // The service refuses an empty set too
        $this->expectException(\InvalidArgumentException::class);
        app(PlatformTeamService::class)->update($member, [], null, $superadmin);
    }

    public function test_a_team_manager_who_is_no_superadmin_gives_no_set_holding_superadmin_or_a_privileged_role(): void
    {
        $superadmin = $this->makeMember();
        $token = $this->confirmedSession($superadmin);
        // Support holds team.manage now, which makes it a privileged role itself
        $this->adminJson('PUT', '/roles', $token, ['role' => 'support', 'permission' => 'admin.team.manage', 'allowed' => true])->assertOk();
        // The actor holds support (team.manage) and sales: no superadmin among them
        $manager = $this->makeMultiMember([PlatformRole::SUPPORT, PlatformRole::SALES]);
        $managerToken = $this->confirmedSession($manager);

        // Inviting: a set with superadmin, finance (refund/payout approval) or support (team.manage now) is refused,
        // even next to harmless roles
        foreach ([['viewer', 'superadmin'], ['sales', 'finance'], ['operator', 'support']] as $i => $roles) {
            $this->adminJson('POST', '/team', $managerToken, ['name' => 'Qo\'g\'irchoq', 'email' => "puppet{$i}@rizqim.test", 'platform_roles' => $roles])
                ->assertForbidden()->assertJsonPath('code', 'superadmin_only');
        }
        $this->adminJson('POST', '/team', $managerToken, ['name' => 'Oddiy', 'email' => 'plain@rizqim.test', 'platform_roles' => ['operator', 'sales']])
            ->assertCreated();

        // Adding a privileged role (or superadmin) to a plain member's set: no
        $plain = $this->makeMultiMember([PlatformRole::OPERATOR, PlatformRole::SALES]);
        foreach ([['operator', 'sales', 'finance'], ['operator', 'superadmin'], ['operator', 'support']] as $roles) {
            $this->adminJson('PATCH', "/team/{$plain->id}", $managerToken, ['platform_roles' => $roles])
                ->assertForbidden()->assertJsonPath('code', 'superadmin_only');
        }
        $this->assertSame([PlatformRole::OPERATOR, PlatformRole::SALES], $plain->refresh()->platformRoles());
        // Plain roles stay theirs to manage
        $this->adminJson('PATCH', "/team/{$plain->id}", $managerToken, ['platform_roles' => ['viewer', 'sales']])->assertOk();

        // A member holding a privileged role next to plain ones: no role change (not even removing the privileged
        // one), no disabling, no 2FA reset, no new invite link
        $mixed = $this->makeMultiMember([PlatformRole::OPERATOR, PlatformRole::FINANCE]);
        $withSuper = $this->makeMultiMember([PlatformRole::SUPERADMIN, PlatformRole::SALES]);
        $invitedMixed = $this->makeMultiMember([PlatformRole::SALES, PlatformRole::FINANCE], false, ['password' => null]);
        foreach ([$mixed, $withSuper] as $target) {
            $this->adminJson('PATCH', "/team/{$target->id}", $managerToken, ['platform_roles' => ['operator']])
                ->assertForbidden()->assertJsonPath('code', 'superadmin_only');
            $this->adminJson('PATCH', "/team/{$target->id}", $managerToken, ['disabled' => true])
                ->assertForbidden()->assertJsonPath('code', 'superadmin_only');
            $this->adminJson('POST', "/team/{$target->id}/reset-2fa", $managerToken)
                ->assertForbidden()->assertJsonPath('code', 'superadmin_only');
        }
        $this->adminJson('POST', "/team/{$invitedMixed->id}/invite", $managerToken)->assertForbidden()->assertJsonPath('code', 'superadmin_only');
        $this->assertSame([PlatformRole::OPERATOR, PlatformRole::FINANCE], $mixed->refresh()->platformRoles());
        $this->assertSame([PlatformRole::SUPERADMIN, PlatformRole::SALES], $withSuper->refresh()->platformRoles());

        // The superadmin does all of it
        $this->stepUp($superadmin, $token);
        $this->adminJson('PATCH', "/team/{$mixed->id}", $token, ['platform_roles' => ['operator']])->assertOk();
        $this->adminJson('PATCH', "/team/{$plain->id}", $token, ['platform_roles' => ['viewer', 'finance']])->assertOk();
        $this->adminJson('POST', '/team', $token, ['name' => 'Ikki rol', 'email' => 'two@rizqim.test', 'platform_roles' => ['superadmin', 'finance']])->assertCreated();
    }

    public function test_nobody_changes_their_own_set_of_roles(): void
    {
        $superadmin = $this->makeMultiMember([PlatformRole::SUPERADMIN, PlatformRole::FINANCE]);
        $this->makeMember(); // another superadmin: the last-superadmin rule is not what refuses below
        $token = $this->confirmedSession($superadmin);

        foreach ([['superadmin'], ['superadmin', 'finance', 'sales'], ['finance']] as $roles) {
            $this->adminJson('PATCH', "/team/{$superadmin->id}", $token, ['platform_roles' => $roles])
                ->assertStatus(422)->assertJsonPath('code', 'cannot_change_self');
        }
        // The same set in another order is no change
        $this->adminJson('PATCH', "/team/{$superadmin->id}", $token, ['platform_roles' => ['finance', 'superadmin']])->assertOk();
        $this->assertSame(0, AuditLog::where('action', 'admin.team.updated')->count());

        // A non-superadmin team manager neither: their own set holds team.manage, a privileged permission, so the
        // superadmin-only rule already refuses before the self rule
        $this->stepUp($superadmin, $token);
        $this->adminJson('PUT', '/roles', $token, ['role' => 'sales', 'permission' => 'admin.team.manage', 'allowed' => true])->assertOk();
        $manager = $this->makeMultiMember([PlatformRole::OPERATOR, PlatformRole::SALES]);
        $managerToken = $this->confirmedSession($manager);
        foreach ([['sales'], ['operator', 'sales', 'viewer']] as $roles) {
            $this->adminJson('PATCH', "/team/{$manager->id}", $managerToken, ['platform_roles' => $roles])
                ->assertForbidden()->assertJsonPath('code', 'superadmin_only');
        }
        $this->assertSame([PlatformRole::OPERATOR, PlatformRole::SALES], $manager->refresh()->platformRoles());
    }

    public function test_the_last_superadmin_cannot_lose_superadmin_from_their_set_or_be_disabled(): void
    {
        $only = $this->makeMultiMember([PlatformRole::SUPERADMIN, PlatformRole::FINANCE]);
        // Somebody nobody can sign in as (disabled) acts through the service
        $actor = $this->makeMember(PlatformRole::SUPERADMIN, attributes: ['platform_disabled_at' => now()]);
        $team = app(PlatformTeamService::class);

        foreach ([[[PlatformRole::FINANCE], null], [[PlatformRole::FINANCE, PlatformRole::OPERATOR], null], [null, true]] as [$roles, $disabled]) {
            try {
                $team->update($only, $roles, $disabled, $actor);
                $this->fail('The last superadmin lost superadmin or was disabled.');
            } catch (AdminAuthException $e) {
                $this->assertSame('last_superadmin', $e->reason);
            }
        }
        $this->assertSame([PlatformRole::SUPERADMIN, PlatformRole::FINANCE], $only->refresh()->platformRoles());
        $this->assertNull($only->platform_disabled_at);

        // Changing the other roles while keeping superadmin is fine
        $team->update($only, [PlatformRole::SUPERADMIN, PlatformRole::SALES], null, $actor);
        $this->assertSame([PlatformRole::SUPERADMIN, PlatformRole::SALES], $only->refresh()->platformRoles());

        // A second superadmin who also holds another role counts: now superadmin may go
        $this->makeMultiMember([PlatformRole::SUPERADMIN, PlatformRole::OPERATOR]);
        $team->update($only, [PlatformRole::SALES], null, $actor);
        $this->assertSame([PlatformRole::SALES], $only->refresh()->platformRoles());
        $this->assertSame(UserRole::PLATFORM, $only->role);
    }

    public function test_role_changes_are_audited_with_the_sets_before_and_after(): void
    {
        $superadmin = $this->makeMember();
        $member = $this->makeMultiMember([PlatformRole::OPERATOR, PlatformRole::SALES]);
        $token = $this->confirmedSession($superadmin);

        $this->adminJson('PATCH', "/team/{$member->id}", $token, ['platform_roles' => ['sales', 'support']])->assertOk();
        $entry = AuditLog::where('action', 'admin.team.updated')->sole();
        $this->assertSame(['platform_roles' => ['operator', 'sales']], $entry->before);
        $this->assertSame(['platform_roles' => ['support', 'sales']], $entry->after);

        $invite = $this->adminJson('POST', '/team', $token, ['name' => 'Kamola', 'email' => 'kamola@rizqim.test', 'platform_roles' => ['viewer', 'sales']])->assertCreated();
        $invited = AuditLog::where('action', 'admin.team.invited')->sole();
        $this->assertSame(['sales', 'viewer'], $invited->after['platform_roles']);
        $this->assertSame($invite->json('data.member.id'), (int) $invited->model_id);
    }

    public function test_the_command_line_creates_a_member_with_several_roles(): void
    {
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('rizqim:admin:create', ['email' => 'ikki@rizqim.uz', 'name' => 'Ikki rol', '--role' => 'sales, operator']));
        $member = User::where('email', 'ikki@rizqim.uz')->sole();
        $this->assertSame([PlatformRole::OPERATOR, PlatformRole::SALES], $member->platformRoles());
        $this->assertSame(['operator', 'sales'], AuditLog::where('action', 'admin.cli.member_created')->sole()->after['platform_roles']);

        // One unknown role among them refuses the lot
        $this->assertSame(1, \Illuminate\Support\Facades\Artisan::call('rizqim:admin:create', ['email' => 'x@rizqim.uz', 'name' => 'X', '--role' => 'operator,owner']));
        $this->assertSame(1, \Illuminate\Support\Facades\Artisan::call('rizqim:admin:create', ['email' => 'x@rizqim.uz', 'name' => 'X', '--role' => ',']));
        $this->assertSame(0, User::where('email', 'x@rizqim.uz')->count());
    }

    public function test_the_database_keeps_the_primary_role_and_the_set_in_step(): void
    {
        $member = $this->makeMultiMember([PlatformRole::OPERATOR, PlatformRole::SALES]);
        $this->assertSame(['operator', 'sales'], json_decode((string) DB::table('users')->where('id', $member->id)->value('platform_roles'), true));
        $this->assertSame('operator', DB::table('users')->where('id', $member->id)->value('platform_role'));

        // A legacy write of the single role replaces the set; leaving the team clears both
        $member->forceFill(['platform_role' => PlatformRole::FINANCE])->save();
        $this->assertSame([PlatformRole::FINANCE], $member->refresh()->platformRoles());
        $member->forceFill(['role' => UserRole::CUSTOMER])->save();
        $this->assertNull($member->refresh()->platform_roles);
        $this->assertNull($member->platform_role);
        $this->assertFalse($member->isPlatformStaff());

        // users.role super_admin set elsewhere (user:assign-role, the old panel) adds superadmin to the set
        $other = $this->makeMember(PlatformRole::SUPPORT);
        $other->forceFill(['role' => UserRole::SUPER_ADMIN])->save();
        $this->assertSame([PlatformRole::SUPERADMIN, PlatformRole::SUPPORT], $other->refresh()->platformRoles());

        // Around the model, the database refuses a set out of step with the primary role, an empty set or a lone column
        foreach ([
            ['platform_roles' => json_encode(['viewer']), 'platform_role' => 'support'],
            ['platform_roles' => json_encode([]), 'platform_role' => null],
            ['platform_roles' => null, 'platform_role' => 'support'],
            ['platform_roles' => json_encode('support'), 'platform_role' => 'support'],
        ] as $row) {
            try {
                DB::transaction(fn () => DB::table('users')->where('id', $other->id)->update($row));
                $this->fail('An inconsistent role set was stored: ' . json_encode($row));
            } catch (QueryException) {
                // refused
            }
        }
    }

    /**
     * Review 16: a narrow column list without platform_roles made platformRoles() fall back to the primary role, so an
     * operator + support member (primary operator, no support.view) was missing from the picker although
     * assertAssignable() (full row) accepts them.
     */
    public function test_the_complaint_assignee_picker_counts_every_role_of_a_member(): void
    {
        $member = $this->makeMultiMember([PlatformRole::OPERATOR, PlatformRole::SUPPORT]);
        $operatorOnly = $this->makeMultiMember([PlatformRole::OPERATOR]);
        $this->assertTrue(app(AdminPermissionService::class)->has($member->fresh(), AdminPermission::SUPPORT_VIEW));

        $ids = array_column(app(ComplaintInbox::class)->assignees(), 'id');
        $this->assertContains((int) $member->id, $ids);
        $this->assertNotContains((int) $operatorOnly->id, $ids);
    }

    /**
     * Review 16: an unknown value in the set would make User::platformRoles() throw (the team list and that member's
     * every request a 500): the database stores known roles only, each once.
     */
    public function test_the_database_refuses_unknown_or_repeated_roles_in_the_set(): void
    {
        $member = $this->makeMultiMember([PlatformRole::OPERATOR]);
        foreach ([['operator', 'owner'], ['operator', 'operator'], ['operator', 7], ['operator', ['sales']], ['operator', 'sales', 'sales']] as $set) {
            try {
                DB::transaction(fn () => DB::table('users')->where('id', $member->id)->update(['platform_roles' => json_encode($set)]));
                $this->fail('The database stored the set ' . json_encode($set));
            } catch (QueryException) {
                // refused
            }
        }

        // A proper multi-role set still goes in around the model
        DB::table('users')->where('id', $member->id)->update(['platform_roles' => json_encode(['operator', 'sales', 'viewer'])]);
        $this->assertSame([PlatformRole::OPERATOR, PlatformRole::SALES, PlatformRole::VIEWER], $member->refresh()->platformRoles());
    }

    /**
     * Review 16: users.role is tied to the set: super_admin exactly when superadmin is among the roles, and a
     * store-level account (owner, cashier, customer...) holds no platform role.
     */
    public function test_the_database_ties_users_role_to_the_set(): void
    {
        $member = $this->makeMultiMember([PlatformRole::OPERATOR, PlatformRole::SALES]);
        $superadmin = $this->makeMultiMember([PlatformRole::SUPERADMIN, PlatformRole::FINANCE]);
        $this->assertSame(UserRole::SUPER_ADMIN, $superadmin->role);

        foreach ([
            [$member, ['role' => 'super_admin']],
            [$member, ['role' => 'owner']],
            [$member, ['role' => 'customer']],
            [$superadmin, ['role' => 'platform']],
        ] as [$user, $row]) {
            try {
                DB::transaction(fn () => DB::table('users')->where('id', $user->id)->update($row));
                $this->fail('Stored ' . json_encode($row) . ' for ' . json_encode($user->platform_roles));
            } catch (QueryException) {
                // refused
            }
        }

        // Store users keep working: no platform role, any store role
        $owner = User::factory()->create(['role' => UserRole::OWNER]);
        $this->assertNull(DB::table('users')->where('id', $owner->id)->value('platform_roles'));
        try {
            DB::transaction(fn () => DB::table('users')->where('id', $owner->id)->update(['platform_role' => 'viewer', 'platform_roles' => json_encode(['viewer'])]));
            $this->fail('A store owner was given a platform role around the model.');
        } catch (QueryException) {
            // refused
        }

        // Through the model, users.role platform takes superadmin out of the set (it used to leave them out of step)
        $superadmin->forceFill(['role' => UserRole::PLATFORM])->save();
        $this->assertSame([PlatformRole::FINANCE], $superadmin->refresh()->platformRoles());
        $this->assertSame(UserRole::PLATFORM, $superadmin->role);
    }

    /**
     * Review 16: the migration brings rows whose users.role and platform role disagree into line before its CHECK, and
     * logs every row it changed. For a team account the platform role decides users.role; a store-level account keeps
     * its store role and loses the platform role; users.role super_admin without a platform role is a superadmin (P4-11).
     */
    // Runs the migration's down()/up() (ALTER TABLE): as the schema owner when the roles are split (CI tests-app-role)
    #[\Tests\Concerns\AsSchemaOwner]
    public function test_the_migration_brings_rows_whose_role_and_platform_role_disagree_into_line(): void
    {
        $migration = require database_path('migrations/2026_12_05_100000_add_platform_roles_to_users_table.php');
        $this->captureWarnings();
        $migration->down();

        $insert = fn (string $role, ?string $platformRole) => DB::table('users')->insertGetId([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'name' => "{$role}/{$platformRole}", 'email' => uniqid('row') . '@rizqim.test', 'role' => $role, 'platform_role' => $platformRole,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $superOperator = $insert('super_admin', 'operator');
        $platformSuper = $insert('platform', 'superadmin');
        $ownerSales = $insert('owner', 'sales');
        $loneSuper = $insert('super_admin', null);
        $fine = $insert('platform', 'finance');
        $store = $insert('cashier', null);

        $migration->up();

        $row = fn (int $id) => (array) DB::table('users')->where('id', $id)->first(['role', 'platform_role', 'platform_roles']);
        $this->assertSame(['role' => 'platform', 'platform_role' => 'operator', 'platform_roles' => '["operator"]'], $row($superOperator));
        $this->assertSame(['role' => 'super_admin', 'platform_role' => 'superadmin', 'platform_roles' => '["superadmin"]'], $row($platformSuper));
        $this->assertSame(['role' => 'owner', 'platform_role' => null, 'platform_roles' => null], $row($ownerSales));
        $this->assertSame(['role' => 'super_admin', 'platform_role' => 'superadmin', 'platform_roles' => '["superadmin"]'], $row($loneSuper));
        $this->assertSame(['role' => 'platform', 'platform_role' => 'finance', 'platform_roles' => '["finance"]'], $row($fine));
        $this->assertSame(['role' => 'cashier', 'platform_role' => null, 'platform_roles' => null], $row($store));

        $this->assertSame([$loneSuper, $superOperator, $platformSuper, $ownerSales], array_column($this->warnings, 'user_id'));
        $this->assertSame(['role' => 'super_admin', 'platform_role' => 'operator'], $this->warnings[1]['before']);
        $this->assertSame(['role' => 'platform', 'platform_role' => 'operator'], $this->warnings[1]['after']);
    }

    /**
     * Review 16: rolling the migration back keeps one role per member (the primary one); the roles it drops are logged.
     */
    // Runs the migration's down()/up() (ALTER TABLE): as the schema owner when the roles are split (CI tests-app-role)
    #[\Tests\Concerns\AsSchemaOwner]
    public function test_rolling_back_keeps_the_primary_role_and_logs_the_dropped_ones(): void
    {
        $member = $this->makeMultiMember([PlatformRole::OPERATOR, PlatformRole::SALES, PlatformRole::VIEWER]);
        $single = $this->makeMultiMember([PlatformRole::FINANCE]);
        $migration = require database_path('migrations/2026_12_05_100000_add_platform_roles_to_users_table.php');
        $this->captureWarnings();

        $migration->down();

        $this->assertSame('operator', DB::table('users')->where('id', $member->id)->value('platform_role'));
        $this->assertSame('finance', DB::table('users')->where('id', $single->id)->value('platform_role'));
        $this->assertSame([['user_id' => (int) $member->id, 'kept' => 'operator', 'dropped' => ['sales', 'viewer']]], $this->warnings);

        $migration->up();
        $this->assertSame([PlatformRole::OPERATOR], $member->refresh()->platformRoles());
    }

    /**
     * Review 16: the superadmin-only rules are checked again on the member's row locked inside the transaction: a
     * member loaded before somebody else gave them a privileged role is not changed by a team manager on the strength
     * of the stale copy (two requests racing).
     */
    public function test_a_change_is_checked_against_the_member_row_locked_in_the_transaction(): void
    {
        $superadmin = $this->makeMember();
        app(AdminPermissionService::class)->setOverride(PlatformRole::SALES, AdminPermission::TEAM_MANAGE, true, $superadmin);
        $manager = $this->makeMultiMember([PlatformRole::OPERATOR, PlatformRole::SALES]);
        $target = $this->makeMultiMember([PlatformRole::OPERATOR, PlatformRole::VIEWER]);
        $team = app(PlatformTeamService::class);

        // The manager's request loaded the target; meanwhile the superadmin's request made them finance
        $stale = User::findOrFail($target->id);
        $team->update(User::findOrFail($target->id), [PlatformRole::OPERATOR, PlatformRole::FINANCE], null, $superadmin);

        foreach ([[[PlatformRole::VIEWER], null], [null, true]] as [$roles, $disabled]) {
            try {
                $team->update(clone $stale, $roles, $disabled, $manager);
                $this->fail('A team manager changed a member who holds a privileged role now.');
            } catch (AdminAuthException $e) {
                $this->assertSame('superadmin_only', $e->reason);
            }
        }
        $this->assertSame([PlatformRole::OPERATOR, PlatformRole::FINANCE], $target->refresh()->platformRoles());
        $this->assertNull($target->platform_disabled_at);

        // The last-superadmin rule reads the locked row too: a stale copy from before the member became the only
        // superadmin does not let them be disabled
        $only = $this->makeMultiMember([PlatformRole::OPERATOR]);
        $staleOnly = User::findOrFail($only->id);
        $team->update(User::findOrFail($only->id), [PlatformRole::SUPERADMIN, PlatformRole::OPERATOR], null, $superadmin);
        $superadmin->forceFill(['platform_disabled_at' => now()])->save();
        try {
            $team->update($staleOnly, null, true, $superadmin->refresh());
            $this->fail('The last superadmin was disabled through a stale copy.');
        } catch (AdminAuthException $e) {
            $this->assertSame('last_superadmin', $e->reason);
        }
        $this->assertNull($only->refresh()->platform_disabled_at);
    }

    /**
     * Review 16 probe: the legacy single field cannot give, nor take from, a privileged role.
     */
    public function test_the_legacy_single_field_cannot_grant_or_touch_a_privileged_role(): void
    {
        [, $mt] = $this->nonSuperadminTeamManager();
        $plain = $this->makeMultiMember([PlatformRole::OPERATOR, PlatformRole::VIEWER]);
        $mixed = $this->makeMultiMember([PlatformRole::OPERATOR, PlatformRole::FINANCE]);

        foreach (['superadmin', 'finance', 'sales'] as $legacy) {
            $this->adminJson('PATCH', "/team/{$plain->id}", $mt, ['platform_role' => $legacy])->assertForbidden();
        }
        $this->adminJson('PATCH', "/team/{$mixed->id}", $mt, ['platform_role' => 'operator'])->assertForbidden();
        $this->adminJson('POST', '/team', $mt, ['name' => 'Legacy', 'email' => 'l@rizqim.test', 'platform_role' => 'superadmin'])->assertForbidden();
        // Both fields with a harmless list and an empty/blank legacy value
        $this->adminJson('POST', '/team', $mt, ['name' => 'Both', 'email' => 'b@rizqim.test', 'platform_roles' => ['viewer'], 'platform_role' => 'superadmin'])->assertStatus(422);
        $this->adminJson('POST', '/team', $mt, ['name' => 'Both', 'email' => 'b2@rizqim.test', 'platform_roles' => [], 'platform_role' => 'superadmin'])->assertStatus(422);
        $this->adminJson('PATCH', "/team/{$plain->id}", $mt, ['platform_roles' => ['viewer'], 'platform_role' => ''])->assertStatus(422);
        // An object (keyed) list, a role in the wrong case
        $this->adminJson('PATCH', "/team/{$plain->id}", $mt, ['platform_roles' => ['a' => 'viewer', 'b' => 'superadmin']])->assertForbidden();
        $this->adminJson('PATCH', "/team/{$plain->id}", $mt, ['platform_roles' => ['Superadmin']])->assertStatus(422);
        $this->assertSame([PlatformRole::OPERATOR, PlatformRole::VIEWER], $plain->refresh()->platformRoles());
        $this->assertSame([PlatformRole::OPERATOR, PlatformRole::FINANCE], $mixed->refresh()->platformRoles());
        $this->assertSame(0, User::whereIn('email', ['l@rizqim.test', 'b@rizqim.test', 'b2@rizqim.test'])->count());
    }

    /**
     * Review 16 probe: a role made privileged by an override is protected even as a secondary role.
     */
    public function test_a_secondary_role_made_privileged_by_an_override_is_protected(): void
    {
        [$superadmin, $mt] = $this->nonSuperadminTeamManager();
        // viewer (last in PlatformRole::cases(), never the primary role next to another one) gets pii.view
        $st = $this->confirmedSession($superadmin);
        $this->adminJson('PUT', '/roles', $st, ['role' => 'viewer', 'permission' => 'admin.pii.view', 'allowed' => true])->assertOk();
        $target = $this->makeMultiMember([PlatformRole::SUPPORT, PlatformRole::VIEWER]);
        $this->adminJson('PATCH', "/team/{$target->id}", $mt, ['platform_roles' => ['support']])->assertForbidden();
        $this->adminJson('PATCH', "/team/{$target->id}", $mt, ['disabled' => true])->assertForbidden();
        $plain = $this->makeMultiMember([PlatformRole::SUPPORT]);
        $this->adminJson('PATCH', "/team/{$plain->id}", $mt, ['platform_roles' => ['support', 'viewer']])->assertForbidden();
    }

    /**
     * Review 16 probe: disabled and invited superadmins with other roles do not count as the remaining superadmin, an
     * active one does.
     */
    public function test_the_last_superadmin_rule_ignores_disabled_and_invited_multi_role_superadmins(): void
    {
        $only = $this->makeMultiMember([PlatformRole::SUPERADMIN, PlatformRole::FINANCE]);
        $this->makeMultiMember([PlatformRole::SUPERADMIN, PlatformRole::SALES], true, ['platform_disabled_at' => now()]);
        $this->makeMultiMember([PlatformRole::SUPERADMIN, PlatformRole::OPERATOR], false, ['password' => null]);
        $other = $this->makeMember();
        $ot = $this->confirmedSession($other);
        $this->adminJson('PATCH', "/team/{$only->id}", $ot, ['platform_roles' => ['finance']])->assertOk();
        $this->assertSame(UserRole::PLATFORM, $only->refresh()->role);
        $this->assertNotContains(AdminPermission::TEAM_MANAGE, app(AdminPermissionService::class)->permissionsFor($only));
    }

    /**
     * The context of every warning logged from now on goes to $this->warnings.
     */
    private function captureWarnings(): void
    {
        Log::listen(function (\Illuminate\Log\Events\MessageLogged $event) {
            if ($event->level === 'warning') {
                $this->warnings[] = $event->context;
            }
        });
    }

    /**
     * A superadmin, and a signed-in operator + sales member whose sales role was given team.manage.
     *
     * @return array{0: User, 1: string, 2: User}
     */
    private function nonSuperadminTeamManager(): array
    {
        $superadmin = $this->makeMember();
        $token = $this->confirmedSession($superadmin);
        $this->adminJson('PUT', '/roles', $token, ['role' => 'sales', 'permission' => 'admin.team.manage', 'allowed' => true])->assertOk();
        $manager = $this->makeMultiMember([PlatformRole::OPERATOR, PlatformRole::SALES]);

        return [$superadmin, $this->confirmedSession($manager), $manager];
    }
}
