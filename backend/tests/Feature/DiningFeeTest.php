<?php

namespace Tests\Feature;

use App\Enums\OrderChannel;
use App\Models\Order;
use App\Models\ServiceFeeSetting;
use App\Services\Notifications\TelegramNotificationService;
use App\Services\Order\DiningFeeCalculator;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1-21 service charge (xizmat haqi) and place fee (joy narxi): the calculation, the settings API, and that Mini App
 * (telegram) orders never get them.
 */
class DiningFeeTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use MenuFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    public function test_the_fees_math(): void
    {
        $fees = app(DiningFeeCalculator::class);
        ServiceFeeSetting::create(['store_id' => $this->store->id, 'service_charge_percent' => '12.5', 'place_fee_amount' => '15000', 'place_fee_mode' => 'per_person']);

        // 12.5% of 123 457 = 15 432.125 → 15 432; 15 000 × 4 guests
        $result = $fees->calculate($this->store->id, $this->branch->id, OrderChannel::POS_HALL, Money::of('123457'), 4);
        $this->assertSame('15432.00', $result['service_charge']->toString());
        $this->assertSame('60000.00', $result['place_fee']->toString());
        $this->assertSame('12.50', $result['service_charge_percent']);

        // Half a so'm rounds up; no guests counts as one
        $result = $fees->calculate($this->store->id, null, OrderChannel::POS_HALL, Money::of('100004'), null);
        $this->assertSame('12501.00', $result['service_charge']->toString());
        $this->assertSame('15000.00', $result['place_fee']->toString());

        // Not on takeaway, delivery apps, mobile or Telegram by default
        foreach ([OrderChannel::POS_TAKEAWAY, OrderChannel::AGGREGATOR, OrderChannel::MOBILE, OrderChannel::TELEGRAM] as $channel) {
            $result = $fees->calculate($this->store->id, $this->branch->id, $channel, Money::of('100000'), 2);
            $this->assertTrue($result['service_charge']->isZero(), $channel->value);
            $this->assertTrue($result['place_fee']->isZero(), $channel->value);
        }
    }

    public function test_a_branch_overrides_the_store_and_channels_are_configurable_but_never_telegram(): void
    {
        $fees = app(DiningFeeCalculator::class);
        ServiceFeeSetting::create(['store_id' => $this->store->id, 'service_charge_percent' => '10']);
        ServiceFeeSetting::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id,
            'service_charge_percent' => '15', 'service_charge_channels' => ['pos_hall', 'pos_takeaway', 'telegram'],
            'place_fee_amount' => '75000', 'place_fee_mode' => 'per_table', 'place_fee_channels' => ['pos_hall'],
        ]);

        $branch = $fees->calculate($this->store->id, $this->branch->id, OrderChannel::POS_TAKEAWAY, Money::of('200000'), 6);
        $this->assertSame('30000.00', $branch['service_charge']->toString());
        $this->assertTrue($branch['place_fee']->isZero());

        $hall = $fees->calculate($this->store->id, $this->branch->id, OrderChannel::POS_HALL, Money::of('200000'), 6);
        $this->assertSame('75000.00', $hall['place_fee']->toString()); // per table, whatever the guests

        $other = $fees->calculate($this->store->id, $this->otherBranch->id, OrderChannel::POS_HALL, Money::of('200000'), 6);
        $this->assertSame('20000.00', $other['service_charge']->toString());

        $telegram = $fees->calculate($this->store->id, $this->branch->id, OrderChannel::TELEGRAM, Money::of('200000'), 6);
        $this->assertTrue($telegram['service_charge']->isZero());
    }

    public function test_mini_app_orders_never_get_the_fees_even_when_configured(): void
    {
        ServiceFeeSetting::create([
            'store_id' => $this->store->id, 'service_charge_percent' => '15', 'service_charge_channels' => ['pos_hall', 'mobile'],
            'place_fee_amount' => '20000',
        ]);

        $response = $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 2]])->assertCreated();
        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        $this->assertSame('100000.00', $order->getRawOriginal('total_price'));
        $this->assertSame('0.00', $order->getRawOriginal('service_charge_amount'));
        $this->assertSame('0.00', $order->getRawOriginal('place_fee_amount'));
        $this->assertNull($order->guests_count);

        // The mobile channel was configured for the service charge (the place fee stays pos_hall only)
        $response = $this->mobileOrder([['variant_id' => $this->variant->uuid, 'quantity' => 2]])->assertCreated();
        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        $this->assertSame('15000.00', $order->getRawOriginal('service_charge_amount'));
        $this->assertSame('0.00', $order->getRawOriginal('place_fee_amount'));
        $this->assertSame('115000.00', $order->getRawOriginal('total_price'));
    }

    public function test_the_card_shows_the_fees_as_their_own_lines(): void
    {
        $order = $this->makeOrder(\App\Enums\OrderStatus::NEW, [
            'channel' => 'pos_hall', 'total_price' => 135000, 'service_charge_amount' => 15000, 'place_fee_amount' => 20000, 'guests_count' => 2,
        ]);

        [$card] = app(TelegramNotificationService::class)->buildMessageAndKeyboard($order->fresh(['items', 'user']));
        $this->assertStringContainsString('🧾 Xizmat haqi: 15 000 UZS', $card);
        $this->assertStringContainsString('🪑 Joy narxi: 20 000 UZS', $card);

        $plain = $this->makeOrder(\App\Enums\OrderStatus::NEW);
        [$card] = app(TelegramNotificationService::class)->buildMessageAndKeyboard($plain->fresh(['items', 'user']));
        $this->assertStringNotContainsString('Xizmat haqi', $card);
        $this->assertStringNotContainsString('Joy narxi', $card);
    }

    public function test_the_owner_sets_the_fees(): void
    {
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/service-fees', [
            'service_charge_percent' => 10,
            'place_fee_amount' => 75000,
            'place_fee_mode' => 'per_table',
        ])->assertOk()->assertJsonPath('data.branch_id', null)->assertJsonPath('data.place_fee_mode', 'per_table');

        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/service-fees', [
            'branch_id' => $this->branch->id,
            'service_charge_percent' => 12,
            'service_charge_channels' => ['pos_hall', 'pos_takeaway'],
            'place_fee_amount' => 0,
            'place_fee_mode' => 'per_person',
        ])->assertOk();

        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/service-fees')
            ->assertOk()
            ->assertJsonCount(2, 'data.settings')
            ->assertJsonPath('data.settings.0.service_charge_percent', '10.00')
            ->assertJsonPath('data.settings.1.service_charge_channels', ['pos_hall', 'pos_takeaway']);

        // Telegram cannot be configured, nor more than 30%
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/service-fees', [
            'service_charge_percent' => 10, 'service_charge_channels' => ['telegram'], 'place_fee_amount' => 0, 'place_fee_mode' => 'per_person',
        ])->assertStatus(422);
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/service-fees', [
            'service_charge_percent' => 31, 'place_fee_amount' => 0, 'place_fee_mode' => 'per_person',
        ])->assertStatus(422);

        // A manager (no settings.manage) and another store's branch
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/service-fees')->assertForbidden();
        $otherStore = $this->makeStore('Other');
        $otherBranch = $this->makeBranch($otherStore, 'Other main');
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/service-fees', [
            'branch_id' => $otherBranch->id, 'service_charge_percent' => 10, 'place_fee_amount' => 0, 'place_fee_mode' => 'per_person',
        ])->assertStatus(422);

        $this->assertSame(2, ServiceFeeSetting::count());
        $this->assertSame(2, \App\Models\AuditLog::where('action', 'settings.service_fees_changed')->count());
    }
}
