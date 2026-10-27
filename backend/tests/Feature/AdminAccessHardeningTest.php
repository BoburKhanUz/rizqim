<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Filament\Auth\PlatformAppAuthentication;
use App\Filament\Pages\Auth\CustomLogin;
use App\Models\AdminLoginChallenge;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Admin\AdminAuthException;
use App\Services\Admin\AdminLoginLockout;
use App\Services\Admin\PlatformTeamService;
use App\Services\Admin\TwoFactorService;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Review of P4-11 (security F1-F3, F5-F7): the control center's access, attacked. A team.manage holder building a
 * stronger sock-puppet account, password guessing from rotating IPv4 / IPv6 addresses, a guessed password enrolling
 * the guesser's authenticator (API and Filament), the Filament 2FA outside the lockout and the audit, a flood of
 * made-up emails in the audit log, phone probing in the search, and a last-superadmin guard counting accounts nobody
 * can sign in as.
 */
class AdminAccessHardeningTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;
    use OrderLifecycleFixtures;

    private function fromAddress(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    private function login(string $email, string $password, string $ip = '127.0.0.1')
    {
        $this->fromAddress($ip);
        $response = $this->adminJson('POST', '/auth/login', null, ['email' => $email, 'password' => $password]);
        $this->fromAddress('127.0.0.1');

        return $response;
    }

    // F1 ------------------------------------------------------------------------------------------------------------

    public function test_only_a_superadmin_changes_the_role_matrix(): void
    {
        $superadmin = $this->makeMember();
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $token = $this->confirmedSession($superadmin);
        $this->adminJson('PUT', '/roles', $token, ['role' => 'operator', 'permission' => 'admin.team.manage', 'allowed' => true])->assertOk();

        // The operator manages the team now; they try to give their own role personal data and the system
        $operatorToken = $this->confirmedSession($operator);
        $this->adminJson('GET', '/roles', $operatorToken)->assertOk();
        foreach (['admin.pii.view', 'admin.system.manage', 'admin.orders.refund_approve', 'admin.audit.view'] as $permission) {
            $this->adminJson('PUT', '/roles', $operatorToken, ['role' => 'operator', 'permission' => $permission, 'allowed' => true])
                ->assertForbidden()->assertJsonPath('code', 'superadmin_only');
        }
        // Nor take a superadmin's grant back, nor hand team.manage on to another role
        $this->adminJson('PUT', '/roles', $operatorToken, ['role' => 'support', 'permission' => 'admin.team.manage', 'allowed' => true])
            ->assertForbidden();
        $this->adminJson('PUT', '/roles', $operatorToken, ['role' => 'operator', 'permission' => 'admin.team.manage', 'allowed' => null])
            ->assertForbidden();

        $this->adminJson('GET', '/me', $operatorToken)->assertJsonPath('data.permissions', fn (array $permissions) => !in_array('admin.pii.view', $permissions, true));
        $this->assertSame(1, AuditLog::where('action', 'admin.roles.updated')->count());
    }

    public function test_a_team_manager_who_is_no_superadmin_cannot_build_a_stronger_account(): void
    {
        $superadmin = $this->makeMember();
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $support = $this->makeMember(PlatformRole::SUPPORT);
        $finance = $this->makeMember(PlatformRole::FINANCE);
        $token = $this->confirmedSession($superadmin);
        $this->adminJson('PUT', '/roles', $token, ['role' => 'operator', 'permission' => 'admin.team.manage', 'allowed' => true])->assertOk();
        $invitedFinance = $this->adminJson('POST', '/team', $token, ['name' => 'Moliya 2', 'email' => 'm2@rizqim.test', 'platform_role' => 'finance'])
            ->assertCreated()->json('data.member.id');

        $operatorToken = $this->confirmedSession($operator);

        // Into a role holding refund approval, team.manage (the operator's own) or the superadmin role: no
        foreach (['finance', 'operator', 'superadmin'] as $role) {
            $this->adminJson('POST', '/team', $operatorToken, ['name' => 'Qo\'g\'irchoq', 'email' => "puppet-{$role}@rizqim.test", 'platform_role' => $role])
                ->assertForbidden()->assertJsonPath('code', 'superadmin_only');
        }
        // Promoting an existing member, or taking over a pending invite of a privileged role: no
        $this->adminJson('PATCH', "/team/{$support->id}", $operatorToken, ['platform_role' => 'finance'])->assertForbidden()->assertJsonPath('code', 'superadmin_only');
        $this->adminJson('POST', "/team/{$invitedFinance}/invite", $operatorToken)->assertForbidden()->assertJsonPath('code', 'superadmin_only');
        // Nor touching a member who holds one (disable the approver, reset their 2FA)
        $this->adminJson('PATCH', "/team/{$finance->id}", $operatorToken, ['disabled' => true])->assertForbidden();
        $this->adminJson('POST', "/team/{$finance->id}/reset-2fa", $operatorToken)->assertForbidden();
        $this->assertSame(PlatformRole::SUPPORT, $support->refresh()->platform_role);
        $this->assertSame(1, User::where('platform_role', 'finance')->whereNull('password')->count());

        // Roles without privileged permissions stay theirs to manage
        $this->adminJson('POST', '/team', $operatorToken, ['name' => 'Yordam 2', 'email' => 'y2@rizqim.test', 'platform_role' => 'support'])->assertCreated();
        $this->adminJson('PATCH', "/team/{$support->id}", $operatorToken, ['platform_role' => 'viewer'])->assertOk();

        // The superadmin does all of it
        $this->stepUp($superadmin, $token);
        $this->adminJson('PATCH', "/team/{$support->refresh()->id}", $token, ['platform_role' => 'finance'])->assertOk();
        $this->adminJson('POST', "/team/{$invitedFinance}/invite", $token)->assertOk();
    }

    // F2: password guessing ---------------------------------------------------------------------------------------

    public function test_bad_passwords_from_rotating_addresses_lock_the_account_everywhere(): void
    {
        $member = $this->makeMember();

        // 15 guesses, each from a new IPv4 address: under the per-address limit every time
        for ($i = 1; $i <= 15; $i++) {
            $this->login($member->email, "guess-{$i}-wrong", "203.0.113.{$i}")->assertStatus(422)->assertJsonPath('code', 'invalid_credentials');
        }

        // The account is locked from every address now, even for the right password
        $this->login($member->email, $this->adminPassword, '198.51.100.77')->assertStatus(429)->assertJsonPath('code', 'locked')->assertHeader('Retry-After');
        $this->assertSame(0, AdminLoginChallenge::count());

        $this->travel(16)->minutes();
        $this->login($member->email, $this->adminPassword, '198.51.100.77')->assertOk();
    }

    public function test_a_made_up_email_is_locked_the_same_way(): void
    {
        $member = $this->makeMember();
        for ($i = 1; $i <= 15; $i++) {
            $this->login('nobody@rizqim.test', "guess-{$i}-wrong", "203.0.113.{$i}");
            $this->login($member->email, "guess-{$i}-wrong", "203.0.113.{$i}");
        }

        // No difference to tell a team email from a made-up one
        $unknown = $this->login('nobody@rizqim.test', 'anything-at-all', '198.51.100.1')->assertStatus(429);
        $known = $this->login($member->email, 'anything-at-all', '198.51.100.1')->assertStatus(429);
        $this->assertSame($unknown->json('code'), $known->json('code'));
    }

    public function test_an_ipv6_slash_64_is_one_address(): void
    {
        $member = $this->makeMember();

        // A customer line hands out a whole /64: five guesses from five of its addresses
        for ($i = 1; $i <= 5; $i++) {
            $this->login($member->email, "guess-{$i}-wrong", "2001:db8:1:2::{$i}")->assertStatus(422);
        }

        $this->login($member->email, $this->adminPassword, '2001:db8:1:2:ffff:ffff:ffff:fffe')->assertStatus(429)->assertJsonPath('code', 'locked');
        // Another /64 is another address
        $this->login($member->email, $this->adminPassword, '2001:db8:1:3::1')->assertOk();

        $this->assertSame('2001:db8:1:2::/64', AdminLoginLockout::addressKey('2001:0db8:0001:0002:abcd::1'));
        $this->assertSame('192.0.2.9', AdminLoginLockout::addressKey('::ffff:192.0.2.9'));
        $this->assertSame('192.0.2.9', AdminLoginLockout::addressKey('192.0.2.9'));
        $this->assertSame('none', AdminLoginLockout::addressKey(null));
    }

    // F2: the right password on an account without 2FA ------------------------------------------------------------

    public function test_the_right_password_alone_does_not_enroll_a_member_without_2fa(): void
    {
        // An invite accepted long ago, the set-up abandoned; an attacker has the password
        $member = $this->makeMember(PlatformRole::FINANCE, twoFactor: false);

        $this->login($member->email, $this->adminPassword)
            ->assertStatus(403)->assertJsonPath('code', 'setup_not_allowed')->assertJsonMissingPath('data.challenge_token');
        $this->assertSame(0, AdminLoginChallenge::count());
        $this->assertFalse($member->refresh()->hasTwoFactorEnabled());
        $entry = AuditLog::where('action', 'admin.auth.login_failed')->sole();
        $this->assertSame('setup_not_allowed', $entry->payload_json['failure']);

        // A wrong password still gets the ordinary answer (no hint the account lacks 2FA)
        $this->login($member->email, 'wrong-password-123')->assertStatus(422)->assertJsonPath('code', 'invalid_credentials');
    }

    public function test_an_accepted_invite_opens_the_set_up_for_24_hours(): void
    {
        $superadmin = $this->makeMember();
        $token = $this->confirmedSession($superadmin);
        $inviteToken = $this->adminJson('POST', '/team', $token, ['name' => 'Aziz', 'email' => 'aziz@rizqim.test', 'platform_role' => 'operator'])
            ->assertCreated()->json('data.invite_token');

        $this->adminJson('POST', '/auth/invite/accept', null, ['token' => $inviteToken, 'password' => 'Yangi-parol-2026'])
            ->assertOk()->assertJsonPath('data.status', 'setup_required');

        // The set-up was abandoned; the member comes back the same day
        $this->travel(23)->hours();
        $this->login('aziz@rizqim.test', 'Yangi-parol-2026')->assertOk()->assertJsonPath('data.status', 'setup_required');

        // Two days later the window is shut: a superadmin re-issues it (2FA reset) and it opens again
        $this->travel(2)->hours();
        $this->login('aziz@rizqim.test', 'Yangi-parol-2026')->assertStatus(403)->assertJsonPath('code', 'setup_not_allowed');

        $member = User::where('email', 'aziz@rizqim.test')->sole();
        $token = $this->confirmedSession($superadmin);
        $this->adminJson('POST', "/team/{$member->id}/reset-2fa", $token)->assertOk();
        $challenge = $this->login('aziz@rizqim.test', 'Yangi-parol-2026')->assertOk()->assertJsonPath('data.status', 'setup_required')->json('data.challenge_token');

        $secret = $this->adminJson('POST', '/auth/2fa/setup', null, ['challenge_token' => $challenge])->json('data.secret');
        $this->adminJson('POST', '/auth/2fa/confirm', null, ['challenge_token' => $challenge, 'code' => $this->codeFor($secret)])->assertOk();
        $this->assertNull($member->refresh()->getAttribute('two_factor_setup_allowed_until'));
    }

    public function test_a_2fa_reset_opens_the_window_and_it_closes_after_24_hours(): void
    {
        $member = $this->makeMember(PlatformRole::SUPPORT);
        app(PlatformTeamService::class)->resetTwoFactor($member, null);

        $this->login($member->email, $this->adminPassword)->assertOk()->assertJsonPath('data.status', 'setup_required');
        $this->travel(25)->hours();
        $this->login($member->email, $this->adminPassword)->assertStatus(403)->assertJsonPath('code', 'setup_not_allowed');
    }

    public function test_the_invite_command_is_for_existing_members_only(): void
    {
        $disabled = $this->makeMember(PlatformRole::SUPERADMIN, twoFactor: false, attributes: ['platform_disabled_at' => now()]);
        $customer = User::create(['name' => 'Mijoz', 'email' => 'mijoz@rizqim.test', 'password' => Hash::make($this->adminPassword)]);

        $this->assertSame(1, Artisan::call('rizqim:admin:invite', ['email' => 'nobody@rizqim.test']));
        $this->assertSame(1, Artisan::call('rizqim:admin:invite', ['email' => $customer->email]));
        $this->assertSame(1, Artisan::call('rizqim:admin:invite', ['email' => $disabled->email]));
        $this->assertSame(1, Artisan::call('rizqim:admin:invite'));

        // A member with 2FA (a forgotten password): a new password, 2FA kept, every session ended
        $member = $this->makeMember(PlatformRole::OPERATOR);
        $session = $this->signIn($member);
        $this->assertSame(0, Artisan::call('rizqim:admin:invite', ['email' => $member->email, '--reason' => 'Parol unutildi']));
        preg_match('#/invite/(pi_[A-Za-z0-9]{48})#', Artisan::output(), $match);
        $this->adminJson('POST', '/auth/invite/accept', null, ['token' => $match[1], 'password' => 'Yangi-parol-2026'])
            ->assertOk()->assertJsonPath('data.status', 'otp_required');
        $this->adminJson('GET', '/me', $session)->assertUnauthorized();
        $this->assertTrue($member->refresh()->hasTwoFactorEnabled());
        $this->assertNull($member->getAttribute('two_factor_setup_allowed_until'));
        $this->assertSame('Parol unutildi', AuditLog::where('action', 'admin.cli.member_reinvited')->sole()->payload_json['reason']);
    }

    // F2 + F3: the Filament channel -------------------------------------------------------------------------------

    public function test_the_filament_set_up_does_not_enroll_outside_the_window(): void
    {
        $legacy = User::create([
            'name' => 'Old SA', 'email' => 'old-sa@rizqim.test', 'password' => Hash::make($this->adminPassword), 'role' => UserRole::SUPER_ADMIN,
        ]);
        Filament::setCurrentPanel(Filament::getPanel('superadmin'));
        $this->actingAs($legacy);
        /** @var PlatformAppAuthentication $provider */
        $provider = Filament::getPanel('superadmin')->getMultiFactorAuthenticationProviders()['app'];
        $twoFactor = app(TwoFactorService::class);

        // Signed in with the password only (2FA required): the set-up page explains instead of offering the set-up
        $this->get('/superadmin/multi-factor-authentication/set-up')->assertOk()->assertSee('rizqim:admin:invite');

        $secret = $twoFactor->generateSecret();
        $this->assertFalse($provider->verifyCode($twoFactor->currentCode($secret), $secret));
        try {
            $provider->saveSecret($legacy, $secret);
            $this->fail('The panel enrolled an authenticator outside the set-up window.');
        } catch (AuthorizationException) {
        }
        $this->assertFalse($legacy->refresh()->hasTwoFactorEnabled());
        $this->assertSame(2, AuditLog::where('action', 'admin.auth.2fa_setup_refused')->count());

        // Inside the window (a reset by another superadmin) the panel's set-up works and closes it
        app(PlatformTeamService::class)->resetTwoFactor($legacy, null);
        $this->actingAs($legacy->refresh());
        $this->assertTrue($provider->verifyCode($twoFactor->currentCode($secret), $secret));
        $provider->saveSecret($legacy, $secret);
        $this->assertTrue($legacy->refresh()->hasTwoFactorEnabled());
        $this->assertNull($legacy->getAttribute('two_factor_setup_allowed_until'));

        // Turning it off in the panel opens the window again (the set-up is required there)
        $provider->saveSecret($legacy, null);
        $this->assertTrue(app(TwoFactorService::class)->setupAllowed($legacy->refresh()));
    }

    public function test_filament_2fa_codes_count_in_the_lockout_and_are_audited(): void
    {
        $superadmin = User::create([
            'name' => 'Old SA', 'email' => 'old-sa@rizqim.test', 'password' => Hash::make($this->adminPassword), 'role' => UserRole::SUPER_ADMIN,
        ]);
        $secret = $this->enableTwoFactor($superadmin);
        Filament::setCurrentPanel(Filament::getPanel('superadmin'));
        /** @var PlatformAppAuthentication $provider */
        $provider = Filament::getPanel('superadmin')->getMultiFactorAuthenticationProviders()['app'];
        $provider->getChallengeFormComponents($superadmin->refresh());

        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($provider->verifyCode($this->codeFor($secret, 10 + $i), $secret, true));
        }
        $this->assertSame(5, AuditLog::where('action', 'admin.auth.2fa_failed')->where('user_id', $superadmin->id)->count());
        $this->assertSame('filament', AuditLog::where('action', 'admin.auth.2fa_failed')->first()->payload_json['channel']);

        // Locked: the right code waits, in the panel and in the admin API alike
        $this->nextStep();
        $this->assertFalse($provider->verifyCode($this->codeFor($secret), $secret, true));
        $this->assertFalse($provider->verifyRecoveryCode('AAAAA-BBBBB', $superadmin));
        $this->assertSame(2, app(TwoFactorService::class)->recoveryCodesLeft($superadmin->refresh()));
        $challenge = $this->loginChallenge($superadmin);
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'code' => $this->codeFor($secret)])
            ->assertStatus(429)->assertJsonPath('code', 'locked');
        $this->assertGreaterThanOrEqual(2, AuditLog::where('action', 'admin.auth.locked_out')->count());

        $this->travel(16)->minutes();
        $this->assertTrue($provider->verifyCode($this->codeFor($secret), $secret, true));
        $this->assertTrue($provider->verifyRecoveryCode('AAAAA-BBBBB', $superadmin));
        $this->assertSame(2, AuditLog::where('action', 'admin.auth.2fa_succeeded')->where('user_id', $superadmin->id)->count());
    }

    public function test_filament_password_guesses_share_the_admin_lockout(): void
    {
        $superadmin = User::create([
            'name' => 'Old SA', 'email' => 'old-sa@rizqim.test', 'password' => Hash::make($this->adminPassword), 'role' => UserRole::SUPER_ADMIN,
        ]);
        $this->enableTwoFactor($superadmin);
        Filament::setCurrentPanel(Filament::getPanel('superadmin'));

        // Five bad passwords in the panel (a minute apart: under Filament's own 5 a minute per address)
        for ($i = 0; $i < 5; $i++) {
            Livewire::test(CustomLogin::class)
                ->fillForm(['email' => $superadmin->email, 'password' => "wrong-password-{$i}"])
                ->call('authenticate')
                ->assertHasFormErrors(['email']);
            $this->travel(61)->seconds();
        }

        // The admin API sees the same count, and so does the panel itself
        $this->adminJson('POST', '/auth/login', null, ['email' => $superadmin->email, 'password' => $this->adminPassword])
            ->assertStatus(429)->assertJsonPath('code', 'locked');
        $login = Livewire::test(CustomLogin::class)
            ->fillForm(['email' => $superadmin->email, 'password' => $this->adminPassword])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
        $this->assertNull($login->get('userUndertakingMultiFactorAuthentication'));

        // Guesses through the API from rotating addresses lock the panel too
        $this->travel(16)->minutes();
        for ($i = 1; $i <= 15; $i++) {
            $this->login($superadmin->email, "guess-{$i}-wrong", "203.0.113.{$i}");
        }
        Livewire::test(CustomLogin::class)
            ->fillForm(['email' => $superadmin->email, 'password' => $this->adminPassword])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->travel(16)->minutes();
        $ok = Livewire::test(CustomLogin::class)
            ->fillForm(['email' => $superadmin->email, 'password' => $this->adminPassword])
            ->call('authenticate')
            ->assertHasNoFormErrors();
        $this->assertNotNull($ok->get('userUndertakingMultiFactorAuthentication'));
    }

    // F5 ------------------------------------------------------------------------------------------------------------

    public function test_made_up_emails_do_not_flood_the_audit_log(): void
    {
        Log::spy();
        $member = $this->makeMember();

        for ($i = 1; $i <= 40; $i++) {
            $this->login("made-up-{$i}@example.test", 'wrong-password-123', '203.0.113.' . $i);
        }
        $this->login($member->email, 'wrong-password-123');
        // Nor do made-up challenge tokens
        for ($i = 1; $i <= 3; $i++) {
            $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => "ac_made-up-{$i}", 'code' => '123456'])->assertStatus(422);
        }
        $this->assertSame(0, AuditLog::where('action', 'admin.auth.challenge_invalid')->count());

        // Only the team account's attempt is in the append-only log
        $this->assertSame(1, AuditLog::where('action', 'admin.auth.login_failed')->count());
        $this->assertSame($member->id, AuditLog::where('action', 'admin.auth.login_failed')->sole()->user_id);

        // The rest went to the app log, at most 30 lines a minute, without the email
        Log::shouldHaveReceived('warning')->times(30)->withArgs(function (string $message, array $context) {
            return str_contains($message, 'no team member') && !str_contains(json_encode($context), 'made-up');
        });
    }

    // F6 ------------------------------------------------------------------------------------------------------------

    public function test_search_by_phone_without_pii_view_needs_the_whole_number(): void
    {
        $this->setUpLifecycleStore();
        $this->store->forceFill(['name' => 'Choyxona', 'phone' => '+998901112233'])->save();
        $this->customer->forceFill(['name' => 'Karimova', 'phone' => '+998935556677'])->save();
        $support = $this->makeMember(PlatformRole::SUPPORT);
        $token = $this->signIn($support);

        // Fragments find nothing: masked "+998 93 *** ** 77" plus a fragment search would spell the number out
        foreach (['5556', '93555', '35556677', '0111'] as $fragment) {
            $this->adminJson('GET', '/search?q=' . $fragment, $token)->assertOk()->assertJsonCount(0, 'data');
        }

        // The whole number, however it is typed, is found (and still shown masked)
        foreach (['+998935556677', '93 555 66 77', '+998 (93) 555-66-77'] as $number) {
            $hit = collect($this->adminJson('GET', '/search?q=' . urlencode($number), $token)->json('data'))->firstWhere('type', 'user');
            $this->assertSame('/users/' . $this->customer->id, $hit['href'] ?? null, $number);
            $this->assertStringContainsString('+998 93 *** ** 77', $hit['subtitle']);
        }
        $store = collect($this->adminJson('GET', '/search?q=901112233', $token)->json('data'))->firstWhere('type', 'store');
        $this->assertSame('/stores/' . $this->store->id, $store['href']);
        $this->assertSame(0, AuditLog::where('action', 'admin.pii.revealed')->count());

        // With pii.view a fragment works and every number shown is an audited reveal
        $superToken = $this->signIn($this->makeMember());
        $hit = collect($this->adminJson('GET', '/search?q=5556', $superToken)->json('data'))->firstWhere('type', 'user');
        $this->assertStringContainsString('+998 93 555 66 77', $hit['subtitle']);
        $this->assertContains(['type' => 'user', 'id' => $this->customer->id, 'field' => 'phone'], AuditLog::where('action', 'admin.pii.revealed')->sole()->payload_json['revealed']);
    }

    // F7 ------------------------------------------------------------------------------------------------------------

    public function test_the_last_superadmin_guard_counts_only_superadmins_somebody_can_sign_in_as(): void
    {
        $only = $this->makeMember();
        // Superadmins on paper: an old one who never set 2FA up, an invite never accepted
        $this->makeMember(PlatformRole::SUPERADMIN, twoFactor: false);
        $this->makeMember(PlatformRole::SUPERADMIN, twoFactor: false, attributes: ['password' => null]);
        $actor = $this->makeMember(PlatformRole::SUPERADMIN, attributes: ['platform_disabled_at' => now()]);

        foreach ([[PlatformRole::OPERATOR, null], [null, true]] as [$role, $disabled]) {
            try {
                app(PlatformTeamService::class)->update($only, $role, $disabled, $actor);
                $this->fail('The last superadmin anyone can sign in as was demoted or disabled.');
            } catch (AdminAuthException $e) {
                $this->assertSame('last_superadmin', $e->reason);
            }
        }
        $this->assertSame(PlatformRole::SUPERADMIN, $only->refresh()->platform_role);
        $this->assertNull($only->platform_disabled_at);

        // A second real one: now it may go
        $this->makeMember(PlatformRole::SUPERADMIN);
        app(PlatformTeamService::class)->update($only, PlatformRole::OPERATOR, null, $actor);
        $this->assertSame(PlatformRole::OPERATOR, $only->refresh()->platform_role);
    }
}
