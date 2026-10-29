<?php

namespace Tests\Feature\AdminStores;

use App\Enums\AdminPermission;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PlatformRolePermission;
use App\Models\User;
use App\Services\Auth\StaffPhoneLoginService;
use App\Services\Finance\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * P4-32: what the old superadmin store and user forms changed and the control center lacked: the store's payment
 * account and payout reserve (stores.manage and, review S#8, finance.manage), and the owner's login phone for an owner
 * who lost the number (stores.impersonate). A step-up and a reason each, audited.
 */
class AdminStoreMoneyAndOwnerTest extends TestCase
{
    use RefreshDatabase;
    use AdminStoresFixtures;

    public function test_payment_account_and_payout_reserve(): void
    {
        $store = $this->makeStore(['payment_account_type' => 'platform_account', 'min_payout_reserve' => '100000.00', 'wallet_balance' => '300000.00']);
        $sales = $this->makeMember(PlatformRole::SALES);
        $token = $this->signIn($sales);

        // Review S#8: it routes money, so stores.manage alone is not enough (admin.finance.manage too)
        $this->adminJson('GET', "/stores/{$store->id}", $token)->assertOk()->assertJsonPath('data.actions.payment_account', false);
        $this->stepUp($sales, $token);
        $this->adminJson('PUT', "/stores/{$store->id}/payment-account", $token, ['min_payout_reserve' => '50000', 'reason' => 'Kelishuv'])
            ->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->assertSame('100000.00', $store->fresh()->min_payout_reserve);

        PlatformRolePermission::create(['platform_role' => PlatformRole::SALES->value, 'permission' => AdminPermission::FINANCE_MANAGE->value, 'allowed' => true]);
        $token = $this->signIn($sales);

        $this->adminJson('GET', "/stores/{$store->id}", $token)->assertOk()
            ->assertJsonPath('data.actions.payment_account', true)
            ->assertJsonPath('data.profile.payment_account_type', 'platform_account')
            ->assertJsonPath('data.finance.min_payout_reserve', '100000.00');

        $this->adminJson('PUT', "/stores/{$store->id}/payment-account", $token, ['min_payout_reserve' => '50000', 'reason' => 'Kelishuv'])
            ->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($sales, $token);
        $this->adminJson('PUT', "/stores/{$store->id}/payment-account", $token, ['min_payout_reserve' => '50000'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('PUT', "/stores/{$store->id}/payment-account", $token, ['reason' => 'Kelishuv'])
            ->assertStatus(422)->assertJsonValidationErrors(['payment_account_type', 'min_payout_reserve']);
        $this->adminJson('PUT', "/stores/{$store->id}/payment-account", $token, ['payment_account_type' => 'bank', 'reason' => 'Kelishuv'])
            ->assertStatus(422)->assertJsonValidationErrors('payment_account_type');
        $this->adminJson('PUT', "/stores/{$store->id}/payment-account", $token, ['min_payout_reserve' => '-1', 'reason' => 'Kelishuv'])
            ->assertStatus(422)->assertJsonValidationErrors('min_payout_reserve');

        $this->adminJson('PUT', "/stores/{$store->id}/payment-account", $token, ['payment_account_type' => 'own_account', 'min_payout_reserve' => '50000', 'reason' => "O'z Payme hisobi"])
            ->assertOk()
            ->assertJsonPath('data.profile.payment_account_type', 'own_account')
            ->assertJsonPath('data.finance.min_payout_reserve', '50000.00')
            ->assertJsonPath('data.finance.available_for_payout', '250000.00');
        $store->refresh();
        $this->assertSame('own_account', $store->payment_account_type);
        $this->assertSame('250000.00', app(LedgerService::class)->availableForPayout($store)->toString());

        $audit = AuditLog::where('action', 'admin.stores.payment_account_changed')->sole();
        $this->assertSame(['payment_account_type' => 'platform_account', 'min_payout_reserve' => '100000.00'], $audit->before);
        $this->assertSame(['payment_account_type' => 'own_account', 'min_payout_reserve' => '50000.00'], $audit->after);
        $this->assertSame("O'z Payme hisobi", $audit->payload_json['reason']);
        $this->assertSame($store->id, (int) $audit->store_id);

        // The same values again change nothing and leave no second entry
        $this->adminJson('PUT', "/stores/{$store->id}/payment-account", $token, ['payment_account_type' => 'own_account', 'reason' => 'Yana'])->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'admin.stores.payment_account_changed')->count());
    }

    public function test_the_account_does_not_change_while_an_online_payment_is_on_its_way(): void
    {
        $store = $this->makeStore(['payment_account_type' => 'platform_account']);
        $customer = User::forceCreate(['name' => 'Mijoz', 'phone' => '+998901112233', 'role' => UserRole::CUSTOMER->value]);
        $order = Order::create([
            'store_id' => $store->id,
            'user_id' => $customer->id,
            'total_price' => 50000,
            'status' => OrderStatus::NEW->value,
            'payment_method' => 'payme',
            'payment_owner' => 'PLATFORM',
            'payment_status' => PaymentStatus::PENDING->value,
        ]);
        $token = $this->confirmedSession($this->makeMember(PlatformRole::SUPERADMIN));

        $this->adminJson('PUT', "/stores/{$store->id}/payment-account", $token, ['payment_account_type' => 'own_account', 'reason' => 'Kelishuv'])
            ->assertStatus(409)->assertJsonPath('code', 'payments_in_flight');
        $this->assertSame('platform_account', $store->fresh()->payment_account_type);
        // The reserve alone may still change
        $this->adminJson('PUT', "/stores/{$store->id}/payment-account", $token, ['min_payout_reserve' => '0', 'reason' => 'Kelishuv'])->assertOk();

        $order->forceFill(['payment_status' => PaymentStatus::PAID->value])->save();
        $this->adminJson('PUT', "/stores/{$store->id}/payment-account", $token, ['payment_account_type' => 'own_account', 'reason' => 'Kelishuv'])->assertOk();
    }

    public function test_viewers_and_other_roles_cannot_change_the_payment_account(): void
    {
        $store = $this->makeStore();
        // By default only the superadmin holds both stores.manage and finance.manage
        foreach ([PlatformRole::SUPPORT, PlatformRole::OPERATOR, PlatformRole::FINANCE, PlatformRole::SALES, PlatformRole::VIEWER] as $role) {
            $token = $this->confirmedSession($this->makeMember($role));
            $this->adminJson('PUT', "/stores/{$store->id}/payment-account", $token, ['min_payout_reserve' => '1', 'reason' => 'Sinov uchun'])->assertForbidden();
        }
    }

    public function test_owner_login_phone_needs_stores_impersonate_and_ends_the_old_numbers_sessions(): void
    {
        $store = $this->makeStore();
        $owner = $this->makeOwner($store, '+998901000001');
        app(StaffPhoneLoginService::class)->vouch($owner, '+998901000001');
        $owner->createToken(\App\Services\Auth\TokenService::ACCESS_TOKEN_NAME);
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $owner->id)->count());

        // A sales member manages the store but may not hand it to another phone
        $sales = $this->makeMember(PlatformRole::SALES);
        $salesToken = $this->confirmedSession($sales);
        $this->adminJson('GET', "/stores/{$store->id}", $salesToken)->assertOk()
            ->assertJsonPath('data.actions.owner_login_phone', false)
            ->assertJsonPath('data.profile.owner_login_phone', ['set' => true, 'via' => 'vouched']);
        $this->adminJson('PUT', "/stores/{$store->id}/owner-login-phone", $salesToken, ['phone' => '+998909999999', 'reason' => 'Raqam yo\'qoldi'])->assertForbidden();

        $superadmin = $this->makeMember(PlatformRole::SUPERADMIN);
        $token = $this->signIn($superadmin);
        $this->adminJson('GET', "/stores/{$store->id}", $token)->assertOk()->assertJsonPath('data.actions.owner_login_phone', true);
        $this->adminJson('PUT', "/stores/{$store->id}/owner-login-phone", $token, ['phone' => '+998909999999', 'reason' => 'Raqam yo\'qoldi'])
            ->assertForbidden()->assertJsonPath('code', 'reauth_required');
        $this->stepUp($superadmin, $token);
        $this->adminJson('PUT', "/stores/{$store->id}/owner-login-phone", $token, ['phone' => '+998909999999'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->adminJson('PUT', "/stores/{$store->id}/owner-login-phone", $token, ['phone' => '90 999 99 99', 'reason' => 'Raqam yo\'qoldi'])
            ->assertStatus(422)->assertJsonPath('code', 'owner_phone_invalid');

        // Another staff account's number is refused
        $other = $this->makeStore();
        $this->makeOwner($other, '+998905550000');
        $this->adminJson('PUT', "/stores/{$store->id}/owner-login-phone", $token, ['phone' => '+998905550000', 'reason' => 'Raqam yo\'qoldi'])
            ->assertStatus(409)->assertJsonPath('code', 'owner_phone_taken');

        $this->adminJson('PUT', "/stores/{$store->id}/owner-login-phone", $token, ['phone' => '+998909999999', 'reason' => "Raqam yo'qoldi"])
            ->assertOk()->assertJsonPath('data.profile.owner_login_phone', ['set' => true, 'via' => 'vouched']);

        $owner->refresh();
        $this->assertSame('+998909999999', StaffPhoneLoginService::loginPhone($owner));
        $this->assertSame(User::PHONE_VIA_VOUCHED, $owner->phone_verified_via);
        $this->assertSame([$owner->id], app(StaffPhoneLoginService::class)->accountsFor('+998909999999')->modelKeys());
        $this->assertSame(0, app(StaffPhoneLoginService::class)->accountsFor('+998901000001')->count());
        // Whoever signed in with the old number signs in again
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $owner->id)->count());

        $audit = AuditLog::where('action', 'admin.stores.owner_login_phone_set')->sole();
        $this->assertSame($owner->id, (int) $audit->model_id);
        $this->assertSame($store->id, (int) $audit->store_id);
        $this->assertStringNotContainsString('9999999', (string) json_encode([$audit->before, $audit->after]));
        $this->assertSame(1, AuditLog::where('action', 'staff.login_phone_changed')->where('store_id', $store->id)->count());
    }

    public function test_owner_login_phone_for_a_store_without_an_owner(): void
    {
        $store = $this->makeStore();
        $token = $this->confirmedSession($this->makeMember(PlatformRole::SUPERADMIN));

        $this->adminJson('PUT', "/stores/{$store->id}/owner-login-phone", $token, ['phone' => '+998909999999', 'reason' => "Raqam yo'qoldi"])
            ->assertStatus(422)->assertJsonPath('code', 'no_owner');
        $this->adminJson('PUT', '/stores/999999/owner-login-phone', $token, ['phone' => '+998909999999', 'reason' => "Raqam yo'qoldi"])
            ->assertNotFound();
    }
}
