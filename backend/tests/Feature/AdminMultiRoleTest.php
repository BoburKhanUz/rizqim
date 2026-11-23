<?php

namespace Tests\Feature;

use App\Enums\AdminPermission;
use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Admin\AdminAuthException;
use App\Services\Admin\AdminPermissionService;
use App\Services\Admin\PlatformTeamService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
}
