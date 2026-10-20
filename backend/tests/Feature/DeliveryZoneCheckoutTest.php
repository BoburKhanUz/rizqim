<?php

namespace Tests\Feature;

use App\Actions\Order\CreateOrderAction;
use App\Domain\Delivery\ZoneResolver;
use App\Enums\BranchStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Models\Branch;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Services\Finance\LedgerService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * P1-32: POST /orders routes and prices a delivery order by the store's delivery zones (DeliveryQuoter, the same as
 * GET /delivery/quote); stores without zones keep the branch radius rule and the store's delivery fee.
 */
class DeliveryZoneCheckoutTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use DeliveryZoneFixtures;

    // Amir Temur Square (inside the centre box), Chorsu Bazaar (outside the centre box, inside the city box)
    private const INSIDE = ['latitude' => 41.3111, 'longitude' => 69.2797];
    private const OUTSIDE = ['latitude' => 41.3265, 'longitude' => 69.2358];
    // Far outside every box (Samarkand)
    private const NOWHERE = ['latitude' => 39.6542, 'longitude' => 66.9597];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        // Main branch next to the square, the second one in the north of the city
        $this->branch->update(['latitude' => 41.3138, 'longitude' => 69.2790]);
        $this->otherBranch->update(['latitude' => 41.3500, 'longitude' => 69.3000]);
        RateLimiter::clear('delivery-quote');
    }

    private function placeDelivery(array $overrides = [], int $quantity = 1, ?Store $store = null): TestResponse
    {
        $store ??= $this->store;
        $variant = $store->is($this->store) ? $this->variant : $this->variantOf($store);

        return $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/orders', array_merge([
            'store_id' => $store->id,
            'type' => 'delivery',
            'payment_method' => 'cash',
            'address' => 'Amir Temur 1',
            'items' => [['variant_id' => $variant->uuid, 'quantity' => $quantity]],
        ], self::INSIDE, $overrides));
    }

    private function quote(array $query): TestResponse
    {
        return $this->getJson('/api/v1/delivery/quote?' . http_build_query($query));
    }

    private function latestOrder(): Order
    {
        return Order::query()->latest('id')->firstOrFail();
    }

    private function variantOf(Store $store): \App\Models\ProductVariant
    {
        $product = \App\Models\Product::create([
            'store_id' => $store->id, 'category_id' => $this->product->category_id,
            'name' => 'Lavash', 'slug' => 'lavash-' . $store->id, 'status' => 'active',
        ]);

        return \App\Models\ProductVariant::create([
            'product_id' => $product->id, 'name' => 'Regular', 'price' => 50000, 'stock' => 10, 'is_active' => true,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    }

    // ---- in zone ---------------------------------------------------------------------------------------------------

    public function test_in_zone_order_takes_the_zone_fee_the_nearest_branch_and_a_snapshot(): void
    {
        // The store-wide fee and minimum no longer apply to a zone delivery
        $this->store->forceFill(['min_order_amount' => 200000, 'payment_settings' => array_merge($this->store->payment_settings, ['delivery_fee' => 5000])])->save();
        $zone = $this->makeZone(['delivery_price' => 12000, 'min_order_amount' => 30000]);

        // A fee the client sends is ignored
        $this->placeDelivery(['delivery_price' => 1, 'delivery_fee' => 1])->assertCreated();

        $order = $this->latestOrder();
        $this->assertSame($this->branch->id, $order->branch_id);
        $this->assertSame('50000.00', $order->subtotal);
        $this->assertSame('12000.00', $order->delivery_price);
        $this->assertSame('62000.00', $order->total_price);
        $this->assertSame('6200.00', $order->commission_amount);
        $this->assertSame($zone->id, $order->delivery_zone_id);
        $this->assertSame('Markaz', $order->delivery_zone_name);
        $this->assertSame('12000.00', $order->delivery_zone_price);
        $this->assertSame('30000.00', $order->delivery_min_order_amount);
        $this->assertSame('62000.00', $order->payments()->firstOrFail()->amount);
        $this->assertSame(9, $this->variant->fresh()->stock);
    }

    public function test_the_order_fee_is_the_quote_fee(): void
    {
        $this->makeZone(['delivery_price' => 12000, 'min_order_amount' => 30000, 'free_delivery_from' => 100000]);

        foreach ([1 => '12000.00', 2 => '0.00'] as $quantity => $fee) {
            $quote = $this->quote(['store' => $this->store->uuid, 'lat' => self::INSIDE['latitude'], 'lng' => self::INSIDE['longitude'], 'subtotal' => 50000 * $quantity])->assertOk();
            $this->placeDelivery([], $quantity)->assertCreated();

            $order = $this->latestOrder();
            $this->assertSame($fee, $quote->json('data.delivery_price'));
            $this->assertSame($quote->json('data.delivery_price'), $order->delivery_price);
            $this->assertSame($quote->json('data.branch.id'), $order->branch_id);
            $this->assertSame($quote->json('data.zone.id'), $order->delivery_zone_id);
            $this->assertSame($quote->json('data.min_order_amount'), $order->delivery_min_order_amount);
        }

        // Free delivery: the zone's own price stays in the snapshot
        $this->assertSame('12000.00', $this->latestOrder()->delivery_zone_price);
        $this->assertSame('100000.00', $this->latestOrder()->total_price);
    }

    public function test_the_zone_branch_cooks_the_order_even_when_another_is_nearer(): void
    {
        $this->makeZone(['branch_id' => $this->otherBranch->id]);

        $this->placeDelivery()->assertCreated();

        $this->assertSame($this->otherBranch->id, $this->latestOrder()->branch_id);
    }

    // ---- refusals --------------------------------------------------------------------------------------------------

    public function test_out_of_zone_is_a_422_with_a_reason_and_creates_nothing(): void
    {
        $this->makeZone();

        $this->placeDelivery(self::OUTSIDE)
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => 'Manzil yetkazib berish hududidan tashqarida: bu joy hududdan tashqarida.',
                'data' => ['reason' => 'outside_zone'],
            ]);

        // Even though the branch radius (5 km) would have covered it
        $this->placeDelivery(self::NOWHERE)->assertStatus(422)->assertJsonPath('data.reason', 'outside_zone');

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $this->variant->fresh()->stock);
    }

    public function test_below_the_zone_minimum_is_a_422_with_the_minimum(): void
    {
        $this->makeZone(['min_order_amount' => 120000]);

        $this->placeDelivery([], 2)
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'message' => 'Bu hududda eng kam buyurtma summasi: 120 000 UZS.',
                'data' => ['reason' => 'below_minimum_order', 'min_order_amount' => '120000.00', 'subtotal' => '100000.00'],
            ]);
        $this->assertSame(0, Order::count());

        // Exactly the minimum is enough
        DeliveryZone::query()->update(['min_order_amount' => 100000]);
        $this->placeDelivery([], 2)->assertCreated();
    }

    public function test_the_closed_zone_branch_refuses_the_order(): void
    {
        $this->makeZone(['branch_id' => $this->otherBranch->id]);
        $this->otherBranch->update(['status' => BranchStatus::INACTIVE->value]);

        $this->placeDelivery()->assertStatus(422)->assertJsonPath('data.reason', 'branch_closed');
        $this->assertSame(0, Order::count());
    }

    public function test_zone_delivery_needs_coordinates(): void
    {
        $this->makeZone();

        $this->expectException(ValidationException::class);

        app(CreateOrderAction::class)->execute(
            $this->customer,
            [['variant_id' => $this->variant->uuid, 'quantity' => 1]],
            ['store_id' => $this->store->id, 'type' => 'delivery', 'payment_method' => 'cash', 'address' => 'Amir Temur 1'],
        );
    }

    // ---- overlapping zones -----------------------------------------------------------------------------------------

    public function test_overlapping_zones_pick_by_priority_then_the_smaller_zone(): void
    {
        $centre = $this->makeZone(['name' => 'Markaz', 'delivery_price' => 12000]);
        $city = $this->makeZone(['name' => 'Shahar', 'delivery_price' => 20000], null, self::CITY_BOX);

        // Same priority: the smaller zone
        $this->placeDelivery()->assertCreated();
        $this->assertSame($centre->id, $this->latestOrder()->delivery_zone_id);
        $this->assertSame('12000.00', $this->latestOrder()->delivery_price);

        // Only the city covers Chorsu
        $this->placeDelivery(self::OUTSIDE)->assertCreated();
        $this->assertSame($city->id, $this->latestOrder()->delivery_zone_id);
        $this->assertSame('20000.00', $this->latestOrder()->delivery_price);

        // Higher priority wins over size
        $city->update(['priority' => 5]);
        $this->placeDelivery()->assertCreated();
        $this->assertSame('Shahar', $this->latestOrder()->delivery_zone_name);
        $this->assertSame('20000.00', $this->latestOrder()->delivery_price);

        // An inactive zone is skipped
        $city->update(['active' => false]);
        $this->placeDelivery()->assertCreated();
        $this->assertSame('Markaz', $this->latestOrder()->delivery_zone_name);
    }

    // ---- branch the customer chose ---------------------------------------------------------------------------------

    public function test_a_chosen_branch_must_serve_a_zone_covering_the_point(): void
    {
        $this->makeZone(['branch_id' => $this->branch->id]);

        $this->placeDelivery(['branch_id' => $this->otherBranch->id])
            ->assertStatus(422)
            ->assertJsonPath('data.reason', 'branch_outside_zone');
        $this->assertSame(0, Order::count());

        $this->placeDelivery(['branch_id' => $this->branch->id])->assertCreated();
        $this->assertSame($this->branch->id, $this->latestOrder()->branch_id);
    }

    public function test_a_chosen_branch_is_kept_in_a_zone_without_a_branch(): void
    {
        $this->makeZone();

        // The main branch is nearer, the customer chose the other one
        $this->placeDelivery(['branch_id' => $this->otherBranch->id])->assertCreated();
        $this->assertSame($this->otherBranch->id, $this->latestOrder()->branch_id);

        // Chosen but closed
        $this->otherBranch->update(['is_active' => false]);
        $this->placeDelivery(['branch_id' => $this->otherBranch->id])->assertStatus(422)->assertJsonPath('data.reason', 'branch_closed');
    }

    public function test_a_chosen_branch_takes_the_best_zone_it_serves_and_the_quote_agrees(): void
    {
        $this->makeZone(['name' => 'Asosiy', 'branch_id' => $this->branch->id, 'priority' => 5, 'delivery_price' => 15000]);
        $other = $this->makeZone(['name' => 'Ikkinchi', 'branch_id' => $this->otherBranch->id, 'delivery_price' => 8000], null, self::CITY_BOX);

        $this->placeDelivery()->assertCreated();
        $this->assertSame('15000.00', $this->latestOrder()->delivery_price);

        $quote = $this->quote(['store' => $this->store->uuid, 'lat' => self::INSIDE['latitude'], 'lng' => self::INSIDE['longitude'], 'branch' => $this->otherBranch->id])->assertOk();
        $this->placeDelivery(['branch_id' => $this->otherBranch->id])->assertCreated();

        $order = $this->latestOrder();
        $this->assertSame($other->id, $order->delivery_zone_id);
        $this->assertSame($this->otherBranch->id, $order->branch_id);
        $this->assertSame('8000.00', $order->delivery_price);
        $this->assertSame($quote->json('data.delivery_price'), $order->delivery_price);
        $this->assertSame($quote->json('data.zone.id'), $order->delivery_zone_id);
    }

    // ---- stores without zones: the radius rule, unchanged ----------------------------------------------------------

    public function test_without_zones_the_branch_radius_and_store_fee_apply(): void
    {
        $this->store->forceFill(['payment_settings' => array_merge($this->store->payment_settings, ['delivery_fee' => 9000])])->save();
        // An inactive zone does not count as having zones
        $this->makeZone(['active' => false]);

        $this->placeDelivery()->assertCreated();

        $order = $this->latestOrder();
        $this->assertSame($this->branch->id, $order->branch_id);
        $this->assertSame('9000.00', $order->delivery_price);
        $this->assertSame('59000.00', $order->total_price);
        $this->assertNull($order->delivery_zone_id);
        $this->assertNull($order->delivery_zone_name);
        $this->assertNull($order->delivery_zone_price);
        $this->assertNull($order->delivery_min_order_amount);

        // A chosen branch is ignored for delivery, as before
        $this->placeDelivery(['branch_id' => $this->otherBranch->id])->assertCreated();
        $this->assertSame($this->branch->id, $this->latestOrder()->branch_id);
    }

    public function test_without_zones_out_of_radius_and_store_minimum_answer_as_before(): void
    {
        $this->placeDelivery(self::NOWHERE)
            ->assertStatus(400)
            ->assertExactJson([
                'success' => false,
                'message' => 'Uzr, siz kiritgan manzil ushbu restoranning yetkazib berish hududidan tashqarida joylashgan.',
                'data' => [],
            ]);

        $this->store->forceFill(['min_order_amount' => 120000])->save();
        $this->placeDelivery([], 2)->assertStatus(400)->assertJsonPath('message', 'Eng kam buyurtma summasi: 120 000 UZS.');
        $this->assertSame(0, Order::count());
    }

    public function test_pickup_ignores_zones(): void
    {
        $this->store->forceFill(['min_order_amount' => 120000])->save();
        $this->makeZone(['min_order_amount' => 0, 'delivery_price' => 12000]);

        $pickup = ['type' => 'pickup', 'branch_id' => $this->otherBranch->id, 'latitude' => null, 'longitude' => null, 'address' => null];
        // The store's minimum still applies to pickup
        $this->placeDelivery($pickup, 2)->assertStatus(400);
        $this->placeDelivery($pickup, 3)->assertCreated();

        $order = $this->latestOrder();
        $this->assertSame($this->otherBranch->id, $order->branch_id);
        $this->assertSame('0.00', $order->delivery_price);
        $this->assertSame('150000.00', $order->total_price);
        $this->assertNull($order->delivery_zone_id);
    }

    // ---- money -----------------------------------------------------------------------------------------------------

    public function test_money_is_exact_to_the_tiyin(): void
    {
        // Line totals are whole UZS (docs/MENU.md); the zone fee may carry tiyin
        $this->variant->update(['price' => 33333]);
        $this->makeZone(['delivery_price' => '12345.67', 'min_order_amount' => '99999.00', 'free_delivery_from' => '100000.00']);

        // 3 x 33 333 = 99 999: meets the minimum exactly, one sum short of free delivery
        $this->placeDelivery([], 3)->assertCreated();

        $order = $this->latestOrder();
        $this->assertSame('99999.00', $order->subtotal);
        $this->assertSame('12345.67', $order->delivery_price);
        $this->assertSame('112344.67', $order->total_price);
        // 10% of 112 344.67 = 11 234.467, half-up
        $this->assertSame('11234.47', $order->commission_amount);
        $this->assertSame('12345.67', $this->quote(['store' => $this->store->uuid, 'lat' => self::INSIDE['latitude'], 'lng' => self::INSIDE['longitude'], 'subtotal' => '99999'])->json('data.delivery_price'));

        // A tiyin below the minimum is below it
        DeliveryZone::query()->update(['min_order_amount' => '99999.01']);
        $this->placeDelivery([], 3)->assertStatus(422)
            ->assertJsonPath('data.min_order_amount', '99999.01')
            ->assertJsonPath('data.subtotal', '99999.00')
            ->assertJsonPath('message', 'Bu hududda eng kam buyurtma summasi: 99 999.01 UZS.');

        // Reaching the free-delivery threshold exactly: no fee
        DeliveryZone::query()->update(['min_order_amount' => 0, 'free_delivery_from' => '99999.00']);
        $this->placeDelivery([], 3)->assertCreated();
        $this->assertSame('0.00', $this->latestOrder()->delivery_price);
        $this->assertSame('99999.00', $this->latestOrder()->total_price);
    }

    public function test_the_zone_fee_reaches_the_ledger_like_the_store_fee_did(): void
    {
        // The same 12 000 fee, once from the store setting (no zones) and once from a zone
        $this->store->forceFill(['payment_settings' => array_merge($this->store->payment_settings, ['delivery_fee' => 12000])])->save();
        $this->placeDelivery(['payment_method' => 'click'])->assertCreated();
        $radius = $this->latestOrder();

        $this->makeZone(['delivery_price' => 12000]);
        $this->placeDelivery(['payment_method' => 'click'])->assertCreated();
        $zoned = $this->latestOrder();

        foreach (['subtotal', 'delivery_price', 'total_price', 'commission_amount', 'payment_owner'] as $column) {
            $this->assertSame($radius->{$column}, $zoned->{$column}, $column);
        }

        foreach ([$radius, $zoned] as $order) {
            $order->update(['status' => OrderStatus::DELIVERED->value, 'payment_status' => PaymentStatus::PAID->value]);
            app(LedgerService::class)->processOrderDelivered($order->fresh());
        }

        foreach ([$radius, $zoned] as $order) {
            // Commission from the snapshot (wallet first, then debt), the full total settled to the wallet
            $commission = Money::sum(
                ...StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::COMMISSION->value)->pluck('amount')->all(),
                ...StoreWalletTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::COMMISSION->value)->pluck('amount')->map(fn ($amount) => Money::of($amount)->abs())->all(),
            );
            $this->assertSame('6200.00', $commission->toString());
            $this->assertSame(['62000.00'], StoreWalletTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::SETTLEMENT->value)->pluck('amount')->map(fn ($amount) => Money::of($amount)->toString())->all());
        }
    }

    // ---- history and concurrency -----------------------------------------------------------------------------------

    public function test_editing_or_deleting_the_zone_later_leaves_the_order_as_it_was(): void
    {
        $zone = $this->makeZone(['delivery_price' => 12000, 'min_order_amount' => 30000]);
        $this->placeDelivery()->assertCreated();
        $order = $this->latestOrder();

        $zone->update(['name' => 'Yangi nom', 'delivery_price' => 25000, 'min_order_amount' => 90000]);
        $order->refresh();
        $this->assertSame('12000.00', $order->delivery_price);
        $this->assertSame('Markaz', $order->delivery_zone_name);
        $this->assertSame('12000.00', $order->delivery_zone_price);
        $this->assertSame('30000.00', $order->delivery_min_order_amount);

        $zone->delete();
        $order->refresh();
        $this->assertNull($order->delivery_zone_id);
        $this->assertSame('Markaz', $order->delivery_zone_name);
        $this->assertSame('12000.00', $order->delivery_zone_price);
        $this->assertSame('62000.00', $order->total_price);
    }

    public function test_a_zone_edited_during_checkout_never_splits_fee_and_snapshot(): void
    {
        $zone = $this->makeZone(['delivery_price' => 12000, 'min_order_amount' => 30000]);

        // The owner saves new prices right after checkout has read the zone
        $this->app->instance(ZoneResolver::class, new class extends ZoneResolver {
            public function resolve(Store|int $store, float $lat, float $lng, ?int $branchId = null): ?DeliveryZone
            {
                $zone = parent::resolve($store, $lat, $lng, $branchId);
                DeliveryZone::query()->whereKey($zone?->id)->update(['delivery_price' => 99000, 'min_order_amount' => 99000]);

                return $zone;
            }
        });
        $this->app->forgetInstance(\App\Domain\Delivery\DeliveryQuoter::class);
        $this->app->forgetInstance(CreateOrderAction::class);

        $this->placeDelivery()->assertCreated();

        $order = $this->latestOrder();
        $this->assertSame($zone->id, $order->delivery_zone_id);
        // Priced, checked and recorded from the zone as it was read: one version throughout
        $this->assertSame('12000.00', $order->delivery_price);
        $this->assertSame('12000.00', $order->delivery_zone_price);
        $this->assertSame('30000.00', $order->delivery_min_order_amount);
        $this->assertSame('62000.00', $order->total_price);
    }

    // ---- tenant isolation ------------------------------------------------------------------------------------------

    public function test_another_stores_zones_and_branches_never_apply(): void
    {
        $foreign = $this->makeStore('Foreign Grill');
        $foreignBranch = $this->makeBranch($foreign, 'Foreign Branch');
        $foreignBranch->update(['latitude' => 41.3112, 'longitude' => 69.2798]);
        $this->makeZone(['name' => 'Begona', 'delivery_price' => 1000, 'branch_id' => $foreignBranch->id], $foreign, self::CITY_BOX);

        // This store has no zones of its own: the radius rule, the store's fee
        $this->placeDelivery()->assertCreated();
        $this->assertNull($this->latestOrder()->delivery_zone_id);
        $this->assertSame('0.00', $this->latestOrder()->delivery_price);

        // With its own zone, a foreign branch can never be chosen (nor revealed)
        $this->makeZone(['delivery_price' => 12000]);
        $this->placeDelivery(['branch_id' => $foreignBranch->id])->assertStatus(422)->assertJsonPath('data.reason', 'branch_outside_zone');
        $this->quote(['store' => $this->store->uuid, 'lat' => self::INSIDE['latitude'], 'lng' => self::INSIDE['longitude'], 'branch' => $foreignBranch->id])
            ->assertStatus(422)->assertJsonPath('data.reason', 'branch_outside_zone');

        // The foreign store's order uses its own zone and branch
        $this->placeDelivery([], 1, $foreign)->assertCreated();
        $order = $this->latestOrder();
        $this->assertSame($foreign->id, $order->store_id);
        $this->assertSame($foreignBranch->id, $order->branch_id);
        $this->assertSame('Begona', $order->delivery_zone_name);
        $this->assertSame('1000.00', $order->delivery_price);
    }
}
