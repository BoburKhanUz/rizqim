<?php

namespace Tests\Feature;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSettingsMergeTest extends TestCase
{
    use RefreshDatabase;

    private function store(): Store
    {
        return Store::forceCreate([
            'name' => 'Merge Cafe',
            'payment_account_type' => 'own_account',
            'payment_settings' => [
                'delivery_fee' => 10000,
                'working_hours' => ['open' => '09:00', 'close' => '22:00'],
                'allowed_methods' => ['cash', 'click', 'payme'],
                'click' => ['enabled' => true, 'service_id' => 's-1', 'merchant_id' => 'm-1', 'secret_key' => 'click-secret'],
                'payme' => ['enabled' => true, 'merchant_id' => 'pm-1', 'secret_key' => 'payme-secret'],
            ],
        ]);
    }

    public function test_sections_a_writer_leaves_out_keep_their_stored_values(): void
    {
        $store = $this->store();

        // e.g. the owner panel saving while the Payme section and Click's credential fields are hidden
        $store->update(['payment_settings' => ['delivery_fee' => 15000, 'click' => ['enabled' => false]]]);

        $store = $store->fresh();
        $settings = $store->payment_settings;
        $this->assertSame(15000, $settings['delivery_fee']);
        $this->assertFalse($settings['click']['enabled']);
        $this->assertSame('m-1', $settings['click']['merchant_id']);
        $this->assertSame('click-secret', $settings['click']['secret_key']);
        $this->assertEquals(['enabled' => true, 'merchant_id' => 'pm-1', 'secret_key' => 'payme-secret'], $settings['payme']);
        $this->assertSame(['open' => '09:00', 'close' => '22:00'], $settings['working_hours']);
        $this->assertFalse((bool) $store->accept_click);
        $this->assertTrue((bool) $store->accept_payme);
        $this->assertTrue($store->collectsOwnPayments('payme'));
    }

    public function test_sent_values_win_lists_are_replaced_and_secrets_need_a_new_value(): void
    {
        $store = $this->store();

        $store->update(['payment_settings' => [
            'allowed_methods' => ['cash'],
            'click' => ['merchant_id' => '', 'secret_key' => '********'],
            'payme' => ['merchant_id' => 'pm-2', 'secret_key' => 'payme-new'],
        ]]);

        $store = $store->fresh();
        $settings = $store->payment_settings;
        $this->assertSame(['cash'], $settings['allowed_methods']);
        $this->assertSame('', $settings['click']['merchant_id']);
        $this->assertSame('click-secret', $settings['click']['secret_key']);
        $this->assertSame('pm-2', $settings['payme']['merchant_id']);
        $this->assertSame('payme-new', $settings['payme']['secret_key']);
        // Clearing the merchant id makes the Click credentials incomplete, so new Click orders go to the platform.
        $this->assertFalse($store->collectsOwnPayments('click'));
    }
}
