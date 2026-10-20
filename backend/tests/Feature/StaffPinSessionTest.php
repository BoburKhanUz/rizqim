<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\StaffPinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Staff PINs (who may set whose, weak PINs, hashing) and PIN sessions on a paired terminal (lockout, switching staff,
 * revocation, no way to tell who exists).
 */
class StaffPinSessionTest extends TestCase
{
    use RefreshDatabase;
    use AccessFixtures;

    private User $cashier;
    private User $waiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $this->waiter = $this->makeStaff(UserRole::WAITER, $this->branch);
    }

    private function setPin(User $actor, User $target, string $pin)
    {
        return $this->actingAs($actor, 'sanctum')->putJson("/api/v1/owner/staff/{$target->id}/pin", ['pin' => $pin]);
    }

    private function signIn(array $terminal, array $credentials)
    {
        return $this->bearerJson('POST', "/api/v1/devices/{$terminal['device']->uuid}/staff-session", $terminal['token'], $credentials);
    }

    public function test_weak_pins_are_refused(): void
    {
        foreach (['0000', '1111', '1234', '4321', '7890', '123456', '654321', '1212', '121212', '2580', '123', '1234567', '12a4'] as $pin) {
            $this->assertNotNull(StaffPinService::weakness($pin), "{$pin} should be refused");
            $this->setPin($this->owner, $this->cashier, $pin)->assertUnprocessable();
        }

        foreach (['4831', '90517', '274916'] as $pin) {
            $this->assertNull(StaffPinService::weakness($pin), "{$pin} should be accepted");
        }
        $this->assertNull($this->cashier->fresh()->pin_hash);
    }

    public function test_the_owner_sets_a_pin_that_is_only_stored_hashed(): void
    {
        $data = $this->setPin($this->owner, $this->cashier, '4831')->assertOk()->json('data');

        $cashier = $this->cashier->fresh();
        $this->assertNotSame('4831', $cashier->pin_hash);
        $this->assertTrue(Hash::check('4831', $cashier->pin_hash));
        $this->assertMatchesRegularExpression('/^\d{4}$/', $data['staff_code']);
        $this->assertSame($cashier->staff_code, $data['staff_code']);

        // The hash never leaves the server
        $employees = $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/employees')->json('data');
        $this->assertArrayNotHasKey('pin_hash', collect($employees)->firstWhere('id', $cashier->id));
        $this->assertStringNotContainsString('4831', json_encode($this->actingAs($cashier, 'sanctum')->getJson('/api/v1/user')->json()));
    }

    public function test_who_may_set_whose_pin(): void
    {
        $secondBranchCashier = $this->makeStaff(UserRole::CASHIER, $this->otherBranch);
        $otherManager = $this->makeStaff(UserRole::MANAGER, $this->branch);
        $foreignStore = $this->makeStore('Foreign Grill');
        $foreignOwner = $this->makeStaff(UserRole::OWNER, null, $foreignStore);

        // Managers: staff below them in their branch, and themselves
        $this->setPin($this->manager, $this->cashier, '4831')->assertOk();
        $this->setPin($this->manager, $this->manager, '5926')->assertOk();
        $this->setPin($this->manager, $secondBranchCashier, '4831')->assertNotFound();
        $this->setPin($this->manager, $otherManager, '4831')->assertNotFound();
        $this->setPin($this->manager, $this->owner, '4831')->assertNotFound();

        // Floor staff and couriers set nobody's PIN
        $this->setPin($this->cashier, $this->waiter, '4831')->assertForbidden();
        $this->setPin($this->courier, $this->courier, '4831')->assertForbidden();

        // Another store's owner cannot even find them
        $this->setPin($foreignOwner, $this->cashier, '7777')->assertNotFound();
        $this->actingAs($foreignOwner, 'sanctum')->deleteJson("/api/v1/owner/staff/{$this->cashier->id}/pin")->assertNotFound();
        $this->assertTrue(Hash::check('4831', $this->cashier->fresh()->pin_hash));

        // Couriers and customers have no terminal PIN
        $this->setPin($this->owner, $this->courier, '4831')->assertNotFound();
        $this->setPin($this->owner, $this->customer, '4831')->assertNotFound();
    }

    public function test_staff_sign_in_on_a_terminal_with_their_pin(): void
    {
        $this->givePin($this->cashier, '4831');
        $terminal = $this->pairTerminal($this->owner, $this->branch);

        $staff = $this->bearerJson('GET', '/api/v1/terminal/staff', $terminal['token'])->assertOk()->json('data');
        $this->assertSame([$this->cashier->id], array_column($staff, 'id'));

        $session = $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '4831'])->assertOk()->json('data');
        $this->assertSame('cashier', $session['user']['role']);
        $this->assertContains('pos.take_payment', $session['permissions']);
        $this->assertNotContains('orders.cancel', $session['permissions']);

        // The session token is a user token for the cashier, bound to the terminal
        $this->bearerJson('GET', '/api/v1/me/permissions', $session['token'])
            ->assertOk()
            ->assertJsonPath('data.role', 'cashier')
            ->assertJsonPath('data.device_uuid', $terminal['device']->uuid);

        // ... and short-lived
        $this->travel(config('auth.tokens.staff_session_ttl') + 1)->minutes();
        $this->bearerJson('GET', '/api/v1/me/permissions', $session['token'])->assertUnauthorized();
    }

    public function test_signing_in_by_staff_code(): void
    {
        $code = $this->givePin($this->waiter, '5926')->staff_code;
        $terminal = $this->pairTerminal($this->owner, $this->branch);

        $this->signIn($terminal, ['staff_code' => $code, 'pin' => '5926'])->assertOk()->assertJsonPath('data.user.id', $this->waiter->id);
    }

    public function test_wrong_pin_unknown_person_and_other_store_staff_look_the_same(): void
    {
        $this->givePin($this->cashier, '4831');
        $foreignStore = $this->makeStore('Foreign Grill');
        $foreignCashier = $this->givePin($this->makeStaff(UserRole::CASHIER, null, $foreignStore), '4831');
        $secondBranchCashier = $this->givePin($this->makeStaff(UserRole::CASHIER, $this->otherBranch), '4831');
        $terminal = $this->pairTerminal($this->owner, $this->branch);

        $answers = [
            $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '9999'])->assertStatus(422)->json(),
            $this->signIn($terminal, ['user_id' => 999999, 'pin' => '4831'])->assertStatus(422)->json(),
            $this->signIn($terminal, ['user_id' => $foreignCashier->id, 'pin' => '4831'])->assertStatus(422)->json(),
            $this->signIn($terminal, ['user_id' => $secondBranchCashier->id, 'pin' => '4831'])->assertStatus(422)->json(),
            $this->signIn($terminal, ['user_id' => $this->waiter->id, 'pin' => '4831'])->assertStatus(422)->json(), // no PIN set
            $this->signIn($terminal, ['user_id' => $this->courier->id, 'pin' => '4831'])->assertStatus(422)->json(), // not a terminal role
        ];

        $this->assertCount(1, array_unique(array_map('json_encode', $answers)));
    }

    public function test_repeated_failures_lock_the_person_out_on_that_terminal_with_backoff(): void
    {
        config(['auth.pin.max_attempts' => 3, 'auth.pin.lockout_seconds' => 60]);
        $this->givePin($this->cashier, '4831');
        $this->givePin($this->waiter, '5926');
        $terminal = $this->pairTerminal($this->owner, $this->branch);
        $other = $this->pairTerminal($this->owner, $this->branch);

        $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '0001'])->assertStatus(422);
        $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '0002'])->assertStatus(422);
        $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '0003'])
            ->assertStatus(429)
            ->assertHeader('Retry-After', '60')
            ->assertJsonPath('data.reason', 'pin_locked');

        // Even the right PIN waits now; another person and another terminal are not affected
        $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '4831'])->assertStatus(429);
        $this->signIn($terminal, ['user_id' => $this->waiter->id, 'pin' => '5926'])->assertOk();
        $this->signIn($other, ['user_id' => $this->cashier->id, 'pin' => '4831'])->assertOk();

        // The next failure after the lockout doubles the wait
        $this->travel(61)->seconds();
        $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '0004'])->assertStatus(429)->assertHeader('Retry-After', '120');

        // A success clears the counter
        $this->travel(121)->seconds();
        $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '4831'])->assertOk();
        $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '0005'])->assertStatus(422);
    }

    public function test_failures_on_all_terminals_add_up_per_person(): void
    {
        config(['auth.pin.max_attempts' => 3, 'auth.pin.max_attempts_per_user' => 5, 'auth.pin.lockout_seconds' => 60]);
        $this->givePin($this->cashier, '4831');
        $this->givePin($this->waiter, '5926');
        [$a, $b, $c] = [$this->pairTerminal($this->owner, $this->branch), $this->pairTerminal($this->owner, $this->branch), $this->pairTerminal($this->owner)];

        // Two wrong PINs on each terminal stay under the per-terminal limit, but the person's total reaches 5
        foreach ([$a, $a, $b, $b] as $i => $terminal) {
            $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '000' . $i])->assertStatus(422);
        }
        $this->signIn($c, ['user_id' => $this->cashier->id, 'pin' => '0009'])->assertStatus(429)->assertHeader('Retry-After', '60');

        // Locked everywhere, even with the right PIN; other people are not
        $this->signIn($a, ['user_id' => $this->cashier->id, 'pin' => '4831'])->assertStatus(429);
        $this->signIn($b, ['user_id' => $this->waiter->id, 'pin' => '5926'])->assertOk();

        // After the wait the right PIN works and clears the person's count and that terminal's (not another
        // terminal's, where somebody else may have been guessing)
        $this->travel(61)->seconds();
        $this->signIn($c, ['user_id' => $this->cashier->id, 'pin' => '4831'])->assertOk();
        $this->signIn($c, ['user_id' => $this->cashier->id, 'pin' => '0010'])->assertStatus(422);
        $this->signIn($c, ['user_id' => $this->cashier->id, 'pin' => '0011'])->assertStatus(422);
        $this->signIn($a, ['user_id' => $this->cashier->id, 'pin' => '0012'])->assertStatus(429);
    }

    public function test_attempts_for_one_person_run_one_at_a_time(): void
    {
        // A parallel request for the same person holds the attempt lock: this one waits, then gives up without a guess
        config(['auth.pin.attempt_wait_seconds' => 1]);
        $this->givePin($this->cashier, '4831');
        $terminal = $this->pairTerminal($this->owner, $this->branch);

        $lock = \Illuminate\Support\Facades\Cache::lock('pin-attempt:u' . $this->cashier->id, 30);
        $this->assertTrue($lock->get());
        $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '4831'])->assertStatus(429)->assertJsonPath('data.reason', 'pin_locked');
        $lock->release();

        $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '4831'])->assertOk();
    }

    public function test_pin_sign_ins_are_limited_per_terminal(): void
    {
        $terminal = $this->pairTerminal($this->owner, $this->branch);

        for ($i = 1; $i <= 10; $i++) {
            $this->signIn($terminal, ['user_id' => 900000 + $i, 'pin' => '4831'])->assertStatus(422);
        }
        $this->signIn($terminal, ['user_id' => 900011, 'pin' => '4831'])->assertStatus(429)->assertJsonMissingPath('data.reason');
    }

    public function test_unknown_people_are_locked_out_the_same_way(): void
    {
        config(['auth.pin.max_attempts' => 2]);
        $terminal = $this->pairTerminal($this->owner, $this->branch);

        $this->signIn($terminal, ['user_id' => 424242, 'pin' => '0001'])->assertStatus(422);
        $this->signIn($terminal, ['user_id' => 424242, 'pin' => '0002'])->assertStatus(429);
    }

    public function test_switching_staff_ends_the_previous_session_on_that_terminal(): void
    {
        $this->givePin($this->cashier, '4831');
        $this->givePin($this->waiter, '5926');
        $terminal = $this->pairTerminal($this->owner, $this->branch);

        $first = $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '4831'])->json('data.token');
        $second = $this->signIn($terminal, ['user_id' => $this->waiter->id, 'pin' => '5926'])->json('data.token');

        $this->bearerJson('GET', '/api/v1/me/permissions', $first)->assertUnauthorized();
        $this->bearerJson('GET', '/api/v1/me/permissions', $second)->assertOk()->assertJsonPath('data.role', 'waiter');

        // The terminal can sign whoever is on it out
        $this->bearerJson('DELETE', "/api/v1/devices/{$terminal['device']->uuid}/staff-session", $terminal['token'])->assertOk();
        $this->bearerJson('GET', '/api/v1/me/permissions', $second)->assertUnauthorized();
    }

    public function test_revoking_the_terminal_or_resetting_the_pin_ends_sessions(): void
    {
        $this->givePin($this->cashier, '4831');
        $terminal = $this->pairTerminal($this->owner, $this->branch);
        $session = $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '4831'])->json('data.token');

        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/v1/owner/staff/{$this->cashier->id}/pin")->assertOk();
        $this->bearerJson('GET', '/api/v1/me/permissions', $session)->assertUnauthorized();
        $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '4831'])->assertStatus(422);

        $this->givePin($this->cashier, '4831');
        $session = $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '4831'])->json('data.token');
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/devices/{$terminal['device']->uuid}/revoke")->assertOk();

        $this->bearerJson('GET', '/api/v1/me/permissions', $session)->assertUnauthorized();
        $this->signIn($terminal, ['user_id' => $this->cashier->id, 'pin' => '4831'])->assertUnauthorized();
    }

    public function test_a_terminal_can_only_open_sessions_on_itself(): void
    {
        $this->givePin($this->cashier, '4831');
        $terminal = $this->pairTerminal($this->owner, $this->branch);
        $other = $this->pairTerminal($this->owner, $this->branch);

        $this->bearerJson('POST', "/api/v1/devices/{$other['device']->uuid}/staff-session", $terminal['token'], ['user_id' => $this->cashier->id, 'pin' => '4831'])
            ->assertForbidden();
        // A user token is not a terminal
        $this->bearerJson('POST', "/api/v1/devices/{$terminal['device']->uuid}/staff-session", $this->owner->createToken('x')->plainTextToken, ['user_id' => $this->cashier->id, 'pin' => '4831'])
            ->assertUnauthorized();
    }

    public function test_store_wide_staff_can_use_any_branch_terminal(): void
    {
        $this->givePin($this->owner, '4831');
        $terminal = $this->pairTerminal($this->owner, $this->otherBranch);

        $this->signIn($terminal, ['user_id' => $this->owner->id, 'pin' => '4831'])->assertOk()->assertJsonPath('data.user.role', 'owner');
    }

    public function test_a_staff_session_token_is_for_order_work_not_administration(): void
    {
        $this->givePin($this->owner, '4826');
        $terminal = $this->pairTerminal($this->owner);
        $session = $this->signIn($terminal, ['user_id' => $this->owner->id, 'pin' => '4826'])->assertOk()->json('data.token');

        $this->assertSame(['staff-session'], \Laravel\Sanctum\PersonalAccessToken::findToken($session)->abilities);

        // POS and order work
        $this->bearerJson('GET', '/api/v1/me/permissions', $session)->assertOk()->assertJsonPath('data.role', 'owner');
        $this->bearerJson('GET', '/api/v1/orders', $session)->assertOk();
        $this->bearerJson('GET', '/api/v1/owner/analytics', $session)->assertOk();

        // Money, settings, permissions, staff and devices, the audit log, account-wide actions: refused
        $refused = [
            ['GET', '/api/v1/owner/finance'],
            ['POST', '/api/v1/owner/payouts'],
            ['GET', '/api/v1/owner/settings'],
            ['GET', '/api/v1/owner/employees'],
            ['GET', '/api/v1/owner/permissions'],
            ['PUT', '/api/v1/owner/permissions'],
            ['PUT', "/api/v1/owner/staff/{$this->cashier->id}/pin"],
            ['PUT', "/api/v1/owner/staff/{$this->cashier->id}/role"],
            ['GET', '/api/v1/owner/devices'],
            ['POST', "/api/v1/owner/devices/{$terminal['device']->uuid}/revoke"],
            ['POST', '/api/v1/owner/devices/pairing-codes'],
            ['GET', '/api/v1/devices'],
            ['GET', '/api/v1/owner/audit-logs'],
            ['POST', '/api/v1/auth/logout-all'],
            ['POST', '/api/v1/user/profile'],
            ['POST', '/api/v1/phone/send-code'],
            ['POST', '/api/v1/phone/verify-code'],
        ];
        foreach ($refused as [$method, $uri]) {
            $this->bearerJson($method, $uri, $session)->assertForbidden()->assertJsonPath('data.reason', 'staff_session_not_allowed');
        }
        $this->assertNull($terminal['device']->fresh()->revoked_at);

        // The owner's own sign-in still reaches them; a session from before the narrow ability ('*') is refused too
        $this->bearerJson('GET', '/api/v1/owner/finance', $this->owner->createToken('app')->plainTextToken)->assertOk();
        $legacy = app(\App\Services\Auth\TokenService::class)->createDeviceToken($this->owner, $terminal['device'], 'staff-session', ['*'], now()->addHour());
        $this->bearerJson('GET', '/api/v1/owner/audit-logs', $legacy->plainTextToken)->assertForbidden();
        $this->bearerJson('GET', '/api/v1/orders', $legacy->plainTextToken)->assertOk();
    }

    public function test_a_staff_code_taken_at_the_same_moment_is_retried(): void
    {
        // Another request gives the drawn code to somebody right after this one checked it was free
        $checks = 0;
        \Illuminate\Support\Facades\DB::listen(function ($query) use (&$checks) {
            if (str_contains($query->sql, '"staff_code" = ?') && str_contains($query->sql, 'exists') && ++$checks === 1) {
                $this->waiter->forceFill(['staff_code' => (string) end($query->bindings)])->saveQuietly();
            }
        });

        // The unique index refuses the first save; a second code is drawn instead of a 500
        $this->setPin($this->owner, $this->cashier, '4831')->assertOk();

        $this->assertSame(2, $checks);
        $this->assertMatchesRegularExpression('/^\d{4}$/', (string) $this->cashier->fresh()->staff_code);
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'staff.pin_set')->where('model_id', $this->cashier->id)->count());
    }
}
