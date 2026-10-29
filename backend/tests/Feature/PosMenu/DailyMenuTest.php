<?php

namespace Tests\Feature\PosMenu;

use App\Domain\Pos\Setup\FormatProfile;
use App\Events\Broadcast\MenuAvailabilityChangedBroadcast;
use App\Models\AuditLog;
use App\Models\DailyMenu;
use App\Models\DailyMenuItem;
use App\Models\Product;
use App\Models\StopListEntry;
use App\Services\Catalog\MenuAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P6-15 "Bugungi menyu": the dated set of dishes of a branch. Module gate, saving and copying, the rotation flag, the
 * effect on availability, checkout (Telegram and POS) and the bootstrap, business-day dates, branch scoping, audit.
 */
class DailyMenuTest extends TestCase
{
    use PosMenuFixtures;
    use RefreshDatabase;

    protected Product $lagman;

    protected Product $mastava;

    protected Product $blackTea;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosHall();
        $this->enableMenuModules();
        $this->lagman = $this->dish('Lag\'mon', '28000', true);
        $this->mastava = $this->dish('Mastava', '22000', true);
        $this->blackTea = $this->dish('Qora choy', '3000');
    }

    protected function today(): string
    {
        return app(\App\Domain\Pos\Menu\DailyMenuService::class)->today($this->branch);
    }

    /** @return array<string, mixed> */
    protected function availability(?int $branchId = null): array
    {
        $branches = $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/catalog/availability?store_id={$this->store->id}")->assertOk()->json('data.branches');

        return collect($branches)->firstWhere('branch_id', $branchId ?? $this->branch->id);
    }

    protected function save(array $productIds, ?string $date = null, ?\App\Models\User $as = null, array $extra = [])
    {
        return $this->actingAs($as ?? $this->owner, 'sanctum')->putJson('/api/v1/owner/daily-menu', array_filter([
            'branch_id' => $this->branch->id,
            'date' => $date,
            'product_ids' => $productIds,
        ] + $extra, fn ($value) => $value !== null));
    }

    public function test_the_module_gates_the_endpoints(): void
    {
        // A plain choyxona branch has no daily menu
        $this->enableMenuModules(modules: []);

        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/daily-menu?branch_id={$this->branch->id}")
            ->assertForbidden()->assertJsonPath('code', 'module_disabled')->assertJsonPath('data.module', 'daily_menu');
        $this->save([$this->lagman->id])->assertForbidden()->assertJsonPath('code', 'module_disabled');
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/daily-menu/rotation', ['product_ids' => []])->assertForbidden();
        $this->assertSame(0, DailyMenu::count());

        // The stolovaya profile has it by default
        $this->enableMenuModules(modules: [], profile: FormatProfile::STOLOVAYA);
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/daily-menu?branch_id={$this->branch->id}")->assertOk();
    }

    public function test_the_owner_sets_todays_menu_and_the_dishes_become_rotating(): void
    {
        Event::fake([MenuAvailabilityChangedBroadcast::class]);

        $response = $this->save([$this->lagman->id])->assertOk();
        $response->assertJsonPath('data.date', $this->today())
            ->assertJsonPath('data.set', true)
            ->assertJsonPath('data.enforced', true)
            ->assertJsonPath('data.product_ids', [$this->lagman->id]);

        $products = collect($response->json('data.products'));
        $this->assertTrue($products->firstWhere('id', $this->lagman->id)['on_menu']);
        $this->assertFalse($products->firstWhere('id', $this->mastava->id)['on_menu']);
        $this->assertTrue($products->firstWhere('id', $this->mastava->id)['daily_rotation']);
        $this->assertFalse($products->firstWhere('id', $this->blackTea->id)['daily_rotation']);

        // A dish put on a menu rotates from then on
        $this->save([$this->lagman->id, $this->blackTea->id])->assertOk();
        $this->assertTrue($this->blackTea->fresh()->daily_rotation);
        $this->assertSame([$this->lagman->id, $this->blackTea->id], DailyMenuItem::orderBy('sort_order')->pluck('product_id')->all());

        $log = AuditLog::where('action', 'menu.daily_menu_saved')->orderBy('id')->get();
        $this->assertCount(2, $log);
        $this->assertSame($this->owner->id, (int) $log[0]->user_id);
        $this->assertSame($this->branch->id, (int) $log[0]->branch_id);
        // Today's menu changed: the menus are told which dishes came on and went off
        Event::assertDispatched(MenuAvailabilityChangedBroadcast::class, fn ($event) => $event->reason === 'daily_menu' && $event->branchId === $this->branch->id);
    }

    public function test_dishes_off_the_menu_are_stopped_for_every_channel(): void
    {
        $this->save([$this->lagman->id])->assertOk();

        $availability = $this->availability();
        $this->assertContains($this->mastava->id, $availability['stopped']['products']);
        $this->assertNotContains($this->lagman->id, $availability['stopped']['products']);
        $this->assertNotContains($this->blackTea->id, $availability['stopped']['products']);
        $entry = collect($availability['stop_list'])->firstWhere('subject_id', $this->mastava->id);
        $this->assertSame("Bugungi menyuda yo'q", $entry['reason']);

        // Telegram checkout
        $this->order([['variant_id' => $this->mastava->variants->first()->uuid, 'quantity' => 1]])->assertStatus(422);
        $this->order([['variant_id' => $this->lagman->variants->first()->uuid, 'quantity' => 1]])->assertCreated();
        $this->order([['variant_id' => $this->blackTea->variants->first()->uuid, 'quantity' => 1]])->assertCreated();

        // The till: a round with the off-menu dish is refused with the reason
        $opened = $this->openPos($this->waiter, $this->room, 2, ['items' => [$this->line($this->mastava->variants->first())]]);
        $opened->assertStatus(422);
        $this->assertStringContainsString("Bugungi menyuda yo'q", json_encode($opened->json(), JSON_UNESCAPED_UNICODE));
        $this->openPos($this->waiter, $this->room, 2, ['items' => [$this->line($this->lagman->variants->first())]])->assertCreated();

        // The POS catalog says the same
        $catalog = $this->actingAs($this->waiter, 'sanctum')->getJson('/api/v1/pos/catalog?channel=pos_hall&branch_id=' . $this->branch->id)->assertOk();
        $byName = collect($catalog->json('data.products'))->keyBy('name');
        $this->assertFalse($byName['Mastava']['is_available']);
        $this->assertSame('stopped', $byName['Mastava']['unavailable_reason']);
        $this->assertTrue($byName["Lag'mon"]['is_available']);
    }

    public function test_a_day_without_a_menu_and_a_branch_without_the_module_sell_everything(): void
    {
        // No menu row for today: nothing is stopped (a forgotten menu never stops the till)
        $this->assertNotContains($this->mastava->id, $this->availability()['stopped']['products']);
        $this->order([['variant_id' => $this->mastava->variants->first()->uuid, 'quantity' => 1]])->assertCreated();

        // A menu exists, but the owner switched the module off: not enforced
        $this->save([$this->lagman->id])->assertOk();
        $this->assertContains($this->mastava->id, $this->availability()['stopped']['products']);
        $this->enableMenuModules(modules: []);
        $this->assertNotContains($this->mastava->id, $this->availability()['stopped']['products']);
        $this->order([['variant_id' => $this->mastava->variants->first()->uuid, 'quantity' => 1]])->assertCreated();
    }

    public function test_another_branch_keeps_its_own_menu_and_a_stop_list_entry_is_not_overwritten(): void
    {
        $this->enableMenuModules($this->otherBranch);
        $this->save([$this->lagman->id])->assertOk();

        // The other branch never made a menu
        $this->assertContains($this->mastava->id, $this->availability()['stopped']['products']);
        $this->assertNotContains($this->mastava->id, $this->availability($this->otherBranch->id)['stopped']['products']);

        // An existing stop-list reason stays
        StopListEntry::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'subject_type' => 'product',
            'subject_id' => $this->mastava->id, 'reason' => 'Go\'sht yo\'q',
        ]);
        $entries = collect($this->availability()['stop_list'])->where('subject_id', $this->mastava->id);
        $this->assertSame(['Go\'sht yo\'q'], $entries->pluck('reason')->all());
    }

    public function test_yesterdays_menu_is_copied_and_dead_dishes_are_left_out(): void
    {
        $yesterday = Carbon::parse($this->today())->subDay()->format('Y-m-d');
        $menu = DailyMenu::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'menu_date' => $yesterday]);
        foreach ([$this->lagman, $this->mastava, $this->blackTea] as $position => $product) {
            DailyMenuItem::create(['daily_menu_id' => $menu->id, 'product_id' => $product->id, 'sort_order' => $position]);
        }
        $this->mastava->forceFill(['status' => 'inactive'])->save();

        $before = $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/daily-menu?branch_id={$this->branch->id}")->assertOk();
        $before->assertJsonPath('data.set', false)->assertJsonPath('data.previous_date', $yesterday);

        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/daily-menu/copy', ['branch_id' => $this->branch->id])
            ->assertOk()->assertJsonPath('data.set', true)->assertJsonPath('data.product_ids', [$this->lagman->id, $this->blackTea->id]);

        // Naming the day explicitly works too; nothing to copy from a day without a menu
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/daily-menu/copy', ['branch_id' => $this->branch->id, 'from_date' => '2020-01-01'])
            ->assertStatus(422)->assertJsonValidationErrors('from_date');
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/daily-menu/copy', ['branch_id' => $this->branch->id, 'from_date' => $this->today()])
            ->assertStatus(422);
    }

    public function test_a_copy_with_no_earlier_menu_is_refused(): void
    {
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/daily-menu/copy', ['branch_id' => $this->branch->id])
            ->assertStatus(422)->assertJsonValidationErrors('from_date');
    }

    public function test_dates_follow_the_business_day_and_stay_within_30_days(): void
    {
        // 02:00 on 15 Nov in Tashkent is still the 14th (the day starts at 05:00)
        Carbon::setTestNow(Carbon::parse('2026-11-14 21:00:00', 'UTC'));
        $this->assertSame('2026-11-14', $this->today());
        $this->save([$this->lagman->id])->assertOk()->assertJsonPath('data.date', '2026-11-14');

        $this->save([$this->lagman->id], '2026-11-13')->assertStatus(422)->assertJsonValidationErrors('date');
        $this->save([$this->lagman->id], '2026-12-30')->assertStatus(422)->assertJsonValidationErrors('date');
        $this->save([$this->lagman->id], '2026-11-20')->assertOk();

        // The 14th's menu is not today's menu after 05:00 on the 15th
        Carbon::setTestNow(Carbon::parse('2026-11-15 01:00:00', 'UTC'));
        $this->assertSame('2026-11-15', $this->today());
        $this->assertNotContains($this->mastava->id, $this->availability()['stopped']['products']);

        Carbon::setTestNow();
    }

    public function test_only_the_stores_dishes_are_accepted_and_the_input_is_checked(): void
    {
        $foreign = Product::create([
            'store_id' => $this->makeStore('Boshqa oshxona')->id,
            'category_id' => $this->lagman->category_id,
            'name' => 'Begona', 'slug' => 'begona-x', 'status' => 'active',
        ]);

        $this->save([$foreign->id])->assertStatus(422)->assertJsonValidationErrors('product_ids');
        $this->save([$this->lagman->id, $this->lagman->id])->assertStatus(422);
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/daily-menu', ['branch_id' => $this->branch->id])->assertStatus(422);
        $this->assertSame(0, DailyMenu::count());
    }

    public function test_an_empty_menu_stops_every_rotating_dish_and_the_rotation_can_be_edited(): void
    {
        $this->save([])->assertOk()->assertJsonPath('data.set', true)->assertJsonPath('data.product_ids', []);
        $stopped = $this->availability()['stopped']['products'];
        $this->assertEqualsCanonicalizing([$this->lagman->id, $this->mastava->id], $stopped);

        // The owner makes Mastava a permanent dish again
        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/daily-menu/rotation', ['product_ids' => [$this->lagman->id]])
            ->assertOk()->assertJsonPath('data.product_ids', [$this->lagman->id]);
        $this->assertFalse($this->mastava->fresh()->daily_rotation);
        $this->assertSame([$this->lagman->id], $this->availability()['stopped']['products']);
        $this->assertTrue(AuditLog::where('action', 'menu.daily_rotation_saved')->exists());

        $this->actingAs($this->owner, 'sanctum')->putJson('/api/v1/owner/daily-menu/rotation', ['product_ids' => [999999]])->assertStatus(422);
    }

    public function test_access_is_by_permission_and_branch(): void
    {
        // A cook has no menu.manage; a branch-bound manager works on their own branch only
        $cook = $this->makeCook();
        $this->save([$this->lagman->id], null, $cook)->assertForbidden();
        $this->enableMenuModules($this->otherBranch);
        $this->actingAs($this->manager, 'sanctum')->putJson('/api/v1/owner/daily-menu', ['branch_id' => $this->otherBranch->id, 'product_ids' => []])->assertForbidden();
        $this->actingAs($this->manager, 'sanctum')->putJson('/api/v1/owner/daily-menu', ['product_ids' => [$this->lagman->id]])->assertOk();
        $this->assertSame($this->branch->id, (int) DailyMenu::firstOrFail()->branch_id);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/owner/daily-menu')->assertUnauthorized();
    }

    public function test_the_bootstrap_carries_the_daily_menu_section(): void
    {
        $bootstrap = fn () => $this->actingAs($this->waiter, 'sanctum')->getJson("/api/v1/pos/bootstrap?branch_id={$this->branch->id}")->assertOk()->json('data');

        $before = $bootstrap();
        $this->assertSame(['business_date' => $this->today(), 'enforced' => true, 'set' => false, 'product_ids' => []], $before['daily_menu']);

        $this->save([$this->lagman->id])->assertOk();
        $this->assertSame(['business_date' => $this->today(), 'enforced' => true, 'set' => true, 'product_ids' => [$this->lagman->id]], $bootstrap()['daily_menu']);

        $this->enableMenuModules(modules: []);
        $this->assertNull($bootstrap()['daily_menu']);
    }
}
