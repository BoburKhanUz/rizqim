<?php

namespace Tests\Feature\Marketplace;

use App\Models\ChannelPrice;
use App\Models\Store;
use App\Models\User;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Review M2 R1-1 and R1-4: what a customer sees on the home feed and the store list follows the store's menu and card
 * at once (the documents are checked against the store revisions they were built from), and an ETag never names
 * tokens newer than its body.
 */
class MarketplaceFreshnessTest extends TestCase
{
    use MarketplaceFixtures;
    use RefreshDatabase;

    private const FEED = '/api/v1/catalog?lat=41.3110&lng=69.2400&view=cards';

    private const STORES = '/api/v1/stores?lat=41.3110&lng=69.2400';

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::create(['name' => 'Mijoz', 'phone' => '+998901110019', 'password' => bcrypt('x')]);
    }

    private function read(string $uri, ?string $etag = null): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $headers = $etag === null ? [] : ['If-None-Match' => $etag];

        return $this->actingAs($this->customer, 'sanctum')->getJson($uri, $headers);
    }

    /**
     * @return array<string, mixed> the feed card of the product named $name, or null
     */
    private function card(TestResponse $response, string $name): ?array
    {
        foreach ((array) $response->json('data.products') as $card) {
            if (($card['name'] ?? null) === $name) {
                return $card;
            }
        }

        return null;
    }

    public function test_a_price_change_reaches_the_feed_card_at_once(): void
    {
        $store = $this->marketStore('Yaqin osh', 41.3110, 69.2400, 5);
        $variant = $this->marketProduct($store, 'Osh', '30000');

        $first = $this->read(self::FEED)->assertOk();
        $this->assertSame('30000.00', $this->card($first, 'Osh')['price']);
        $tag = (string) $first->headers->get('ETag');
        $this->read(self::FEED, $tag)->assertStatus(304);

        $variant->update(['price' => '35000']);
        $menu = $this->read("/api/v1/catalog?store_id={$store->uuid}")->assertOk()->json('data.products.0.variants.0.price');
        $this->assertSame('35000.00', $menu);

        // Inside the pool's fresh time: the new price, and the old tag no longer validates
        $after = $this->read(self::FEED, $tag)->assertOk();
        $this->assertSame('35000.00', $this->card($after, 'Osh')['price']);
        $this->assertSame('35000.00', $this->card($after, 'Osh')['variant']['price']);
    }

    public function test_a_channel_price_and_a_removed_dish_reach_the_feed_at_once(): void
    {
        $store = $this->marketStore('Yaqin osh', 41.3110, 69.2400, 5);
        $variant = $this->marketProduct($store, 'Osh', '30000');
        $this->marketProduct($store, 'Lag\'mon', '25000');
        $this->assertNotNull($this->card($this->read(self::FEED)->assertOk(), 'Osh'));

        ChannelPrice::create(['store_id' => $store->id, 'product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'channel' => 'telegram', 'price' => '31000']);
        $this->assertSame('31000.00', $this->card($this->read(self::FEED)->assertOk(), 'Osh')['price']);

        $variant->product->update(['status' => 'inactive']);
        $feed = $this->read(self::FEED)->assertOk();
        $this->assertNull($this->card($feed, 'Osh'), 'a deactivated dish leaves the feed at once');
        $this->assertNotNull($this->card($feed, 'Lag\'mon'));
    }

    public function test_the_debt_lock_reaches_the_store_list_and_the_feed_at_once(): void
    {
        $store = $this->marketStore('Qarzdor', 41.3110, 69.2400, 5, ['credit_limit' => 100000]);
        $this->marketProduct($store, 'Osh', '30000');
        $this->assertFalse($this->read(self::STORES)->assertOk()->json('data.0.is_order_locked'));
        $this->assertTrue($this->card($this->read(self::FEED)->assertOk(), 'Osh')['store']['accepting_orders']);

        // As the ledger writes it (LedgerService: forceFill + save on the locked row)
        Store::query()->whereKey($store->id)->first()->forceFill(['debt_balance' => '5000000'])->save();

        $card = $this->read(self::STORES)->assertOk()->json('data.0');
        $this->assertTrue($card['is_order_locked']);
        $this->assertFalse($card['accepting_orders']);
        $this->assertFalse($this->card($this->read(self::FEED)->assertOk(), 'Osh')['store']['accepting_orders']);
    }

    // --- R1-4: the ETag's tokens are never newer than its body ------------------------------------------------------

    /**
     * Runs $write once, right after the first cached document of $family is read (between the document and anything
     * the request does next).
     */
    private function interleave(string $family, callable $write): void
    {
        $done = false;
        Event::listen(CacheHit::class, function (CacheHit $event) use (&$done, $family, $write) {
            if (!$done && str_starts_with($event->key, $family)) {
                $done = true;
                $write();
            }
        });
    }

    public function test_a_write_between_the_store_list_document_and_its_etag_never_gives_a_stale_304(): void
    {
        $store = $this->marketStore('Eski nom', 41.3110, 69.2400, 5);
        $this->read(self::STORES)->assertOk(); // the area document is warm

        $this->interleave('mkt:area:', fn () => $store->update(['name' => 'Yangi nom']));
        $raced = $this->read(self::STORES)->assertOk();
        $next = $this->read(self::STORES, (string) $raced->headers->get('ETag'));

        if ($raced->json('data.0.name') !== 'Yangi nom') {
            $next->assertOk(); // an old body's tag must not validate after the write
        }
        $this->assertSame('Yangi nom', $next->getStatusCode() === 304 ? $raced->json('data.0.name') : $next->json('data.0.name'));
    }

    public function test_a_write_between_the_feed_pool_and_its_etag_never_gives_a_stale_304(): void
    {
        $store = $this->marketStore('Yaqin osh', 41.3110, 69.2400, 5);
        $variant = $this->marketProduct($store, 'Osh', '30000');
        $this->read(self::FEED)->assertOk(); // the pool is warm

        $this->interleave('mkt:feed:', fn () => $variant->update(['price' => '35000']));
        $raced = $this->read(self::FEED)->assertOk();
        $next = $this->read(self::FEED, (string) $raced->headers->get('ETag'));

        $shown = $next->getStatusCode() === 304 ? $this->card($raced, 'Osh')['price'] : $this->card($next, 'Osh')['price'];
        $this->assertSame('35000.00', $shown, 'after the write, the customer sees the new price');
    }
}
