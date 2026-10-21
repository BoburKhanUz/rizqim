<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Employees\Pages\ManageEmployees;
use App\Filament\Superadmin\Resources\GlobalStores\Pages\CreateGlobalStore;
use App\Filament\Superadmin\Resources\GlobalUsers\Pages\ManageGlobalUsers;
use App\Jobs\SendTelegramRequest;
use App\Models\AuditLog;
use App\Models\District;
use App\Models\Region;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\StaffPhoneLoginService;
use App\Services\Sms\SmsServiceInterface;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * P1-34 review B: how a staff login phone was established (users.phone_verified_via = sms | vouched) decides what it
 * may do. The employee form vouches only for a phone it changed and never replaces one the person proved by SMS;
 * every set, change and clear is audited without the clear number and ends the app sessions of the old number; a
 * vouched phone never signs anybody in to a customer account; a store does not vouch a phone its holder proved on
 * another store's staff account.
 */
class StaffLoginPhoneSecurityTest extends TestCase
{
    use RefreshDatabase;
    use AccessFixtures;

    /** @var list<string> */
    private array $codes = [];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.bot_token' => '123456:login-phone']);
        Queue::fake([SendTelegramRequest::class]);
        $this->mock(SmsServiceInterface::class)->shouldReceive('send')->andReturnUsing(function (string $phone, string $message) {
            preg_match('/\d{6}/', $message, $match);
            $this->codes[] = $match[0];

            return true;
        });

