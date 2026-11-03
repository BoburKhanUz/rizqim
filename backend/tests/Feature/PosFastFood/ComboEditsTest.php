<?php

namespace Tests\Feature\PosFastFood;

use App\Domain\Pos\Setup\MenuVersion;
use App\Events\Broadcast\KitchenItemStatusChangedBroadcast;
use App\Models\Combo;
use App\Models\ComboSlot;
use App\Models\ComboSlotOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Review P6 wave 3 (combos): an edit in the constructor keeps the slots and options it did not change (their uuids, so
 * a till's cached picks still sell, online and offline), a removed one is soft-deleted (an offline sale of it still
 * syncs), a switch-off is only the flag; an offline sale of a combo switched off is kept; an offline pick of another
 * combo's option is refused; menu_version moves with combos; an online pickup's kitchen time counts the parts' prep; the
 * kitchen progress counts the parts; a combo sold with no picks is a plain line.
 */
class ComboEditsTest extends TestCase
{
    use ComboFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCombo();
    }

    public function test_an_edit_keeps_the_unchanged_slots_and_options_and_an_offline_sale_of_a_cached_pick_syncs(): void
    {
        $this->makeCombo();
        $slotUuids = ComboSlot::query()->orderBy('sort_order')->pluck('uuid')->all();
        $ayran = $this->option($this->ayran);
        $pepsi = $this->option($this->pepsi);

        // The owner only changes the Ayran delta
        $slots = $this->comboSlots();
        $slots[1]['options'][2]['price_delta'] = '2500';
        $saved = $this->saveCombo($slots)->assertCreated();
        $this->assertSame($slotUuids, array_column($saved->json('data.slots'), 'uuid'));
        $this->assertSame($ayran, $this->option($this->ayran));
        $this->assertSame($pepsi, $this->option($this->pepsi));
        $this->assertSame('2500.00', $saved->json('data.slots.1.options.2.price_delta'));
        $this->assertSame(7, ComboSlotOption::withTrashed()->count());

        // The till took its menu before the edit: the sale syncs, priced by the server's delta (the manager sees it)
        $order = (string) Str::uuid();
        $line = $this->line($this->comboVariant, '1') + ['combo' => [['option_id' => $ayran]], 'combo_delta' => '2000'];
        $this->sync([$this->op('order.open', [
            'order' => $order, 'local_number' => 'K1-009', 'channel' => 'pos_takeaway', 'items' => [$line], 'send' => true,
            'menu_taken_at' => now()->subHour()->toJSON(),
        ])])->assertOk()->assertJsonPath('data.results.0.status', 'conflict');
        $sold = Order::where('external_id', $order)->firstOrFail();
        $this->assertNotEmpty(array_filter((array) $sold->sync_flags, fn ($flag) => ($flag['flag'] ?? null) === 'offline_price_mismatch'));
        $this->assertSame('47500.00', Money::of($sold->subtotal)->toString());
        $this->assertSame(['Lavash', 'Ayran'], array_column(OrderItem::where('order_id', $sold->id)->firstOrFail()->combo_parts, 'name'));

        // An online till with the same cached uuids after an identical re-save sells as before
        $this->saveCombo($slots)->assertCreated();
        $this->counterOrder([$this->comboLine([['option_id' => $pepsi]])])->assertCreated();
    }

    public function test_a_removed_option_or_slot_is_soft_deleted_and_an_offline_sale_of_it_still_syncs(): void
    {
        $this->makeCombo();
        $fanta = $this->option($this->fanta);
        $hot = $this->option($this->hotSauce);
        $pepsi = $this->option($this->pepsi);

        // Fanta and the whole "Sous" slot leave the combo; Pepsi stays where it was
        $slots = $this->comboSlots();
        unset($slots[1]['options'][1], $slots[2]);
        $slots[1]['options'] = array_values($slots[1]['options']);
        $saved = $this->saveCombo(array_values($slots))->assertCreated()->assertJsonCount(2, 'data.slots');
        $this->assertSame(['Pepsi', 'Ayran'], array_column($saved->json('data.slots.1.options'), 'name'));
        $this->assertSame($pepsi, $this->option($this->pepsi));
        $this->assertTrue(ComboSlotOption::withTrashed()->where('uuid', $fanta)->firstOrFail()->trashed());
        $this->assertTrue(ComboSlot::withTrashed()->where('name', 'Sous')->firstOrFail()->trashed());

        // Online: no longer this combo's
        $this->counterOrder([$this->comboLine([['option_id' => $fanta]])])->assertStatus(422)->assertJsonPath('code', 'combo_option_invalid');

        // Offline, the till still had them: the sale is the store's, with its parts
        $order = (string) Str::uuid();
        $line = $this->line($this->comboVariant, '1') + ['combo' => [['option_id' => $fanta], ['option_id' => $hot]], 'combo_delta' => '0'];
        $this->sync([$this->op('order.open', [
            'order' => $order, 'local_number' => 'K1-010', 'channel' => 'pos_takeaway', 'items' => [$line], 'send' => true,
        ])])->assertOk()->assertJsonPath('data.results.0.status', 'applied');
        $combo = OrderItem::where('order_id', Order::where('external_id', $order)->firstOrFail()->id)->firstOrFail();
        $this->assertSame(['Lavash', 'Fanta', 'Achchiq sous'], array_column($combo->combo_parts, 'name'));
        $this->assertSame(3, $combo->comboChildren()->count());
    }

    public function test_an_offline_sale_of_a_combo_switched_off_is_kept_and_a_switch_off_only_sets_the_flag(): void
    {
        $combo = $this->makeCombo();
        $pepsi = $this->option($this->pepsi);
        $fanta = $this->option($this->fanta);

        // A part dish switched off in the menu does not stop the owner switching the combo off; the slots stay
        $this->fanta->forceFill(['is_active' => false])->save();
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/combos/{$combo->id}", ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertFalse($combo->refresh()->is_active);
        $this->assertSame($fanta, $this->option($this->fanta));
        $this->assertSame(7, ComboSlotOption::withTrashed()->count());

        // The till sold it as a combo before it heard: kept, priced by the server's current combo
        $order = (string) Str::uuid();
        $line = $this->line($this->comboVariant, '1') + ['combo' => [['option_id' => $pepsi]], 'combo_delta' => '0'];
        $this->sync([$this->op('order.open', [
            'order' => $order, 'local_number' => 'K1-011', 'channel' => 'pos_takeaway', 'items' => [$line], 'send' => true,
        ])])->assertOk()->assertJsonPath('data.results.0.status', 'applied');
        $item = OrderItem::where('order_id', Order::where('external_id', $order)->firstOrFail()->id)->firstOrFail();
        $this->assertSame(['Lavash', 'Pepsi'], array_column($item->combo_parts, 'name'));

        // A till that knew it is off sells the product as a plain dish (no picks): no parts
        $plain = (string) Str::uuid();
        $this->sync([$this->op('order.open', [
            'order' => $plain, 'local_number' => 'K1-012', 'channel' => 'pos_takeaway', 'items' => [$this->line($this->comboVariant, '1')], 'send' => true,
        ])])->assertOk()->assertJsonPath('data.results.0.status', 'applied');
        $this->assertNull(OrderItem::where('order_id', Order::where('external_id', $plain)->firstOrFail()->id)->firstOrFail()->combo_parts);

        // Online the combo is off: a pick is refused
        $this->counterOrder([$this->comboLine([['option_id' => $pepsi]])])->assertStatus(422)->assertJsonPath('code', 'combo_option_invalid');
    }

    public function test_an_offline_pick_of_another_combos_option_is_refused(): void
    {
        $this->makeCombo();
        $other = $this->makeVariant($this->makeProduct('Burger kombo', ['mxik_code' => '10202001018000001']), '50000', ['name' => 'Kombo', 'stock' => 1000]);
        $this->saveCombo([['name' => 'Ichimlik', 'options' => [['variant_id' => $this->pepsi->id, 'is_default' => true]]]], $other->product)->assertCreated();
        $foreign = (string) ComboSlotOption::query()->whereHas('slot.combo', fn ($query) => $query->where('product_id', $other->product_id))->value('uuid');

        $order = (string) Str::uuid();
        $line = $this->line($this->comboVariant, '1') + ['combo' => [['option_id' => $foreign]], 'combo_delta' => '0'];
        $this->sync([$this->op('order.open', [
            'order' => $order, 'local_number' => 'K1-013', 'channel' => 'pos_takeaway', 'items' => [$line], 'send' => true,
        ])])->assertOk()->assertJsonPath('data.results.0.status', 'rejected');
        $this->assertNull(Order::where('external_id', $order)->first());
    }

    public function test_menu_version_moves_when_a_combo_is_created_edited_switched_off_or_deleted(): void
    {
        $versions = [MenuVersion::forStore((int) $this->store->id)];
        $this->travel(2)->seconds();
        $combo = $this->makeCombo();
        $versions[] = MenuVersion::forStore((int) $this->store->id);

        $this->travel(2)->seconds();
        $slots = $this->comboSlots();
        $slots[1]['options'][2]['price_delta'] = '2500';
        $this->saveCombo($slots)->assertCreated();
        $versions[] = MenuVersion::forStore((int) $this->store->id);

        $this->travel(2)->seconds();
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/combos/{$combo->id}", ['is_active' => false])->assertOk();
        $versions[] = MenuVersion::forStore((int) $this->store->id);

        $this->travel(2)->seconds();
        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/v1/owner/combos/{$combo->id}")->assertOk();
        $versions[] = MenuVersion::forStore((int) $this->store->id);

        // Each change moves it (a till holding the previous value refetches); after the delete the menu is as at first
        foreach ([1, 2, 3, 4] as $k) {
            $this->assertNotSame($versions[$k - 1], $versions[$k], "step {$k}: " . implode(' ', $versions));
        }
    }

    public function test_an_online_pickup_for_later_counts_the_prep_time_of_the_combo_parts(): void
    {
        $this->makeCombo();
        Carbon::setTestNow(Carbon::parse('2026-10-22 13:00:00', 'UTC'));
        // The combo product itself is quick; the chicken lavash inside it takes 40 minutes
        $this->comboProduct->forceFill(['wait_minutes' => 5])->save();
        $this->chickenLavash->product->forceFill(['wait_minutes' => 40])->save();

        $this->order([$this->comboLine([['option_id' => $this->option($this->chickenLavash)]])], ['pickup_at' => '2026-10-22T15:00:00Z'])->assertCreated();
        $online = Order::where('channel', 'telegram')->latest('id')->firstOrFail();
        $this->assertSame('2026-10-22T14:20:00+00:00', $online->kitchen_fire_at?->utc()->toIso8601String());
    }

    public function test_the_kitchen_progress_counts_the_parts_and_a_combo_with_no_picks_is_a_plain_line(): void
    {
        $this->makeCombo();
        $uuid = $this->counterOrder([$this->comboLine(), ['variant_id' => $this->tea->uuid, 'quantity' => 1]])->assertCreated()->json('data.uuid');
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $combo = OrderItem::where('order_id', $order->id)->whereNotNull('combo_parts')->firstOrFail();
        $part = $combo->comboChildren()->firstOrFail();
        $part->forceFill(['kitchen_status' => 'ready', 'ready_at' => now()])->save();

        // Lavash + Pepsi + the tea, as the ticket counts them (not the combo line)
        $event = new KitchenItemStatusChangedBroadcast($order->refresh(), $part->refresh());
        $this->assertSame(3, $event->itemsTotal);
        $this->assertSame(1, $event->itemsReady);

        // A combo whose slots are all optional, sold with no picks: no parts, a plain line everywhere
        $sauces = $this->makeVariant($this->makeProduct('Sous kombo', ['mxik_code' => '10202001019000001']), '3000', ['name' => 'Kombo', 'stock' => 1000]);
        $this->saveCombo([['name' => 'Sous', 'min_picks' => 0, 'max_picks' => 2, 'options' => [['variant_id' => $this->hotSauce->id]]]], $sauces->product)->assertCreated();
        $uuid = $this->counterOrder([['variant_id' => $sauces->uuid, 'quantity' => 1, 'combo' => []]])->assertCreated()->json('data.uuid');
        $line = OrderItem::where('order_id', Order::where('uuid', $uuid)->firstOrFail()->id)->firstOrFail();
        $this->assertNull($line->combo_parts);
        $this->assertFalse($line->isComboLine());
        $this->assertSame(0, $line->comboChildren()->count());
        $this->assertNotNull(Product::find($sauces->product_id));
        $this->assertNotNull(Combo::where('product_id', $sauces->product_id)->first());
    }
}
