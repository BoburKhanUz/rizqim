<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\Auth\StaffPhoneLoginService;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P1-36: store staff sign in to the partner panel (and the staff apps) with an SMS code, `account: staff`. Only a staff
 * account holding the phone as its login phone is found; a customer never becomes staff and nothing is created.
 */
class AuthStaffPhoneLoginTest extends TestCase
{
    use RefreshDatabase;
    use AccessFixtures;

    private const PHONE = '+998901112233';

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

        $this->setUpLifecycleStore();
    }

    private function sendCode(string $phone = self::PHONE): string
    {
        // Past the one-code-a-minute cooldown of otp-send
        $this->travel(61)->seconds();
        $this->withBearer(null)->postJson('/api/v1/auth/phone/send-code', ['phone' => $phone])->assertOk();

        return end($this->codes);
    }

    private function wrongCode(string $right): string
    {
        return $right === '111111' ? '222222' : '111111';
    }

    private function device(string $type = 'web', ?string $uuid = null): array
    {
        return array_filter(['type' => $type, 'uuid' => $uuid, 'name' => 'Hamkor veb-panel', 'platform' => 'web']);
    }

    private function staffLogin(string $code, string $phone = self::PHONE, ?array $device = null)
    {
        return $this->withBearer(null)->postJson('/api/v1/auth/phone/verify', [
            'phone' => $phone,
            'code' => $code,
            'account' => 'staff',
            'device' => $device ?? $this->device(),
        ]);
    }

    private function giveLoginPhone(User $user, string $phone = self::PHONE): User
    {
        app(StaffPhoneLoginService::class)->vouch($user, $phone);

        return $user->refresh();
    }

    public function test_an_owner_signs_in_to_their_store_with_a_device_bound_session(): void
    {
        $this->giveLoginPhone($this->owner);

        $data = $this->staffLogin($this->sendCode())->assertOk()->json('data');

        $this->assertSame($this->owner->id, $data['user']['id']);
        $this->assertSame('owner', $data['user']['role']);
        // The same `user` shape as every login
        $this->assertArrayNotHasKey('branch', $data['user']);
        $this->assertSame($this->store->id, $data['store']['id']);
        $this->assertFalse($data['is_new_user']);
        $this->assertStringStartsWith('rt_', $data['refresh_token']);
        $this->assertSame('web', $data['device']['type']);
        $this->assertSame($this->store->id, $data['device']['store_id']);
        // Nothing was created: no customer for the phone
        $this->assertFalse(User::where('phone', self::PHONE)->where('role', UserRole::CUSTOMER->value)->exists());

        // A full owner session: the permission list, and the routes a terminal PIN session is refused
        $this->bearerJson('GET', '/api/v1/me/permissions', $data['token'])
            ->assertOk()
            ->assertJsonPath('data.role', 'owner')
            ->assertJsonPath('data.store_id', $this->store->id);
        $this->bearerJson('GET', '/api/v1/owner/audit-logs', $data['token'])->assertOk();

        // The refresh token family works as for any app
        $this->withBearer(null)->postJson('/api/v1/auth/refresh', ['refresh_token' => $data['refresh_token'], 'device_uuid' => $data['device']['uuid']])
            ->assertOk();
        // Past the grace window for a lost answer (docs/AUTH.md)
        $this->travel(31)->seconds();
        $this->withBearer(null)->postJson('/api/v1/auth/refresh', ['refresh_token' => $data['refresh_token'], 'device_uuid' => $data['device']['uuid']])
            ->assertUnauthorized()
            ->assertJsonPath('data.reason', 'refresh_reused');
    }

    public function test_the_sign_in_is_in_the_audit_log_without_the_phone(): void
    {
        $this->giveLoginPhone($this->manager);

        $data = $this->staffLogin($this->sendCode())->assertOk()->json('data');

        $log = AuditLog::where('action', 'staff.signed_in')->sole();
        $this->assertSame($this->manager->id, $log->user_id);
        $this->assertSame($this->store->id, $log->store_id);
        $this->assertSame($this->branch->id, $log->branch_id);
        $this->assertSame(Device::where('uuid', $data['device']['uuid'])->value('id'), $log->device_id);
        $this->assertSame('phone', $log->payload_json['via']);
        $this->assertStringNotContainsString('901112233', json_encode($log->toArray()));
    }

    public function test_a_manager_gets_their_branch_and_manager_rights(): void
    {
        $this->giveLoginPhone($this->manager);

        $data = $this->staffLogin($this->sendCode(), device: $this->device('partner_mobile'))->assertOk()->json('data');

        $this->assertSame($this->manager->id, $data['user']['id']);
        $permissions = $this->bearerJson('GET', '/api/v1/me/permissions', $data['token'])->assertOk()->json('data');
        $this->assertSame('manager', $permissions['role']);
        $this->assertSame($this->branch->id, $permissions['branch_id']);
        $this->assertTrue($permissions['branch_scoped']);
        $this->assertContains('orders.view', $permissions['permissions']);
        $this->assertNotContains('finance.view', $permissions['permissions']);
        // The owner's own pages stay closed
        $this->bearerJson('GET', '/api/v1/owner/audit-logs', $data['token'])->assertForbidden();
    }

    public function test_other_staff_such_as_an_app_only_courier_sign_in_too(): void
    {
        $courier = $this->giveLoginPhone($this->makeStaff(UserRole::COURIER, $this->branch, null, ['telegram_id' => null, 'telegram_invite_token' => (string) Str::uuid()]));

        $this->staffLogin($this->sendCode(), device: $this->device('courier'))
            ->assertOk()
            ->assertJsonPath('data.user.id', $courier->id)
            ->assertJsonPath('data.device.type', 'courier');
    }

    public function test_a_customer_only_phone_never_becomes_staff_and_is_left_alone(): void
    {
        $customer = $this->makeCustomer(0, ['phone' => self::PHONE]);
        $customer->forceFill(['phone_verified_at' => now()])->save();
        $users = User::count();

        $this->staffLogin($this->sendCode())
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.reason', 'staff_account_not_found');

        $this->assertSame($users, User::count());
        $this->assertSame(UserRole::CUSTOMER, $customer->fresh()->role);
        $this->assertSame(0, $customer->tokens()->count());
        $this->assertSame(0, RefreshToken::count());

        // The customer app's sign-in is unchanged: the customer account
        $this->withBearer(null)->postJson('/api/v1/auth/phone/verify', ['phone' => self::PHONE, 'code' => $this->sendCode()])
            ->assertOk()
            ->assertJsonPath('data.user.id', $customer->id)
            ->assertJsonPath('data.user.role', 'customer');
    }

    public function test_a_phone_nobody_holds_creates_nothing(): void
    {
        $users = User::count();

        $this->staffLogin($this->sendCode())->assertForbidden()->assertJsonPath('data.reason', 'staff_account_not_found');

        $this->assertSame($users, User::count());
        $this->assertSame(0, Device::count());
    }

    public function test_a_staff_phone_typed_without_a_code_or_a_vouch_signs_nobody_in(): void
    {
        // The owner's phone as the old store wizard / a profile form wrote it: a contact detail only
        $this->owner->update(['phone' => self::PHONE]);
        $this->assertNull($this->owner->fresh()->phone_verified_at);

        $this->staffLogin($this->sendCode())->assertForbidden()->assertJsonPath('data.reason', 'staff_account_not_found');

        // Proving the phone while signed in (Mini App / app) makes it a login phone
        $this->travel(61)->seconds();
        $this->actingAs($this->owner->fresh(), 'sanctum')->postJson('/api/v1/phone/send-code', ['phone' => self::PHONE])->assertOk();
        $this->actingAs($this->owner->fresh(), 'sanctum')->postJson('/api/v1/phone/verify-code', ['phone' => self::PHONE, 'code' => end($this->codes)])->assertOk();

        $this->staffLogin($this->sendCode())->assertOk()->assertJsonPath('data.user.id', $this->owner->id);
    }

    public function test_changing_a_login_phone_without_a_code_drops_it(): void
    {
        $this->giveLoginPhone($this->owner);
        $this->owner->update(['phone' => '+998907770000']);
        $this->owner->update(['phone' => self::PHONE]);

        $this->staffLogin($this->sendCode())->assertForbidden();
    }

    public function test_the_customer_account_sharing_the_phone_stays_a_customer_one(): void
    {
        $this->giveLoginPhone($this->owner);
        $customer = $this->makeCustomer(0, ['phone' => self::PHONE]);
        $customer->forceFill(['phone_verified_at' => now()])->save();

        $this->staffLogin($this->sendCode())->assertOk()->assertJsonPath('data.user.id', $this->owner->id);
        $this->withBearer(null)->postJson('/api/v1/auth/phone/verify', ['phone' => self::PHONE, 'code' => $this->sendCode()])
            ->assertOk()
            ->assertJsonPath('data.user.id', $customer->id);
    }

    public function test_a_wrong_code_signs_nobody_in(): void
    {
        $this->giveLoginPhone($this->owner);
        $code = $this->sendCode();

        $this->staffLogin($this->wrongCode($code))->assertUnprocessable();

        $this->assertSame(0, RefreshToken::count());
        $this->assertSame(0, $this->owner->tokens()->count());
        $this->assertFalse(AuditLog::where('action', 'staff.signed_in')->exists());
    }

    public function test_five_wrong_codes_lock_the_code_and_the_day_cap_holds(): void
    {
        $this->giveLoginPhone($this->owner);
        $code = $this->sendCode();

        foreach (range(1, 5) as $attempt) {
            $this->staffLogin($this->wrongCode($code))->assertUnprocessable();
        }

        // The right code no longer works: a new one is needed
        $this->staffLogin($code)->assertUnprocessable()->assertJsonPath('message', "Urinishlar soni tugadi. Iltimos, kodni qaytadan so'rang.");
        $this->assertSame(0, RefreshToken::count());

        config(['auth.phone_verification.max_attempts_per_day' => 7]);
        $this->staffLogin($this->wrongCode($fresh = $this->sendCode()))->assertUnprocessable();
        $this->staffLogin($fresh)->assertUnprocessable()->assertJsonPath('message', "Bugun urinishlar soni tugadi. Iltimos, keyinroq qayta urinib ko'ring.");
    }

    public function test_a_code_is_spent_by_a_staff_sign_in(): void
    {
        $this->giveLoginPhone($this->owner);
        $code = $this->sendCode();

        $this->staffLogin($code)->assertOk();
        $this->staffLogin($code)->assertUnprocessable();
    }

    public function test_staff_sign_in_needs_a_staff_device(): void
    {
        $this->giveLoginPhone($this->owner);
        $code = $this->sendCode();

        $this->withBearer(null)->postJson('/api/v1/auth/phone/verify', ['phone' => self::PHONE, 'code' => $code, 'account' => 'staff'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('device');
        $this->staffLogin($code, device: $this->device('customer_mobile'))->assertUnprocessable()->assertJsonValidationErrors('device.type');
        $this->staffLogin($code, device: $this->device('pos'))->assertUnprocessable()->assertJsonValidationErrors('device.type');
        // Validation ran before the code was looked at: it still works
        $this->staffLogin($code)->assertOk();
    }

    public function test_a_person_who_is_staff_in_two_stores_picks_the_store(): void
    {
        $secondStore = $this->makeStore('Second Store');
        $owner = $this->giveLoginPhone($this->owner);
        $managerElsewhere = $this->giveLoginPhone($this->makeStaff(UserRole::MANAGER, null, $secondStore));

        $response = $this->staffLogin($this->sendCode())
            ->assertStatus(409)
            ->assertJsonPath('data.reason', 'account_selection_required');
        $this->assertSame(0, RefreshToken::count());

        $accounts = collect($response->json('data.accounts'));
        $this->assertSame([$owner->id, $managerElsewhere->id], $accounts->pluck('user_id')->all());
        $this->assertSame([$this->store->id, $secondStore->id], $accounts->pluck('store.id')->all());
        $this->assertSame(['owner', 'manager'], $accounts->pluck('role')->all());
        $ticket = $response->json('data.selection_token');

        $data = $this->withBearer(null)->postJson('/api/v1/auth/phone/select-account', [
            'selection_token' => $ticket,
            'user_id' => $managerElsewhere->id,
            'device' => $this->device(),
        ])->assertOk()->json('data');

        $this->assertSame($managerElsewhere->id, $data['user']['id']);
        $this->assertSame($secondStore->id, $data['store']['id']);
        $this->bearerJson('GET', '/api/v1/me/permissions', $data['token'])->assertJsonPath('data.store_id', $secondStore->id);
        $this->assertSame($managerElsewhere->id, AuditLog::where('action', 'staff.signed_in')->sole()->user_id);

        // The ticket is single use
        $this->withBearer(null)->postJson('/api/v1/auth/phone/select-account', [
            'selection_token' => $ticket,
            'user_id' => $owner->id,
            'device' => $this->device(),
        ])->assertUnprocessable()->assertJsonPath('data.reason', 'selection_invalid');
    }

    public function test_a_selection_ticket_only_opens_the_accounts_it_offered(): void
    {
        $secondStore = $this->makeStore('Second Store');
        $this->giveLoginPhone($this->owner);
        $this->giveLoginPhone($this->makeStaff(UserRole::MANAGER, null, $secondStore));
        // Someone else's account in the first store
        $stranger = $this->giveLoginPhone($this->makeStaff(UserRole::COURIER, $this->branch), '+998909990000');

        $ticket = $this->staffLogin($this->sendCode())->assertStatus(409)->json('data.selection_token');

        $this->withBearer(null)->postJson('/api/v1/auth/phone/select-account', [
            'selection_token' => $ticket,
            'user_id' => $stranger->id,
            'device' => $this->device(),
        ])->assertUnprocessable()->assertJsonPath('data.reason', 'selection_invalid');

        // A failed pick spends the ticket too, so it cannot be used to probe accounts
        $this->withBearer(null)->postJson('/api/v1/auth/phone/select-account', [
            'selection_token' => $ticket,
            'user_id' => $this->owner->id,
            'device' => $this->device(),
        ])->assertUnprocessable();

        $this->withBearer(null)->postJson('/api/v1/auth/phone/select-account', [
            'selection_token' => 'sel_' . Str::random(48),
            'user_id' => $this->owner->id,
            'device' => $this->device(),
        ])->assertUnprocessable()->assertJsonPath('data.reason', 'selection_invalid');
        $this->assertSame(0, RefreshToken::count());
    }

    public function test_a_ticket_expires_and_an_account_that_lost_the_phone_is_not_signed_in(): void
    {
        $secondStore = $this->makeStore('Second Store');
        $this->giveLoginPhone($this->owner);
        $elsewhere = $this->giveLoginPhone($this->makeStaff(UserRole::MANAGER, null, $secondStore));

        $ticket = $this->staffLogin($this->sendCode())->assertStatus(409)->json('data.selection_token');
        $this->travel(6)->minutes();
        $this->withBearer(null)->postJson('/api/v1/auth/phone/select-account', ['selection_token' => $ticket, 'user_id' => $elsewhere->id, 'device' => $this->device()])
            ->assertUnprocessable();

        $ticket = $this->staffLogin($this->sendCode())->assertStatus(409)->json('data.selection_token');
        $elsewhere->update(['phone' => '+998905550000']);
        $this->withBearer(null)->postJson('/api/v1/auth/phone/select-account', ['selection_token' => $ticket, 'user_id' => $elsewhere->id, 'device' => $this->device()])
            ->assertUnprocessable();
    }

    public function test_a_storeless_account_is_not_a_staff_login(): void
    {
        $storeless = User::create(['name' => 'Lost', 'role' => UserRole::MANAGER]);
        $storeless->forceFill(['phone' => self::PHONE, 'phone_verified_at' => now()])->save();

        $this->staffLogin($this->sendCode())->assertForbidden()->assertJsonPath('data.reason', 'staff_account_not_found');
    }

    public function test_a_super_admin_is_not_a_staff_login_and_is_sent_to_the_control_center(): void
    {
        $admin = User::create(['name' => 'Admin', 'role' => UserRole::SUPER_ADMIN, 'email' => 'root@example.uz', 'password' => bcrypt('x')]);
        $admin->forceFill(['phone' => self::PHONE, 'phone_verified_at' => now()])->save();

        $this->staffLogin($this->sendCode())->assertForbidden()->assertJsonPath('data.reason', 'platform_account');
    }

    public function test_vouching_only_takes_a_staff_account_and_a_proper_number(): void
    {
        $service = app(StaffPhoneLoginService::class);

        $this->assertThrows(fn () => $service->vouch($this->customer->fresh(), self::PHONE), \InvalidArgumentException::class);
        $this->assertThrows(fn () => $service->vouch($this->owner, '90 111 22 33'), \InvalidArgumentException::class);

        $service->vouch($this->owner, null);
        $this->assertNull($this->owner->fresh()->phone);
        $this->assertNull($this->owner->fresh()->phone_verified_at);
    }
}
