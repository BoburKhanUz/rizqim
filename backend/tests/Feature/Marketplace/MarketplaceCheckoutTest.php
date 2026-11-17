<?php

namespace Tests\Feature\Marketplace;

use App\Domain\Marketplace\ReadModelCache;
use App\Domain\Marketplace\StoreMenu;
use App\Enums\DeviceType;
use App\Enums\OrderChannel;
use App\Models\Device;
use App\Models\Order;
use App\Models\StopListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\MenuFixtures;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Money is decided by the database at order time: whatever the cached documents say (here: forged in the cache, as
 * a missed invalidation would leave them), checkout prices and checks every line, the store's state and its
 * reachability against the live tables.
 */
class MarketplaceCheckoutTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use MenuFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
    }

    /**
     * Warm the store's documents, then overwrite them in the cache with what the closure makes of them.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $menu
     * @param  callable(array<string, mixed>): array<string, mixed>|null  $store
     * @param  callable(array<string, mixed>): array<string, mixed>|null  $availability
     */
    private function forge(callable $menu, ?callable $store = null, ?callable $availability = null): void
    {
        // A stopped clock: the availability document's key changes with the 15 s bucket of the clock, so on the real
        // clock a bucket edge between the warm-up, the overwrite and the next read lost the forged copy
        $this->freezeTime();
        $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$this->store->uuid}")->assertOk();
        $menus = app(StoreMenu::class);
        $cache = app(ReadModelCache::class)->store();
        $this->assertNotNull($cache);

        $id = (int) $this->store->id;
        foreach ([[$menus->menuKey($id, OrderChannel::TELEGRAM), $menu], [$menus->storeKey($id), $store], [$menus->availabilityKey($id), $availability]] as [$key, $change]) {
            $key = $key[0].':'.app(ReadModelCache::class)->deploy().':'.$key[1];
            $value = ReadModelCache::decode($cache->get($key));
            $this->assertIsArray($value, $key);
            if ($change !== null) {
                $cache->put($key, ReadModelCache::encode($change($value)), 3600);
            }
        }
    }

    /** @return array<string, mixed> */
    private function shownVariant(): array
    {
        $products = $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$this->store->uuid}")->assertOk()->json('data.products');

        return collect($products)->firstWhere('id', $this->product->id)['variants'][0];
    }

    public function test_a_cached_price_is_shown_but_the_order_is_priced_from_the_live_tables(): void
    {
        $this->forge(function (array $menu) {
            $menu['products'][0]['variants'][0]['price'] = '1.00';

            return $menu;
        });
        $this->assertSame('1.00', $this->shownVariant()['price']);

        $response = $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 2]])->assertCreated();
        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        $this->assertSame('100000.00', $order->getRawOriginal('subtotal'));
        $this->assertSame('50000.00', $order->items()->firstOrFail()->getRawOriginal('unit_price'));
    }

    public function test_a_product_the_cache_still_shows_is_refused_when_the_tables_say_it_is_gone(): void
    {
        // The menu as it was cached before the dish was switched off, left in place as a missed invalidation would
        $old = null;
        $this->forge(function (array $menu) use (&$old) {
            return $old = $menu;
        });
        DB::table('product_variants')->where('id', $this->variant->id)->update(['is_active' => false]);
        $this->forge(fn (array $menu) => $old);
        $this->assertSame($this->variant->uuid, $this->shownVariant()['uuid']);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertStatus(400);

        DB::table('product_variants')->where('id', $this->variant->id)->update(['is_active' => true]);
        DB::table('products')->where('id', $this->product->id)->update(['status' => 'inactive']);
        $this->forge(fn (array $menu) => $old);
        $this->assertSame($this->variant->uuid, $this->shownVariant()['uuid']);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertStatus(400);
        $this->assertSame(0, Order::count());
    }

    public function test_a_stop_listed_dish_the_cached_availability_still_offers_is_refused(): void
    {
        StopListEntry::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'subject_type' => 'variant', 'subject_id' => $this->variant->id]);
        $this->forge(fn (array $menu) => $menu, null, function (array $availability) {
            foreach ($availability['branches'] as &$branch) {
                $branch['stopped'] = ['products' => [], 'variants' => [], 'modifiers' => []];
                $branch['stop_list'] = [];
            }

            return $availability;
        });
        $shown = $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$this->store->uuid}")->json('data.availability');
        $this->assertSame([], $shown[0]['stopped']['variants']);

        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertStatus(422);
        $this->assertSame(0, Order::count());
    }

    public function test_a_store_the_cache_shows_open_is_refused_when_it_is_closed_paused_or_locked(): void
    {
        $openCard = function (array $store) {
            $store['schedule']['flag'] = true;
            $store['available'] = true;
            $store['store']['is_order_locked'] = false;

            return $store;
        };

        // Paused (the open switch off)
        DB::table('stores')->where('id', $this->store->id)->update(['is_open' => false]);
        $this->forge(fn (array $menu) => $menu, $openCard);
        $this->assertTrue($this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$this->store->uuid}")->json('data.store.is_open'));
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertStatus(400)->assertJsonPath('message', 'Uzr, ushbu restoran hozir yopiq yoki ish vaqtidan tashqari.');

        // Outside its hours
        DB::table('stores')->where('id', $this->store->id)->update(['is_open' => true, 'open_time' => '03:00:00', 'close_time' => '03:01:00']);
        $this->travelTo(now()->setTimezone('Asia/Tashkent')->setTime(12, 0));
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertStatus(400);

        // Payment required, suspended
        DB::table('stores')->where('id', $this->store->id)->update(['open_time' => '00:00:00', 'close_time' => '00:00:00', 'status' => 'payment_required']);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertStatus(400);
        DB::table('stores')->where('id', $this->store->id)->update(['status' => 'suspended']);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertStatus(400);
        $this->assertSame(0, Order::count());
    }

    public function test_reachability_is_off_by_default_and_when_on_refuses_a_store_whose_terminals_are_silent(): void
    {
        $terminal = Device::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'type' => DeviceType::POS, 'name' => 'Kassa 1']);
        $terminal->forceFill(['last_seen_at' => now()->subHour()])->save();
        DB::table('stores')->where('id', $this->store->id)->update(['marketplace_offline_minutes' => 10]);

        // Off by default (the switch): accepted, shown as accepting
        $this->assertTrue($this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$this->store->uuid}")->json('data.store.accepting_orders'));
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertCreated();

        // On: the store shows as not accepting and checkout refuses with the message
        config(['marketplace.reachability.enabled' => true]);
        $this->assertFalse($this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$this->store->uuid}")->json('data.store.accepting_orders'));
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertStatus(400)->assertJsonPath('message', \App\Domain\Marketplace\StoreReachability::REFUSAL);

        // A terminal seen recently: accepted again (checkout reads it live)
        $terminal->forceFill(['last_seen_at' => now()->subMinutes(2)])->save();
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertCreated();

        // Customer displays do not count; a revoked terminal does not count; no terminal at all: never applies
        $terminal->forceFill(['last_seen_at' => now()->subHour()])->save();
        Device::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'type' => DeviceType::DISPLAY, 'name' => 'Ekran'])->forceFill(['last_seen_at' => now()])->save();
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertStatus(400);
        $terminal->forceFill(['revoked_at' => now()])->save();
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertCreated();

        // A store without its own limit is never affected
        $terminal->forceFill(['revoked_at' => null])->save();
        DB::table('stores')->where('id', $this->store->id)->update(['marketplace_offline_minutes' => null]);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertCreated();
        $this->assertSame(4, Order::count());
    }
}
