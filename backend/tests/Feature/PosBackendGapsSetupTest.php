<?php

namespace Tests\Feature;

use App\Domain\Pos\Hall\BusinessCalendar;
use App\Enums\DayType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RoomTariffCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P3-26 (setup gaps): the `quick_add` product flag and the bootstrap's `quick_items`, a product's fiscal fields alone
 * (PATCH /owner/products/{id}/fiscal), the typed POS menu (GET /pos/catalog), one holiday list per branch (the room
 * tariff calendar and the POS settings are views of branches.pos_settings.holidays), and a validation error's reason.
 */
class PosBackendGapsSetupTest extends TestCase
{
    use PosHallFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosHall();
    }

    public function test_quick_items_list_the_quick_add_products_then_the_choy_non_items(): void
    {
        $this->assertSame([], $this->pos($this->cashier, 'GET', '/pos/bootstrap')->assertOk()->json('data.quick_items'));

        $this->choyNonRule(null, '1', '1');
        Product::whereKey($this->shashlik->product_id)->update(['quick_add' => true]);
        Product::whereKey($this->tea->product_id)->update(['quick_add' => true]);
        // An inactive product is not offered
        $hidden = $this->makeVariant($this->makeProduct('Kompot', ['quick_add' => true, 'status' => 'inactive']), '6000');

        $items = $this->pos($this->cashier, 'GET', '/pos/bootstrap')->assertOk()->json('data.quick_items');
        $this->assertSame([
            [$this->tea->product_id, $this->tea->uuid, 'quick_add'],
            [$this->shashlik->product_id, $this->shashlik->uuid, 'quick_add'],
            [$this->bread->product_id, $this->bread->uuid, 'auto_guest'],
        ], array_map(fn (array $item) => [$item['product_id'], $item['variant_uuid'], $item['source']], $items));
        $this->assertSame('Choy', $items[0]['name']);
        $this->assertSame('Choynak', $items[0]['variant_name']);
        $this->assertNull($items[0]['quantity_per_guest']);
        $this->assertSame('1.000', $items[2]['quantity_per_guest']);
        $this->assertNotContains($hidden->uuid, array_column($items, 'variant_uuid'));
    }

    public function test_the_owner_marks_a_product_quick_add_and_edits_only_its_fiscal_fields(): void
    {
        $variants = json_encode([['name' => 'Choynak', 'price' => 5000]]);
        $created = $this->pos($this->owner, 'POST', '/owner/products', [
            'name' => "Ko'k choy", 'category_id' => $this->product->category_id, 'is_active' => true, 'variants' => $variants, 'quick_add' => true,
        ])->assertOk()->json('data');
        $this->assertTrue(Product::findOrFail($created['id'])->quick_add);

        // An update leaving it out keeps it; false clears it
        $variantId = $created['variants'][0]['id'];
        $this->pos($this->owner, 'POST', "/owner/products/{$created['id']}", ['name' => "Ko'k choy", 'category_id' => $this->product->category_id, 'variants' => json_encode([['id' => $variantId, 'name' => 'Choynak', 'price' => 5000]])])->assertOk();
        $this->assertTrue(Product::findOrFail($created['id'])->quick_add);
        $this->pos($this->owner, 'POST', "/owner/products/{$created['id']}", ['name' => "Ko'k choy", 'category_id' => $this->product->category_id, 'quick_add' => false, 'variants' => json_encode([['id' => $variantId, 'name' => 'Choynak', 'price' => 5000]])])->assertOk();
        $this->assertFalse(Product::findOrFail($created['id'])->quick_add);

        // Fiscal fields only: the rest of the product is untouched
        $this->pos($this->owner, 'PATCH', "/owner/products/{$created['id']}/fiscal", [
            'mxik_code' => '10202002007000001', 'package_code' => '1500211', 'vat_rate' => '12', 'kind' => 'dish',
            'variants' => [['id' => $variantId, 'unit_label' => 'choynak']],
        ])->assertOk()
            ->assertJsonPath('data.mxik_code', '10202002007000001')
            ->assertJsonPath('data.vat_rate', '12.00')
            ->assertJsonPath('data.variants.0.unit_label', 'choynak')
            ->assertJsonPath('data.name', "Ko'k choy");
        $this->assertSame('5000.00', (string) ProductVariant::findOrFail($variantId)->price);

        // Left out: kept; empty: cleared
        $this->pos($this->owner, 'PATCH', "/owner/products/{$created['id']}/fiscal", ['vat_rate' => '', 'variants' => [['id' => $variantId, 'unit_label' => '']]])->assertOk()
            ->assertJsonPath('data.mxik_code', '10202002007000001')
            ->assertJsonPath('data.vat_rate', null)
            ->assertJsonPath('data.variants.0.unit_label', null);

        // Invalid codes, another product's variant, another store's product, no menu.manage
        $this->pos($this->owner, 'PATCH', "/owner/products/{$created['id']}/fiscal", ['mxik_code' => '123'])->assertUnprocessable()
            ->assertJsonPath('errors.mxik_code.0', "MXIK kodi 17 ta raqamdan iborat bo'lishi kerak.")
            ->assertJsonPath('data.reason', 'validation');
        $this->pos($this->owner, 'PATCH', "/owner/products/{$created['id']}/fiscal", ['variants' => [['id' => $this->tea->id, 'unit_label' => 'x']]])->assertUnprocessable()->assertJsonValidationErrors(['variants.0.id']);
        $this->assertNull($this->tea->refresh()->unit_label);
        $foreign = $this->makeStore('Begona');
        $foreignOwner = $this->makeStaff(\App\Enums\UserRole::OWNER, null, $foreign);
        $this->pos($foreignOwner, 'PATCH', "/owner/products/{$created['id']}/fiscal", ['mxik_code' => '10202002007000002'])->assertNotFound();
        $this->pos($this->cashier, 'PATCH', "/owner/products/{$created['id']}/fiscal", ['mxik_code' => '10202002007000002'])->assertForbidden();
    }

    public function test_the_typed_pos_menu_is_the_same_as_the_catalog_channel(): void
    {
        Product::whereKey($this->tea->product_id)->update(['quick_add' => true]);
        $typed = $this->pos($this->cashier, 'GET', '/pos/catalog')->assertOk()->json('data');
        $legacy = $this->pos($this->cashier, 'GET', '/catalog?channel=pos_hall')->assertOk()->json('data');

        $this->assertSame('pos_hall', $typed['channel']);
        $this->assertSame(array_column($legacy['products'], 'id'), array_column($typed['products'], 'id'));
        $tea = collect($typed['products'])->firstWhere('id', $this->tea->product_id);
        $this->assertTrue($tea['quick_add']);
        $this->assertFalse($tea['batch_tracked']);
        $this->assertTrue(collect($typed['products'])->firstWhere('id', $this->plov->product_id)['batch_tracked']);
        $this->assertSame('pos_takeaway', $this->pos($this->cashier, 'GET', '/pos/catalog?channel=pos_takeaway')->assertOk()->json('data.channel'));
        $this->pos($this->cashier, 'GET', '/pos/catalog?channel=telegram')->assertUnprocessable();
    }

    public function test_one_holiday_list_behind_the_calendar_and_the_pos_settings(): void
    {
        // The calendar writes the branch's list; the POS settings and the bootstrap read the same one
        $this->pos($this->owner, 'PUT', '/owner/room-tariffs/calendar', ['branch_id' => $this->branch->id, 'weekend_days' => [7], 'holidays' => ['2026-12-31', '2026-03-21']])->assertOk()
            ->assertJsonPath('data.holidays', ['2026-03-21', '2026-12-31'])
            ->assertJsonPath('data.weekend_days', [7]);
        $this->assertNull(RoomTariffCalendar::where('branch_id', $this->branch->id)->value('holidays'));
        $this->pos($this->owner, 'GET', "/owner/branches/{$this->branch->id}/pos-settings")->assertOk()->assertJsonPath('data.pos_settings.holidays', ['2026-03-21', '2026-12-31']);
        $this->pos($this->cashier, 'GET', '/pos/bootstrap')->assertOk()->assertJsonPath('data.branch.pos_settings.holidays', ['2026-03-21', '2026-12-31']);

        // The POS settings change it; the calendar shows the change
        $this->pos($this->owner, 'PUT', "/owner/branches/{$this->branch->id}/pos-settings", ['pos_settings' => ['holidays' => ['2026-05-09']]])->assertOk();
        $this->pos($this->owner, 'GET', "/owner/room-tariffs?branch_id={$this->branch->id}")->assertOk()->assertJsonPath('data.calendar.holidays', ['2026-05-09']);

        $calendar = app(BusinessCalendar::class);
        $this->assertSame(DayType::HOLIDAY, $calendar->dayType($this->branch->refresh(), '2026-05-09'));
        $this->assertSame(DayType::WEEKDAY, $calendar->dayType($this->branch, '2026-03-21'), 'Saturday is not a weekend day any more');
        $this->assertSame(DayType::WEEKEND, $calendar->dayType($this->branch, '2026-03-22'));

        // The other settings stay as they were
        $this->pos($this->owner, 'PUT', "/owner/branches/{$this->branch->id}/pos-settings", ['pos_settings' => ['mode' => 'table']])->assertOk();
        $this->pos($this->owner, 'PUT', '/owner/room-tariffs/calendar', ['branch_id' => $this->branch->id, 'weekend_days' => [6, 7], 'holidays' => []])->assertOk();
        $this->pos($this->owner, 'GET', "/owner/branches/{$this->branch->id}/pos-settings")->assertOk()
            ->assertJsonPath('data.pos_settings.mode', 'table')
            ->assertJsonPath('data.pos_settings.holidays', []);
    }

    public function test_the_migration_merges_the_calendar_holidays_into_the_pos_settings(): void
    {
        $this->branch->forceFill(['pos_settings' => ['mode' => 'hybrid', 'holidays' => ['2026-01-01']]])->save();
        RoomTariffCalendar::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'weekend_days' => [6, 7], 'holidays' => ['2026-03-21', '2026-01-01']]);
        $migration = require database_path('migrations/2026_10_27_100300_move_room_calendar_holidays_to_pos_settings.php');

        $migration->up();
        $settings = json_decode((string) DB::table('branches')->where('id', $this->branch->id)->value('pos_settings'), true);
        $this->assertSame(['2026-01-01', '2026-03-21'], $settings['holidays']);
        $this->assertSame('hybrid', $settings['mode']);
        $this->assertNull(RoomTariffCalendar::where('branch_id', $this->branch->id)->value('holidays'));

        $migration->down();
        $this->assertSame(['2026-01-01', '2026-03-21'], RoomTariffCalendar::where('branch_id', $this->branch->id)->firstOrFail()->holidays);
    }
}
