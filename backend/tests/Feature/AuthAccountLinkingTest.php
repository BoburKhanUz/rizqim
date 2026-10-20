<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One customer identity per SMS-verified phone across Telegram and the mobile app; never a merge that could lose data,
 * never a link on a phone that was only typed in.
 */
class AuthAccountLinkingTest extends TestCase
{
    use RefreshDatabase;
    use AccessFixtures;

    private const PHONE = '+998901234567';

    /** @var list<string> */
    private array $codes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(SmsServiceInterface::class)->shouldReceive('send')->andReturnUsing(function (string $phone, string $message) {
            preg_match('/\d{6}/', $message, $match);
            $this->codes[] = $match[0];

            return true;
        });
    }

    /**
     * Past the one-code-a-minute cooldown of otp-send.
     */
    private function nextMinute(): void
    {
        $this->travel(61)->seconds();
    }

    private function phoneLogin(string $phone = self::PHONE)
    {
        $this->nextMinute();
        $this->withBearer(null)->postJson('/api/v1/auth/phone/send-code', ['phone' => $phone])->assertOk();

        return $this->withBearer(null)->postJson('/api/v1/auth/phone/verify', ['phone' => $phone, 'code' => end($this->codes)]);
    }

    private function verifyInMiniApp(User $user, string $phone = self::PHONE)
    {
        $this->nextMinute();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/phone/send-code', ['phone' => $phone])->assertOk();

        return $this->actingAs($user, 'sanctum')->postJson('/api/v1/phone/verify-code', ['phone' => $phone, 'code' => end($this->codes)]);
    }

    private function telegramCustomer(string $telegramId, array $attributes = []): User
    {
        return User::create(['name' => "TG {$telegramId}", 'telegram_id' => $telegramId, 'role' => UserRole::CUSTOMER] + $attributes);
    }

    public function test_an_unknown_phone_creates_one_verified_customer(): void
    {
        $data = $this->phoneLogin()->assertOk()->json('data');

        $this->assertTrue($data['is_new_user']);
        $this->assertNotEmpty($data['token']);
        $this->assertArrayNotHasKey('refresh_token', $data);
        $user = User::sole();
        $this->assertSame(UserRole::CUSTOMER, $user->role);
        $this->assertNotNull($user->phone_verified_at);

        // Signing in again is the same account
        $this->assertFalse($this->phoneLogin()->assertOk()->json('data.is_new_user'));
        $this->assertSame(1, User::count());
    }

    public function test_a_wrong_code_signs_nobody_in(): void
    {
        $this->withBearer(null)->postJson('/api/v1/auth/phone/send-code', ['phone' => self::PHONE])->assertOk();
        $wrong = end($this->codes) === '111111' ? '222222' : '111111';

        $this->postJson('/api/v1/auth/phone/verify', ['phone' => self::PHONE, 'code' => $wrong])->assertUnprocessable();
        $this->assertSame(0, User::count());
    }

    public function test_the_mobile_app_signs_into_the_telegram_account_that_verified_the_phone(): void
    {
        $telegram = $this->telegramCustomer('8800001');
        $this->verifyInMiniApp($telegram)->assertOk();
        $this->assertNotNull($telegram->fresh()->phone_verified_at);

        $data = $this->phoneLogin()->assertOk()->json('data');

        $this->assertSame($telegram->id, $data['user']['id']);
        $this->assertFalse($data['is_new_user']);
        $this->assertSame(1, User::count());
    }

    public function test_a_phone_typed_into_a_profile_without_a_code_is_never_linked(): void
    {
        // Someone typed a stranger's number into their own profile
        $squatter = $this->telegramCustomer('8800002', ['phone' => self::PHONE]);
        $this->assertNull($squatter->phone_verified_at);

        $data = $this->phoneLogin()->assertOk()->json('data');

        $this->assertNotSame($squatter->id, $data['user']['id']);
        $this->assertTrue($data['is_new_user']);
    }

    public function test_changing_the_phone_without_a_code_drops_the_verification(): void
    {
        $user = $this->telegramCustomer('8800003');
        $this->verifyInMiniApp($user)->assertOk();

        $user->fresh()->update(['phone' => '+998907777777']);

        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_two_customers_with_the_same_verified_phone_are_a_conflict_and_nothing_changes(): void
    {
        $first = $this->telegramCustomer('8800004');
        $second = $this->telegramCustomer('8800005');
        foreach ([$first, $second] as $user) {
            $user->forceFill(['phone' => self::PHONE, 'phone_verified_at' => now()])->save();
        }
        $before = User::orderBy('id')->get()->map->only(['id', 'telegram_id', 'phone'])->all();

        $this->phoneLogin()
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.reason', 'phone_linked_to_several_accounts');

        $this->assertSame($before, User::orderBy('id')->get()->map->only(['id', 'telegram_id', 'phone'])->all());
        $this->assertSame(0, \Laravel\Sanctum\PersonalAccessToken::count());
    }

    public function test_a_fresh_telegram_account_is_linked_into_the_mobile_account_holding_the_phone(): void
    {
        $mobile = $this->phoneLogin()->assertOk()->json('data.user');
        $telegram = $this->telegramCustomer('8800006');
        $miniAppToken = $telegram->createToken('mobile-api')->plainTextToken;

        $this->verifyInMiniApp($telegram)
            ->assertOk()
            ->assertJsonPath('account_linked', true)
            ->assertJsonPath('user.id', $mobile['id']);

        // The Telegram identity moved over; the old Mini App session must re-authenticate (into the linked account)
        $this->assertSame('8800006', User::find($mobile['id'])->telegram_id);
        $this->assertNull($telegram->fresh()->telegram_id);
        $this->assertNotNull($telegram->fresh(), 'the emptied account is kept, not deleted');
        $this->bearerJson('GET', '/api/v1/user', $miniAppToken)->assertUnauthorized();
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.linked', 'model_id' => $mobile['id']]);
    }

    public function test_a_telegram_account_with_data_is_never_merged(): void
    {
        $mobile = $this->phoneLogin()->assertOk()->json('data.user');
        $telegram = $this->telegramCustomer('8800007');
        Address::create(['user_id' => $telegram->id, 'title' => 'Uy', 'address' => 'Chilonzor 1', 'latitude' => 41.3, 'longitude' => 69.2]);

        $this->verifyInMiniApp($telegram)
            ->assertStatus(409)
            ->assertJsonPath('data.reason', 'phone_linked_to_another_account');

        $this->assertSame('8800007', $telegram->fresh()->telegram_id);
        $this->assertNull($telegram->fresh()->phone);
        $this->assertNull(User::find($mobile['id'])->telegram_id);
        $this->assertSame(1, $telegram->addresses()->count());
        $this->assertSame(0, AuditLog::where('action', 'account.linked')->count());
    }

    public function test_a_phone_held_by_another_telegram_account_is_a_conflict(): void
    {
        $owner = $this->telegramCustomer('8800008');
        $owner->forceFill(['phone' => self::PHONE, 'phone_verified_at' => now()])->save();
        $other = $this->telegramCustomer('8800009');

        $this->verifyInMiniApp($other)->assertStatus(409);

        $this->assertSame('8800008', $owner->fresh()->telegram_id);
        $this->assertSame('8800009', $other->fresh()->telegram_id);
    }

    public function test_staff_verifying_a_phone_keep_the_old_behaviour(): void
    {
        $this->setUpLifecycleStore();
        $customer = $this->telegramCustomer('8800010');
        $customer->forceFill(['phone' => self::PHONE, 'phone_verified_at' => now()])->save();

        $this->verifyInMiniApp($this->courier)->assertOk();

        $this->assertSame(self::PHONE, $this->courier->fresh()->phone);
        $this->assertNotNull($this->courier->fresh()->phone_verified_at);
    }

    public function test_send_code_answers_the_same_for_known_and_unknown_phones(): void
    {
        $known = $this->telegramCustomer('8800011');
        $known->forceFill(['phone' => self::PHONE, 'phone_verified_at' => now()])->save();

        $a = $this->withBearer(null)->postJson('/api/v1/auth/phone/send-code', ['phone' => self::PHONE])->assertOk()->json();
        $this->nextMinute();
        $b = $this->withBearer(null)->postJson('/api/v1/auth/phone/send-code', ['phone' => '+998909999999'])->assertOk()->json();

        $this->assertSame($a, $b);
    }
}
