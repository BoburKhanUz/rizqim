<?php

namespace Tests\Feature;

use App\Enums\PlatformRole;
use App\Models\User;
use App\Services\Admin\TwoFactorService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

/**
 * P4-11: helpers for the admin API tests: team members with or without 2FA, TOTP codes from the application clock,
 * signing in through the real endpoints, bearer requests that authenticate afresh, step-up.
 */
trait AdminFixtures
{
    protected string $adminPassword = 'Tog-va-daryo-2026!';

    /** @var array<int, string> user id => TOTP secret */
    protected array $secrets = [];

    protected function makeMember(PlatformRole $role = PlatformRole::SUPERADMIN, bool $twoFactor = true, array $attributes = []): User
    {
        static $sequence = 0;
        $sequence++;

        $member = new User();
        $member->forceFill(array_merge([
            'name' => ucfirst($role->value) . " {$sequence}",
            'email' => "{$role->value}{$sequence}@rizqim.test",
            'password' => Hash::make($this->adminPassword),
            'platform_role' => $role,
        ], $attributes))->save();

        if ($twoFactor) {
            $this->enableTwoFactor($member);
        }

        return $member->refresh();
    }

    protected function enableTwoFactor(User $member): string
    {
        $service = app(TwoFactorService::class);
        $secret = $service->generateSecret();
        // As if confirmed two steps ago: the current code is fresh
        $service->enable($member, $secret, $service->currentStep() - 2);
        $service->storeRecoveryCodes($member, ['AAAAA-BBBBB', 'CCCCC-DDDDD']);
        $this->secrets[$member->id] = $secret;

        return $secret;
    }

    protected function codeFor(User|string $memberOrSecret, int $offsetSteps = 0): string
    {
        $secret = $memberOrSecret instanceof User ? $this->secrets[$memberOrSecret->id] : $memberOrSecret;

        return app(TwoFactorService::class)->currentCode($secret, $offsetSteps);
    }

    /**
     * Move to the next 30 s step, so a fresh code is not a replay of the last accepted one.
     */
    protected function nextStep(): void
    {
        $this->travel(30)->seconds();
    }

    protected function adminJson(string $method, string $uri, ?string $token = null, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $request = $token !== null ? $this->withToken($token) : $this;

        return $request->json($method, '/api/v1/admin' . $uri, $data);
    }

    protected function loginChallenge(User $member, ?string $password = null): string
    {
        return $this->adminJson('POST', '/auth/login', null, ['email' => $member->email, 'password' => $password ?? $this->adminPassword])
            ->assertOk()
            ->json('data.challenge_token');
    }

    /**
     * A full sign-in through the API: password, then the current code. Returns the session token.
     */
    protected function signIn(User $member): string
    {
        $challenge = $this->loginChallenge($member);
        $this->nextStep();

        return $this->adminJson('POST', '/auth/2fa/verify', null, ['challenge_token' => $challenge, 'code' => $this->codeFor($member)])
            ->assertOk()
            ->json('data.token');
    }

    protected function stepUp(User $member, string $token): void
    {
        $this->nextStep();
        $this->adminJson('POST', '/auth/confirm', $token, ['code' => $this->codeFor($member)])->assertOk();
    }

    /**
     * Signed in and confirmed (step-up), ready for team and role changes.
     */
    protected function confirmedSession(User $member): string
    {
        $token = $this->signIn($member);
        $this->stepUp($member, $token);

        return $token;
    }
}
