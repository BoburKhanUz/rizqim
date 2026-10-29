<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PlatformRole;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use App\Services\Payment\Fiscal\ClickMerchantApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\AdminStores\AdminStoresFixtures;
use Tests\TestCase;

/**
 * Review C#2 (wave 3): the merchant account an online order's money goes to is fixed at checkout (`payment_owner`).
 * Its Click and Payme callbacks, refunds and fiscal calls are verified with THAT account's keys, never with the
 * store's current payment account type, so changing the account (Ovqatxonalar → To'lov hisobi) never orphans a
 * payment that is on its way or already paid. The change itself waits while any online order is still unpaid.
 */
class PaymentAccountSwitchTest extends TestCase
{
    use RefreshDatabase;
    use AdminStoresFixtures;

    private const PLATFORM_CLICK = 'platform-click-key';

    private const PLATFORM_PAYME = 'platform-payme-key';

    private const STORE_CLICK = 'store-click-key';

    private const STORE_PAYME = 'store-payme-key';

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.click.secret_key' => self::PLATFORM_CLICK, 'services.payme.secret_key' => self::PLATFORM_PAYME]);
        $this->customer = User::forceCreate(['name' => 'Mijoz', 'phone' => '+998901112233', 'role' => UserRole::CUSTOMER->value]);
    }

    private function ownAccountStore(): Store
    {
        $settings = Store::initialPaymentSettings(['cash', 'click', 'payme']);
        $settings['click'] += ['service_id' => 'S1', 'merchant_id' => 'M1', 'secret_key' => self::STORE_CLICK, 'merchant_user_id' => 'U1'];
        $settings['payme'] += ['merchant_id' => 'PM1', 'secret_key' => self::STORE_PAYME];

        return $this->makeStore(['payment_account_type' => 'own_account', 'payment_settings' => $settings]);
    }

    private function order(Store $store, string $method, string $owner, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'store_id' => $store->id,
            'user_id' => $this->customer->id,
            'total_price' => 50000,
            'status' => OrderStatus::NEW->value,
            'payment_method' => $method,
            'payment_owner' => $owner,
            'payment_status' => PaymentStatus::PENDING->value,
        ], $attributes));
    }

    private function click(Order $order, string $key, int $action)
    {
        $prepareId = $action === 1 ? (string) $order->id : '';

        return $this->postJson('/api/v1/payments/click/webhook', [
            'click_trans_id' => '7' . $order->id, 'service_id' => 'S1', 'click_paydoc_id' => '1',
            'merchant_trans_id' => $order->uuid, 'merchant_prepare_id' => $order->id,
            'amount' => '50000', 'action' => $action, 'error' => 0, 'sign_time' => '2026-10-18 12:00:00',
            'sign_string' => md5('7' . $order->id . 'S1' . $key . $order->uuid . $prepareId . '50000' . $action . '2026-10-18 12:00:00'),
        ]);
    }

    private function payme(string $method, array $params, string $key)
    {
        return $this->postJson('/api/v1/payments/payme/webhook', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params],
            ['Authorization' => 'Basic ' . base64_encode('Paycom:' . $key)]);
    }

    /** What the account change used to allow (or an owner's partner-web change could do): the type flips under a live order */
    private function switchTo(Store $store, string $type): void
    {
        $store->forceFill(['payment_account_type' => $type])->save();
    }

    public function test_own_to_platform_a_store_owned_click_payment_is_still_verified_with_the_stores_key(): void
    {
        $store = $this->ownAccountStore();
        $order = $this->order($store, 'click', 'STORE');
        $this->click($order, self::STORE_CLICK, 0)->assertJson(['error' => 0]);

        $this->switchTo($store, 'platform_account');

        // The platform key never confirms money paid into the store's own merchant account
        $this->click($order, self::PLATFORM_CLICK, 1)->assertJson(['error' => -1]);
        $this->click($order, self::STORE_CLICK, 1)->assertJson(['error' => 0]);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);

        // Click's fiscal call for it still goes to the store's own Click account
        $this->assertSame(['merchant_user_id' => 'U1', 'secret_key' => self::STORE_CLICK, 'service_id' => 'S1'], app(ClickMerchantApi::class)->credentials($order->fresh()));
    }

    public function test_own_to_platform_a_store_owned_payme_payment_completes_and_is_refunded_with_the_stores_key(): void
    {
        $store = $this->ownAccountStore();
        $order = $this->order($store, 'payme', 'STORE');
        $account = ['order_id' => $order->uuid];

        $this->payme('CreateTransaction', ['id' => 'trx-own', 'time' => 1760000000000, 'amount' => 5000000, 'account' => $account], self::STORE_PAYME)->assertJsonPath('result.state', 1);
        $this->switchTo($store, 'platform_account');

        $this->payme('PerformTransaction', ['id' => 'trx-own'], self::PLATFORM_PAYME)->assertJsonPath('error.code', -32504);
        $this->payme('PerformTransaction', ['id' => 'trx-own'], self::STORE_PAYME)->assertJsonPath('result.state', 2);
        $this->payme('CheckTransaction', ['id' => 'trx-own'], self::STORE_PAYME)->assertJsonPath('result.state', 2);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);

        // A refund after the switch: still the store's cashbox
        $this->payme('CancelTransaction', ['id' => 'trx-own', 'reason' => 5], self::PLATFORM_PAYME)->assertJsonPath('error.code', -32504);
        $this->payme('CancelTransaction', ['id' => 'trx-own', 'reason' => 5], self::STORE_PAYME)->assertJsonPath('result.state', -2);
        $this->assertSame(PaymentStatus::REFUNDED, Payment::where('provider_transaction_id', 'trx-own')->sole()->status);

        // The platform cashbox's statement still leaves the store's own cashbox out
        $statement = $this->payme('GetStatement', ['from' => 1759999999000, 'to' => 1760000001000], self::PLATFORM_PAYME)->assertOk();
        $this->assertSame([], $statement->json('result.transactions'));
    }

    public function test_platform_to_own_a_platform_collected_payment_stays_on_the_platform_keys(): void
    {
        $store = $this->makeStore(['payment_account_type' => 'platform_account', 'payment_settings' => Store::initialPaymentSettings(['cash', 'click', 'payme'])]);
        $click = $this->order($store, 'click', 'PLATFORM');
        $payme = $this->order($store, 'payme', 'PLATFORM');
        $this->payme('CreateTransaction', ['id' => 'trx-platform', 'time' => 1760000000000, 'amount' => 5000000, 'account' => ['order_id' => $payme->uuid]], self::PLATFORM_PAYME)
            ->assertJsonPath('result.state', 1);

        // The store now collects into its own accounts (keys entered in the partner panel)
        $own = $this->ownAccountStore();
        $store->forceFill(['payment_account_type' => 'own_account', 'payment_settings' => $own->payment_settings])->save();

        $this->click($click, self::STORE_CLICK, 0)->assertJson(['error' => -1]);
        $this->click($click, self::PLATFORM_CLICK, 0)->assertJson(['error' => 0]);
        $this->click($click, self::PLATFORM_CLICK, 1)->assertJson(['error' => 0]);
        $this->payme('PerformTransaction', ['id' => 'trx-platform'], self::STORE_PAYME)->assertJsonPath('error.code', -32504);
        $this->payme('PerformTransaction', ['id' => 'trx-platform'], self::PLATFORM_PAYME)->assertJsonPath('result.state', 2);
        $this->assertSame([PaymentStatus::PAID, PaymentStatus::PAID], [$click->fresh()->payment_status, $payme->fresh()->payment_status]);

        $statement = $this->payme('GetStatement', ['from' => 1759999999000, 'to' => 1760000001000], self::PLATFORM_PAYME)->assertOk();
        $this->assertSame(['trx-platform'], array_column($statement->json('result.transactions'), 'id'));
    }

    public function test_the_account_waits_for_every_unpaid_online_order_at_any_stage(): void
    {
        $store = $this->ownAccountStore();
        $token = $this->confirmedSession($this->makeMember(PlatformRole::SUPERADMIN));
        $switch = fn () => $this->adminJson('PUT', "/stores/{$store->id}/payment-account", $token, ['payment_account_type' => 'platform_account', 'reason' => 'Kelishuv']);

        // Accepted (or further along) and still unpaid: its callback is on its way
        $accepted = $this->order($store, 'click', 'STORE', ['status' => OrderStatus::ACCEPTED->value]);
        $switch()->assertStatus(409)->assertJsonPath('code', 'payments_in_flight');
        $accepted->forceFill(['status' => OrderStatus::DELIVERED->value])->save();
        $switch()->assertStatus(409)->assertJsonPath('code', 'payments_in_flight');
        // A failed or cancelled attempt on a live order can still be paid again
        $accepted->forceFill(['payment_status' => PaymentStatus::CANCELLED->value])->save();
        $switch()->assertStatus(409)->assertJsonPath('code', 'payments_in_flight');
        $this->assertSame('own_account', $store->fresh()->payment_account_type);

        // Paid, or an order that is over: nothing more is coming
        $accepted->forceFill(['payment_status' => PaymentStatus::PAID->value])->save();
        foreach ([OrderStatus::CANCELED, OrderStatus::REJECTED, OrderStatus::REFUNDED] as $status) {
            $this->order($store, 'payme', 'STORE', ['status' => $status->value]);
        }
        $this->order($store, 'cash', 'STORE', ['status' => OrderStatus::ACCEPTED->value]);
        $switch()->assertOk()->assertJsonPath('data.profile.payment_account_type', 'platform_account');
    }
}
