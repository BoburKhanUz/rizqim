<?php

namespace Tests\Feature;

use App\Enums\AdminPermission;
use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\PlatformInvite;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * P4-11: Tizim → Jamoa and Rollar: invites and their acceptance, role changes and disabling with their guards
 * (yourself, the last superadmin, superadmin-only), 2FA resets, the editable role matrix and its effect on
 * `admin.can`; the artisan commands for the first superadmin and a lost phone.
 */
class AdminTeamTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;

    public function test_invite_accept_and_first_sign_in(): void
    {
        $superadmin = $this->makeMember();
        $token = $this->confirmedSession($superadmin);

        $invite = $this->adminJson('POST', '/team', $token, ['name' => 'Dilnoza', 'email' => 'Dilnoza@Rizqim.test', 'platform_role' => 'support'])
            ->assertCreated()
            ->assertJsonPath('data.member.status', 'invited')
            ->assertJsonPath('data.member.email', 'dilnoza@rizqim.test')
            ->assertJsonPath('data.member.platform_role', 'support')
            ->assertJsonPath('data.member.two_factor_enabled', false);
        $inviteToken = $invite->json('data.invite_token');
        $this->assertSame('http://localhost:3100/invite/' . $inviteToken, $invite->json('data.invite_url'));
        $this->assertSame(1, PlatformInvite::where('token_hash', hash('sha256', $inviteToken))->count());

        $member = User::where('email', 'dilnoza@rizqim.test')->sole();
        $this->assertSame(UserRole::PLATFORM, $member->role);
        $this->assertNull($member->password);
        // No sign-in before accepting
        $this->adminJson('POST', '/auth/login', null, ['email' => $member->email, 'password' => ''])->assertStatus(422);

        $this->adminJson('POST', '/auth/invite/accept', null, ['token' => $inviteToken, 'password' => 'short'])->assertStatus(422);
        $this->adminJson('POST', '/auth/invite/accept', null, ['token' => $inviteToken, 'password' => 'dilnoza@rizqim.test'])->assertStatus(422);

        $accepted = $this->adminJson('POST', '/auth/invite/accept', null, ['token' => $inviteToken, 'password' => 'Yangi-parol-2026'])
            ->assertOk()->assertJsonPath('data.status', 'setup_required');
        $this->adminJson('POST', '/auth/invite/accept', null, ['token' => $inviteToken, 'password' => 'Boshqa-parol-2026'])
            ->assertStatus(422)->assertJsonPath('code', 'invite_invalid');

        $challenge = $accepted->json('data.challenge_token');
        $secret = $this->adminJson('POST', '/auth/2fa/setup', null, ['challenge_token' => $challenge])->json('data.secret');
        $session = $this->adminJson('POST', '/auth/2fa/confirm', null, ['challenge_token' => $challenge, 'code' => $this->codeFor($secret)])
            ->assertOk()->json('data.token');

        $this->adminJson('GET', '/me', $session)->assertJsonPath('data.platform_role', 'support');
        $this->adminJson('GET', "/team/{$member->id}", $token)->assertJsonPath('data.status', 'active')->assertJsonPath('data.two_factor_enabled', true)
            ->assertJsonPath('data.active_sessions', 1);
        $this->adminJson('POST', '/auth/login', null, ['email' => $member->email, 'password' => 'Yangi-parol-2026'])->assertJsonPath('data.status', 'otp_required');

        $this->assertSame(1, AuditLog::where('action', 'admin.team.invited')->where('model_id', $member->id)->count());
        $this->assertSame(1, AuditLog::where('action', 'admin.auth.invite_accepted')->where('user_id', $member->id)->count());
        // The invite token never reaches the log
        $this->assertStringNotContainsString($inviteToken, AuditLog::all()->toJson());
    }

    public function test_an_invite_expires_after_72_hours_and_a_reissued_one_replaces_it(): void
    {
        $superadmin = $this->makeMember();
        $token = $this->confirmedSession($superadmin);

        $invite = $this->adminJson('POST', '/team', $token, ['name' => 'Aziz', 'email' => 'aziz@rizqim.test', 'platform_role' => 'operator'])->assertCreated();
        $memberId = $invite->json('data.member.id');
        $first = $invite->json('data.invite_token');

        $second = $this->adminJson('POST', "/team/{$memberId}/invite", $token)->assertOk()->json('data.invite_token');
        $this->adminJson('POST', '/auth/invite/accept', null, ['token' => $first, 'password' => 'Yangi-parol-2026'])
            ->assertStatus(422)->assertJsonPath('code', 'invite_invalid');

        $this->travel(73)->hours();
        $this->adminJson('POST', '/auth/invite/accept', null, ['token' => $second, 'password' => 'Yangi-parol-2026'])
            ->assertStatus(422)->assertJsonPath('code', 'invite_invalid');

        $token = $this->confirmedSession($superadmin);
        $third = $this->adminJson('POST', "/team/{$memberId}/invite", $token)->assertOk()->json('data.invite_token');
        $this->adminJson('POST', '/auth/invite/accept', null, ['token' => $third, 'password' => 'Yangi-parol-2026'])->assertOk();

        $this->stepUp($superadmin, $token);
        $this->adminJson('POST', "/team/{$memberId}/invite", $token)->assertStatus(409)->assertJsonPath('code', 'invite_already_accepted');
        $this->adminJson('POST', '/team', $token, ['name' => 'Aziz 2', 'email' => 'AZIZ@rizqim.test', 'platform_role' => 'operator'])
            ->assertStatus(422)->assertJsonPath('code', 'email_taken');
    }

    public function test_team_changes_need_team_manage_and_step_up(): void
    {
        $superadmin = $this->makeMember();
        $operator = $this->makeMember(PlatformRole::OPERATOR);

        $operatorToken = $this->confirmedSession($operator);
        $this->adminJson('GET', '/team', $operatorToken)->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->adminJson('POST', '/team', $operatorToken, ['name' => 'Xurshid', 'email' => 'x@rizqim.test', 'platform_role' => 'viewer'])->assertForbidden();
        $this->adminJson('GET', '/roles', $operatorToken)->assertForbidden();

        $token = $this->signIn($superadmin);
        $this->adminJson('GET', '/team', $token)->assertOk()->assertJsonCount(2, 'data');
        foreach ([
            ['POST', '/team', ['name' => 'Xurshid', 'email' => 'x@rizqim.test', 'platform_role' => 'viewer']],
            ['PATCH', "/team/{$operator->id}", ['platform_role' => 'viewer']],
            ['POST', "/team/{$operator->id}/invite", []],
            ['POST', "/team/{$operator->id}/reset-2fa", []],
            ['PUT', '/roles', ['role' => 'operator', 'permission' => 'admin.audit.view', 'allowed' => true]],
        ] as [$method, $uri, $data]) {
            $this->adminJson($method, $uri, $token, $data)->assertForbidden()->assertJsonPath('code', 'reauth_required');
        }

        $this->stepUp($superadmin, $token);
        $this->adminJson('PATCH', "/team/{$operator->id}", $token, ['platform_role' => 'viewer'])
            ->assertOk()->assertJsonPath('data.platform_role', 'viewer');
        $this->assertSame(PlatformRole::VIEWER, $operator->refresh()->platform_role);
        $this->assertSame(UserRole::PLATFORM, $operator->role);

        // Promoting to superadmin also gives the old store-level super_admin role; demoting takes it away
        $this->adminJson('PATCH', "/team/{$operator->id}", $token, ['platform_role' => 'superadmin'])->assertOk();
        $this->assertSame(UserRole::SUPER_ADMIN, $operator->refresh()->role);
        $this->adminJson('PATCH', "/team/{$operator->id}", $token, ['platform_role' => 'finance'])->assertOk();
        $this->assertSame(UserRole::PLATFORM, $operator->refresh()->role);
    }

    public function test_nobody_demotes_or_disables_themselves_and_the_last_superadmin_stays(): void
    {
        $superadmin = $this->makeMember();
        $token = $this->confirmedSession($superadmin);

        $this->adminJson('PATCH', "/team/{$superadmin->id}", $token, ['platform_role' => 'viewer'])
            ->assertStatus(422)->assertJsonPath('code', 'cannot_change_self');
        $this->adminJson('PATCH', "/team/{$superadmin->id}", $token, ['disabled' => true])
            ->assertStatus(422)->assertJsonPath('code', 'cannot_change_self');
        $this->adminJson('POST', "/team/{$superadmin->id}/reset-2fa", $token)
            ->assertStatus(422)->assertJsonPath('code', 'cannot_change_self');

        // A second superadmin, disabled: still the only active one is protected
        $second = $this->makeMember(PlatformRole::SUPERADMIN, attributes: ['platform_disabled_at' => now()]);
        $legacyOnly = $this->makeMember(PlatformRole::SUPERADMIN);
        $this->adminJson('PATCH', "/team/{$legacyOnly->id}", $token, ['disabled' => true])->assertOk();

        // Now $superadmin is the last active one: another superadmin cannot demote or disable them
        $this->adminJson('PATCH', "/team/{$second->id}", $token, ['disabled' => false])->assertOk();
        $secondToken = $this->confirmedSession($second->refresh());
        $this->adminJson('PATCH', "/team/{$superadmin->id}", $secondToken, ['platform_role' => 'operator'])->assertOk();
        $this->adminJson('PATCH', "/team/{$second->id}", $secondToken, ['disabled' => true])
            ->assertStatus(422)->assertJsonPath('code', 'cannot_change_self');

        // $second is the last superadmin now: nobody can demote them (the only one who could is themselves)
        $this->assertSame(1, User::where('platform_role', 'superadmin')->whereNull('platform_disabled_at')->count());
        $this->assertSame(1, AuditLog::where('action', 'admin.team.updated')->where('model_id', $superadmin->id)->count());
    }

    public function test_the_last_active_superadmin_cannot_be_demoted_or_disabled(): void
    {
        $only = $this->makeMember();
        $disabled = $this->makeMember(PlatformRole::SUPERADMIN);
        $token = $this->confirmedSession($disabled);
        $this->adminJson('PATCH', "/team/{$only->id}", $token, ['platform_role' => 'viewer'])->assertOk();

        // $disabled is the only active superadmin; grant team.manage to viewers, a viewer tries
        $this->stepUp($disabled, $token);
        $this->adminJson('PUT', '/roles', $token, ['role' => 'viewer', 'permission' => 'admin.team.manage', 'allowed' => true])->assertOk();
        $viewerToken = $this->confirmedSession($only->refresh());

        $this->adminJson('PATCH', "/team/{$disabled->id}", $viewerToken, ['disabled' => true])
            ->assertForbidden()->assertJsonPath('code', 'superadmin_only');
        $this->adminJson('POST', '/team', $viewerToken, ['name' => 'Yulduz', 'email' => 'y@rizqim.test', 'platform_role' => 'superadmin'])
            ->assertForbidden()->assertJsonPath('code', 'superadmin_only');
        $this->adminJson('POST', '/team', $viewerToken, ['name' => 'Yulduz', 'email' => 'y@rizqim.test', 'platform_role' => 'viewer'])->assertCreated();

        // Another superadmin (here a disabled one acting through the service) still cannot leave none
        try {
            app(\App\Services\Admin\PlatformTeamService::class)->update($disabled->refresh(), PlatformRole::OPERATOR, null, $this->makeMember(PlatformRole::SUPERADMIN, attributes: ['platform_disabled_at' => now()]));
            $this->fail('The last superadmin was demoted.');
        } catch (\App\Services\Admin\AdminAuthException $e) {
            $this->assertSame('last_superadmin', $e->reason);
        }
        $this->assertSame(PlatformRole::SUPERADMIN, $disabled->refresh()->platform_role);
    }

    public function test_reset_2fa_ends_sessions_and_the_member_sets_it_up_again(): void
    {
        $superadmin = $this->makeMember();
        $finance = $this->makeMember(PlatformRole::FINANCE);
        $financeToken = $this->signIn($finance);
        $token = $this->confirmedSession($superadmin);

        $this->adminJson('POST', "/team/{$finance->id}/reset-2fa", $token)->assertOk()->assertJsonPath('data.two_factor_enabled', false);
        $this->adminJson('GET', '/me', $financeToken)->assertUnauthorized();
        $this->assertSame([], $finance->refresh()->getAppAuthenticationRecoveryCodes() ?? []);
        $this->adminJson('POST', '/auth/login', null, ['email' => $finance->email, 'password' => $this->adminPassword])
            ->assertJsonPath('data.status', 'setup_required');
        $this->assertSame(1, AuditLog::where('action', 'admin.team.2fa_reset')->where('model_id', $finance->id)->count());
    }

    public function test_roles_matrix_and_overrides_change_what_admin_can_allows(): void
    {
        $superadmin = $this->makeMember();
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $operatorToken = $this->signIn($operator);
        $token = $this->confirmedSession($superadmin);

        $matrix = $this->adminJson('GET', '/roles', $token)->assertOk();
        $this->assertSame(['superadmin', 'operator', 'support', 'finance', 'sales', 'viewer'], array_column($matrix->json('data.roles'), 'key'));
        $this->assertFalse($matrix->json('data.roles.0.editable'));
        $this->assertSame("Faqat ko'rish", $matrix->json('data.roles.5.label'));
        $this->assertCount(count(AdminPermission::cases()), $matrix->json('data.permissions'));
        $this->assertSame(AdminPermission::values(), $matrix->json('data.grants.superadmin'));
        $viewer = $matrix->json('data.grants.viewer');
        $this->assertContains('admin.audit.view', $viewer);
        $this->assertContains('admin.today.view', $viewer);
        $this->assertNotContains('admin.pii.view', $viewer);
        $this->assertNotContains('admin.export', $viewer);
        $this->assertNotContains('admin.orders.intervene', $viewer);
        $this->assertContains('admin.marketing.view', $viewer);
        // The built-in map, before overrides
        $this->assertSame($matrix->json('data.grants'), $matrix->json('data.defaults'));

        $this->adminJson('GET', '/audit-logs', $operatorToken)->assertForbidden();

        $this->adminJson('PUT', '/roles', $token, ['role' => 'operator', 'permission' => 'admin.audit.view', 'allowed' => true])
            ->assertOk()
            ->assertJsonPath('data.grants.operator', fn (array $grants) => in_array('admin.audit.view', $grants, true))
            ->assertJsonPath('data.defaults.operator', fn (array $defaults) => !in_array('admin.audit.view', $defaults, true));
        $this->adminJson('GET', '/audit-logs', $operatorToken)->assertOk();
        $this->adminJson('GET', '/me', $operatorToken)->assertJsonPath('data.permissions', fn (array $permissions) => in_array('admin.audit.view', $permissions, true));

        // Take a default away, then reset it
        $this->adminJson('PUT', '/roles', $token, ['role' => 'operator', 'permission' => 'admin.today.view', 'allowed' => false])->assertOk();
        $this->assertFalse(app(AdminPermissionService::class)->has($operator, AdminPermission::TODAY_VIEW));
        $this->adminJson('PUT', '/roles', $token, ['role' => 'operator', 'permission' => 'admin.today.view', 'allowed' => null])->assertOk();
        $this->assertTrue(app(AdminPermissionService::class)->has($operator, AdminPermission::TODAY_VIEW));

        // The superadmin is fixed
        $this->adminJson('PUT', '/roles', $token, ['role' => 'superadmin', 'permission' => 'admin.team.manage', 'allowed' => false])
            ->assertStatus(422)->assertJsonPath('code', 'role_not_editable');
        $this->assertTrue(app(AdminPermissionService::class)->has($superadmin, AdminPermission::TEAM_MANAGE));

        $this->assertSame(3, AuditLog::where('action', 'admin.roles.updated')->count());
        // A disabled member holds nothing
        $operator->forceFill(['platform_disabled_at' => now()])->save();
        $this->assertSame([], app(AdminPermissionService::class)->permissionsFor($operator));
    }

    public function test_the_first_superadmin_is_created_from_the_command_line(): void
    {
        $this->assertSame(0, Artisan::call('rizqim:admin:create', ['email' => 'Boss@Rizqim.uz', 'name' => 'Bosh admin']));
        $output = Artisan::output();
        $this->assertMatchesRegularExpression('#http://localhost:3100/invite/pi_[A-Za-z0-9]{48}#', $output);
        preg_match('#/invite/(pi_[A-Za-z0-9]{48})#', $output, $match);

        $member = User::where('email', 'boss@rizqim.uz')->sole();
        $this->assertSame(PlatformRole::SUPERADMIN, $member->platform_role);
        $this->assertSame(UserRole::SUPER_ADMIN, $member->role);
        $this->assertSame(1, AuditLog::where('action', 'admin.cli.member_created')->where('model_id', $member->id)->count());

        $this->adminJson('POST', '/auth/invite/accept', null, ['token' => $match[1], 'password' => 'Juda-uzun-parol-1'])
            ->assertOk()->assertJsonPath('data.status', 'setup_required');

        $this->assertSame(1, Artisan::call('rizqim:admin:create', ['email' => 'boss@rizqim.uz', 'name' => 'Again']));
        $this->assertSame(1, Artisan::call('rizqim:admin:create', ['email' => 'x@rizqim.uz', 'name' => 'X', '--role' => 'owner']));
    }

    public function test_a_lost_2fa_is_reset_from_the_command_line(): void
    {
        $member = $this->makeMember(PlatformRole::SUPERADMIN);
        $token = $this->signIn($member);

        $this->assertSame(0, Artisan::call('rizqim:admin:reset-2fa', ['email' => strtoupper($member->email), '--force' => true, '--reason' => "Telefon yo'qoldi"]));
        $this->assertFalse($member->refresh()->hasTwoFactorEnabled());
        $this->adminJson('GET', '/me', $token)->assertUnauthorized();

        $entry = AuditLog::where('action', 'admin.cli.2fa_reset')->sole();
        $this->assertSame($member->id, (int) $entry->model_id);
        $this->assertSame("Telefon yo'qoldi", $entry->payload_json['reason']);

        $this->assertSame(1, Artisan::call('rizqim:admin:reset-2fa', ['email' => 'nobody@rizqim.test', '--force' => true]));
    }
}
