<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthPhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    /** @var array<int, array{phone: string, code: string}> */
    protected array $sentSms = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create(['name' => 'Phone User', 'telegram_id' => '7070707', 'role' => UserRole::CUSTOMER]);

        $this->mock(SmsServiceInterface::class)
            ->shouldReceive('send')
            ->andReturnUsing(function (string $phone, string $message) {
                preg_match('/\d{6}/', $message, $matches);
                $this->sentSms[] = ['phone' => $phone, 'code' => $matches[0] ?? ''];

                return true;
            });
    }

    private function sendCode(string $phone, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->user, 'sanctum')->postJson('/api/v1/phone/send-code', ['phone' => $phone]);
    }

    private function verifyCode(string $phone, string $code)
    {
        return $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/phone/verify-code', ['phone' => $phone, 'code' => $code]);
    }

    private function wrongCodeFor(string $code): string
    {
        return $code === '111111' ? '222222' : '111111';
    }

    public function test_code_is_not_returned_in_response_even_with_app_debug(): void
    {
        config(['app.debug' => true, 'auth.phone_verification.expose_code_in_response' => true]);

        $this->sendCode('+998901112233')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', null);
    }

    public function test_code_is_returned_only_with_local_env_and_explicit_flag(): void
    {
        $this->app['env'] = 'local';
        config(['app.env' => 'local', 'auth.phone_verification.expose_code_in_response' => true]);

        $response = $this->sendCode('+998901112233')->assertOk();

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $response->json('code'));
        $this->assertSame($this->sentSms[0]['code'], $response->json('code'));
    }

    public function test_correct_code_verifies_the_phone(): void
    {
        $this->sendCode('+998901112233')->assertOk();

        $this->verifyCode('+998901112233', $this->sentSms[0]['code'])->assertOk();

        $this->assertSame('+998901112233', $this->user->fresh()->phone);
    }

    public function test_resend_is_blocked_during_cooldown(): void
    {
        $this->sendCode('+998901112233')->assertOk();

        $this->sendCode('+998901112233')
            ->assertStatus(429)
            ->assertJsonPath('success', false);

        $this->travel(61)->seconds();

        $this->sendCode('+998901112233')->assertOk();
        $this->assertCount(2, $this->sentSms);
    }

    public function test_cooldown_applies_per_phone_across_users(): void
    {
        $other = User::create(['name' => 'Other User', 'telegram_id' => '7070708', 'role' => UserRole::CUSTOMER]);

        $this->sendCode('+998901112233')->assertOk();
        $this->sendCode('+998901112233', $other)->assertStatus(429);

        $this->assertCount(1, $this->sentSms);
    }

    public function test_requesting_new_codes_cannot_be_used_for_unlimited_guessing(): void
    {
        $phone = '+998901112233';
        $maxAttempts = (int) config('auth.phone_verification.max_attempts');

        for ($round = 0; $round < 5; $round++) {
            $this->sendCode($phone)->assertOk();
            $code = end($this->sentSms)['code'];

            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                $this->verifyCode($phone, $this->wrongCodeFor($code))->assertStatus(422);
            }

            // Per-code attempt limit: even the correct code is refused now
            $this->verifyCode($phone, $code)
                ->assertStatus(422)
                ->assertJsonPath('message', 'Urinishlar soni tugadi. Iltimos, kodni qaytadan so\'rang.');

            $this->travel(61)->seconds();
        }

        // Hourly cap per phone/user: no sixth code within the hour
        $this->sendCode($phone)->assertStatus(429);
        $this->assertCount(5, $this->sentSms);
        $this->assertNull($this->user->fresh()->phone);
    }

    public function test_codes_per_user_are_capped_per_hour_across_phones(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->sendCode('+99890111220' . $i)->assertOk();
            $this->travel(61)->seconds();
        }

        $this->sendCode('+998901112299')->assertStatus(429);
        $this->assertCount(5, $this->sentSms);

        $this->travel(1)->hours();

        $this->sendCode('+998901112299')->assertOk();
    }
}
