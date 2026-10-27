<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Models\AdminLoginChallenge;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Admin\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * P4-11: the admin sign-in: email + password, then TOTP (or the first-time 2FA set-up), recovery codes, lockout,
 * single-use challenges and replay protection. Every attempt is audited without secrets.
 */
class AdminAuthLoginTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;

    public function test_first_sign_in_sets_up_2fa_then_opens_a_session_with_recovery_codes(): void
    {
        $member = $this->makeMember(PlatformRole::OPERATOR, twoFactor: false);

        $login = $this->adminJson('POST', '/auth/login', null, ['email' => strtoupper($member->email), 'password' => $this->adminPassword])
            ->assertOk()
            ->assertJsonPath('data.status', 'setup_required');
        $challenge = $login->json('data.challenge_token');
        $this->assertNotNull($login->json('data.expires_at'));

        $setup = $this->adminJson('POST', '/auth/2fa/setup', null, ['challenge_token' => $challenge])->assertOk();
        $secret = $setup->json('data.secret');
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertStringStartsWith('otpauth://totp/RIZQIM:' . rawurlencode($member->email), $setup->json('data.otpauth_uri'));
        $this->assertStringContainsString('issuer=RIZQIM', $setup->json('data.otpauth_uri'));
        $this->assertStringStartsWith('<svg', $setup->json('data.qr_svg'));

        // A retried set-up request gets the same secret; nothing is saved on the account yet
        $this->adminJson('POST', '/auth/2fa/setup', null, ['challenge_token' => $challenge])->assertJsonPath('data.secret', $secret);
        $this->assertFalse($member->refresh()->hasTwoFactorEnabled());

        $this->adminJson('POST', '/auth/2fa/confirm', null, ['challenge_token' => $challenge, 'code' => '000000'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_code');

        $confirm = $this->adminJson('POST', '/auth/2fa/confirm', null, ['challenge_token' => $challenge, 'code' => $this->codeFor($secret)])
            ->assertOk()
            ->assertJsonPath('data.member.platform_role', 'operator')
            ->assertJsonPath('data.member.two_factor_enabled', true)
            ->assertJsonPath('data.member.recovery_codes_left', 10);
        $this->assertCount(10, $confirm->json('data.recovery_codes'));
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{5}-[A-Z2-9]{5}$/', $confirm->json('data.recovery_codes.0'));

        $member->refresh();
        $this->assertTrue($member->hasTwoFactorEnabled());
        $this->assertSame($secret, $member->getAppAuthenticationSecret());
        // At rest: encrypted secret, hashed recovery codes
        $raw = \DB::table('users')->where('id', $member->id)->first();
        $this->assertStringNotContainsString($secret, (string) $raw->app_authentication_secret);
        $this->assertStringNotContainsString($confirm->json('data.recovery_codes.0'), (string) $raw->app_authentication_recovery_codes);
        $this->assertTrue(Hash::check($confirm->json('data.recovery_codes.0'), $member->getAppAuthenticationRecoveryCodes()[0]));
        $this->assertArrayNotHasKey('app_authentication_secret', $member->toArray());

        $token = $confirm->json('data.token');
        $this->adminJson('GET', '/me', $token)
            ->assertOk()
            ->assertJsonPath('data.platform_role', 'operator')
            ->assertJsonPath('data.member.email', $member->email)
            ->assertJsonPath('data.two_factor_enabled', true)
            ->assertJsonPath('data.recovery_codes_left', 10)
            ->assertJsonPath('data.session.current', true);
        $this->adminJson('GET', '/auth/me', $token)->assertOk()->assertJsonPath('data.member.id', $member->id);

        // The challenge is spent; the secret is never shown again
        $this->adminJson('POST', '/auth/2fa/setup', null, ['challenge_token' => $challenge])->assertStatus(422)->assertJsonPath('code', 'challenge_invalid');

        // Set-up only once: the next sign-in asks for the code, and set-up is refused
        $next = $this->adminJson('POST', '/auth/login', null, ['email' => $member->email, 'password' => $this->adminPassword])
            ->assertJsonPath('data.status', 'otp_required');
        $this->adminJson('POST', '/auth/2fa/setup', null, ['challenge_token' => $next->json('data.challenge_token')])
            ->assertStatus(409)->assertJsonPath('code', 'two_factor_already_enabled');
        $this->adminJson('POST', '/auth/2fa/confirm', null, ['challenge_token' => $next->json('data.challenge_token'), 'code' => $this->codeFor($secret, 1)])
            ->assertStatus(409);
    }

    public function test_a_member_with_2fa_signs_in_with_the_code(): void
    {
        $member = $this->makeMember(PlatformRole::FINANCE);
        $challenge = $this->loginChallenge($member);

        $this->nextStep();
        $session = $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'code' => $this->codeFor($member)])
            ->assertOk()
            ->assertJsonPath('data.member.platform_role', 'finance')
            ->assertJsonPath('data.member.permissions', fn (array $permissions) => in_array('admin.payouts.approve', $permissions, true) && !in_array('admin.team.manage', $permissions, true));

        $this->assertNotNull($session->json('data.expires_at'));
        $this->assertNotNull($member->refresh()->platform_last_login_at);
        $this->adminJson('GET', '/me', $session->json('data.token'))->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.auth.login_succeeded')->where('user_id', $member->id)->count());
    }

    public function test_unknown_email_and_wrong_password_get_the_same_answer(): void
    {
        $member = $this->makeMember();

        $wrong = $this->adminJson('POST', '/auth/login', null, ['email' => $member->email, 'password' => 'wrong-password-123']);
        $unknown = $this->adminJson('POST', '/auth/login', null, ['email' => 'nobody@rizqim.test', 'password' => 'wrong-password-123']);

        $wrong->assertStatus(422)->assertJsonPath('code', 'invalid_credentials');
        $unknown->assertStatus(422);
        $this->assertSame($wrong->json(), $unknown->json());

        // A store owner or customer with a password is not a team member
        $customer = User::create(['name' => 'Mijoz', 'email' => 'mijoz@rizqim.test', 'password' => Hash::make($this->adminPassword)]);
        $this->adminJson('POST', '/auth/login', null, ['email' => $customer->email, 'password' => $this->adminPassword])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_credentials');

        // Every attempt is audited, never with the password
        $failed = AuditLog::where('action', 'admin.auth.login_failed')->get();
        $this->assertCount(3, $failed);
        $this->assertSame($member->id, $failed->first()->user_id);
        $this->assertNotNull($failed->first()->ip);
        foreach ($failed as $entry) {
            $this->assertStringNotContainsString('wrong-password-123', json_encode($entry->toArray()));
            $this->assertStringNotContainsString($this->adminPassword, json_encode($entry->toArray()));
        }
    }

    public function test_wrong_code_and_wrong_recovery_code_are_refused(): void
    {
        $member = $this->makeMember();
        $challenge = $this->loginChallenge($member);

        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'code' => $this->codeFor($member, 5)])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_code');
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'recovery_code' => 'ZZZZZ-ZZZZZ'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_recovery_code');
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'code' => '12ab56'])
            ->assertStatus(422);

        // The challenge is still good for the right code
        $this->nextStep();
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'code' => $this->codeFor($member)])->assertOk();
        $this->assertSame(2, AuditLog::where('action', 'admin.auth.2fa_failed')->count());
    }

    public function test_five_bad_passwords_lock_the_account_at_that_address_for_15_minutes(): void
    {
        $member = $this->makeMember();

        for ($i = 0; $i < 5; $i++) {
            $this->adminJson('POST', '/auth/login', null, ['email' => $member->email, 'password' => "wrong-password-{$i}"])->assertStatus(422);
        }

        // Even the right password now waits
        $this->adminJson('POST', '/auth/login', null, ['email' => $member->email, 'password' => $this->adminPassword])
            ->assertStatus(429)
            ->assertJsonPath('code', 'locked')
            ->assertHeader('Retry-After');

        // Another address is not locked out
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.8.7']);
        $this->adminJson('POST', '/auth/login', null, ['email' => $member->email, 'password' => $this->adminPassword])->assertOk();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);

        $this->travel(16)->minutes();
        $this->adminJson('POST', '/auth/login', null, ['email' => $member->email, 'password' => $this->adminPassword])->assertOk();
        $this->assertGreaterThanOrEqual(1, AuditLog::where('action', 'admin.auth.locked_out')->count());
    }

    public function test_five_bad_codes_lock_the_second_step(): void
    {
        $member = $this->makeMember();

        for ($i = 0; $i < 5; $i++) {
            $challenge = $this->loginChallenge($member);
            $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'code' => $this->codeFor($member, 10 + $i)])
                ->assertStatus(422);
        }

        $challenge = $this->loginChallenge($member);
        $this->nextStep();
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'code' => $this->codeFor($member)])
            ->assertStatus(429)->assertJsonPath('code', 'locked');

        $this->travel(16)->minutes();
        $challenge = $this->loginChallenge($member);
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'code' => $this->codeFor($member)])->assertOk();
    }

    public function test_a_challenge_expires_after_5_minutes_and_is_single_use(): void
    {
        $member = $this->makeMember();

        $challenge = $this->loginChallenge($member);
        $this->travel(5)->minutes();
        $this->travel(1)->seconds();
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'code' => $this->codeFor($member)])
            ->assertStatus(422)->assertJsonPath('code', 'challenge_invalid');

        $challenge = $this->loginChallenge($member);
        $this->nextStep();
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'code' => $this->codeFor($member)])->assertOk();
        $this->nextStep();
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'code' => $this->codeFor($member)])
            ->assertStatus(422)->assertJsonPath('code', 'challenge_invalid');

        // Only hashes are stored
        $this->assertFalse(AdminLoginChallenge::where('token_hash', $challenge)->exists());
        $this->assertTrue(AdminLoginChallenge::where('token_hash', hash('sha256', $challenge))->exists());
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => 'ac_made-up', 'code' => '123456'])->assertStatus(422);
    }

    public function test_a_recovery_code_signs_in_once(): void
    {
        $member = $this->makeMember();

        $challenge = $this->loginChallenge($member);
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'recovery_code' => 'aaaaa bbbbb'])
            ->assertOk()
            ->assertJsonPath('data.member.recovery_codes_left', 1);

        $challenge = $this->loginChallenge($member);
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'recovery_code' => 'AAAAA-BBBBB'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_recovery_code');
        $this->assertSame(1, app(TwoFactorService::class)->recoveryCodesLeft($member->refresh()));
    }

    public function test_a_code_is_not_accepted_twice_in_its_30_second_window(): void
    {
        $member = $this->makeMember();
        $this->nextStep();
        $code = $this->codeFor($member);

        $first = $this->loginChallenge($member);
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $first, 'code' => $code])->assertOk();

        $second = $this->loginChallenge($member);
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $second, 'code' => $code])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_code');
        // Nor an older one
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $second, 'code' => $this->codeFor($member, -1)])
            ->assertStatus(422);

        $this->nextStep();
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $second, 'code' => $this->codeFor($member)])->assertOk();
    }

    public function test_a_disabled_member_is_told_so_only_after_the_right_password(): void
    {
        $member = $this->makeMember(PlatformRole::SUPPORT, attributes: ['platform_disabled_at' => now()]);

        $this->adminJson('POST', '/auth/login', null, ['email' => $member->email, 'password' => 'wrong-password-123'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_credentials');
        $this->adminJson('POST', '/auth/login', null, ['email' => $member->email, 'password' => $this->adminPassword])
            ->assertStatus(403)->assertJsonPath('code', 'member_disabled');
    }

    public function test_an_existing_super_admin_becomes_a_platform_superadmin_and_sets_up_2fa_at_next_sign_in(): void
    {
        $legacy = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@rizqim.test',
            'password' => Hash::make($this->adminPassword),
            'role' => UserRole::SUPER_ADMIN,
        ]);

        $this->assertSame(PlatformRole::SUPERADMIN, $legacy->refresh()->platform_role);
        $this->adminJson('POST', '/auth/login', null, ['email' => $legacy->email, 'password' => $this->adminPassword])
            ->assertOk()->assertJsonPath('data.status', 'setup_required');
    }
}
