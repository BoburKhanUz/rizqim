<?php

namespace Tests\Feature\PosFastFood;

use App\Models\AuditLog;
use App\Models\Combo;
use App\Models\DailySet;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StopListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-31 "Kombo": the owner's constructor (rules, module, audit), the price and delta maths, the slot rules (required,
 * limits, options of the slot, defaults), availability (a stale pick refused, a combo closed by an empty required
 * slot), the kitchen split (each part on its station, the combo ready when all are), voids and the POS catalog.
 */
class ComboTest extends TestCase
{
    use ComboFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCombo();
    }

    public function test_the_owner_builds_a_combo_with_slots_options_deltas_and_defaults(): void
    {
        $response = $this->saveCombo()->assertCreated();
        $response->assertJsonPath('data.product_name', 'Lavash kombo')->assertJsonPath('data.prices', ['45000.00']);
        $slots = $response->json('data.slots');
        $this->assertSame(['Asosiy', 'Ichimlik', 'Sous'], array_column($slots, 'name'));
        $this->assertSame([true, true, false], array_column($slots, 'is_required'));
        $this->assertSame('5000.00', $slots[0]['options'][1]['price_delta']);
        $this->assertSame("Mol go'shtli", $slots[0]['options'][0]['modifiers'][0]['name']);
        $this->assertSame("Asosiy — Lavash (Katta), Mol go'shtli", $slots[0]['options'][0]['receipt_label']);
        $this->assertSame('Ichimlik — 0,4 l Pepsi', $slots[1]['options'][0]['receipt_label']);
        $this->assertTrue(AuditLog::where('action', 'menu.combo_saved')->exists());

        $listed = $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/combos')->assertOk();
        $listed->assertJsonCount(1, 'data.combos')->assertJsonPath('data.fiscal_mode', 'single');

        // Saving again replaces the slots; update keeps them when not sent; switched off it is a plain dish
        $this->saveCombo([['name' => 'Ichimlik', 'options' => [['variant_id' => $this->pepsi->id]]]])->assertCreated()->assertJsonCount(1, 'data.slots');
        $id = Combo::query()->value('id');
        $this->actingAs($this->owner, 'sanctum')->putJson("/api/v1/owner/combos/{$id}", ['is_active' => false])->assertOk()
            ->assertJsonPath('data.is_active', false)->assertJsonCount(1, 'data.slots');
        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/v1/owner/combos/{$id}")->assertOk();
        $this->assertSame(0, Combo::count());
        $this->assertTrue(AuditLog::where('action', 'menu.combo_deleted')->exists());
    }

    public function test_the_constructor_refuses_what_cannot_be_a_combo(): void
    {
        $slot = fn (array $options, array $extra = []) => [['name' => 'Asosiy', 'options' => $options] + $extra];

        $this->saveCombo([])->assertStatus(422)->assertJsonValidationErrors('slots');
        // Not itself, nor a dish sold by weight, nor a negative delta, nor two defaults, nor a modifier of another dish
        $this->saveCombo($slot([['variant_id' => $this->comboVariant->id]]))->assertStatus(422)->assertJsonValidationErrors('slots.0.options.0.variant_id');
        $this->saveCombo($slot([['variant_id' => $this->plov->id]]))->assertStatus(422)->assertJsonValidationErrors('slots.0.options.0.variant_id');
        $this->saveCombo($slot([['variant_id' => $this->pepsi->id, 'price_delta' => '-100']]))->assertStatus(422);
        $this->saveCombo($slot([['variant_id' => $this->pepsi->id, 'is_default' => true], ['variant_id' => $this->fanta->id, 'is_default' => true]]))
            ->assertStatus(422)->assertJsonValidationErrors('slots.0.options');
        $this->saveCombo($slot([['variant_id' => $this->pepsi->id, 'modifiers' => [['modifier_id' => $this->modifier($this->meat, 'Pishloq')->id]]]]))
            ->assertStatus(422)->assertJsonValidationErrors('slots.0.options.0.modifiers.0');
        $this->saveCombo($slot([['variant_id' => $this->pepsi->id]], ['min_picks' => 3, 'max_picks' => 2]))->assertStatus(422);

        // A kompleks is not a combo, and a combo is not a part of another combo
        DailySet::create(['store_id' => $this->store->id, 'product_id' => $this->ayran->product_id, 'is_active' => true]);
        $this->saveCombo(null, $this->ayran->product)->assertStatus(422)->assertJsonValidationErrors('product_id');
        $this->saveCombo($slot([['variant_id' => $this->ayran->id]]))->assertStatus(422);
        $this->saveCombo($slot([['variant_id' => $this->pepsi->id]]))->assertCreated();
        $this->saveCombo($slot([['variant_id' => $this->comboVariant->id]]), $this->fanta->product)->assertStatus(422);

        // The module gates the constructor (a choyxona has no combos by default); managers read but do not edit
        $this->branch->forceFill(['pos_settings' => \App\Domain\Pos\Setup\PosSettings::withFormat($this->branch->pos_settings, \App\Domain\Pos\Setup\FormatProfile::CHOYXONA, [])])->save();
        $this->otherBranch->forceFill(['pos_settings' => \App\Domain\Pos\Setup\PosSettings::withFormat($this->otherBranch->pos_settings, \App\Domain\Pos\Setup\FormatProfile::CHOYXONA, [])])->save();
        $this->saveCombo()->assertForbidden()->assertJsonPath('code', 'module_disabled')->assertJsonPath('data.module', 'combo');
        $this->branch->forceFill(['pos_settings' => \App\Domain\Pos\Setup\PosSettings::withFormat($this->branch->pos_settings, \App\Domain\Pos\Setup\FormatProfile::FAST_FOOD, [])])->save();
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/combos')->assertOk();
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/owner/combos', ['product_id' => $this->comboProduct->id, 'slots' => $this->comboSlots()])->assertForbidden();
    }

    public function test_the_price_is_the_combo_price_plus_the_deltas_of_the_picks(): void
    {
        $this->makeCombo();

        // Nothing picked: every required slot takes its default, the optional sauce is left out
        $line = $this->counterOrder([$this->comboLine()])->assertCreated()->json('data.items.0');
        $this->assertSame('45000.00', $line['unit_price']);
        $this->assertSame('0.00', $line['modifiers_total']);
        $this->assertSame('45000.00', $line['total_price']);
        $this->assertSame(['Lavash', 'Pepsi'], array_column($line['combo_parts'], 'name'));

        // Tovuq +5 000, ayran +2 000, two hot sauces (0): 52 000 a combo, two combos
        $line = $this->counterOrder([$this->comboLine([
            ['option_id' => $this->option($this->chickenLavash)],
            ['option_id' => $this->option($this->ayran)],
            ['option_id' => $this->option($this->hotSauce), 'quantity' => 2],
        ], 2)])->assertCreated()->json('data.items.0');
        $this->assertSame('7000.00', $line['modifiers_total']);
        $this->assertSame('104000.00', $line['total_price']);
        $this->assertSame(['Tovuq lavash', 'Ayran', 'Achchiq sous'], array_column($line['combo_parts'], 'name'));
        $this->assertSame([1, 1, 2], array_column($line['combo_parts'], 'quantity'));
        $this->assertSame(['5000.00', '2000.00', '0.00'], array_column($line['combo_parts'], 'price_delta'));

        // The buyer adds cheese to the lavash (the slot allows it): +3 000; dropping the preset gives nothing back
        $line = $this->counterOrder([$this->comboLine([
            ['option_id' => $this->option($this->lavash), 'modifiers' => [$this->modifier($this->meat, "Mol go'shtli")->uuid, $this->modifier($this->meat, 'Pishloq')->uuid]],
        ])])->assertCreated()->json('data.items.0');
        $this->assertSame('48000.00', $line['total_price']);
        $this->assertSame("Asosiy — Lavash (Katta), Mol go'shtli, Pishloq", $line['combo_parts'][0]['label']);
        $line = $this->counterOrder([$this->comboLine([['option_id' => $this->option($this->lavash), 'modifiers' => []]])])->assertCreated()->json('data.items.0');
        $this->assertSame('45000.00', $line['total_price']);

        // The order total is the lines' (one priced line per combo, its kitchen lines cost nothing)
        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame('45000.00', \App\Support\Money::of($order->subtotal)->toString());
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(2, OrderItem::withComboParts()->where('order_id', $order->id)->whereNotNull('combo_parent_id')->count());
        $this->assertSame('0.00', \App\Support\Money::of(OrderItem::withComboParts()->where('order_id', $order->id)->whereNotNull('combo_parent_id')->sum('total_price'))->toString());
    }

    public function test_the_slot_rules_are_checked_on_the_server(): void
    {
        $combo = $this->makeCombo();
        $line = fn (array $picks) => $this->counterOrder([$this->comboLine($picks)]);

        // Two drinks in a one-drink slot, three sauces in a two-sauce slot
        $line([['option_id' => $this->option($this->pepsi)], ['option_id' => $this->option($this->fanta)]])
            ->assertStatus(422)->assertJsonPath('code', 'combo_slot_limit')->assertJsonValidationErrors('items.0.combo');
        $line([['option_id' => $this->option($this->hotSauce), 'quantity' => 3]])->assertStatus(422)->assertJsonPath('code', 'combo_slot_limit');
        // An option of another combo (or none at all)
        $line([['option_id' => (string) \Illuminate\Support\Str::uuid()]])->assertStatus(422)->assertJsonPath('code', 'combo_option_invalid');
        // A required slot without a default, left out
        ComboSlotOptionDefaults::clear($combo, 'Ichimlik');
        $line([])->assertStatus(422)->assertJsonPath('code', 'combo_slot_required')->assertJsonPath('data.reason', 'combo_slot_required');
        $line([['option_id' => $this->option($this->fanta)]])->assertCreated();
        // A plain dish takes no picks
        $this->counterOrder([['variant_id' => $this->pepsi->uuid, 'quantity' => 1, 'combo' => [['option_id' => $this->option($this->fanta)]]]])
            ->assertStatus(422)->assertJsonPath('code', 'combo_option_invalid');
        $this->assertSame(1, Order::count());
    }

    public function test_an_unavailable_option_is_refused_and_an_empty_required_slot_closes_the_combo(): void
    {
        $this->makeCombo();
        $stop = fn ($variant) => StopListEntry::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'subject_type' => 'variant', 'subject_id' => $variant->id, 'reason' => 'Tugadi']);

        $stop($this->pepsi);
        // The default drink is gone: a stale pick of it is refused with a clear code, the other drinks still sell
        $this->counterOrder([$this->comboLine([['option_id' => $this->option($this->pepsi)]])])
            ->assertStatus(422)->assertJsonPath('code', 'combo_option_unavailable');
        $this->counterOrder([$this->comboLine([['option_id' => $this->option($this->fanta)]])])->assertCreated();

        // The POS catalog marks the option, the combo stays open
        $catalog = collect($this->tillAs('GET', '/catalog?channel=pos_takeaway&branch_id=' . $this->branch->id)->assertOk()->json('data.products'))->keyBy('name');
        $drinks = collect($catalog['Lavash kombo']['combo']['slots'][1]['options'])->keyBy('name');
        $this->assertFalse($drinks['Pepsi']['is_available']);
        $this->assertTrue($drinks['Fanta']['is_available']);
        $this->assertTrue($catalog['Lavash kombo']['is_available']);

        // Every drink gone (stop-list, the branch switch, off the menu): the combo closes on the till and in Telegram
        $stop($this->fanta);
        $this->ayran->product->forceFill(['status' => 'inactive'])->save();
        $catalog = collect($this->tillAs('GET', '/catalog?channel=pos_takeaway&branch_id=' . $this->branch->id)->assertOk()->json('data.products'))->keyBy('name');
        $this->assertFalse($catalog['Lavash kombo']['is_available']);
        $availability = $this->getJson('/api/v1/catalog/availability?store_id=' . $this->store->id)->assertOk()->json('data');
        $branch = collect($availability['branches'] ?? $availability)->firstWhere('branch_id', $this->branch->id);
        $this->assertContains($this->comboProduct->id, $branch['stopped']['products']);
        $this->counterOrder([$this->comboLine([['option_id' => $this->option($this->fanta)]])])->assertStatus(422);
        $this->order([$this->comboLine()])->assertStatus(422);

        // The drink is back: so is the combo
        StopListEntry::query()->delete();
        $this->counterOrder([$this->comboLine()])->assertCreated();
    }

    public function test_each_part_goes_to_its_own_station_and_the_combo_is_ready_when_all_are(): void
    {
        $this->makeCombo();
        $cook = $this->makeCook();
        $uuid = $this->counterOrder([
            $this->comboLine([['option_id' => $this->option($this->chickenLavash)], ['option_id' => $this->option($this->hotSauce)]]),
            ['variant_id' => $this->tea->uuid, 'quantity' => 1],
        ])->assertCreated()->json('data.uuid');
        $order = Order::where('uuid', $uuid)->firstOrFail();

        $combo = OrderItem::where('order_id', $order->id)->whereNotNull('combo_parts')->firstOrFail();
        $parts = $combo->comboChildren()->get();
        $this->assertSame(['Tovuq lavash', 'Pepsi', 'Achchiq sous'], $parts->pluck('product_name')->all());
        // Each part on its dish's station, sent with the combo; the combo line itself goes to no station
        $this->assertSame([1000 + $this->chickenLavash->product_id, 1000 + $this->pepsi->product_id, 1000 + $this->hotSauce->product_id], $parts->pluck('station_id')->all());
        $this->assertTrue($parts->every(fn (OrderItem $part) => $part->sent_at !== null && $part->kitchen_status->value === 'sent'));
        $this->assertNull($combo->station_id);
        $this->assertSame($parts->pluck('id')->all(), array_column($combo->combo_parts, 'item_id'));

        // The kitchen ticket shows the parts with the combo's name, never the combo line
        $ticket = collect($this->actingAs($cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertOk()->json('data'))->firstWhere('uuid', $uuid);
        $this->assertSame(['Tovuq lavash', 'Pepsi', 'Achchiq sous', 'Choy'], array_column($ticket['items'], 'product_name'));
        $this->assertSame('Lavash kombo', $ticket['items'][0]['combo']['name']);
        $this->assertSame((int) $combo->id, $ticket['items'][0]['combo']['item_id']);
        $this->assertSame(4, $ticket['items_total']);

        // Bump the parts one by one: the combo line is ready with the last one, a recall takes it back
        foreach ($parts->take(2) as $part) {
            $this->actingAs($cook, 'sanctum')->postJson("/api/v1/staff/orders/{$uuid}/items/{$part->id}/ready")->assertOk();
        }
        $this->assertSame('sent', $combo->refresh()->kitchen_status->value);
        $this->actingAs($cook, 'sanctum')->postJson("/api/v1/staff/orders/{$uuid}/items/{$parts[2]->id}/ready")->assertOk();
        $this->assertSame('ready', $combo->refresh()->kitchen_status->value);
        $this->assertNotNull($combo->ready_at);
        $this->actingAs($cook, 'sanctum')->postJson("/api/v1/staff/orders/{$uuid}/items/{$parts[0]->id}/recall")->assertOk();
        $this->assertSame('sent', $combo->refresh()->kitchen_status->value);
        $this->actingAs($cook, 'sanctum')->postJson("/api/v1/staff/orders/{$uuid}/items/{$parts[0]->id}/ready")->assertOk();

        // The tea too: the takeaway is ready (every line of the order, the combo by its parts)
        $tea = OrderItem::where('order_id', $order->id)->whereNull('combo_parts')->firstOrFail();
        $this->actingAs($cook, 'sanctum')->postJson("/api/v1/staff/orders/{$uuid}/items/{$tea->id}/ready")->assertOk();
        $this->assertSame('ready', $order->refresh()->status->value);
    }

    public function test_voiding_a_combo_voids_its_parts_and_a_part_is_never_voided_alone(): void
    {
        $this->makeCombo();
        $response = $this->counterOrder([$this->comboLine()])->assertCreated();
        $order = Order::where('uuid', $response->json('data.uuid'))->firstOrFail();
        $combo = OrderItem::where('order_id', $order->id)->firstOrFail();
        $part = $combo->comboChildren()->firstOrFail();

        // A part's kitchen line is not a line of the order
        $this->tillAs('POST', "/pos/orders/{$order->uuid}/items/{$part->id}/void", ['version' => (int) $order->version, 'reason' => 'Xato'])->assertNotFound();

        $this->tillAs('POST', "/pos/orders/{$order->uuid}/items/{$combo->id}/void", [
            'version' => (int) $order->refresh()->version, 'reason' => 'Mijoz voz kechdi', 'approval_token' => $this->managerApproval('orders.void_item'),
        ])->assertOk();
        $this->assertSame('void', $combo->refresh()->kitchen_status->value);
        $this->assertTrue($combo->comboChildren()->get()->every(fn (OrderItem $item) => $item->kitchen_status->value === 'void' && $item->void_reason === 'Mijoz voz kechdi'));
        $this->assertSame('0.00', \App\Support\Money::of($order->refresh()->subtotal)->toString());
    }

    public function test_the_receipt_preview_and_the_fiscal_mode_setting(): void
    {
        $combo = $this->makeCombo();

        $preview = $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/combos/{$combo->id}/preview", [
            'picks' => [['option_id' => $this->option($this->fanta)], ['option_id' => $this->option($this->hotSauce)]],
        ])->assertOk()->json('data');
        $this->assertSame('45000.00', $preview['price']);
        $this->assertSame(['Asosiy — Lavash (Katta), Mol go\'shtli', 'Ichimlik — Fanta (0,4 l)', 'Sous — Achchiq sous (Dona)'], array_column($preview['parts'], 'label'));
        $this->assertSame([['name' => 'Lavash kombo', 'mxik_code' => '10202001009000001', 'quantity' => 1, 'amount' => '45000.00']], $preview['fiscal']['single']);
        $this->assertSame(['34285.71', '8571.43', '2142.86'], array_column($preview['fiscal']['split'], 'amount'));
        $this->assertSame('single', $preview['fiscal_mode']);
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/combos/{$combo->id}/preview", [
            'picks' => [['option_id' => $this->option($this->pepsi)], ['option_id' => $this->option($this->fanta)]],
        ])->assertStatus(422)->assertJsonPath('code', 'combo_slot_limit');

        // The owner switches the store to receipts by parts (audited with the other fiscal settings)
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/fiscal/settings', ['fiscal_settings' => ['combo_fiscal_mode' => 'split']])
            ->assertOk()->assertJsonPath('data.fiscal_settings.combo_fiscal_mode', 'split');
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/fiscal/settings', ['fiscal_settings' => ['combo_fiscal_mode' => 'parts']])->assertStatus(422);
        $this->assertTrue(AuditLog::where('action', 'settings.fiscal_changed')->exists());
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/combos')->assertOk()->assertJsonPath('data.fiscal_mode', 'split');
    }

    private function managerApproval(string $permission): string
    {
        return (string) $this->tillAs('POST', '/pos/approvals', ['permission' => $permission, 'user_id' => $this->manager->id, 'pin' => self::MANAGER_PIN])
            ->assertCreated()->json('data.token');
    }
}

/**
 * Test helper: takes the default off a slot's options.
 */
final class ComboSlotOptionDefaults
{
    public static function clear(Combo $combo, string $slot): void
    {
        $slotId = $combo->slots()->where('name', $slot)->value('id');
        \App\Models\ComboSlotOption::query()->where('combo_slot_id', $slotId)->update(['is_default' => false]);
    }
}
