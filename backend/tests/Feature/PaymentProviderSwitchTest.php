<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentProvider;
use App\Models\Store;
use App\Models\StoreTopupRequest;
use App\Services\Payment\PaymentProviderSwitch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-32 (owner decision): the order flow follows the team's switches in Tizim → To'lov tizimlari. An inactive provider
 * is not offered and takes no new payment (`payment_provider_inactive`), payments already started still complete; the
 * mode picks Payme's sandbox checkout for the platform account.
 */
class PaymentProviderSwitchTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    private const PAYME_KEY = 'platform_payme_key';

    private const CLICK_SECRET = 'platform_click_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.payme.secret_key' => self::PAYME_KEY,
            'services.click.secret_key' => self::CLICK_SECRET,
            'services.telegram.webapp_url' => 'https://app.rizqim.test',
            'services.partner_web.url' => 'https://panel.rizqim.test/',
        ]);

        $this->setUpLifecycleStore();
        $this->store->forceFill([
            'payment_account_type' => 'platform_account',
            'payment_settings' => array_merge($this->store->payment_settings, ['payme' => ['enabled' => true]]),
        ])->save();

        PaymentProvider::forceCreate(['name' => 'Payme', 'slug' => 'payme', 'is_active' => true, 'mode' => 'production', 'credentials' => ['merchant_id' => 'platform-merchant']]);
        PaymentProvider::forceCreate(['name' => 'Click', 'slug' => 'click', 'is_active' => true, 'mode' => 'production', 'credentials' => []]);
    }

    private function switchProvider(string $slug, array $change): void
    {
        PaymentProvider::where('slug', $slug)->sole()->forceFill($change)->save();
    }

    private function placeOrder(string $method)
    {
        return $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $this->store->id,
            'type' => 'pickup',
            'branch_id' => $this->branch->id,
            'payment_method' => $method,
            'items' => [['variant_id' => $this->variant->uuid, 'quantity' => 1]],
        ]);
    }

    private function paymeRpc(string $method, array $params)
    {
        return $this->postJson('/api/v1/payments/payme/webhook', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], [
            'Authorization' => 'Basic ' . base64_encode('Paycom:' . self::PAYME_KEY),
        ]);
    }

    private function click(int $action, Order $order, string $amount)
    {
        $signTime = '2026-10-28 12:00:00';
        $data = [
            'click_trans_id' => '9001',
            'service_id' => '32104',
            'click_paydoc_id' => '555',
            'merchant_trans_id' => $order->uuid,
            'amount' => $amount,
            'action' => $action,
            'error' => 0,
            'error_note' => 'Success',
            'sign_time' => $signTime,
            'sign_string' => md5('9001' . '32104' . self::CLICK_SECRET . $order->uuid . ($action === 1 ? $order->id : '') . $amount . $action . $signTime),
        ];
        if ($action === 1) {
            $data['merchant_prepare_id'] = $order->id;
        }

        return $this->postJson('/api/v1/payments/click/webhook', $data);
    }

    public function test_an_inactive_provider_is_not_offered_and_every_store_keeps_what_it_saved(): void
    {
        $this->switchProvider('payme', ['is_active' => false]);

        $stores = collect($this->actingAs($this->customer)->getJson('/api/v1/stores')->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(['enabled' => false, 'available' => false], $stores[$this->store->id]['payment_settings']['payme']);
        $this->assertTrue($stores[$this->store->id]['payment_settings']['click']['enabled']);

        $this->actingAs($this->owner)->getJson('/api/v1/owner/settings')->assertOk()
            ->assertJsonPath('data.payment_settings.payme', ['enabled' => false, 'available' => false, 'unavailable_reason' => 'switched_off'])
            ->assertJsonPath('data.payment_settings.uzum.unavailable_reason', 'not_integrated');

        // Saved as it was, and on again as soon as the team switches it back
        $this->assertTrue($this->store->fresh()->payment_settings['payme']['enabled']);
        $this->switchProvider('payme', ['is_active' => true]);
        $stores = collect($this->actingAs($this->customer)->getJson('/api/v1/stores')->assertOk()->json('data'))->keyBy('id');
        $this->assertTrue($stores[$this->store->id]['payment_settings']['payme']['enabled']);
    }

    public function test_checkout_refuses_an_inactive_provider_with_a_clear_code(): void
    {
        $this->switchProvider('payme', ['is_active' => false]);

        $this->placeOrder('payme')->assertStatus(422)
            ->assertJsonPath('code', PaymentProviderSwitch::INACTIVE_CODE)
            ->assertJsonPath('data.reason', PaymentProviderSwitch::INACTIVE_CODE)
            ->assertJsonPath('data.payment_method', 'payme')
            ->assertJsonPath('errors.payment_method.0', PaymentProviderSwitch::inactiveMessage('payme'));
        $this->assertSame(0, Order::count());
        $this->assertSame(10, (int) $this->variant->fresh()->stock);

        // The other providers are untouched
        $this->placeOrder('click')->assertCreated();
        $this->placeOrder('cash')->assertCreated();
    }

    public function test_the_switch_applies_at_once_through_the_admin_screen_cache(): void
    {
        // A first read fills the cache
        $this->placeOrder('click')->assertCreated();

        $this->switchProvider('click', ['is_active' => false]);
        $this->placeOrder('click')->assertStatus(422)->assertJsonPath('code', PaymentProviderSwitch::INACTIVE_CODE);

        $this->switchProvider('click', ['is_active' => true]);
        $this->placeOrder('click')->assertCreated();
    }

    public function test_a_store_top_up_through_an_inactive_provider_is_refused(): void
    {
        $this->switchProvider('click', ['is_active' => false]);

        $this->actingAs($this->owner)->postJson('/api/v1/owner/finance/top-up', ['amount' => 50000, 'provider' => 'click'])
            ->assertStatus(422)
            ->assertJsonPath('code', PaymentProviderSwitch::INACTIVE_CODE)
            ->assertJsonPath('errors.provider.0', PaymentProviderSwitch::inactiveMessage('click'));
        $this->assertSame(0, StoreTopupRequest::count());

        $url = $this->actingAs($this->owner)->postJson('/api/v1/owner/finance/top-up', ['amount' => 50000, 'provider' => 'payme'])
            ->assertOk()->json('data.payment_url');
        $this->assertStringStartsWith('https://checkout.paycom.uz/', $url);
        // Back to the partner panel's Moliya page, not the removed /admin panel
        $this->assertStringContainsString('c=' . urlencode('https://panel.rizqim.test/finance'), base64_decode(substr($url, strlen('https://checkout.paycom.uz/'))));
    }

    public function test_payme_callbacks_for_a_payment_already_started_still_work_when_switched_off(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW, ['payment_method' => 'payme', 'payment_owner' => 'PLATFORM', 'payment_status' => PaymentStatus::PENDING->value]);
        $this->paymeRpc('CreateTransaction', ['id' => 'trx-1', 'time' => 1760000000000, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]])
            ->assertJsonPath('result.state', 1);

        $this->switchProvider('payme', ['is_active' => false, 'mode' => 'test']);

        $this->paymeRpc('CheckTransaction', ['id' => 'trx-1'])->assertJsonPath('result.state', 1);
        $this->paymeRpc('PerformTransaction', ['id' => 'trx-1'])->assertJsonPath('result.state', 2);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
    }

    public function test_click_callbacks_for_an_order_already_placed_still_work_when_switched_off(): void
    {
        $order = $this->makeOrder(OrderStatus::NEW, ['payment_method' => 'click', 'payment_owner' => 'PLATFORM', 'payment_status' => PaymentStatus::PENDING->value]);

        $this->switchProvider('click', ['is_active' => false]);

        $this->click(0, $order, '50000')->assertJson(['error' => 0]);
        $this->click(1, $order, '50000')->assertJson(['error' => 0]);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
    }

    public function test_the_mode_picks_paymes_sandbox_for_the_platform_account_only(): void
    {
        $production = $this->placeOrder('payme')->assertCreated()->json('data.payment_url');
        $this->assertStringStartsWith('https://checkout.paycom.uz/', $production);
        $params = base64_decode(substr($production, strlen('https://checkout.paycom.uz/')));
        $this->assertStringContainsString('m=platform-merchant;', $params);
        // Back to the Mini App, not the removed /admin panel
        $this->assertStringContainsString('c=' . urlencode('https://app.rizqim.test'), $params);

        $this->switchProvider('payme', ['mode' => 'test']);
        $sandbox = $this->placeOrder('payme')->assertCreated()->json('data.payment_url');
        $this->assertStringStartsWith('https://checkout.test.paycom.uz/', $sandbox);
        $this->assertStringContainsString('m=platform-merchant;', base64_decode(substr($sandbox, strlen('https://checkout.test.paycom.uz/'))));

        $topup = $this->actingAs($this->owner)->postJson('/api/v1/owner/finance/top-up', ['amount' => 50000, 'provider' => 'payme'])->assertOk()->json('data.payment_url');
        $this->assertStringStartsWith('https://checkout.test.paycom.uz/', $topup);

        // A store collecting into its own Payme cashbox has no test mode of the platform's
        $this->store->forceFill([
            'payment_account_type' => 'own_account',
            'payment_settings' => array_merge($this->store->payment_settings, ['payme' => ['enabled' => true, 'merchant_id' => 'own-cashbox', 'secret_key' => 'own-key']]),
        ])->save();
        $own = $this->placeOrder('payme')->assertCreated()->json('data.payment_url');
        $this->assertStringStartsWith('https://checkout.paycom.uz/', $own);

        // Click has one checkout host in both modes
        $this->switchProvider('click', ['mode' => 'test']);
        $this->assertStringStartsWith('https://my.click.uz/services/pay?', $this->placeOrder('click')->assertCreated()->json('data.payment_url'));
    }

    public function test_a_provider_without_a_row_is_on_and_in_production(): void
    {
        PaymentProvider::query()->delete();

        $this->assertTrue(Store::isPaymentMethodAvailable('payme'));
        $url = $this->placeOrder('payme')->assertCreated()->json('data.payment_url');
        $this->assertStringStartsWith('https://checkout.paycom.uz/', $url);
    }

    public function test_a_store_created_while_a_provider_is_off_still_starts_with_it_on(): void
    {
        $this->switchProvider('payme', ['is_active' => false]);

        $settings = Store::initialPaymentSettings(['cash', 'click', 'payme']);
        $this->assertTrue($settings['payme']['enabled']);
        $this->assertFalse($settings['uzum']['enabled']);
    }

    public function test_the_owner_cannot_switch_on_a_provider_the_team_switched_off_and_saving_keeps_it(): void
    {
        $this->switchProvider('payme', ['is_active' => false]);

        $settings = $this->actingAs($this->owner)->getJson('/api/v1/owner/settings')->assertOk()->json('data');
        $body = [
            'name' => $settings['name'],
            'timezone' => $settings['timezone'],
            'is_open' => true,
            'payment_settings' => ['cash' => ['enabled' => true], 'click' => ['enabled' => true], 'payme' => ['enabled' => true]],
        ];
        $this->actingAs($this->owner)->postJson('/api/v1/owner/settings', $body)->assertStatus(422)
            ->assertJsonPath('errors.payment_settings.0', "Payme orqali to'lovni RIZQIM vaqtincha o'chirgan, uni yoqib bo'lmaydi.");

        $body['payment_settings']['payme'] = ['enabled' => false];
        $this->actingAs($this->owner)->postJson('/api/v1/owner/settings', $body)->assertOk();
        $this->assertTrue($this->store->fresh()->payment_settings['payme']['enabled']);
    }
}
