<?php

namespace Tests\Feature;

use App\Actions\Order\CreateOrderAction;
use App\Enums\DeviceType;
use App\Models\ChannelPrice;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\StopListEntry;
use App\Support\Quantity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * P1-34 (review A): checkout integrity. Customers cannot place hall (dine_in) orders, which skipped the branch's
 * stop-list and batches; the channel comes from the sign-in, not the body; weighed lines are never merged; numeric
 * ids past bigint are a 422/404, not a 500; an Idempotency-Key is marked used in the order's own transaction;
 * rolling back the P1-21 unit migrations never rounds stored fractions.
 */
class CheckoutIntegrityTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use MenuFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    public function test_customers_cannot_place_hall_orders(): void
    {
        // Sold out (batch-tracked, no open batch) and stop-listed on every branch: a pickup is refused...
        $osh = $this->makeProduct('Osh', ['batch_tracked' => true]);
        $portion = $this->makeVariant($osh, '55000', ['name' => '1 porsiya']);
        foreach ([$this->branch, $this->otherBranch] as $branch) {
            StopListEntry::create(['store_id' => $this->store->id, 'branch_id' => $branch->id, 'subject_type' => 'variant', 'subject_id' => $this->variant->id]);
        }
        $this->order([['variant_id' => $portion->uuid, 'quantity' => 1]])->assertStatus(422);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]])->assertStatus(422);

        // ...and a hall order, which had no branch to check against, is refused outright (Telegram and mobile tokens)
        $message = "Zalda ovqatlanish buyurtmasini ilovadan berib bo'lmaydi: uni ofitsiant yoki kassa qabul qiladi. Yetkazib berish yoki olib ketishni tanlang.";
        $this->order([['variant_id' => $portion->uuid, 'quantity' => 3]], ['type' => 'dine_in', 'branch_id' => null])
            ->assertStatus(422)
            ->assertJsonPath('errors.type.0', $message);
        $this->order([['variant_id' => $this->variant->uuid, 'quantity' => 1]], ['type' => 'dine_in'])->assertStatus(422);
        $this->mobileOrder([['variant_id' => $this->variant->uuid, 'quantity' => 1]], ['type' => 'dine_in', 'branch_id' => null])
            ->assertStatus(422)
            ->assertJsonPath('errors.type.0', $message);

        $this->assertSame(0, Order::count());
        $this->assertSame(10, $this->variant->fresh()->stock);
    }

    public function test_an_order_without_a_branch_is_never_placed(): void
    {
        $osh = $this->makeProduct('Osh', ['batch_tracked' => true]);
        $portion = $this->makeVariant($osh, '55000', ['name' => '1 porsiya']);

        // The action itself (whatever calls it): no branch, no stop-list or batch check, so no order
        try {
            app(CreateOrderAction::class)->execute($this->customer, [['variant_id' => $portion->uuid, 'quantity' => 1]], [
                'store_id' => $this->store->id, 'type' => 'dine_in', 'payment_method' => 'cash',
            ]);
            $this->fail('An order without a branch was placed');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('type', $e->errors());
        }

        $this->assertSame(0, Order::count());
    }

    public function test_the_channel_comes_from_the_sign_in_not_the_body(): void
    {
        // Hidden on Telegram, 1 000 on the mobile app
        ChannelPrice::create(['store_id' => $this->store->id, 'product_id' => $this->product->id, 'product_variant_id' => $this->variant->id, 'channel' => 'mobile', 'price' => '1000']);
        ChannelPrice::create(['store_id' => $this->store->id, 'product_id' => $this->product->id, 'product_variant_id' => $this->variant->id, 'channel' => 'telegram', 'is_hidden' => true]);
        $items = [['variant_id' => $this->variant->uuid, 'quantity' => 1]];

        // A Telegram (initData) session claiming to be the mobile app gets the Telegram menu
        $this->order($items, ['channel' => 'mobile'])->assertStatus(422);
        $this->order($items)->assertStatus(422);

        // The mobile app's device token gets the mobile menu, whatever the body says
        $response = $this->mobileOrder($items, ['channel' => 'telegram'])->assertCreated();
        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        $this->assertSame('mobile', $order->channel->value);
        $this->assertSame('1000.00', $order->getRawOriginal('subtotal'));

        // A staff app's device (partner, courier) is not the customer mobile app
        $this->mobileOrder($items, [], null, DeviceType::PARTNER_MOBILE)->assertStatus(422);

        // A value outside the customer channels is still a 422
        $this->mobileOrder($items, ['channel' => 'pos_hall'])->assertStatus(422);
        $this->assertSame(1, Order::count());
    }

    public function test_weighed_lines_are_never_merged_so_the_total_is_the_sum_of_the_lines(): void
    {
        $meat = $this->makeProduct("Go'sht");
        $kg = $this->makeVariant($meat, '100000', ['unit' => 'kg', 'quantity_step' => '0.1', 'min_quantity' => '0.5', 'stock' => 50]);
        $sauce = $this->makeGroup('Sous', ['Achchiq' => '5000'], [], [], $meat);
        $hot = $this->modifier($sauce, 'Achchiq')->uuid;

        // Two 0.5 kg portions with sauce: 55 000 each, as the Mini App shows each line (lib/linePrice.ts)
        $response = $this->order([
            ['variant_id' => $kg->uuid, 'quantity' => '0.5', 'modifiers' => [$hot]],
            ['variant_id' => $kg->uuid, 'quantity' => '0.5', 'modifiers' => [$hot]],
        ])->assertCreated();
        $order = Order::where('uuid', $response->json('data.order_uuid'))->firstOrFail();
        $this->assertSame('110000.00', $order->getRawOriginal('subtotal'));
        $this->assertSame(['55000.00', '55000.00'], $order->items()->orderBy('id')->get()->map(fn ($item) => $item->getRawOriginal('total_price'))->all());
        $this->assertSame('49.000', DB::table('product_variants')->where('id', $kg->id)->value('stock'));

        // Without modifiers too: each line is rounded on its own (16 666.5 -> 16 667, twice)
        $odd = $this->makeVariant($meat, '33333', ['name' => 'Arzon', 'unit' => 'kg', 'quantity_step' => '0.1', 'min_quantity' => '0.1', 'stock' => 10]);
        $response = $this->order([
            ['variant_id' => $odd->uuid, 'quantity' => '0.5'],
            ['variant_id' => $odd->uuid, 'quantity' => '0.5'],
        ])->assertCreated();
        $this->assertSame('33334.00', Order::where('uuid', $response->json('data.order_uuid'))->value('subtotal'));

        // Each weighed line is its own portion: two halves of the minimum are not one minimum
        $this->order([
            ['variant_id' => $kg->uuid, 'quantity' => '0.2', 'modifiers' => [$hot]],
            ['variant_id' => $kg->uuid, 'quantity' => '0.3', 'modifiers' => [$hot]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.quantity');
    }

    public function test_ids_past_bigint_are_refused_not_a_server_error(): void
    {
        $huge = '99999999999999999999';

        $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog/availability?store_id={$huge}")
            ->assertStatus(422)->assertJsonValidationErrors('store_id');
        $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog/availability?store_id={$this->store->id}&branch_id={$huge}")
            ->assertStatus(422)->assertJsonValidationErrors('branch_id');
        $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog/availability?store_id={$this->store->id}&branch_id={$this->branch->id}")
            ->assertOk()->assertJsonCount(1, 'data.branches');
        $this->actingAs($this->customer, 'sanctum')->getJson("/api/v1/catalog?store_id={$huge}")
            ->assertStatus(422)->assertJsonValidationErrors('store_id');

        $items = [['variant_id' => $this->variant->uuid, 'quantity' => 1]];
        $this->order($items, ['store_id' => $huge])->assertStatus(422)->assertJsonValidationErrors('store_id');
        $this->order($items, ['branch_id' => $huge])->assertStatus(422)->assertJsonValidationErrors('branch_id');

        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/v1/owner/stop-list/{$huge}")->assertNotFound();
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/products/{$huge}/channel-prices")->assertNotFound();
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/batches/{$huge}/close")->assertNotFound();
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/branch-variants?branch_id={$huge}")->assertStatus(422);
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/v1/stores/{$huge}/rate", ['rating' => 5])->assertNotFound();
        $this->assertSame(0, Order::count());
    }

    public function test_a_key_whose_order_committed_is_never_taken_over(): void
    {
        config(['menu.idempotency.wait_seconds' => 0]);
        $items = [['variant_id' => $this->variant->uuid, 'quantity' => 1]];
        $this->order($items, [], null, ['Idempotency-Key' => 'crash-1'])->assertCreated();
        $row = IdempotencyKey::sole();
        $this->assertNull($row->locked_until);

        // The worker died after the order committed but before the response was stored: long past any lock
        $row->forceFill(['response_status' => null, 'response_body' => null, 'response_content_type' => null])->save();
        $this->travel(5)->minutes();

        $this->order($items, [], null, ['Idempotency-Key' => 'crash-1'])
            ->assertStatus(409)
            ->assertJsonPath('message', "Shu kalit bilan yuborilgan so'rov hali bajarilmoqda. Birozdan so'ng qayta urinib ko'ring.");
        $this->assertSame(1, Order::count());
        $this->assertSame(1, IdempotencyKey::count());
    }

    public function test_an_attempt_whose_key_was_taken_over_rolls_its_order_back(): void
    {
        $items = [['variant_id' => $this->variant->uuid, 'quantity' => 1]];
        $takenOver = null;

        // While the first attempt is still running (here: validating the body, before its transaction), a retry takes
        // its key over, as one would after lock_seconds
        DB::listen(function ($query) use (&$takenOver) {
            if ($takenOver === null && str_contains($query->sql, 'from "stores"')) {
                $takenOver = now()->addMinutes(3)->startOfSecond();
                DB::table('idempotency_keys')->update(['locked_until' => $takenOver]);
            }
        });

        $this->order($items, [], null, ['Idempotency-Key' => 'slow-1'])
            ->assertStatus(409)
            ->assertJsonPath('message', "Shu kalit bilan yuborilgan so'rov boshqa urinishda bajarilmoqda. Birozdan so'ng qayta urinib ko'ring.");

        // Its order was rolled back, and the key stays the new owner's (not released, nothing stored over it)
        $this->assertSame(0, Order::count());
        $this->assertSame(10, $this->variant->fresh()->stock);
        $row = IdempotencyKey::sole();
        $this->assertNull($row->response_status);
        $this->assertTrue($row->locked_until->equalTo($takenOver));
    }

    public function test_quantity_whole_check_uses_no_deprecated_brick_api(): void
    {
        $deprecations = [];
        set_error_handler(function (int $level, string $message) use (&$deprecations) {
            $deprecations[] = $message;

            return true;
        }, E_USER_DEPRECATED | E_DEPRECATED);

        try {
            $this->assertTrue(Quantity::of('2')->isWhole());
            $this->assertTrue(Quantity::of('2.000')->isWhole());
            $this->assertFalse(Quantity::of('0.5')->isWhole());
            $this->assertFalse(Quantity::of('1.001')->isWhole());
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
    }

    public function test_rolling_back_the_unit_migrations_never_rounds_fractions(): void
    {
        /** @var Migration $units */
        $units = require database_path('migrations/2026_10_22_100100_add_sale_units_to_product_variants_table.php');
        /** @var Migration $snapshot */
        $snapshot = require database_path('migrations/2026_10_22_100500_add_menu_snapshot_to_order_items_table.php');

        DB::table('product_variants')->where('id', $this->variant->id)->update(['stock' => '2.500']);
        try {
            $units->down();
            $this->fail('The rollback rounded fractional stock');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('product_variants.stock has fractional values', $e->getMessage());
        }
        $this->assertSame('2.500', DB::table('product_variants')->where('id', $this->variant->id)->value('stock'));

        $meat = $this->makeProduct("Go'sht");
        $kg = $this->makeVariant($meat, '100000', ['unit' => 'kg', 'quantity_step' => '0.1', 'min_quantity' => '0.5', 'stock' => 5]);
        $this->order([['variant_id' => $kg->uuid, 'quantity' => '1.5']])->assertCreated();
        try {
            $snapshot->down();
            $this->fail('The rollback rounded fractional order quantities');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('order_items.quantity has fractional values', $e->getMessage());
        }
        $this->assertSame('1.500', DB::table('order_items')->value('quantity'));
    }
}
