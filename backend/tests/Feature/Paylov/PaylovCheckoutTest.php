<?php

namespace Tests\Feature\Paylov;

use App\Enums\PaymentStatus;
use App\Enums\PlatformRole;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentProvider;
use App\Models\Store;
use App\Services\Payment\PaymentProviderSwitch;
use Database\Seeders\PaymentProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Feature\AdminSystem\AdminSystemFixtures;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Wave P: Paylov in the provider registry and at checkout: the link (developer.paylov.uz/merchants/creating-payment-link),
 * the refusal of an unconfigured account before any row (as PR #39 does for Click/Payme), the method shown to customers
 * only while it is on and configured, its keys in the control centre and in a store's own payment settings (never read
 * back), the fake driver off production, and app:check-production.
 */
class PaylovCheckoutTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use PaylovFixtures;
    use AdminSystemFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->store->forceFill([
            'payment_account_type' => 'platform_account',
            'credit_limit' => -1000000,
            'payment_settings' => array_merge($this->store->payment_settings ?? [], ['paylov' => ['enabled' => true]]),
        ])->save();
        $this->setUpPaylov();
    }

    private function placeOrder(string $method = 'paylov', ?Store $store = null)
    {
        return $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => ($store ?? $this->store)->id,
            'type' => 'pickup',
            'branch_id' => $store ? $store->branches()->value('id') : $this->branch->id,
            'payment_method' => $method,
            'items' => [['variant_id' => $this->variant->uuid, 'quantity' => 1]],
        ]);
    }

    /**
     * The query inside a checkout link (base64 after /checkout/create/).
     */
    private static function linkQuery(string $url): string
    {
        $prefix = 'https://my.paylov.uz/checkout/create/';
        self::assertStringStartsWith($prefix, $url);

        return (string) base64_decode(substr($url, strlen($prefix)), true);
    }

    public function test_checkout_answers_a_paylov_link_with_the_exact_amount_in_tiyin_and_the_order_uuid(): void
    {
        $response = $this->placeOrder()->assertCreated();
        $order = Order::where('uuid', $response->json('data.order_uuid'))->sole();

        $this->assertSame('paylov', $order->payment_method);
        $this->assertSame('PLATFORM', $order->payment_owner);
        $this->assertSame('50000.00', (string) $order->total_price);
        $this->assertSame(
            'merchant_id=' . $this->paylov['merchant_id']
                . '&amount=5000000&currency_id=860&return_url=' . urlencode(PaymentProviderSwitch::orderReturnUrl())
                . '&amount_in_tiyin=True&account.order_id=' . $order->uuid,
            self::linkQuery($response->json('data.payment_url')),
        );
        // No Paylov call at checkout
        Http::assertNothingSent();
    }

    public function test_the_link_amount_is_exact_integer_tiyin_never_a_float(): void
    {
        $order = $this->paylovOrder($this->store, ['total_price' => '50000.57']);

        $url = app(\App\Services\Order\PaymentLinkService::class)->forOrder($order);

        $this->assertStringContainsString('&amount=5000057&currency_id=860&', self::linkQuery($url));
        // A store that switched Paylov off gets no link
        $this->store->forceFill(['payment_settings' => array_merge($this->store->payment_settings, ['paylov' => ['enabled' => false]])])->save();
        $this->assertSame('', app(\App\Services\Order\PaymentLinkService::class)->forOrder($order->fresh()));
    }

    public function test_the_carts_store_list_shows_paylov_for_a_store_collecting_into_its_own_account(): void
    {
        foreach (array_keys($this->paylov) as $key) {
            config(["services.paylov.{$key}" => null]);
        }
        $this->store->forceFill([
            'payment_account_type' => 'own_account',
            'payment_settings' => array_merge($this->store->payment_settings ?? [], ['paylov' => ['enabled' => true] + self::makeAccount()]),
        ])->save();

        $cards = $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/stores?for=checkout')->assertOk();
        $card = collect($cards->json('data'))->firstWhere('id', $this->store->id);

        // Offered; as for Click/Payme, only the public merchant id is in the card, never a key
        $this->assertTrue($card['payment_settings']['paylov']['enabled']);
        $this->assertSame(['enabled', 'merchant_id'], array_keys($card['payment_settings']['paylov']));
        $this->assertArrayNotHasKey('payment_account_type', $card);
    }

    public function test_checkout_refuses_paylov_without_every_platform_credential_before_any_row(): void
    {
        foreach (['merchant_id', 'consumer_secret', 'callback_password'] as $key) {
            config(["services.paylov.{$key}" => null]);
            Log::spy();

            $this->placeOrder()->assertStatus(422)
                ->assertJsonPath('code', 'payment_provider_unconfigured')
                ->assertJsonPath('data.reason', 'payment_provider_unconfigured')
                ->assertJsonValidationErrors('payment_method');
            $this->assertSame(0, Order::count(), $key);
            Log::shouldHaveReceived('error')->withArgs(fn ($message, $context = []) => str_contains($message, 'not configured') && ($context['missing'] ?? null) === [$key])->once();

            config(["services.paylov.{$key}" => $this->paylov[$key]]);
        }

        // Stored in the control centre counts as configured
        config(['services.paylov.consumer_secret' => null]);
        PaymentProvider::forceCreate(['name' => 'Paylov', 'slug' => 'paylov', 'is_active' => true, 'mode' => 'production', 'credentials' => ['consumer_secret' => $this->paylov['consumer_secret']]]);
        $this->placeOrder()->assertCreated();
    }

    public function test_a_store_collecting_into_its_own_paylov_account_needs_no_platform_credentials(): void
    {
        foreach (array_keys($this->paylov) as $key) {
            config(["services.paylov.{$key}" => null]);
        }
        $own = self::makeAccount();
        $this->store->forceFill([
            'payment_account_type' => 'own_account',
            'payment_settings' => array_merge($this->store->payment_settings ?? [], ['paylov' => ['enabled' => true] + $own]),
        ])->save();

        $response = $this->placeOrder()->assertCreated();
        $order = Order::where('uuid', $response->json('data.order_uuid'))->sole();

        $this->assertSame('STORE', $order->payment_owner);
        $this->assertStringStartsWith('merchant_id=' . $own['merchant_id'] . '&', self::linkQuery($response->json('data.payment_url')));
    }

    public function test_customers_see_paylov_only_while_it_is_on_and_configured(): void
    {
        $shown = fn () => $this->store->fresh()->toArray()['payment_settings']['paylov'] ?? null;
        $this->assertSame(['enabled' => true], $shown());

        // Not configured: not offered (Click and Payme stay as they were: shown, refused at checkout)
        config(['services.paylov.consumer_key' => null]);
        $this->assertSame(['enabled' => false, 'available' => false], $shown());
        $this->assertNotContains('paylov', $this->store->fresh()->active_payment_methods);
        config(['services.paylov.consumer_key' => $this->paylov['consumer_key']]);
        $this->assertContains('paylov', $this->store->fresh()->active_payment_methods);

        // Switched off by the team: not offered, and checkout refuses it with payment_provider_inactive
        PaymentProvider::forceCreate(['name' => 'Paylov', 'slug' => 'paylov', 'is_active' => false, 'mode' => 'production', 'credentials' => []]);
        $this->assertSame(['enabled' => false, 'available' => false], $shown());
        $this->placeOrder()->assertStatus(422)->assertJsonPath('code', 'payment_provider_inactive');
        $this->assertSame(0, Order::count());

        // The store's own switch
        PaymentProvider::where('slug', 'paylov')->update(['is_active' => true]);
        PaymentProviderSwitch::forget();
        $this->store->forceFill(['payment_settings' => array_merge($this->store->payment_settings, ['paylov' => ['enabled' => false]])])->save();
        $this->assertSame(['enabled' => false], $shown());
        $this->placeOrder()->assertStatus(400);
        $this->assertSame(0, Order::count());
    }

    public function test_the_control_centre_lists_paylov_with_masked_keys_and_stores_each_one(): void
    {
        config(['services.paylov.consumer_secret' => null]);
        $this->seed(PaymentProviderSeeder::class);

        $response = $this->adminJson('GET', '/system/payment-providers', $this->superToken())->assertOk();
        $paylov = collect($response->json('data'))->firstWhere('slug', 'paylov');
        $this->assertSame(['Paylov', true, true, 'production'], [$paylov['name'], $paylov['wired'], $paylov['is_active'], $paylov['mode']]);
        $keys = array_column($paylov['credentials'], null, 'key');
        $this->assertSame(['merchant_id', 'consumer_key', 'consumer_secret', 'username', 'password', 'callback_login', 'callback_password'], array_keys($keys));
        $this->assertSame([false, 'env'], [$keys['consumer_secret']['configured'], $keys['consumer_key']['source']]);
        foreach ($this->paylov as $value) {
            $this->assertStringNotContainsString($value, $response->getContent());
        }

        $secret = Str::random(44);
        $saved = $this->adminJson('PUT', '/system/payment-providers/paylov/credentials/consumer_secret', $this->superToken(), ['value' => $secret, 'reason' => 'Paylov kaliti'])->assertOk();
        $this->assertStringNotContainsString($secret, $saved->getContent());
        $this->assertSame($secret, PaymentProvider::platformCredential('paylov', 'consumer_secret'));
    }

    public function test_an_owner_saves_their_own_paylov_keys_and_never_reads_them_back(): void
    {
        $this->store->forceFill(['payment_account_type' => 'own_account'])->save();
        $own = self::makeAccount();

        $this->actingAs($this->owner)->postJson('/api/v1/owner/settings', [
            'is_open' => true,
            'payment_settings' => ['paylov' => ['enabled' => true] + $own],
        ])->assertOk();

        $stored = $this->store->fresh()->payment_settings['paylov'];
        foreach ($own as $key => $value) {
            $this->assertSame($value, $stored[$key], $key);
        }
        $this->assertTrue($this->store->fresh()->collectsOwnPayments('paylov'));

        $shown = $this->actingAs($this->owner)->getJson('/api/v1/owner/settings')->assertOk()
            ->assertJsonPath('data.payment_settings.paylov.merchant_id', $own['merchant_id'])
            ->assertJsonPath('data.payment_settings.paylov.has_consumer_secret', true)
            ->assertJsonPath('data.payment_settings.paylov.has_callback_login', true)
            ->assertJsonPath('data.payment_settings.paylov.has_username', true);
        $public = $this->getJson('/api/v1/stores/' . $this->store->id);
        foreach (['consumer_key', 'consumer_secret', 'username', 'password', 'callback_login', 'callback_password'] as $key) {
            $this->assertStringNotContainsString($own[$key], $shown->getContent(), $key);
            $this->assertStringNotContainsString($own[$key], $public->getContent(), $key);
        }

        // A blank or masked secret keeps the stored one
        $this->actingAs($this->owner)->postJson('/api/v1/owner/settings', [
            'is_open' => true,
            'payment_settings' => ['paylov' => ['enabled' => true, 'consumer_secret' => '', 'password' => '********']],
        ])->assertOk();
        $this->assertSame([$own['consumer_secret'], $own['password']], [$this->store->fresh()->payment_settings['paylov']['consumer_secret'], $this->store->fresh()->payment_settings['paylov']['password']]);
    }

    public function test_test_mode_off_production_uses_the_fake_driver_and_production_never_does(): void
    {
        config(['app.env' => 'staging']);
        $this->seed(PaymentProviderSeeder::class);
        $this->adminJson('PATCH', '/system/payment-providers/paylov', $this->superToken(), ['mode' => 'test', 'reason' => 'Sinov'])->assertOk();

        $order = $this->paylovOrder($this->store);
        $txn = (string) Str::uuid();
        $this->perform($order, $txn)->assertExactJson(self::answer());

        // No sandbox: the fake confirms the recorded amount, nothing leaves the server
        Http::assertNothingSent();
        $this->assertSame(PaymentStatus::PAID, Payment::where('provider_transaction_id', $txn)->sole()->status);

        // On production the fake is never used, not even with PAYMENTS_ALLOW_TEST_MODE: a live payment is confirmed
        // by the real Paylov
        config(['app.env' => 'production', 'services.payments.allow_test_mode' => true]);
        PaymentProviderSwitch::forget();
        $live = $this->paylovOrder($this->store);
        $liveTxn = (string) Str::uuid();
        $this->perform($live, $liveTxn)->assertExactJson(self::answer());
        $this->assertCount(1, $this->sentTo('/merchant/getTransactions/'));

        // ... and Paylov cannot be put in test there
        $this->adminJson('PATCH', '/system/payment-providers/paylov', $this->superToken(), ['mode' => 'production', 'reason' => 'Ishga tushirdik'])->assertOk();
        $this->adminJson('PATCH', '/system/payment-providers/paylov', $this->superToken(), ['mode' => 'test', 'reason' => 'Sinov'])
            ->assertStatus(422)->assertJsonPath('code', 'test_mode_not_allowed');
    }

    public function test_check_production_names_each_missing_paylov_credential(): void
    {
        config(['app.env' => 'production', 'services.paylov.consumer_secret' => null, 'services.paylov.callback_password' => null]);

        Artisan::call('app:check-production');
        $out = Artisan::output();

        $this->assertStringContainsString('paylov: consumer_secret (PAYLOV_CONSUMER_SECRET), callback_password (PAYLOV_CALLBACK_PASSWORD)', $out);
        foreach ($this->paylov as $value) {
            $this->assertStringNotContainsString($value, $out);
        }

        PaymentProvider::forceCreate(['name' => 'Paylov', 'slug' => 'paylov', 'is_active' => false, 'mode' => 'production', 'credentials' => []]);
        Artisan::call('app:check-production');
        $this->assertStringNotContainsString('PAYLOV_CONSUMER_SECRET', Artisan::output());
    }
}
