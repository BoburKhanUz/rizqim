<?php

namespace Tests\Feature\Marketplace;

use App\Domain\Marketplace\ReadModelCache;
use App\Domain\Marketplace\StoreMenu;
use App\Models\Branch;
use App\Models\StopListEntry;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The read model's cache: one builder per cold document, stale-while-revalidate, a dead cache store served from the
 * database, and the conditional GETs (304) of the customer browse reads.
 */
class MarketplaceCacheTest extends TestCase
{
    use RefreshDatabase;
    use MarketplaceFixtures;

    private User $customer;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::create(['name' => 'Mijoz', 'phone' => '+998901110002', 'password' => bcrypt('x')]);
        $this->store = $this->marketStore('Osh markazi', 41.3110, 69.2400, 5);
        $this->marketProduct($this->store, 'Osh', '30000');
    }

    public function test_a_warm_document_is_not_rebuilt_and_a_stale_one_is_served_once_then_refreshed(): void
    {
        $cache = app(ReadModelCache::class);
        $builds = 0;
        $build = function () use (&$builds) {
            return ['n' => ++$builds];
        };

        $cache->remember('mkt:test', 'a', 'menu', $build);
        $this->assertSame(['n' => 1], $cache->remember('mkt:test', 'a', 'menu', $build));
        $this->assertSame(1, $builds);

        // Past "fresh" (300 s): the old copy once more, the rebuild deferred to after the response
        $this->travel(301)->seconds();
        $this->assertSame(['n' => 1], $cache->remember('mkt:test', 'a', 'menu', $build));
        app(\Illuminate\Support\Defer\DeferredCallbackCollection::class)->invoke();
        $this->assertSame(2, $builds);
        $this->assertSame(['n' => 2], $cache->remember('mkt:test', 'a', 'menu', $build));

        // Past "stale" (3600 s): gone, built at once
        $this->travel(3601)->seconds();
        $this->assertSame(['n' => 3], $cache->remember('mkt:test', 'a', 'menu', $build));
    }

    public function test_a_cold_document_waits_for_the_builder_that_holds_the_lock_and_builds_itself_if_it_never_frees(): void
    {
        config(['marketplace.lock_wait_seconds' => 1]);
        $cache = app(ReadModelCache::class);
        $store = $cache->store();
        $this->assertNotNull($store);

        // The builder that held the lock stored the document while this request waited: the waiter takes its copy
        $store->put('mkt:test:b', 'other', 60);
        $this->assertSame('other', (fn () => $this->buildLocked($store, 'mkt:test:b', fn () => 'me'))->call($cache));

        // The lock is never freed and nothing is stored: after lock_wait_seconds it builds the document itself
        $lock = \Illuminate\Support\Facades\Cache::lock('mkt:lock:mkt:test:'.$cache->deploy().':c', 10);
        $this->assertTrue($lock->get());
        $started = microtime(true);
        $this->assertSame(['by' => 'me'], $cache->remember('mkt:test', 'c', 'menu', fn () => ['by' => 'me']));
        $this->assertGreaterThanOrEqual(0.5, microtime(true) - $started);
        $lock->release();
    }

    public function test_a_burst_on_a_cold_store_menu_builds_it_once(): void
    {
        // The availability overlay (it reads `products` too) is keyed by the store's own 15 s bucket of the clock
        // (shifted by StoreMenu::availabilityOffset) and refreshed after its 5 s fresh time: on the real clock a burst
        // that crossed the store's bucket edge rebuilt it, legitimately. The clock is pinned to the start of this
        // store's bucket, so only the cache decides what is read.
        $stale = (int) config('marketplace.ttl.availability')[1];
        $this->travelTo(\Illuminate\Support\Carbon::createFromTimestamp($this->nextAvailabilityEdge((int) $this->store->id, now()->getTimestamp())));

        // Sequential stand-in for a burst: the first request builds, the next 20 read its copy (no products query)
        $uri = "/api/v1/catalog?store_id={$this->store->uuid}";
        $this->actingAs($this->customer, 'sanctum')->getJson($uri)->assertOk();
        $burst = function () use ($uri) {
            for ($i = 0; $i < 20; $i++) {
                $this->actingAs($this->customer, 'sanctum')->getJson($uri)->assertOk();
            }
        };
        $this->assertNotContains('products', $this->tablesRead($burst));

        // At the store's next bucket edge the overlay is rebuilt once, by the first request after it (the menu is not: its
        // own query never runs); the rest of the burst reads that copy again
        $this->travel($stale)->seconds();
        $first = $this->tablesRead(fn () => $this->actingAs($this->customer, 'sanctum')->getJson($uri)->assertOk());
        $this->assertContains('stop_list_entries', $first, 'the availability overlay is rebuilt at its bucket edge');
        $this->assertNotContains('categories', $first, 'the menu document is not rebuilt with it');
        $this->assertNotContains('products', $this->tablesRead($burst));
    }

    public function test_stores_do_not_share_an_availability_bucket_edge_and_each_rebuilds_once_per_bucket(): void
    {
        $menus = app(StoreMenu::class);
        $stale = (int) config('marketplace.ttl.availability')[1];
        $a = $this->store;
        // A second store whose offset differs from the first one's: ids that differ by a multiple of `stale` share an
        // offset by design, and the sequence is not reset between tests, so create stores until the offsets differ
        do {
            $b = $this->marketStore('Kabob uyi', 41.3115, 69.2405, 5);
        } while ($menus->availabilityOffset((int) $b->id) === $menus->availabilityOffset((int) $a->id));
        $this->marketProduct($b, 'Kabob', '25000');

        // (a) Key level: a frozen clock walked second by second over four buckets, from A's bucket edge
        $start = $this->nextAvailabilityEdge((int) $a->id, now()->getTimestamp());
        $runs = [];   // store => list of [version, seconds it was the key]
        $changes = []; // store => seconds at which its version changed
        for ($t = $start; $t < $start + 4 * $stale; $t++) {
            $this->travelTo(Carbon::createFromTimestamp($t));
            foreach (['a' => $a, 'b' => $b] as $name => $store) {
                $version = $menus->availabilityKey((int) $store->id)[1];
                $last = count($runs[$name] ?? []) - 1;
                if ($last >= 0 && $runs[$name][$last][0] === $version) {
                    $runs[$name][$last][1]++;
                } else {
                    $runs[$name][] = [$version, 1];
                    if ($t > $start) {
                        $changes[$name][] = $t;
                    }
                }
            }
        }
        foreach (['a', 'b'] as $name) {
            // The first and last runs are cut by the walk's ends; every whole one spans exactly `stale` seconds (one
            // cold build per bucket, no copy outlives it)
            $whole = array_slice($runs[$name], 1, -1);
            $this->assertGreaterThanOrEqual(2, count($whole), $name);
            $this->assertSame(array_fill(0, count($whole), $stale), array_column($whole, 1), $name);
            $this->assertCount(count(array_unique(array_column($runs[$name], 0))), $runs[$name], $name);
        }
        $this->assertSame(0, $runs['a'][0][1] % $stale, 'the walk starts at A\'s edge');
        $this->assertSame([], array_values(array_intersect($changes['a'], $changes['b'])), 'the two stores never change version in the same second');

        // (b) HTTP level: both overlays warm one second before A's edge (inside both fresh times, inside B's bucket)
        $edge = $this->nextAvailabilityEdge((int) $a->id, $start + 4 * $stale);
        $this->travelTo(Carbon::createFromTimestamp($edge - 1));
        $uri = fn (Store $store) => "/api/v1/catalog/availability?store_id={$store->uuid}";
        foreach ([$a, $b] as $store) {
            $this->actingAs($this->customer, 'sanctum')->getJson($uri($store))->assertOk();
            $this->assertNotContains('stop_list_entries', $this->tablesRead(fn () => $this->actingAs($this->customer, 'sanctum')->getJson($uri($store))->assertOk()));
        }

        // At A's edge: A's overlay is rebuilt, B's is served from its copy (B's own edge is elsewhere in the bucket)
        $this->travelTo(Carbon::createFromTimestamp($edge));
        $this->assertNotSame($edge, $this->nextAvailabilityEdge((int) $b->id, $edge - 1));
        $this->assertContains('stop_list_entries', $this->tablesRead(fn () => $this->actingAs($this->customer, 'sanctum')->getJson($uri($a))->assertOk()), 'A is rebuilt at its edge');
        $this->assertNotContains('stop_list_entries', $this->tablesRead(fn () => $this->actingAs($this->customer, 'sanctum')->getJson($uri($b))->assertOk()), 'B is not rebuilt at A\'s edge');
    }

    public function test_a_broken_cache_store_serves_from_the_database(): void
    {
        config(['cache.stores.broken' => ['driver' => 'redis', 'connection' => 'missing-connection'], 'marketplace.cache_store' => 'broken']);

        $response = $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$this->store->uuid}")->assertOk();
        $this->assertSame('Osh', $response->json('data.products.0.name'));
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/stores?lat=41.3120&lng=69.2450')->assertOk()->assertJsonPath('data.0.id', $this->store->id);
    }

    public function test_repeat_visits_get_a_304_until_something_they_show_changes(): void
    {
        $uris = [
            "/api/v1/catalog?store_id={$this->store->uuid}",
            '/api/v1/stores?lat=41.3120&lng=69.2450&page=1',
            '/api/v1/catalog?lat=41.3120&lng=69.2450&view=cards',
            "/api/v1/catalog/availability?store_id={$this->store->uuid}",
        ];

        foreach ($uris as $uri) {
            $first = $this->actingAs($this->customer, 'sanctum')->getJson($uri)->assertOk();
            $etag = $first->headers->get('ETag');
            $this->assertNotNull($etag, $uri);
            $this->assertSame('no-cache, private', $first->headers->get('Cache-Control'));

            // Unchanged: 304 without reading the store or menu tables
            $read = $this->tablesRead(fn () => $this->actingAs($this->customer, 'sanctum')->getJson($uri, ['If-None-Match' => $etag])->assertStatus(304));
            $this->assertSame([], array_values(array_intersect($read, ['products', 'product_variants', 'stores', 'branches'])), $uri);
        }

        $tags = [];
        foreach ($uris as $uri) {
            $tags[$uri] = $this->actingAs($this->customer, 'sanctum')->getJson($uri)->headers->get('ETag');
        }

        // A stop-list entry (the store revision): every answer about the store is read again
        StopListEntry::create(['store_id' => $this->store->id, 'branch_id' => Branch::where('store_id', $this->store->id)->value('id'), 'subject_type' => 'product', 'subject_id' => \App\Models\Product::value('id')]);
        foreach ($uris as $uri) {
            $this->actingAs($this->customer, 'sanctum')->getJson($uri, ['If-None-Match' => $tags[$uri]])->assertOk();
        }

        // And the clock: a store-menu ETag stops validating after 30 s
        $etag = $this->actingAs($this->customer, 'sanctum')->getJson($uris[0])->headers->get('ETag');
        $this->travel(31)->seconds();
        $this->actingAs($this->customer, 'sanctum')->getJson($uris[0], ['If-None-Match' => $etag])->assertOk();
    }

    public function test_the_cart_store_list_stays_live(): void
    {
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/stores?for=checkout')->assertOk();
        $read = $this->tablesRead(fn () => $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/stores?for=checkout')->assertOk());
        $this->assertContains('stores', $read);
    }

    /**
     * The first second after $t at which the store's availability key changes.
     */
    private function nextAvailabilityEdge(int $storeId, int $t): int
    {
        $stale = (int) config('marketplace.ttl.availability')[1];
        $offset = app(StoreMenu::class)->availabilityOffset($storeId);

        return (intdiv($t + $offset, $stale) + 1) * $stale - $offset;
    }
}
