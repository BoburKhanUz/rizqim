<?php

namespace Tests\Feature\Marketplace;

use App\Domain\Marketplace\GeoGrid;
use App\Domain\Marketplace\MarketplaceRevision;
use App\Domain\Marketplace\StoreDirectory;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Device;
use App\Models\ProductVariant;
use App\Models\StopListEntry;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Wave M2 (M2-3): the marketplace browse reads come from the read model with the old JSON, see every change after
 * commit, and do not read the menu/store tables on a warm cache.
 */
class MarketplaceReadModelTest extends TestCase
{
    use RefreshDatabase;
    use MarketplaceFixtures;

    private User $customer;

    private Store $near;

    private Store $north;

    private Store $far;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::create(['name' => 'Mijoz', 'phone' => '+998901110001', 'password' => bcrypt('x')]);
        $this->near = $this->marketStore('Yaqin osh', 41.3110, 69.2400, 5);
        $this->north = $this->marketStore('Shimol kabob', 41.3500, 69.3000, 8);
        $this->far = $this->marketStore('Uzoq somsa', 41.0000, 69.0000, 5);
        $this->marketProduct($this->near, 'Osh', '30000');
        $this->marketProduct($this->near, 'Manti', '25000');
        $this->marketProduct($this->north, 'Kabob', '40000');
        $this->marketProduct($this->far, 'Somsa', '8000');
    }

    /** @return array<string, mixed> */
    private function getData(string $uri): array
    {
        return $this->actingAs($this->customer, 'sanctum')->getJson($uri)->assertOk()->json();
    }

    public function test_store_list_is_the_old_answer_for_points_regions_and_no_location(): void
    {
        $branch = Branch::where('store_id', $this->north->id)->firstOrFail();
        $branch->forceFill(['region_id' => null, 'district_id' => null])->save();
        // Review M2 R1-3: an updated row moves to the end of the heap (as earlier tests' writes do in a full run); the
        // old list without location must still come in id order, the read model's order
        Store::query()->whereKey($this->near->id)->update(['description' => 'yangilangan']);

        foreach (['/api/v1/stores?lat=41.3120&lng=69.2450', '/api/v1/stores?lat=41.3490&lng=69.2990', '/api/v1/stores?lat=40.0&lng=60.0', '/api/v1/stores'] as $uri) {
            $this->withReadModel(false);
            $old = $this->getData($uri)['data'];
            $this->withReadModel(true);
            $new = $this->getData($uri)['data'];

            $this->assertSame(array_column($old, 'id'), array_column($new, 'id'), $uri);
            foreach ($new as $i => $card) {
                $this->assertArrayHasKey('accepting_orders', $card);
                unset($card['accepting_orders']);
                if (isset($old[$i]['min_distance'])) {
                    $this->assertEqualsWithDelta((float) $old[$i]['min_distance'], $card['min_distance'], 0.0001, $uri);
                    unset($old[$i]['min_distance'], $card['min_distance']);
                }
                ksort($card);
                $expected = $old[$i];
                ksort($expected);
                $this->assertSame($expected, $card, $uri);
            }
        }
    }

    public function test_the_cell_candidates_give_the_exact_radius_answer_near_cell_edges(): void
    {
        // Branches just inside and just outside 5 km of points on both sides of cell edges
        $grid = GeoGrid::make();
        $edgeLat = 41.30; // a cell edge with the default 0.05 degree cells
        $points = [[$edgeLat - 0.0001, 69.2501], [$edgeLat + 0.0001, 69.2499], [41.3249, 69.2999], [41.3251, 69.3001]];
        foreach ([[41.2600, 69.2500, 4.6], [41.3400, 69.2500, 4.5], [41.3000, 69.3100, 5.0], [41.3700, 69.3500, 6.5]] as $i => [$lat, $lng, $radius]) {
            $this->marketStore("Chet {$i}", $lat, $lng, $radius);
        }

        foreach ($points as [$lat, $lng]) {
            $uri = "/api/v1/stores?lat={$lat}&lng={$lng}";
            $this->withReadModel(false);
            $old = array_column($this->getData($uri)['data'], 'id');
            $this->withReadModel(true);
            $new = array_column($this->getData($uri)['data'], 'id');
            sort($old);
            sort($new);
            $this->assertSame($old, $new, "{$uri} ({$grid->cellOf($lat, $lng)})");
        }
    }

    public function test_store_menu_and_availability_are_the_old_answer(): void
    {
        StopListEntry::create(['store_id' => $this->near->id, 'branch_id' => Branch::where('store_id', $this->near->id)->value('id'), 'subject_type' => 'product', 'subject_id' => \App\Models\Product::where('name', 'Manti')->value('id')]);

        foreach (["/api/v1/catalog?store_id={$this->near->uuid}", "/api/v1/catalog?store_id={$this->near->id}"] as $uri) {
            $this->withReadModel(false);
            $old = $this->getData($uri);
            $this->withReadModel(true);
            $new = $this->getData($uri);

            $this->assertArrayHasKey('accepting_orders', $new['data']['store']);
            unset($new['data']['store']['accepting_orders']);
            $this->assertEquals($old, $new, $uri);
        }

        $uri = "/api/v1/catalog/availability?store_id={$this->near->uuid}";
        $this->withReadModel(false);
        $old = $this->getData($uri);
        $this->withReadModel(true);
        $new = $this->getData($uri);
        unset($old['data']['generated_at'], $new['data']['generated_at']);
        $this->assertSame($old, $new);

        // Unknown and unavailable stores: the old statuses
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/catalog?store_id=00000000-0000-4000-8000-000000000000')->assertForbidden();
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/catalog?store_id=not-a-uuid')->assertNotFound();
        $this->far->forceFill(['status' => 'payment_required'])->save();
        $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$this->far->uuid}")->assertForbidden();
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/catalog/availability?store_id=00000000-0000-4000-8000-000000000000')->assertNotFound();
    }

    public function test_the_feed_shows_open_stores_serving_the_point_with_the_old_product_shape(): void
    {
        $uri = '/api/v1/catalog?lat=41.3120&lng=69.2450';
        $this->withReadModel(false);
        $old = $this->getData($uri)['data'];
        $this->withReadModel(true);
        $new = $this->getData($uri)['data'];

        $byId = fn (array $products) => collect($products)->keyBy('id')->sortKeys()->all();
        $this->assertSame(array_keys($byId($old['products'])), array_keys($byId($new['products'])));
        $this->assertEquals($byId($old['products']), $byId($new['products']));
        $this->assertSame($old['categories'], $new['categories']);
        $this->assertSame($old['promos'], $new['promos']);
        $this->assertContains($new['highlight_product']['id'], array_column($new['products'], 'id'));

        // A store switched off shows no dishes; a store outside its hours neither (checked per request)
        $this->near->forceFill(['is_open' => false])->save();
        $this->assertSame(['Kabob'], array_column($this->getData($uri)['data']['products'], 'name'));
        DB::table('stores')->where('id', $this->north->id)->update(['open_time' => '03:00:00', 'close_time' => '03:01:00']);
        $this->travelTo(now()->setTimezone('Asia/Tashkent')->setTime(12, 0));
        $this->assertSame([], $this->getData($uri)['data']['products']);
    }

    public function test_cards_view_and_a_paged_store_list(): void
    {
        $cards = $this->getData('/api/v1/catalog?lat=41.3120&lng=69.2450&view=cards&limit=2')['data'];
        $this->assertCount(2, $cards['products']);
        $card = collect($cards['products'])->firstWhere('name', 'Osh') ?? $cards['products'][0];
        $this->assertSame(['id', 'uuid', 'store_id', 'category_id', 'name', 'description', 'image_url', 'image_thumb_urls', 'batch_tracked', 'price', 'unit', 'needs_options', 'variant', 'store'], array_keys($card));
        $this->assertFalse($card['needs_options']);
        $this->assertSame(['id', 'uuid', 'name', 'price', 'unit', 'stock'], array_keys($card['variant']));
        $this->assertSame('piece', $card['unit']);
        $this->assertSame(['id', 'uuid', 'name', 'address', 'logo_url', 'accepting_orders'], array_keys($card['store']));
        $this->assertTrue($card['store']['accepting_orders']);

        $page = $this->getData('/api/v1/stores?lat=41.3120&lng=69.2450&per_page=1');
        $this->assertCount(1, $page['data']);
        $this->assertSame(['page' => 1, 'per_page' => 1, 'total' => 2, 'last_page' => 2], $page['meta']);
        $this->assertSame($this->near->id, $page['data'][0]['id']);
        $second = $this->getData('/api/v1/stores?lat=41.3120&lng=69.2450&per_page=1&page=2');
        $this->assertSame($this->north->id, $second['data'][0]['id']);
        $this->assertArrayNotHasKey('meta', $this->getData('/api/v1/stores?lat=41.3120&lng=69.2450'));
    }

    public function test_a_card_store_accepts_orders_exactly_when_the_store_list_says_so(): void
    {
        // North is over its credit limit (order-locked) but open: it is in the feed, not accepting orders
        DB::table('stores')->where('id', $this->north->id)->update(['debt_balance' => 500000, 'credit_limit' => 0]);
        $point = 'lat=41.3300&lng=69.2700';
        $listed = collect($this->getData("/api/v1/stores?{$point}")['data'])->pluck('accepting_orders', 'id')->all();
        $this->assertSame([$this->near->id => true, $this->north->id => false], $listed);

        $cards = $this->getData("/api/v1/catalog?{$point}&view=cards&limit=10")['data']['products'];
        $this->assertNotEmpty($cards);
        $this->assertEqualsCanonicalizing([$this->near->id, $this->north->id], array_values(array_unique(array_column($cards, 'store_id'))));
        foreach ($cards as $card) {
            $this->assertSame($listed[$card['store_id']], $card['store']['accepting_orders'], $card['name']);
        }

        // Per request, not baked into the cached feed: the terminal rule turns it off without a feed rebuild
        config(['marketplace.reachability.enabled' => true]);
        DB::table('stores')->where('id', $this->near->id)->update(['marketplace_offline_minutes' => 5]);
        Device::create([
            'store_id' => $this->near->id, 'branch_id' => Branch::where('store_id', $this->near->id)->value('id'),
            'type' => 'pos', 'name' => 'Kassa', 'last_seen_at' => now()->subHour(),
        ]);
        $listed = collect($this->getData("/api/v1/stores?{$point}")['data'])->pluck('accepting_orders', 'id')->all();
        $this->assertFalse($listed[$this->near->id]);
        foreach ($this->getData("/api/v1/catalog?{$point}&view=cards&limit=10")['data']['products'] as $card) {
            $this->assertSame($listed[$card['store_id']], $card['store']['accepting_orders'], $card['name']);
        }
    }

    public function test_a_card_that_needs_options_still_names_the_unit_of_its_price(): void
    {
        $kg = $this->marketProduct($this->near, 'Qazi', '120000');
        $kg->update(['unit' => 'kg']);
        $two = $this->marketProduct($this->near, 'Lag\'mon', '28000');
        ProductVariant::create(['product_id' => $two->product_id, 'name' => 'Yarim', 'price' => '21000', 'stock' => 100, 'is_active' => true]);

        $cards = collect($this->getData('/api/v1/catalog?lat=41.3120&lng=69.2450&view=cards&limit=10')['data']['products'])->keyBy('name');
        $this->assertTrue($cards['Qazi']['needs_options']);
        $this->assertNull($cards['Qazi']['variant']);
        $this->assertSame('120000.00', $cards['Qazi']['price']);
        $this->assertSame('kg', $cards['Qazi']['unit']);
        $this->assertTrue($cards["Lag'mon"]['needs_options']);
        $this->assertSame('21000.00', $cards["Lag'mon"]['price']);
        $this->assertSame('piece', $cards["Lag'mon"]['unit']);
        $this->assertSame($cards['Osh']['variant']['unit'], $cards['Osh']['unit']);
    }

    public function test_a_warm_browse_reads_no_store_or_menu_table(): void
    {
        // A stopped clock: the availability overlay is keyed by the 15 s bucket of the clock and refreshed after 5 s, so
        // on the real clock a run that crossed a bucket edge legitimately read the stop-list and batch tables again
        $this->freezeTime();
        $uris = [
            '/api/v1/stores?lat=41.3120&lng=69.2450',
            '/api/v1/stores?lat=41.3120&lng=69.2450&page=1',
            '/api/v1/catalog?lat=41.3120&lng=69.2450',
            '/api/v1/catalog?lat=41.3120&lng=69.2450&view=cards',
            "/api/v1/catalog?store_id={$this->near->uuid}",
            "/api/v1/catalog?store_id={$this->near->id}",
            "/api/v1/catalog/availability?store_id={$this->near->uuid}",
        ];
        foreach ($uris as $uri) {
            $this->getData($uri); // cold: builds the documents
        }

        $forbidden = ['products', 'product_variants', 'stores', 'branches', 'categories', 'channel_prices', 'modifier_groups', 'modifiers', 'stop_list_entries', 'menu_batches'];
        foreach ($uris as $uri) {
            $read = $this->tablesRead(fn () => $this->getData($uri));
            $this->assertSame([], array_values(array_intersect($read, $forbidden)), "{$uri} read: ".implode(', ', $read));
        }
    }

    public function test_every_change_is_seen_by_the_next_request(): void
    {
        $menu = "/api/v1/catalog?store_id={$this->near->uuid}";
        $list = '/api/v1/stores?lat=41.3120&lng=69.2450';
        $feed = '/api/v1/catalog?lat=41.3120&lng=69.2450';
        $osh = \App\Models\Product::where('name', 'Osh')->firstOrFail();
        foreach ([$menu, $list, $feed] as $uri) {
            $this->getData($uri);
        }

        // A price (Eloquent) and a price written by a mass update (no model events)
        $osh->variants()->firstOrFail()->update(['price' => '31000']);
        $this->assertSame('31000.00', collect($this->getData($menu)['data']['products'])->firstWhere('name', 'Osh')['variants'][0]['price']);
        DB::table('product_variants')->where('product_id', $osh->id)->update(['price' => '32000']);
        $this->assertSame('32000.00', collect($this->getData($menu)['data']['products'])->firstWhere('name', 'Osh')['variants'][0]['price']);

        // A stop-list entry shows in the availability at once
        $branchId = (int) Branch::where('store_id', $this->near->id)->value('id');
        StopListEntry::create(['store_id' => $this->near->id, 'branch_id' => $branchId, 'subject_type' => 'product', 'subject_id' => $osh->id]);
        $this->assertContains($osh->id, $this->getData($menu)['data']['availability'][0]['stopped']['products']);

        // A product switched off leaves the menu
        DB::table('products')->where('id', $osh->id)->update(['status' => 'inactive']);
        $this->assertNotContains('Osh', array_column($this->getData($menu)['data']['products'], 'name'));

        // The store card: a name, the open switch, a radius that no longer reaches the point
        DB::table('stores')->where('id', $this->near->id)->update(['name' => 'Yangi nom']);
        $this->assertSame('Yangi nom', collect($this->getData($list)['data'])->firstWhere('id', $this->near->id)['name']);
        $this->near->forceFill(['is_open' => false])->save();
        $this->assertFalse(collect($this->getData($list)['data'])->firstWhere('id', $this->near->id)['is_open']);
        $this->assertFalse($this->getData($menu)['data']['store']['is_open']);
        DB::table('branches')->where('store_id', $this->near->id)->update(['radius_km' => 0.1]);
        $this->assertNotContains($this->near->id, array_column($this->getData($list)['data'], 'id'));

        // A category renamed by the RIZQIM team
        Category::query()->update(['name' => 'Milliy taomlar']);
        $this->assertSame(['Milliy taomlar'], array_values(array_unique(array_column($this->getData($feed)['data']['categories'], 'name'))));
    }

    public function test_a_debt_only_write_does_not_rebuild_the_areas_but_a_card_write_does(): void
    {
        $revisions = app(MarketplaceRevision::class);
        $before = $revisions->directory();
        DB::table('stores')->where('id', $this->near->id)->update(['debt_balance' => DB::raw('debt_balance + 1'), 'updated_at' => now()]);
        $this->assertSame($before, $revisions->directory());

        DB::table('stores')->where('id', $this->near->id)->update(['is_open' => false]);
        $this->assertNotSame($before, $after = $revisions->directory());
        Branch::where('store_id', $this->north->id)->firstOrFail()->update(['radius_km' => 7]);
        $this->assertNotSame($after, $revisions->directory());

        // A write inside a rolled-back transaction touches nothing
        $after = $revisions->directory();
        DB::beginTransaction();
        DB::table('branches')->where('store_id', $this->north->id)->update(['radius_km' => 9]);
        DB::rollBack();
        $this->assertSame($after, $revisions->directory());
    }

    public function test_the_read_model_switch_restores_the_database_reads(): void
    {
        $this->withReadModel(false);
        $this->assertFalse(StoreDirectory::enabled());
        $read = $this->tablesRead(fn () => $this->getData('/api/v1/stores?lat=41.3120&lng=69.2450'));
        $this->assertContains('stores', $read);
    }
}
