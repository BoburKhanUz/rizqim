<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uzum and Paynet have no payment webhook yet, so they are switched off everywhere while what stores saved is kept.
 */
class PaymentUnavailableMethodsTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    private const SAVED_UZUM = ['enabled' => true, 'merchant_id' => 'uzum-merchant', 'secret_key' => 'uzum-secret'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->store->forceFill([
            'payment_account_type' => 'own_account',
            'payment_settings' => array_merge($this->store->payment_settings, [
                'payme' => ['enabled' => true],
                'uzum' => self::SAVED_UZUM,
                'paynet' => true,
            ]),
        ])->save();
    }

    private function assertSavedSettingsKept(): void
    {
        $settings = $this->store->fresh()->payment_settings;
        $this->assertEquals(self::SAVED_UZUM, $settings['uzum']);
        // Saved as a legacy bare `true`, which saving turns into its section form
        $this->assertSame(['enabled' => true], $settings['paynet']);
    }

    public function test_checkout_refuses_uzum_and_paynet_even_where_a_store_enabled_them(): void
    {
        foreach (['uzum', 'paynet'] as $method) {
            $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', [
                'store_id' => $this->store->id,
                'type' => 'pickup',
                'branch_id' => $this->branch->id,
                'payment_method' => $method,
                'items' => [['variant_id' => $this->variant->uuid, 'quantity' => 1]],
            ])->assertStatus(422)->assertJsonPath('errors.payment_method.0', "Bu to'lov usuli hozircha mavjud emas");
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(10, (int) $this->variant->fresh()->stock);
    }

    public function test_serialized_stores_show_them_unavailable_whatever_was_saved(): void
    {
        $bare = $this->makeStore('Bare Cafe');
        $bare->forceFill(['payment_settings' => null])->save();
        $this->makeBranch($bare, 'Bare Branch');

        $stores = collect($this->actingAs($this->customer)->getJson('/api/v1/stores')->assertOk()->json('data'))->keyBy('id');

        foreach ([$this->store->id, $bare->id] as $id) {
            $this->assertSame(['enabled' => false, 'available' => false], $stores[$id]['payment_settings']['uzum']);
            $this->assertSame(['enabled' => false, 'available' => false], $stores[$id]['payment_settings']['paynet']);
        }
        $this->assertTrue($stores[$this->store->id]['payment_settings']['payme']['enabled']);
        $this->assertSavedSettingsKept();
    }

    public function test_owner_settings_show_them_unavailable_and_refuse_to_enable_them(): void
    {
        $this->actingAs($this->owner)->getJson('/api/v1/owner/settings')->assertOk()
            ->assertJsonPath('data.payment_settings.uzum', ['enabled' => false, 'available' => false, 'unavailable_reason' => 'not_integrated'])
            ->assertJsonPath('data.payment_settings.paynet', ['enabled' => false, 'available' => false, 'unavailable_reason' => 'not_integrated'])
            ->assertJsonPath('data.payment_settings.payme.enabled', true)
            ->assertJsonPath('data.allowed_methods', ['cash', 'click', 'payme']);

        $payload = fn (array $uzum) => ['is_open' => true, 'payment_settings' => ['payme' => ['enabled' => true], 'uzum' => $uzum]];

        $this->actingAs($this->owner)->postJson('/api/v1/owner/settings', $payload(['enabled' => true]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_settings');
        $this->actingAs($this->owner)->postJson('/api/v1/owner/settings', ['is_open' => true, 'payment_settings' => ['paynet' => true]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('payment_settings');
        $this->assertSavedSettingsKept();

        // The mini-app always sends every toggle: switched off it saves, without touching what was stored
        $this->actingAs($this->owner)->postJson('/api/v1/owner/settings', $payload(['enabled' => false, 'merchant_id' => 'other']))
            ->assertOk()
            ->assertJsonPath('data.payment_settings.uzum', ['enabled' => false, 'available' => false]);
        $this->assertSavedSettingsKept();
    }
}
