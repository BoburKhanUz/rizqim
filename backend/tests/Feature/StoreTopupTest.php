<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Store;
use App\Models\User;
use App\Models\StoreTopupRequest;
use App\Enums\UserRole;

class StoreTopupTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;
    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::forceCreate([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'balance' => 150000.00, // owes 150,000 UZS
            'credit_limit' => -100000.00, // limit is 100,000 UZS debt
            'commission_rate' => 10,
            'status' => 'payment_required', // blocked due to debt limit exceeded
        ]);

        $this->owner = User::create([
            'name' => 'Owner Name',
            'phone' => '+998901234567',
            'role' => UserRole::OWNER->value,
            'store_id' => $this->store->id,
            'password' => bcrypt('password'),
        ]);
    }

    public function test_owner_can_initiate_click_topup(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/owner/finance/top-up', [
                'amount' => 50000,
                'provider' => 'click',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'topup_uuid',
                    'payment_url',
                ]
            ]);

        $this->assertDatabaseHas('store_topup_requests', [
            'store_id' => $this->store->id,
            'amount' => 50000.00,
            'provider' => 'click',
            'status' => 'pending',
        ]);
    }

    public function test_click_webhook_prepares_and_completes_topup(): void
    {
        config(['services.click.secret_key' => 'click_test_secret']);

        $topup = StoreTopupRequest::create([
            'store_id' => $this->store->id,
            'amount' => 250000.00,
            'provider' => 'click',
            'status' => 'pending',
        ]);

        // 1. Prepare (Action 0)
        $signString = md5(
            '111222' .
            '32104' .
            'click_test_secret' .
            'topup_' . $topup->uuid .
            '250000' .
            '0' .
            '2026-06-06 12:00:00'
        );

        $prepareResponse = $this->postJson('/api/v1/payments/click/webhook', [
            'click_trans_id' => '111222',
            'service_id' => '32104',
            'click_paydoc_id' => '333444',
            'merchant_trans_id' => 'topup_' . $topup->uuid,
            'amount' => '250000',
            'action' => 0,
            'error' => 0,
            'sign_time' => '2026-06-06 12:00:00',
            'sign_string' => $signString,
        ]);

        $prepareResponse->assertStatus(200)
            ->assertJson([
                'error' => 0,
                'error_note' => 'Success',
            ]);

        // 2. Complete (Action 1)
        $signStringComplete = md5(
            '111222' .
            '32104' .
            'click_test_secret' .
            'topup_' . $topup->uuid .
            $topup->id .
            '250000' .
            '1' .
            '2026-06-06 12:00:00'
        );

        $completeResponse = $this->postJson('/api/v1/payments/click/webhook', [
            'click_trans_id' => '111222',
            'service_id' => '32104',
            'click_paydoc_id' => '333444',
            'merchant_trans_id' => 'topup_' . $topup->uuid,
            'merchant_prepare_id' => $topup->id,
            'amount' => '250000',
            'action' => 1,
            'error' => 0,
            'sign_time' => '2026-06-06 12:00:00',
            'sign_string' => $signStringComplete,
        ]);

        $completeResponse->assertStatus(200)
            ->assertJson([
                'error' => 0,
                'error_note' => 'Success',
            ]);

        // Assert topup is completed
        $this->assertDatabaseHas('store_topup_requests', [
            'id' => $topup->id,
            'status' => 'paid',
        ]);

        // Assert balance is updated (150,000 - 250,000 = -100,000 UZS debt/surplus)
        $this->store->refresh();
        $this->assertEquals(-100000.00, $this->store->balance);

        // Assert store is reactivated (debt is now <= -100,000 UZS)
        $this->assertEquals('active', $this->store->status->value);

        // Assert transaction log is recorded
        $this->assertDatabaseHas('store_transactions', [
            'store_id' => $this->store->id,
            'amount' => -250000.00,
            'balance_after' => -100000.00,
        ]);
    }

    public function test_payout_respects_dynamic_min_payout_reserve(): void
    {
        // 1. Give store some wallet balance but set min_payout_reserve
        $this->store->forceFill([
            'wallet_balance' => 200000.00,
            'min_payout_reserve' => 150000.00, // reserve is 150,000 UZS
        ])->save();

        // 2. Request payout of 100,000 UZS (which would leave 100,000 UZS, below the 150,000 UZS reserve)
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/owner/payouts', [
                'amount' => 100000,
                'card_number' => '8600123456789012',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        // 3. Request payout of 50,000 UZS (which leaves 150,000 UZS reserve) - should succeed
        $responseSuccess = $this->actingAs($this->owner)
            ->postJson('/api/v1/owner/payouts', [
                'amount' => 50000,
                'card_number' => '8600123456789012',
            ]);

        $responseSuccess->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_superadmin_can_approve_payout_and_wallet_is_deducted(): void
    {
        // 1. Give store wallet balance and create a pending payout request
        $this->store->forceFill([
            'wallet_balance' => 300000.00,
            'min_payout_reserve' => 100000.00,
        ])->save();

        $payout = \App\Models\PayoutRequest::create([
            'store_id' => $this->store->id,
            'amount' => 150000.00,
            'card_number' => '8600123456789012',
            'status' => 'pending',
        ]);

        // 2. Perform state transition: pending -> completed (simulate Superadmin action)
        $payout->update([
            'status' => 'completed',
            'resolution_notes' => 'Approved by SuperAdmin',
        ]);

        // 3. Verify store wallet balance is reduced
        $this->store->refresh();
        $this->assertEquals(150000.00, $this->store->wallet_balance);

        // 4. Verify wallet transaction is logged
        $this->assertDatabaseHas('store_wallet_transactions', [
            'store_id' => $this->store->id,
            'amount' => -150000.00,
            'balance_before' => 300000.00,
            'balance_after' => 150000.00,
        ]);
    }

    public function test_payment_settings_sync_and_merge(): void
    {
        $this->store->update([
            'payment_settings' => [
                'delivery_fee' => 12000,
                'click' => [
                    'enabled' => true,
                    'merchant_id' => '12345',
                    'secret_key' => 'secret_val',
                ],
                'allowed_methods' => ['cash', 'click'],
            ]
        ]);

        $this->store->refresh();
        $this->assertTrue((bool)$this->store->accept_click);
        $this->assertFalse((bool)$this->store->accept_payme);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/owner/settings', [
                'is_open' => true,
                'payment_settings' => [
                    'delivery_fee' => 15000,
                    'click' => ['enabled' => false],
                    'payme' => ['enabled' => true],
                ]
            ]);

        $response->assertStatus(200);

        $this->store->refresh();
        $settings = $this->store->payment_settings;

        $this->assertEquals(15000, $settings['delivery_fee']);
        $this->assertFalse($settings['click']['enabled']);
        $this->assertEquals('12345', $settings['click']['merchant_id']);
        $this->assertEquals('secret_val', $settings['click']['secret_key']);
        $this->assertTrue($settings['payme']['enabled']);

        $this->assertFalse((bool)$this->store->accept_click);
        $this->assertTrue((bool)$this->store->accept_payme);
    }

    public function test_owner_can_configure_min_order_amount(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/owner/settings');

        $response->assertStatus(200)
            ->assertJsonPath('data.min_order_amount', 0);

        $updateResponse = $this->actingAs($this->owner)
            ->postJson('/api/v1/owner/settings', [
                'is_open' => true,
                'min_order_amount' => 45000,
                'payment_settings' => [
                    'delivery_fee' => 10000,
                ]
            ]);

        $updateResponse->assertStatus(200);

        $this->store->refresh();
        $this->assertEquals(45000.00, $this->store->min_order_amount);

        $responseAfter = $this->actingAs($this->owner)
            ->getJson('/api/v1/owner/settings');

        $responseAfter->assertStatus(200)
            ->assertJsonPath('data.min_order_amount', 45000);
    }
}
