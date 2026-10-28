<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * P4-11: admin sessions: 12 hours at most, 60 minutes idle, listed and revocable; step-up confirmations for 10
 * minutes; and the wall between admin tokens and store/customer tokens, both ways.
 */
class AdminSessionTest extends TestCase
{
    use RefreshDatabase;
    use AdminFixtures;
    use AccessFixtures;

    public function test_a_session_dies_after_60_idle_minutes(): void
    {
        $member = $this->makeMember(PlatformRole::VIEWER);
        $token = $this->signIn($member);

        $this->travel(59)->minutes();
        $this->adminJson('GET', '/me', $token)->assertOk();

        $this->travel(61)->minutes();
        $this->adminJson('GET', '/me', $token)->assertUnauthorized();
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $member->id)->count());
    }

    public function test_a_session_dies_12_hours_after_sign_in_however_busy(): void
    {
        $member = $this->makeMember(PlatformRole::VIEWER);
        $token = $this->signIn($member);

        for ($i = 0; $i < 13; $i++) {
            $this->travel(55)->minutes();
            $this->adminJson('GET', '/me', $token)->assertOk(); // up to 11 h 55 min
        }

        $this->travel(6)->minutes(); // past 12 h
        $this->adminJson('GET', '/me', $token)->assertUnauthorized();
    }

    public function test_an_admin_token_never_acts_on_store_or_customer_routes(): void
    {
        $this->setUpLifecycleStore();
        $superadmin = $this->makeMember(PlatformRole::SUPERADMIN);
        $operator = $this->makeMember(PlatformRole::OPERATOR);

        foreach ([$superadmin, $operator] as $member) {
            $token = $this->signIn($member);

            foreach (['/api/v1/user', '/api/v1/me/permissions', '/api/v1/orders', '/api/v1/owner/audit-logs', '/api/v1/terminal'] as $uri) {
                $this->bearerJson('GET', $uri, $token)->assertUnauthorized();
            }
            $this->bearerJson('POST', '/api/v1/auth/logout', $token)->assertUnauthorized();
            $this->bearerJson('PUT', '/api/v1/owner/permissions', $token, ['role' => 'manager', 'permission' => 'menu.manage', 'allowed' => true])->assertUnauthorized();

            // The admin API itself is fine
            $this->adminJson('GET', '/me', $token)->assertOk();
        }
    }

    public function test_store_customer_and_terminal_tokens_never_reach_the_admin_api(): void
    {
        $this->setUpLifecycleStore();
        $legacy = User::create(['name' => 'Old SA', 'email' => 'old-sa@rizqim.test', 'password' => bcrypt('x'), 'role' => UserRole::SUPER_ADMIN]);
        $this->enableTwoFactor($legacy);

        $tokens = [
            'owner' => $this->owner->createToken('mini-app')->plainTextToken,
            'customer' => $this->customer->createToken('mini-app')->plainTextToken,
            // A superadmin's own store-level token (Mini App) is not an admin session
            'superadmin sanctum' => $legacy->createToken('mini-app')->plainTextToken,
            // Even one named and scoped like an admin session but issued with every ability
            'wildcard' => $legacy->createToken('admin', ['*'], now()->addHour())->plainTextToken,
            'terminal' => $this->pairTerminal($this->owner)['token'],
        ];

        foreach ($tokens as $kind => $token) {
            $this->adminJson('GET', '/me', $token)->assertUnauthorized();
            $this->adminJson('GET', '/team', $token)->assertUnauthorized();
        }

        // Their own routes still work for them; P4-25: a superadmin's non-admin token works nowhere (AuthSuperadminControlCenterTest)
        $this->bearerJson('GET', '/api/v1/user', $tokens['owner'])->assertOk();
        $this->bearerJson('GET', '/api/v1/user', $tokens['superadmin sanctum'])->assertUnauthorized();
        $this->bearerJson('GET', '/api/v1/me/permissions', $tokens['superadmin sanctum'])->assertUnauthorized();
    }

    public function test_a_non_superadmin_team_member_has_no_store_level_token_at_all(): void
    {
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $this->assertSame(UserRole::PLATFORM, $operator->role);

        $token = $operator->createToken('mini-app')->plainTextToken;
        $this->bearerJson('GET', '/api/v1/user', $token)->assertUnauthorized();
        $this->assertSame([], app(\App\Services\Auth\PermissionService::class)->permissionsFor($operator));
    }

    public function test_sessions_are_listed_and_revoked_another_one_with_step_up(): void
    {
        $member = $this->makeMember(PlatformRole::SUPPORT);
        $first = $this->signIn($member);
        $second = $this->signIn($member);

        $list = $this->adminJson('GET', '/auth/sessions', $second)->assertOk()->assertJsonCount(2, 'data');
        $this->assertTrue($list->json('data.0.current'));
        $this->assertFalse($list->json('data.1.current'));
        $this->assertNotNull($list->json('data.0.ip'));
        $firstId = $list->json('data.1.id');

        $this->adminJson('DELETE', "/auth/sessions/{$firstId}", $second)->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($member, $second);
        $this->adminJson('DELETE', "/auth/sessions/{$firstId}", $second)->assertOk();
        $this->adminJson('GET', '/me', $first)->assertUnauthorized();

        // Another member's session is not mine to see or end
        $other = $this->makeMember(PlatformRole::SUPPORT);
        $otherToken = $this->signIn($other);
        $otherId = $this->adminJson('GET', '/auth/sessions', $otherToken)->json('data.0.id');
        $this->adminJson('DELETE', "/auth/sessions/{$otherId}", $second)->assertNotFound();

        // Ending the current session is a log-out, no step-up needed
        $currentId = $this->adminJson('GET', '/auth/sessions', $otherToken)->json('data.0.id');
        $this->adminJson('DELETE', "/auth/sessions/{$currentId}", $otherToken)->assertOk();
        $this->adminJson('GET', '/me', $otherToken)->assertUnauthorized();

        $this->adminJson('POST', '/auth/logout', $second)->assertOk();
        $this->adminJson('GET', '/me', $second)->assertUnauthorized();
    }

    public function test_step_up_lasts_10_minutes_and_needs_a_fresh_code(): void
    {
        $member = $this->makeMember(PlatformRole::SUPERADMIN);
        $token = $this->signIn($member);

        $this->adminJson('POST', '/auth/2fa/recovery-codes', $token)->assertForbidden()->assertJsonPath('code', 'reauth_required');

        // The code that just opened the session is spent
        $this->adminJson('POST', '/auth/confirm', $token, ['code' => $this->codeFor($member)])->assertStatus(422)->assertJsonPath('code', 'invalid_code');

        $this->nextStep();
        $confirm = $this->adminJson('POST', '/auth/confirm', $token, ['code' => $this->codeFor($member)])->assertOk();
        $this->assertNotNull($confirm->json('data.reauthenticated_until'));
        $this->adminJson('GET', '/me', $token)->assertJsonPath('data.session.reauthenticated_until', $confirm->json('data.reauthenticated_until'));

        $codes = $this->adminJson('POST', '/auth/2fa/recovery-codes', $token)->assertOk()->json('data.recovery_codes');
        $this->assertCount(10, $codes);
        $this->adminJson('GET', '/me', $token)->assertJsonPath('data.recovery_codes_left', 10);

        // Another session of the same member is not confirmed
        $other = $this->signIn($member);
        $this->adminJson('POST', '/auth/2fa/recovery-codes', $other)->assertForbidden();

        $this->travel(11)->minutes();
        $this->adminJson('POST', '/auth/2fa/recovery-codes', $token)->assertForbidden()->assertJsonPath('code', 'reauth_required');
    }

    public function test_disabling_a_member_ends_their_sessions(): void
    {
        $superadmin = $this->makeMember(PlatformRole::SUPERADMIN);
        $operator = $this->makeMember(PlatformRole::OPERATOR);
        $operatorToken = $this->signIn($operator);
        $adminToken = $this->confirmedSession($superadmin);

        $this->adminJson('PATCH', "/team/{$operator->id}", $adminToken, ['disabled' => true])
            ->assertOk()->assertJsonPath('data.status', 'disabled');
        $this->adminJson('GET', '/me', $operatorToken)->assertUnauthorized();

        // A disabled superadmin loses their store-level tokens too
        $other = $this->makeMember(PlatformRole::SUPERADMIN);
        $storeToken = $other->createToken('mini-app')->plainTextToken;
        $this->adminJson('PATCH', "/team/{$other->id}", $adminToken, ['disabled' => true])->assertOk();
        $this->bearerJson('GET', '/api/v1/me/permissions', $storeToken)->assertUnauthorized();
    }
}
