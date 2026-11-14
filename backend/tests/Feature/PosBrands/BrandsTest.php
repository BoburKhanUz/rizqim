<?php

namespace Tests\Feature\PosBrands;

use App\Domain\Pos\Setup\FormatProfile;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\BrandChannel;
use App\Models\ChannelPrice;
use App\Models\MenuPriceHistory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Catalog\ChannelMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-55 (module multi_brand): brands in one kitchen — the owner's CRUD (audited), brand × channel visibility and
 * price through ChannelMenu (a variant's own channel price wins), a brand switched off on a branch (stopped there),
 * the order lines' brand snapshot on the kitchen ticket and the till's order, the catalogue's brand filter, the module
 * gate and tenant isolation. A store without brands keeps every shape.
 */
class BrandsTest extends TestCase
{
    use RefreshDatabase;
    use DarkKitchenFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDarkKitchen();
    }

    public function test_the_owner_manages_brands_and_every_change_is_audited(): void
    {
        $created = $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/brands', ['name' => 'Burger Bro', 'short_code' => 'bb', 'color' => '#e11d48'])
            ->assertCreated()
            ->assertJsonPath('data.short_code', 'BB')
            ->assertJsonPath('data.color', '#E11D48')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.channels', [])
            ->assertJsonPath('data.off_branch_ids', []);
        $id = $created->json('data.id');

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/brands', ['name' => 'Boshqa', 'short_code' => 'BB'])->assertStatus(422)->assertJsonValidationErrors('short_code');
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/brands', ['name' => 'Rang', 'short_code' => 'R1', 'color' => 'red'])->assertStatus(422);
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/owner/brands', ['name' => 'X', 'short_code' => 'X'])->assertForbidden();

        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/brands/{$id}", ['name' => 'Burger Brothers', 'sort_order' => 2])->assertOk()->assertJsonPath('data.name', 'Burger Brothers');
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/brands/{$id}/channels", ['channels' => [
            ['channel' => 'telegram', 'is_hidden' => true],
            ['channel' => 'mobile', 'price_percent' => '10'],
            ['channel' => 'pos_hall', 'price_percent' => 0], // the default: nothing stored
        ]])->assertOk()->assertJsonPath('data.channels', [
            ['channel' => 'mobile', 'is_hidden' => false, 'price_percent' => '10.00'],
            ['channel' => 'telegram', 'is_hidden' => true, 'price_percent' => null],
        ]);
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/brands/{$id}/channels", ['channels' => [['channel' => 'nope']]])->assertStatus(422);
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/brands/{$id}/branches", ['branches' => [['branch_id' => $this->otherBranch->id, 'is_active' => false]]])
            ->assertOk()->assertJsonPath('data.off_branch_ids', [$this->otherBranch->id]);

        [$dish] = $this->brandDish(null, 'Klassik burger');
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/product-brands', ['brand_id' => $id, 'product_ids' => [$dish->id, $this->product->id]])
            ->assertOk()->assertJsonPath('data.changed', 2);
        $this->assertSame($id, $dish->fresh()->brand_id);
        $listed = $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/brands')->assertOk();
        $this->assertSame(2, $listed->json('data.brands.0.products_count'));
        $this->assertContains('aggregator', $listed->json('data.channels_available'));

        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/v1/owner/brands/{$id}")->assertOk();
        $this->assertNull($dish->fresh()->brand_id); // back to the house brand
        $this->assertSame(0, BrandChannel::count());

        $this->assertSame(
            ['brand.created', 'brand.updated', 'brand.channels_changed', 'brand.branches_changed', 'brand.products_assigned', 'brand.deleted'],
            AuditLog::where('store_id', $this->store->id)->where('action', 'like', 'brand.%')->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_brand_by_channel_visibility_and_price(): void
    {
        $brand = $this->makeBrand('Lavash Lab', 'LL');
        [$lavash, $lavashVariant] = $this->brandDish($brand, 'Lavash', '40000');
        [$wrap, $wrapVariant] = $this->brandDish($brand, 'Wrap', '30000');
        [$soup, $soupVariant] = $this->brandDish(null, "Sho'rva", '25000');
        // The wrap's own mobile price wins over the brand's percent
        ChannelPrice::create(['store_id' => $this->store->id, 'product_id' => $wrap->id, 'product_variant_id' => $wrapVariant->id, 'channel' => 'mobile', 'price' => '31000']);

        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/brands/{$brand->id}/channels", ['channels' => [
            ['channel' => 'telegram', 'is_hidden' => true],
            ['channel' => 'mobile', 'price_percent' => '12.5'],
            ['channel' => 'pos_takeaway', 'price_percent' => '-10'],
        ]])->assertOk();

        // Telegram: the brand is not sold there
        $telegram = collect($this->getJson("/api/v1/catalog?store_id={$this->store->uuid}")->assertOk()->json('data.products'))->pluck('name')->all();
        $this->assertNotContains('Lavash', $telegram);
        $this->assertContains("Sho'rva", $telegram);
        $this->order([['variant_id' => $lavashVariant->uuid, 'quantity' => 1]])->assertStatus(422);

        // Mobile: 40 000 × 1.125 = 45 000; the wrap keeps its own 31 000; the house dish its 25 000
        $mobile = collect($this->getJson("/api/v1/catalog?store_id={$this->store->uuid}&channel=mobile")->assertOk()->json('data.products'))->keyBy('name');
        $this->assertSame('45000.00', $mobile['Lavash']['variants'][0]['price']);
        $this->assertSame('40000.00', $mobile['Lavash']['variants'][0]['base_price']);
        $this->assertSame('31000.00', $mobile['Wrap']['variants'][0]['price']);
        $this->assertSame('25000.00', (string) $mobile["Sho'rva"]['variants'][0]['price']);
        $uuid = $this->mobileOrder([['variant_id' => $lavashVariant->uuid, 'quantity' => 1]])->assertCreated()->json('data.order_uuid');
        $this->assertSame('45000.00', (string) Order::where('uuid', $uuid)->value('total_price'));

        // The till's channel (half up to whole so'm), and its price history for an offline till
        $this->assertSame('36000.00', ChannelMenu::for($this->store->id, OrderChannel::POS_TAKEAWAY)->priceOf($lavashVariant)->toString());
        $this->assertSame('25000.00', ChannelMenu::for($this->store->id, OrderChannel::POS_TAKEAWAY)->priceOf($soupVariant)->toString());
        $this->assertSame('36000.00', MenuPriceHistory::where('product_variant_id', $lavashVariant->id)->where('channel', 'pos_takeaway')->orderByDesc('id')->value('price'));

        // An inactive brand is hidden everywhere
        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/brands/{$brand->id}", ['is_active' => false])->assertOk();
        $this->assertNotContains('Lavash', collect($this->getJson("/api/v1/catalog?store_id={$this->store->uuid}&channel=mobile")->json('data.products'))->pluck('name')->all());
        $this->assertTrue(ChannelMenu::for($this->store->id, OrderChannel::POS_HALL)->isProductHidden($lavash));
    }

    public function test_a_brand_switched_off_on_a_branch_is_stopped_there_only(): void
    {
        $brand = $this->makeBrand('Burger Bro', 'BB');
        [$burger, $variant] = $this->brandDish($brand, 'Burger');

        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/brands/{$brand->id}/branches", ['branches' => [['branch_id' => $this->branch->id, 'is_active' => false]]])->assertOk();

        $branch = $this->getJson("/api/v1/catalog/availability?store_id={$this->store->id}&branch_id={$this->branch->id}")->json('data.branches.0');
        $this->assertSame([$burger->id], $branch['stopped']['products']);
        $this->assertSame("Burger Bro bu filialda o'chirilgan", $branch['stop_list'][0]['reason']);
        $this->order([['variant_id' => $variant->uuid, 'quantity' => 1]])->assertStatus(422);
        $this->order([['variant_id' => $variant->uuid, 'quantity' => 1]], ['branch_id' => $this->otherBranch->id], $this->makeCustomer())->assertCreated();

        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/brands/{$brand->id}/branches", ['branches' => [['branch_id' => $this->branch->id, 'is_active' => true]]])->assertOk();
        $this->order([['variant_id' => $variant->uuid, 'quantity' => 1]], [], $this->makeCustomer())->assertCreated();
    }

    public function test_order_lines_carry_their_brand_to_the_kitchen_ticket_and_the_till(): void
    {
        $burgers = $this->makeBrand('Burger Bro', 'BB', ['color' => '#DC2626']);
        $lavash = $this->makeBrand('Lavash Lab', 'LL', ['color' => '#16A34A']);
        [, $burger] = $this->brandDish($burgers, 'Burger');
        [, $wrap] = $this->brandDish($lavash, 'Lavash');

        $uuid = $this->order([
            ['variant_id' => $burger->uuid, 'quantity' => 1],
            ['variant_id' => $wrap->uuid, 'quantity' => 2],
            ['variant_id' => $this->variant->uuid, 'quantity' => 1], // the house brand
        ])->assertCreated()->json('data.order_uuid');
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $order->forceFill(['status' => OrderStatus::ACCEPTED])->save();

        $items = OrderItem::where('order_id', $order->id)->orderBy('id')->get();
        $this->assertSame([$burgers->id, $lavash->id, null], $items->pluck('brand_id')->all());
        $this->assertSame(['id' => $burgers->id, 'name' => 'Burger Bro', 'code' => 'BB', 'color' => '#DC2626'], $items[0]->brand);

        // A later rename does not touch what was sold
        $burgers->update(['short_code' => 'BRO']);

        $cook = $this->makeCook();
        $ticket = $this->actingAs($cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertOk()->json('data.0');
        $this->assertSame('BB', $ticket['items'][0]['brand']['code']);
        $this->assertSame('#16A34A', $ticket['items'][1]['brand']['color']);
        $this->assertArrayNotHasKey('brand', $ticket['items'][2]);
        $this->assertSame(['BB', 'LL'], array_column($ticket['brands'], 'code'));
    }

    public function test_a_store_without_brands_keeps_its_shapes(): void
    {
        $uuid = $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertCreated()->json('data.order_uuid');
        Order::where('uuid', $uuid)->update(['status' => OrderStatus::ACCEPTED->value]);
        $ticket = $this->actingAs($this->makeCook(), 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertOk()->json('data.0');
        $this->assertArrayNotHasKey('brands', $ticket);
        $this->assertSame(['id', 'product_name', 'variant_name', 'quantity', 'unit', 'quantity_label', 'modifiers', 'kitchen_status', 'ready_at'], array_keys($ticket['items'][0]));
        $this->assertArrayNotHasKey('brands', $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$this->store->uuid}")->assertOk()->json('data'));
        $this->assertSame([], $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/pos/catalog')->assertOk()->json('data.brands'));
    }

    public function test_the_catalogue_filters_by_brand(): void
    {
        $brand = $this->makeBrand('Lavash Lab', 'LL');
        $hidden = $this->makeBrand('Faqat zal', 'FZ');
        BrandChannel::create(['store_id' => $this->store->id, 'brand_id' => $hidden->id, 'channel' => 'telegram', 'is_hidden' => true]);
        $this->brandDish($brand, 'Lavash');

        $data = $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$this->store->uuid}&brand={$brand->uuid}")->assertOk()->json('data');
        $this->assertSame(['Lavash'], array_column($data['products'], 'name'));
        $this->assertSame($brand->id, $data['products'][0]['brand_id']);
        $this->assertSame([['id' => $brand->id, 'uuid' => $brand->uuid, 'name' => 'Lavash Lab', 'code' => 'LL', 'color' => '#E11D48', 'logo_url' => null]], $data['brands']);
        $this->getJson("/api/v1/catalog?store_id={$this->store->uuid}&brand={$brand->id}")->assertOk()->assertJsonCount(1, 'data.products');
        $this->getJson("/api/v1/catalog?store_id={$this->store->uuid}&brand=99999")->assertNotFound();

        // The till lists the brands of its channel
        $this->assertSame(['LL', 'FZ'], array_column($this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/pos/catalog')->json('data.brands'), 'code'));
    }

    public function test_the_module_gates_the_brand_routes_and_another_stores_brand_is_not_found(): void
    {
        $brand = $this->makeBrand('Burger Bro', 'BB');

        // A choyxona store: no multi_brand
        $tea = $this->makeStore('Choyxona');
        $teaOwner = $this->makeStaff(UserRole::OWNER, null, $tea);
        $teaBranch = $this->makeBranch($tea, 'Asosiy');
        $teaBranch->forceFill(['pos_settings' => \App\Domain\Pos\Setup\PosSettings::withFormat($teaBranch->pos_settings, FormatProfile::CHOYXONA, [])])->save();
        $this->actingAs($teaOwner, 'sanctum')->getJson('/api/v1/owner/brands')->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $this->actingAs($teaOwner, 'sanctum')->getJson('/api/v1/owner/reports/channel-revenue')->assertForbidden();

        // Another dark kitchen: another store's brand is 404
        $other = $this->makeStore('Other Kitchen');
        $otherOwner = $this->makeStaff(UserRole::OWNER, null, $other);
        $this->useDarkKitchen($this->makeBranch($other, 'Main'));
        $this->actingAs($otherOwner, 'sanctum')->patchJson("/api/v1/owner/brands/{$brand->id}", ['name' => 'X'])->assertNotFound();
        $this->actingAs($otherOwner, 'sanctum')->deleteJson("/api/v1/owner/brands/{$brand->id}")->assertNotFound();
        $this->actingAs($otherOwner, 'sanctum')->putJson('/api/v1/owner/product-brands', ['brand_id' => $brand->id, 'product_ids' => [$this->product->id]])->assertNotFound();
        $this->actingAs($otherOwner, 'sanctum')->putJson('/api/v1/owner/product-brands', ['brand_id' => null, 'product_ids' => [$this->product->id]])->assertStatus(422);
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/brands/{$brand->id}/branches", ['branches' => [['branch_id' => $other->branches()->value('id'), 'is_active' => false]]])->assertStatus(422);
        $this->assertSame([], $this->actingAs($otherOwner, 'sanctum')->getJson('/api/v1/owner/brands')->json('data.brands'));
    }
}
