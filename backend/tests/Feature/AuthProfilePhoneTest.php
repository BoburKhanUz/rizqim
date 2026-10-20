<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthProfilePhoneTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE_MESSAGE = "Telefon raqamini o'zgartirish uchun uni SMS kod orqali tasdiqlang.";

    protected User $user;

    protected ?string $lastCode = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Profile User',
            'telegram_id' => '5151515',
            'phone' => '+998901110000',
            'role' => UserRole::CUSTOMER,
        ]);

        $this->mock(SmsServiceInterface::class)
            ->shouldReceive('send')
            ->andReturnUsing(function (string $phone, string $message) {
                preg_match('/\d{6}/', $message, $matches);
                $this->lastCode = $matches[0] ?? null;

                return true;
            });
    }

    private function requireSmsVerification(bool $required): void
    {
        Cache::forever('setting_sms_driver', $required ? 'eskiz' : 'log');
    }

    private function updateProfile(array $payload)
    {
        return $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/user/profile', $payload);
    }

    public function test_unverified_phone_change_is_rejected_when_sms_verification_is_required(): void
    {
        $this->requireSmsVerification(true);

        $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/user')
            ->assertJsonPath('data.sms_verification_required', true);

        $this->updateProfile(['name' => 'Profile User', 'phone' => '+998909999999'])
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', self::PHONE_MESSAGE);

        $this->updateProfile(['name' => 'Profile User', 'phone' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');

        $this->assertSame('+998901110000', $this->user->fresh()->phone);
    }

    public function test_unchanged_or_omitted_phone_still_passes_when_sms_verification_is_required(): void
    {
        $this->requireSmsVerification(true);

        $this->updateProfile(['name' => 'Renamed', 'phone' => '+998901110000', 'address' => 'Chilonzor 1'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->updateProfile(['name' => 'Renamed Again'])->assertOk();

        $userWithoutPhone = User::create(['name' => 'No Phone', 'telegram_id' => '5151516', 'role' => UserRole::CUSTOMER]);
        $this->actingAs($userWithoutPhone, 'sanctum')
            ->postJson('/api/v1/user/profile', ['name' => 'No Phone', 'phone' => '', 'address' => ''])
            ->assertOk();

        $this->assertSame('Renamed Again', $this->user->fresh()->name);
        $this->assertSame('+998901110000', $this->user->fresh()->phone);
    }

    public function test_phone_is_changed_through_otp_when_sms_verification_is_required(): void
    {
        $this->requireSmsVerification(true);

        $this->actingAs($this->user, 'sanctum')->postJson('/api/v1/phone/send-code', ['phone' => '+998909999999'])->assertOk();
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/phone/verify-code', ['phone' => '+998909999999', 'code' => $this->lastCode])
            ->assertOk()
            ->assertJsonPath('user.phone', '+998909999999');

        $this->assertSame('+998909999999', $this->user->fresh()->phone);

        $this->user->refresh();
        $this->updateProfile(['name' => 'Profile User', 'phone' => '+998909999999'])->assertOk();
    }

    public function test_phone_can_be_changed_directly_when_sms_verification_is_off(): void
    {
        $this->requireSmsVerification(false);

        $this->updateProfile(['name' => 'Profile User', 'phone' => '+998909999999'])->assertOk();

        $this->assertSame('+998909999999', $this->user->fresh()->phone);
    }
}
