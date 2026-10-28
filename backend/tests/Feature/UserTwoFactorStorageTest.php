<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Models\User;
use App\Services\Admin\TwoFactorService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P4-33: the TOTP secret and the recovery codes used to be stored through Filament's app-authentication traits; User
 * keeps the same columns and casts (`encrypted`, `encrypted:array` of bcrypt hashes) with its own code. A member who
 * enrolled before still signs in with their authenticator app and their saved recovery codes.
 */
class UserTwoFactorStorageTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;

    /** An APP_KEY fixed for the stored values below */
    private const KEY = 'rrrrrrrrrrrrrrrrrrrrrrrrrrrrrrrr';

    private const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    /** The secret as the old traits' `encrypted` cast wrote it with KEY (Crypt::encryptString) */
    private const STORED_SECRET = 'eyJpdiI6IlMySHBzVy9OU0F3d1Y5YWNXYTQzNlE9PSIsInZhbHVlIjoidkRWVW1XdlhWMGkrZ3dCZCtnREw5WDBkNzNVZmhDTXpBang5YTJlbGNTSmFiaFlWMG84TW1IcW1XSko4NWphUiIsIm1hYyI6ImZjNjlkZWNjMmUyMzIyMjMyYmZjMjgyYjUyZTE1YmI1YWM4Zjg0ZjVmMTNmOGFlYmZjZTRmOTRmZDMxYmVkYjQiLCJ0YWciOiIifQ==';

    /** The recovery codes as the old traits' `encrypted:array` cast wrote them with KEY: one bcrypt hash of QWERT-YUPAS */
    private const STORED_CODES = 'eyJpdiI6IlkraHZpZDltQnR3djJSQ05kSjZFVnc9PSIsInZhbHVlIjoibjRwUUJiUjRvT0l6OVpGQ01QVURmZ2hlZkRBMFVUTlJrUFAzQStISmlXT0xwUnIvMVBEbWJ3MkdzZTg5UlYxWXZaUkVDQlEwNThralNQMVg3RVk2NmlkdWhuVnAvUHRaT3pWcE51ZjVna3c9IiwibWFjIjoiOTQzMzk2MWY5Y2UyNzI5MDFkNzM2MWJmZmYwN2VmN2QwN2JlYWFlMzk0MWZmZWUwN2E2NDJiNzY2Njk3YjFkZCIsInRhZyI6IiJ9';

    protected function tearDown(): void
    {
        Model::encryptUsing(null);

        parent::tearDown();
    }

    public function test_a_member_enrolled_before_still_passes_2fa_with_the_app_and_a_recovery_code(): void
    {
        Model::encryptUsing(new Encrypter(self::KEY, 'aes-256-cbc'));
        $member = $this->makeMember(PlatformRole::SUPERADMIN, twoFactor: false);
        DB::table('users')->where('id', $member->id)->update([
            'app_authentication_secret' => self::STORED_SECRET,
            'app_authentication_recovery_codes' => self::STORED_CODES,
            'two_factor_confirmed_at' => now()->subMonth(),
        ]);

        $member = $member->fresh();
        $this->assertTrue($member->hasTwoFactorEnabled());
        $this->assertSame(self::SECRET, $member->getAppAuthenticationSecret());
        $this->assertSame(1, app(TwoFactorService::class)->recoveryCodesLeft($member));

        // The code from the authenticator app
        $challenge = $this->loginChallenge($member);
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'code' => $this->codeFor(self::SECRET)])
            ->assertOk()
            ->assertJsonPath('data.member.two_factor_enabled', true)
            ->assertJsonPath('data.member.recovery_codes_left', 1);

        // A saved recovery code (typed loosely), once
        $challenge = $this->loginChallenge($member);
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'recovery_code' => 'qwert yupas'])
            ->assertOk()
            ->assertJsonPath('data.member.recovery_codes_left', 0);
        $challenge = $this->loginChallenge($member);
        $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'recovery_code' => 'QWERT-YUPAS'])
            ->assertStatus(422);
        $this->assertSame(self::SECRET, $member->fresh()->getAppAuthenticationSecret());
    }

    public function test_the_secret_and_codes_are_written_in_the_same_format_and_never_serialized(): void
    {
        $member = $this->makeMember(PlatformRole::OPERATOR);
        $raw = DB::table('users')->where('id', $member->id)->first(['app_authentication_secret', 'app_authentication_recovery_codes']);

        $this->assertSame($this->secrets[$member->id], Crypt::decryptString($raw->app_authentication_secret));
        $hashes = json_decode(Crypt::decryptString($raw->app_authentication_recovery_codes), true);
        $this->assertCount(2, $hashes);
        $this->assertTrue(password_verify('AAAAA-BBBBB', $hashes[0]));

        $this->assertArrayNotHasKey('app_authentication_secret', $member->toArray());
        $this->assertArrayNotHasKey('app_authentication_recovery_codes', $member->toArray());

        // Turning 2FA off clears both
        app(TwoFactorService::class)->disable($member);
        $member = $member->fresh();
        $this->assertFalse($member->hasTwoFactorEnabled());
        $this->assertNull($member->getAppAuthenticationRecoveryCodes());
        $this->assertNull($member->two_factor_confirmed_at);
    }
}
