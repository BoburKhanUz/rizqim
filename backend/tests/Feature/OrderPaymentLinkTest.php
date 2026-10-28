<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Services\Order\PaymentLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPaymentLinkTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        config([
            'services.click.service_id' => 'click-service',
            'services.click.merchant_id' => 'click-merchant',
            'services.payme.merchant_id' => 'payme-merchant',
        ]);
    }

    public function test_click_and_payme_orders_get_the_platform_checkout_link(): void
    {
        $links = app(PaymentLinkService::class);
        $click = $this->makeOrder(OrderStatus::NEW, ['payment_method' => 'click', 'total_price' => 52500]);
        $payme = $this->makeOrder(OrderStatus::NEW, ['payment_method' => 'payme', 'total_price' => 52500]);
        config(['services.telegram.webapp_url' => null]);

        // P4-32: the customer comes back to the Mini App (TELEGRAM_WEBAPP_URL), else the site; no longer the old /admin panel
        $this->assertSame(
            "https://my.click.uz/services/pay?service_id=click-service&merchant_id=click-merchant&amount=52500&transaction_param={$click->uuid}&return_url=" . urlencode(url('/')),
            $links->forOrder($click)
        );

        $paymeUrl = $links->forOrder($payme);
        $this->assertStringStartsWith('https://checkout.paycom.uz/', $paymeUrl);
        $this->assertSame(
            "m=payme-merchant;ac.order_id={$payme->uuid};a=5250000;c=" . urlencode(url('/')),
            base64_decode(substr($paymeUrl, strlen('https://checkout.paycom.uz/')))
        );
    }

    public function test_checkout_returns_the_link_only_for_online_payment(): void
    {
        $checkout = fn (string $method) => $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
            'store_id' => $this->store->id,
            'type' => 'pickup',
            'branch_id' => $this->branch->id,
            'payment_method' => $method,
            'items' => [['variant_id' => $this->variant->uuid, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertStringStartsWith('https://my.click.uz/services/pay?service_id=click-service', $checkout('click')->json('data.payment_url'));
        $this->assertNull($checkout('cash')->json('data.payment_url'));
    }

    public function test_uzum_and_paynet_orders_never_get_a_link(): void
    {
        $this->store->forceFill(['payment_settings' => [
            'uzum' => ['enabled' => true, 'merchant_id' => 'uzum-merchant'],
            'paynet' => ['enabled' => true, 'service_id' => 'paynet-service', 'merchant_id' => 'paynet-merchant'],
        ]])->save();

        foreach (['uzum', 'paynet', 'cash', 'keshbek'] as $method) {
            $this->assertNull(app(PaymentLinkService::class)->forOrder($this->makeOrder(OrderStatus::NEW, ['payment_method' => $method])), $method);
        }
    }

    public function test_a_provider_the_store_switched_off_gets_an_empty_link(): void
    {
        $this->store->forceFill(['payment_settings' => ['click' => ['enabled' => false]]])->save();

        $this->assertSame('', app(PaymentLinkService::class)->forOrder($this->makeOrder(OrderStatus::NEW, ['payment_method' => 'click'])));
    }
}
