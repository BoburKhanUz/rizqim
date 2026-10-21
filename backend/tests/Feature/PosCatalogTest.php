<?php

namespace Tests\Feature;

use App\Enums\KitchenStationKind;
use App\Enums\UserRole;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P3-13: GET /catalog?channel=pos_hall|pos_takeaway is the store staff's POS menu with the channel's prices,
 * visibility, availability, modifiers and units; the customer catalog of telegram/mobile is unchanged and never shows
 * POS channels.
 */
class PosCatalogTest extends TestCase
{
    use RefreshDatabase;
    use PosSetupFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpManagement();
    }

    private function prices(array $prices, ?\App\Models\Product $product = null): void
    {
        $product ??= $this->product;
        $this->as($this->owner, 'PUT', "/api/v1/owner/products/{$product->id}/channel-prices", ['prices' => $prices])->assertOk();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function posProduct(array $data, \App\Models\Product $product): ?array
    {
        return collect($data['products'])->firstWhere('id', $product->id);
    }

    public function test_pos_prices_and_visibility_come_from_the_channel(): void
    {
        $second = $this->makeVariant($this->product, '60000', ['name' => 'Katta']);
        $this->prices([
            ['channel' => 'pos_hall', 'product_variant_id' => $this->variant->id, 'price' => 52000],
            ['channel' => 'pos_takeaway', 'product_variant_id' => $this->variant->id, 'price' => 48000],
            ['channel' => 'pos_takeaway', 'product_variant_id' => $second->id, 'is_hidden' => true],
            ['channel' => 'telegram', 'product_variant_id' => $this->variant->id, 'price' => 47000],
        ]);

        $hall = $this->as($this->manager, 'GET', '/api/v1/catalog?channel=pos_hall')->assertOk()->json('data');
        $variants = collect($this->posProduct($hall, $this->product)['variants'])->keyBy('name');
        $this->assertSame('pos_hall', $hall['channel']);
        $this->assertSame('52000.00', $variants['Regular']['price']);
        $this->assertSame('50000.00', $variants['Regular']['base_price']);
        $this->assertSame('60000.00', $variants['Katta']['price']); // no override: the variant's own price

        $takeaway = $this->as($this->manager, 'GET', '/api/v1/catalog?channel=pos_takeaway')->assertOk()->json('data');
        $variants = collect($this->posProduct($takeaway, $this->product)['variants'])->keyBy('name');
        $this->assertSame('48000.00', $variants['Regular']['price']);
        $this->assertArrayNotHasKey('Katta', $variants->all()); // hidden on this channel

        // A whole dish hidden on the hall channel is left out there only
        $this->prices([['channel' => 'pos_hall', 'is_hidden' => true]]);
        $this->assertNull($this->posProduct($this->as($this->manager, 'GET', '/api/v1/catalog?channel=pos_hall')->json('data'), $this->product));
        $this->assertNotNull($this->posProduct($this->as($this->manager, 'GET', '/api/v1/catalog?channel=pos_takeaway')->json('data'), $this->product));
    }

    public function test_units_modifiers_stations_and_categories_are_included(): void
    {
        $kg = $this->makeProduct('Osh kg');
        $weighed = $this->makeVariant($kg, '90000', ['name' => 'kg', 'unit' => 'kg', 'quantity_step' => '0.100', 'min_quantity' => '0.500']);
        $group = $this->makeGroup('Sous', ['Achchiq' => 0, 'Pishloq' => 3000], ['min_select' => 0, 'max_select' => 2], [], $this->product);
        $mangal = $this->makeStation($this->branch, 'Mangal', KitchenStationKind::MANGAL);
        $osh = $this->makeStation($this->branch, 'Osh', KitchenStationKind::OSH);
        $this->route($this->branch, $osh, categoryId: $this->product->category_id);
        $this->route($this->branch, $mangal, productId: $kg->id);

        $data = $this->as($this->manager, 'GET', '/api/v1/catalog?channel=pos_hall')->assertOk()->json('data');

        $burger = $this->posProduct($data, $this->product);
        $this->assertSame($osh->id, $burger['station_id']);
        $this->assertTrue($burger['is_available']);
        $this->assertNull($burger['unavailable_reason']);
        $this->assertSame('Sous', $burger['modifier_groups'][0]['name']);
        $this->assertSame(['Achchiq', 'Pishloq'], array_column($burger['modifier_groups'][0]['modifiers'], 'name'));
        $this->assertTrue($burger['modifier_groups'][0]['modifiers'][0]['is_available']);

        $osh = $this->posProduct($data, $kg);
        $this->assertSame($mangal->id, $osh['station_id']); // a product route beats the category's
        $this->assertSame('kg', $osh['variants'][0]['unit']);
        $this->assertEquals(0.1, $osh['variants'][0]['quantity_step']);
        $this->assertEquals(0.5, $osh['variants'][0]['min_quantity']);

        $this->assertContains($this->product->category_id, array_column($data['categories'], 'id'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $data['menu_version']);
        $this->assertSame($this->as($this->manager, 'GET', '/api/v1/pos/bootstrap')->json('data.menu_version'), $data['menu_version']);
        $this->assertSame($this->branch->id, $data['branch']['id']);
        $this->assertSame($this->branch->id, $data['availability']['branch_id']);
        $this->assertSame($group->id, $burger['modifier_groups'][0]['id']);
    }

    public function test_stopped_and_sold_out_dishes_are_marked_for_this_branch_only(): void
    {
        $osh = $this->makeProduct('Osh', ['batch_tracked' => true]);
        $portion = $this->makeVariant($osh, '55000', ['name' => '1 porsiya']);
        $plov = $this->makeProduct('Palov');
        $stopped = $this->makeVariant($plov, '40000', ['name' => 'Oddiy']);
        $group = $this->makeGroup('Sous', ['Achchiq' => 0, 'Pishloq' => 3000], [], [], $this->product);
        $this->as($this->manager, 'POST', '/api/v1/owner/stop-list', ['type' => 'variant', 'id' => $stopped->id])->assertCreated();
        $this->as($this->manager, 'POST', '/api/v1/owner/stop-list', ['type' => 'modifier', 'id' => $this->modifier($group, 'Pishloq')->id])->assertCreated();

        $data = $this->as($this->manager, 'GET', '/api/v1/catalog?channel=pos_hall')->assertOk()->json('data');

        // The kazan is empty (a tracked dish without an open batch), the plov variant is stopped
        $oshProduct = $this->posProduct($data, $osh);
        $this->assertFalse($oshProduct['is_available']);
        $this->assertSame('sold_out', $oshProduct['unavailable_reason']);
        $this->assertSame('sold_out', $oshProduct['variants'][0]['unavailable_reason']);
        $this->assertFalse($oshProduct['variants'][0]['is_available']);
        $this->assertSame('stopped', $this->posProduct($data, $plov)['variants'][0]['unavailable_reason']);
        $this->assertSame([true, false], array_column($this->posProduct($data, $this->product)['modifier_groups'][0]['modifiers'], 'is_available'));
        $this->assertContains($stopped->id, $data['availability']['stopped']['variants']);

        // Open a kazan: the dish is available again, with the batch in the availability block
        $this->as($this->owner, 'POST', '/api/v1/owner/batches', ['branch_id' => $this->branch->id, 'product_id' => $osh->id, 'quantity' => 30, 'label' => '1-qozon'])->assertCreated();
        $data = $this->as($this->manager, 'GET', '/api/v1/catalog?channel=pos_hall')->json('data');
        $this->assertTrue($this->posProduct($data, $osh)['is_available']);
        $this->assertSame([['label' => '1-qozon', 'quantity_left' => 30]], array_map(fn ($batch) => ['label' => $batch['label'], 'quantity_left' => $batch['quantity_left']], $data['availability']['batches']));

        // The other branch has its own stop-list and kazan
        $other = $this->as($this->owner, 'GET', "/api/v1/catalog?channel=pos_hall&branch_id={$this->otherBranch->id}")->assertOk()->json('data');
        $this->assertSame($this->otherBranch->id, $other['availability']['branch_id']);
        $this->assertSame([], $other['availability']['batches']);
        $this->assertTrue($this->posProduct($other, $plov)['variants'][0]['is_available']);
        $this->assertFalse($this->posProduct($other, $osh)['is_available']);
    }

    public function test_it_is_for_the_stores_own_staff_only(): void
    {
        $this->as(null, 'GET', '/api/v1/catalog?channel=pos_hall')->assertUnauthorized();
        $this->as($this->customer, 'GET', '/api/v1/catalog?channel=pos_hall')->assertForbidden();
        $this->as($this->customer, 'GET', "/api/v1/catalog?channel=pos_hall&store_id={$this->store->uuid}")->assertForbidden();
        $this->as($this->courier, 'GET', '/api/v1/catalog?channel=pos_takeaway')->assertForbidden();
        $this->as($this->foreignOwner, 'GET', "/api/v1/catalog?channel=pos_hall&store_id={$this->store->uuid}&branch_id={$this->branch->id}")->assertNotFound();
        $this->as($this->foreignOwner, 'GET', "/api/v1/catalog?channel=pos_hall&branch_id={$this->branch->id}")->assertNotFound();
        $this->as($this->owner, 'GET', "/api/v1/catalog?channel=pos_hall&store_id={$this->foreignStore->uuid}&branch_id={$this->branch->id}")->assertNotFound();
        $this->as($this->owner, 'GET', "/api/v1/catalog?channel=pos_hall&branch_id={$this->foreignBranch->id}")->assertNotFound();
        $this->as($this->owner, 'GET', '/api/v1/catalog?channel=pos_hall')->assertStatus(422); // the owner names the branch
        $this->as($this->manager, 'GET', "/api/v1/catalog?channel=pos_hall&branch_id={$this->otherBranch->id}")->assertForbidden();

        // The store given as uuid or id is fine when it is the caller's own
        $this->as($this->manager, 'GET', "/api/v1/catalog?channel=pos_hall&store_id={$this->store->uuid}")->assertOk();
        $this->as($this->manager, 'GET', "/api/v1/catalog?channel=pos_hall&store_id={$this->store->id}")->assertOk();

        // The menu is not another store's
        $foreignProduct = \App\Models\Product::create(['store_id' => $this->foreignStore->id, 'category_id' => $this->product->category_id, 'name' => 'Begona', 'slug' => 'begona-pos', 'status' => 'active']);
        $this->makeVariant($foreignProduct, '1000');
        $ids = array_column($this->as($this->manager, 'GET', '/api/v1/catalog?channel=pos_hall')->json('data.products'), 'id');
        $this->assertContains($this->product->id, $ids);
        $this->assertNotContains($foreignProduct->id, $ids);

        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $this->as($cook, 'GET', '/api/v1/catalog?channel=pos_hall')->assertOk();
    }

    public function test_a_terminal_staff_session_reads_its_branch_menu(): void
    {
        [$token] = $this->terminalSession($this->makeStaff(UserRole::CASHIER, $this->branch));
        $this->prices([['channel' => 'pos_hall', 'product_variant_id' => $this->variant->id, 'price' => 52000]]);

        $data = $this->bearerJson('GET', '/api/v1/catalog?channel=pos_hall', $token)->assertOk()->json('data');
        $this->assertSame('52000.00', $this->posProduct($data, $this->product)['variants'][0]['price']);
        $this->assertSame($this->branch->id, $data['branch']['id']);

        $this->bearerJson('GET', "/api/v1/catalog?channel=pos_hall&branch_id={$this->otherBranch->id}", $token)->assertForbidden();
    }

    public function test_the_customer_catalog_is_unchanged_and_never_shows_pos_prices(): void
    {
        $this->prices([
            ['channel' => 'pos_hall', 'product_variant_id' => $this->variant->id, 'price' => 52000],
            ['channel' => 'telegram', 'product_variant_id' => $this->variant->id, 'price' => 47000],
        ]);

        $storeCatalog = fn (string $query = '', $user = null) => $this->as($user ?? $this->customer, 'GET', "/api/v1/catalog?store_id={$this->store->uuid}{$query}");

        // Telegram (the default) and mobile keep their shapes and prices
        $telegram = $storeCatalog()->assertOk();
        $this->assertSame(['store', 'categories', 'products', 'availability'], array_keys($telegram->json('data')));
        $this->assertSame('47000.00', $this->posProduct($telegram->json('data'), $this->product)['variants'][0]['price']);
        $this->assertSame('47000.00', $this->posProduct($storeCatalog('&channel=telegram')->json('data'), $this->product)['variants'][0]['price']);
        $this->assertSame('50000.00', $this->posProduct($storeCatalog('&channel=mobile')->json('data'), $this->product)['variants'][0]['price']);

        // Staff reading the customer feed still get the customer shape (no channel, or telegram/mobile)
        $this->assertSame(['store', 'categories', 'products', 'availability'], array_keys($storeCatalog('', $this->manager)->json('data')));

        // The global feed is untouched too
        $this->as($this->customer, 'GET', '/api/v1/catalog')->assertOk()->assertJsonStructure(['data' => ['categories', 'products', 'promos', 'highlight_product']]);

        // POS channels never reach a customer, with or without a store
        $this->as($this->customer, 'GET', '/api/v1/catalog?channel=pos_hall')->assertForbidden();
        $this->as($this->customer, 'GET', '/api/v1/catalog?channel=pos_takeaway')->assertForbidden();
        $this->assertStringNotContainsString('52000', (string) $storeCatalog('&channel=pos_hall')->getContent());

        // Anything else keeps falling back to the Mini App channel as before
        $this->assertSame('47000.00', $this->posProduct($storeCatalog('&channel=aggregator')->json('data'), $this->product)['variants'][0]['price']);
    }
}
