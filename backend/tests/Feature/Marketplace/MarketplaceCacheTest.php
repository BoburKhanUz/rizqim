<?php

namespace Tests\Feature\Marketplace;

use App\Domain\Marketplace\ReadModelCache;
use App\Models\Branch;
use App\Models\StopListEntry;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        // Sequential stand-in for a burst: the first request builds, the next 20 read its copy (no products query)
        $uri = "/api/v1/catalog?store_id={$this->store->uuid}";
        $this->actingAs($this->customer, 'sanctum')->getJson($uri)->assertOk();
        $read = $this->tablesRead(function () use ($uri) {
            for ($i = 0; $i < 20; $i++) {
                $this->actingAs($this->customer, 'sanctum')->getJson($uri)->assertOk();
            }
        });
        $this->assertNotContains('products', $read);
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
}
