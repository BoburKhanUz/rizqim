<?php

namespace Tests\Feature;

use App\Actions\Auth\GenerateAuthTokenAction;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::create(['name' => 'Token User', 'telegram_id' => '6060606', 'role' => UserRole::CUSTOMER]);
    }

    private function getUserWithToken(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/v1/user');
    }

    public function test_tokens_expire_after_thirty_days_by_default(): void
    {
        $this->assertSame(60 * 24 * 30, config('sanctum.expiration'));

        $token = app(GenerateAuthTokenAction::class)->execute($this->customer());

        $this->getUserWithToken($token)->assertOk();

        $this->travel(31)->days();

        $this->getUserWithToken($token)->assertUnauthorized();
    }

    public function test_new_tokens_carry_an_expiry_timestamp(): void
    {
        $user = $this->customer();

        app(GenerateAuthTokenAction::class)->execute($user);

        $expiresAt = $user->tokens()->first()->expires_at;
        $this->assertNotNull($expiresAt);
        $this->assertTrue($expiresAt->between(now()->addDays(30)->subMinute(), now()->addDays(30)->addMinute()));
    }

    public function test_login_prunes_expired_tokens_and_keeps_only_the_most_recent_ones(): void
    {
        $user = $this->customer();
        $action = app(GenerateAuthTokenAction::class);

        $legacy = $user->createToken('mobile-api')->accessToken;
        $legacy->forceFill(['created_at' => now()->subDays(40)])->save();

        $tokens = [];
        for ($i = 0; $i < 7; $i++) {
            $tokens[] = $action->execute($user);
        }

        $this->assertSame(GenerateAuthTokenAction::MAX_ACTIVE_TOKENS, $user->tokens()->count());
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $legacy->id]);

        $this->getUserWithToken(end($tokens))->assertOk();
        $this->getUserWithToken($tokens[0])->assertUnauthorized();
    }
}