        $this->setUpLifecycleStore();
        $this->admin = User::create(['name' => 'Super Admin', 'role' => UserRole::SUPER_ADMIN->value, 'email' => 'root@example.uz', 'password' => bcrypt('password')]);
    }

    private function sendCode(string $phone): string
    {
        // Past the one-code-a-minute cooldown of otp-send
        $this->travel(61)->seconds();
        $this->withBearer(null)->postJson('/api/v1/auth/phone/send-code', ['phone' => $phone])->assertOk();

        return end($this->codes);
    }

    private function staffLogin(string $phone, string $type = 'web')
    {
        return $this->withBearer(null)->postJson('/api/v1/auth/phone/verify', [
            'phone' => $phone,
            'code' => $this->sendCode($phone),
            'account' => 'staff',
            'device' => ['type' => $type, 'name' => 'Ilova'],
        ]);
    }

    private function panel(User $user, ?Store $tenant = null): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAs($user, 'web');
        Filament::setCurrentPanel('admin');
        Filament::setTenant($tenant ?? $this->store);
    }

    private function superadmin(): void
    {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('superadmin');
    }

    /** The holder proved the phone with a code (Mini App / app phone verification) */
    private function proveBySms(User $user, string $phone): void
    {
        $user->forceFill(['phone' => $phone, 'phone_verified_at' => now(), 'phone_verified_via' => User::PHONE_VIA_SMS])->save();
    }

    /**
     * @return list<string>
     */
    private function auditActions(User $subject): array
    {
        return AuditLog::where('model_type', $subject->getMorphClass())->where('model_id', $subject->id)
            ->where('action', 'like', 'staff.login_phone_%')->orderBy('id')->pluck('action')->all();
    }

    public function test_a_phone_proven_by_sms_is_recorded_as_such_and_a_vouched_one_as_vouched(): void
    {
        $this->actingAs($this->courier, 'sanctum');
        $this->postJson('/api/v1/phone/send-code', ['phone' => '+998907770020'])->assertOk();
        $this->postJson('/api/v1/phone/verify-code', ['phone' => '+998907770020', 'code' => end($this->codes)])->assertOk();
        $this->assertSame(User::PHONE_VIA_SMS, $this->courier->fresh()->phone_verified_via);

        app(StaffPhoneLoginService::class)->vouch($this->manager, '+998907770021');
        $this->assertSame(User::PHONE_VIA_VOUCHED, $this->manager->fresh()->phone_verified_via);

        // A phone changed without a code loses both
        $this->manager->fresh()->update(['phone' => '+998907770022']);
        $this->assertNull($this->manager->fresh()->phone_verified_at);
        $this->assertNull($this->manager->fresh()->phone_verified_via);
        // Not in any API answer
        $this->assertArrayNotHasKey('phone_verified_via', $this->courier->fresh()->toArray());
    }

    public function test_an_unrelated_edit_does_not_vouch_a_phone_the_employee_typed_themselves(): void
    {
        $this->courier->update(['phone' => '+998907770001']); // their own profile, no code
        $this->panel($this->owner);

        Livewire::test(ManageEmployees::class)
            ->callTableAction('edit', $this->courier, data: ['name' => 'Renamed Courier'])
            ->assertHasNoTableActionErrors();

        $courier = $this->courier->fresh();
        $this->assertSame('Renamed Courier', $courier->name);
        $this->assertNull($courier->phone_verified_at);
        $this->assertSame([], $this->auditActions($courier));
        $this->staffLogin('+998907770001', 'courier')->assertForbidden()->assertJsonPath('data.reason', 'staff_account_not_found');
    }

    public function test_a_manager_cannot_take_over_a_courier_by_replacing_their_sms_proven_phone(): void
    {
        $this->proveBySms($this->courier, '+998907770002');
        $this->panel($this->manager);

        Livewire::test(ManageEmployees::class)
            ->callTableAction('edit', $this->courier, data: ['phone' => '+998907770099'])
            ->assertHasTableActionErrors(['phone']);
        // Nor clear it (and set their own number in a second step)
        Livewire::test(ManageEmployees::class)
            ->callTableAction('edit', $this->courier, data: ['phone' => null])
            ->assertHasTableActionErrors(['phone']);
        // The same phone with another change is fine
        Livewire::test(ManageEmployees::class)
            ->callTableAction('edit', $this->courier, data: ['name' => 'Courier Renamed'])
            ->assertHasNoTableActionErrors();

        $courier = $this->courier->fresh();
        $this->assertSame('+998907770002', $courier->phone);
        $this->assertSame(User::PHONE_VIA_SMS, $courier->phone_verified_via);
        $this->assertSame([], $this->auditActions($courier));
        $this->staffLogin('+998907770099', 'courier')->assertForbidden();
    }

    public function test_setting_changing_and_clearing_a_login_phone_is_audited_without_the_number(): void
    {
        $this->panel($this->owner);

        Livewire::test(ManageEmployees::class)
            ->callAction('create', data: ['name' => 'App Courier', 'role' => UserRole::COURIER->value, 'branch_id' => $this->branch->id, 'phone' => '+998907770031'])
            ->assertHasNoActionErrors();
        $courier = User::where('name', 'App Courier')->sole();
        $this->assertSame(User::PHONE_VIA_VOUCHED, $courier->phone_verified_via);

        Livewire::test(ManageEmployees::class)
            ->callTableAction('edit', $courier, data: ['phone' => '+998907770032'])
            ->assertHasNoTableActionErrors();
        Livewire::test(ManageEmployees::class)
            ->callTableAction('edit', $courier->fresh(), data: ['phone' => null])
            ->assertHasNoTableActionErrors();

        $this->assertSame(['staff.login_phone_set', 'staff.login_phone_changed', 'staff.login_phone_cleared'], $this->auditActions($courier));
        $this->assertNull($courier->fresh()->phone);

        $logs = AuditLog::where('action', 'like', 'staff.login_phone_%')->get();
        foreach ($logs as $log) {
            $this->assertSame($this->store->id, (int) $log->store_id);
            $this->assertSame($this->owner->id, (int) $log->user_id);
        }
        $changed = $logs->firstWhere('action', 'staff.login_phone_changed');
        $this->assertSame('+99890*****31', $changed->before['phone']);
        $this->assertSame('+99890*****32', $changed->after['phone']);
        $this->assertSame('vouched', $changed->payload_json['via']);
        $this->assertSame('vouched', $changed->payload_json['previous_via']);
        $json = json_encode($logs->toArray());
        $this->assertStringNotContainsString('907770031', $json);
        $this->assertStringNotContainsString('907770032', $json);

        // The owner's audit panel names them
        $this->assertSame("Kirish raqami o'zgartirildi", \App\Filament\Resources\AuditLogs\AuditLogResource::actionLabels()['staff.login_phone_changed']);
    }

    public function test_changing_or_clearing_a_login_phone_ends_the_app_sessions_of_the_old_number(): void
    {
        app(StaffPhoneLoginService::class)->vouch($this->manager, '+998907770010');
        $session = $this->staffLogin('+998907770010')->assertOk()->json('data');
        // The manager's terminal PIN session and Mini App token are not tied to the phone
        $this->givePin($this->manager);
        $terminal = $this->pairTerminal($this->owner, $this->branch);
        $pin = $this->bearerJson('POST', "/api/v1/devices/{$terminal['device']->uuid}/staff-session", $terminal['token'], ['user_id' => $this->manager->id, 'pin' => '4831'])
            ->assertOk()->json('data.token');
        $miniApp = $this->manager->createToken('telegram-auth')->plainTextToken;

        $this->panel($this->owner);
        Livewire::test(ManageEmployees::class)
            ->callTableAction('edit', $this->manager, data: ['phone' => null])
            ->assertHasNoTableActionErrors();
        $this->assertNull($this->manager->fresh()->phone);

        $this->withBearer(null)->postJson('/api/v1/auth/refresh', ['refresh_token' => $session['refresh_token'], 'device_uuid' => $session['device']['uuid']])
            ->assertUnauthorized();
        $this->bearerJson('GET', '/api/v1/me/permissions', $session['token'])->assertUnauthorized();
        $this->bearerJson('GET', '/api/v1/me/permissions', $pin)->assertOk();
        $this->bearerJson('GET', '/api/v1/me/permissions', $miniApp)->assertOk();

        // A change does the same
        app(StaffPhoneLoginService::class)->vouch($this->courier, '+998907770011');
        $courierSession = $this->staffLogin('+998907770011', 'courier')->assertOk()->json('data');
        $this->panel($this->owner);
        Livewire::test(ManageEmployees::class)
            ->callTableAction('edit', $this->courier->fresh(), data: ['phone' => '+998907770012'])
            ->assertHasNoTableActionErrors();
        $this->bearerJson('GET', '/api/v1/me/permissions', $courierSession['token'])->assertUnauthorized();
        $this->staffLogin('+998907770011', 'courier')->assertForbidden();
        $this->staffLogin('+998907770012', 'courier')->assertOk()->assertJsonPath('data.user.id', $this->courier->id);
    }

    public function test_a_vouched_phone_never_signs_its_holder_in_to_a_customer_account(): void
    {
        // An owner typed somebody else's number for their courier; the super admin later makes the account a customer
        app(StaffPhoneLoginService::class)->vouch($this->courier, '+998907770003');
        $this->superadmin();
        Livewire::test(ManageGlobalUsers::class)
            ->callTableAction('edit', $this->courier->fresh(), data: ['role' => UserRole::CUSTOMER->value])
            ->assertHasNoTableActionErrors();

        $exCourier = $this->courier->fresh();
        $this->assertSame(UserRole::CUSTOMER, $exCourier->role);
        $this->assertNull($exCourier->phone_verified_at);
        $this->assertNull($exCourier->phone_verified_via);
        $cleared = AuditLog::where('action', 'staff.login_phone_cleared')->sole();
        $this->assertSame($this->store->id, (int) $cleared->store_id);

        // The real holder of the number signs in to the customer app: a new account of their own
        $data = $this->withBearer(null)->postJson('/api/v1/auth/phone/verify', ['phone' => '+998907770003', 'code' => $this->sendCode('+998907770003')])
            ->assertOk()->json('data');
        $this->assertNotSame($exCourier->id, $data['user']['id']);
        $this->assertTrue($data['is_new_user']);

        // Whatever path changed the role, a vouched phone does not count for customers
        $legacy = $this->makeCustomer(0, ['phone' => '+998907770004']);
        $legacy->forceFill(['phone_verified_at' => now(), 'phone_verified_via' => User::PHONE_VIA_VOUCHED])->save();
        $data = $this->withBearer(null)->postJson('/api/v1/auth/phone/verify', ['phone' => '+998907770004', 'code' => $this->sendCode('+998907770004')])
            ->assertOk()->json('data');
        $this->assertNotSame($legacy->id, $data['user']['id']);
    }

    public function test_an_sms_proven_phone_survives_a_role_change(): void
    {
        $this->proveBySms($this->courier, '+998907770005');
        $this->courier->update(['role' => UserRole::CUSTOMER->value, 'store_id' => null, 'branch_id' => null]);

        $this->assertNotNull($this->courier->fresh()->phone_verified_at);
        $this->assertSame(User::PHONE_VIA_SMS, $this->courier->fresh()->phone_verified_via);
    }

    public function test_a_store_does_not_vouch_a_phone_proven_on_another_stores_staff_account(): void
    {
        $this->proveBySms($this->owner, '+998907770006');
        $other = $this->makeStore('Evil');
        $otherBranch = $this->makeBranch($other, 'Evil B');
        $otherOwner = $this->makeStaff(UserRole::OWNER, null, $other);
        $this->panel($otherOwner, $other);

        Livewire::test(ManageEmployees::class)
            ->callAction('create', data: ['name' => 'Lifecycle Owner Copy', 'role' => UserRole::MANAGER->value, 'branch_id' => $otherBranch->id, 'phone' => '+998907770006'])
            ->assertHasActionErrors(['phone']);
        $this->assertDatabaseMissing('users', ['name' => 'Lifecycle Owner Copy']);

        // Nor onto an existing employee
        $theirCourier = $this->makeStaff(UserRole::COURIER, $otherBranch, $other);
        Livewire::test(ManageEmployees::class)
            ->callTableAction('edit', $theirCourier, data: ['phone' => '+998907770006'])
            ->assertHasTableActionErrors(['phone']);

        // The holder signs in to their own store only
        $this->staffLogin('+998907770006')->assertOk()->assertJsonPath('data.user.id', $this->owner->id);
    }

    public function test_a_person_in_two_stores_sees_each_store_name_and_role_in_the_picker(): void
    {
        // Vouched by both stores (not SMS-proven, so the second store may add them)
        app(StaffPhoneLoginService::class)->vouch($this->manager, '+998907770007');
        $other = $this->makeStore('Ikkinchi Kafe');
        $otherBranch = $this->makeBranch($other, 'Chilonzor');
        $this->panel($this->makeStaff(UserRole::OWNER, null, $other), $other);
        Livewire::test(ManageEmployees::class)
            ->callAction('create', data: ['name' => 'Moonlighter', 'role' => UserRole::COURIER->value, 'branch_id' => $otherBranch->id, 'phone' => '+998907770007'])
            ->assertHasNoActionErrors();

        $accounts = $this->staffLogin('+998907770007')->assertStatus(409)->json('data.accounts');

        $this->assertSame(['Lifecycle Burger', 'Ikkinchi Kafe'], array_column(array_column($accounts, 'store'), 'name'));
        $this->assertSame(['manager', 'courier'], array_column($accounts, 'role'));
        $this->assertSame([UserRole::MANAGER->labelUz(), UserRole::COURIER->labelUz()], array_column($accounts, 'role_label'));
    }

    public function test_the_super_admin_paths_audit_the_login_phone_too(): void
    {
        $this->owner->update(['phone' => '+998907770008']);
        $this->superadmin();
        Livewire::test(ManageGlobalUsers::class)->callTableAction('vouchLoginPhone', $this->owner);
        $this->assertSame(['staff.login_phone_set'], $this->auditActions($this->owner));
        $this->assertSame($this->admin->id, (int) AuditLog::where('action', 'staff.login_phone_set')->sole()->user_id);

        // Editing the phone there makes it a contact detail: the login phone is cleared, audited
        Livewire::test(ManageGlobalUsers::class)
            ->callTableAction('edit', $this->owner->fresh(), data: ['phone' => '+998907770009'])
            ->assertHasNoTableActionErrors();
        $this->assertNull($this->owner->fresh()->phone_verified_at);
        $this->assertSame(['staff.login_phone_set', 'staff.login_phone_cleared'], $this->auditActions($this->owner));

        // The store wizard
        $region = Region::create(['name_uz' => 'Toshkent shahri', 'is_active' => true]);
        $district = District::create(['region_id' => $region->id, 'name_uz' => 'Chilonzor tumani', 'is_active' => true]);
        Livewire::test(CreateGlobalStore::class)
            ->fillForm([
                'name' => 'Wizard Cafe',
                'commission_rate' => 10,
                'payment_account_type' => 'platform_account',
                'owner_name' => 'Wizard Owner',
                'owner_password' => 'secret123',
                'owner_phone' => '+998907770013',
                'branch_region_id' => $region->id,
                'branch_district_id' => $district->id,
                'branch_address' => 'Bunyodkor 1',
                'branch_radius' => 5,
                'branch_lat' => 41.3,
                'branch_lng' => 69.2,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
        $wizardOwner = User::where('name', 'Wizard Owner')->sole();
        $this->assertSame(['staff.login_phone_set'], $this->auditActions($wizardOwner));
        $this->assertSame($wizardOwner->store_id, (int) AuditLog::where('model_id', $wizardOwner->id)->where('action', 'staff.login_phone_set')->sole()->store_id);
    }

    public function test_a_selection_ticket_is_redeemed_once_under_a_lock(): void
    {
        $service = app(StaffPhoneLoginService::class);
        $service->vouch($this->owner, '+998907770014');
        $accounts = $service->accountsFor('+998907770014');
        $ticket = $service->issueTicket('+998907770014', $accounts);
        $key = 'staff-login-selection:' . hash('sha256', $ticket);

        // A concurrent redeem holding the lock: this one gets nothing
        $lock = Cache::lock($key . ':redeem', 10);
        $this->assertTrue($lock->get());
        $this->assertNull($service->redeemTicket($ticket, $this->owner->id));
        $lock->release();

        $this->assertSame($this->owner->id, $service->redeemTicket($ticket, $this->owner->id)?->id);
        $this->assertNull($service->redeemTicket($ticket, $this->owner->id));
    }
}
