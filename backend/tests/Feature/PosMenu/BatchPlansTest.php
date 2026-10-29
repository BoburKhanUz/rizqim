<?php

namespace Tests\Feature\PosMenu;

use App\Events\Broadcast\MenuAvailabilityChangedBroadcast;
use App\Models\AuditLog;
use App\Models\MenuBatch;
use App\Models\MenuBatchPlan;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Feature\PosBilling\PosBillingFixtures;
use Tests\TestCase;

/**
 * P6-15 "keyingi partiya HH:MM": planning the next batch of a sold-out dish, where it is shown (public availability, the
 * POS catalog, the bootstrap), what finishes it (opening the batch), what hides it (time passed, canceled), the module
 * gate and the access rules.
 */
class BatchPlansTest extends TestCase
{
    use PosBillingFixtures;
    use PosMenuModules {
        PosMenuModules::enableMenuModules as enableModules;
    }
    use RefreshDatabase;

    protected Product $osh;

    protected ProductVariant $portion;

    protected string $managerToken;

    protected function setUp(): void
    {
        parent::setUp();

        // 11:00 in Tashkent, a fixed moment so "12:30" is ahead (before the terminal sessions, which expire)
        Carbon::setTestNow(Carbon::parse('2026-11-14 06:00:00', 'UTC'));
        $this->setUpPosBilling();
        $this->osh = $this->makeProduct('Osh', ['batch_tracked' => true]);
        $this->portion = $this->makeVariant($this->osh, '55000', ['name' => '1 porsiya']);
        // One staff session per till: the manager's is the one used here
        $this->managerToken = $this->pinSession($this->till, $this->manager, self::MANAGER_PIN);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    protected function availability(): array
    {
        $branches = $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/catalog/availability?store_id={$this->store->id}")->assertOk()->json('data.branches');

        return collect($branches)->firstWhere('branch_id', $this->branch->id);
    }

    /** @return array<string, mixed>|null the dish as the till's catalog shows it */
    protected function tillDish(string $name = 'Osh'): ?array
    {
        $this->app['auth']->forgetGuards();

        return collect($this->pos('GET', '/catalog?channel=pos_hall', [], $this->managerToken)->assertOk()->json('data.products'))->firstWhere('name', $name);
    }

    protected function plan(array $data = [], ?string $token = null)
    {
        $this->app['auth']->forgetGuards();
        $request = $token !== null ? $this->withBearer($token) : $this->actingAs($this->owner, 'sanctum');

        return $request->postJson('/api/v1/owner/batch-plans', $data + ['branch_id' => $this->branch->id, 'product_id' => $this->osh->id, 'time' => '12:30']);
    }

    protected function openBatch(string $quantity = '10')
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/batches', [
            'branch_id' => $this->branch->id, 'product_id' => $this->osh->id, 'quantity' => $quantity,
        ]);
    }

    public function test_a_planned_batch_is_shown_beside_tugadi_and_finished_by_opening_the_batch(): void
    {
        Event::fake([MenuAvailabilityChangedBroadcast::class]);

        $response = $this->plan(['quantity' => '12', 'label' => '2-qozon'])->assertCreated();
        $response->assertJsonPath('data.time', '12:30')->assertJsonPath('data.status', 'planned')->assertJsonPath('data.quantity', 12)
            ->assertJsonPath('data.product_name', 'Osh')->assertJsonPath('data.overdue', false);
        Event::assertDispatched(MenuAvailabilityChangedBroadcast::class, fn ($event) => $event->reason === 'batch_plan' && $event->items === []);
        $this->assertTrue(AuditLog::where('action', 'menu.batch_planned')->exists());

        // Public availability
        $next = $this->availability()['next_batches'];
        $this->assertCount(1, $next);
        $this->assertSame('12:30', $next[0]['time']);
        $this->assertSame($this->osh->id, $next[0]['product_id']);
        $this->assertSame('2026-11-14T07:30:00.000000Z', $next[0]['planned_at']);
        $this->assertContains($this->portion->id, $this->availability()['sold_out']['variants']);

        // The POS catalog: on the sold-out dish and its variant
        $osh = $this->tillDish();
        $this->assertSame('12:30', $osh['next_batch']['time']);
        $this->assertSame('12:30', $osh['variants'][0]['next_batch']['time']);
        $this->assertSame('sold_out', $osh['unavailable_reason']);

        // The bootstrap
        $this->app['auth']->forgetGuards();
        $this->assertSame('12:30', $this->pos('GET', '/bootstrap', [], $this->managerToken)->assertOk()->json('data.next_batches.0.time'));

        // The cook opens the batch: the plan is done and the time disappears
        $this->openBatch()->assertCreated();
        $this->assertSame([], $this->availability()['next_batches']);
        $plan = MenuBatchPlan::firstOrFail();
        $this->assertSame('started', $plan->status);
        $this->assertSame(MenuBatch::firstOrFail()->id, (int) $plan->menu_batch_id);
        $osh = $this->tillDish();
        $this->assertNull($osh['next_batch']);
        $this->assertTrue($osh['is_available']);
    }

    public function test_a_plan_only_shows_while_the_dish_is_sold_out(): void
    {
        $this->openBatch()->assertCreated();
        $this->plan()->assertCreated();

        // Available now: the catalog carries no time on it (the plan still waits for its batch)
        $osh = $this->tillDish();
        $this->assertTrue($osh['is_available']);
        $this->assertNull($osh['next_batch']);
    }

    public function test_a_variant_plan_is_for_that_variant_and_a_whole_dish_plan_serves_all(): void
    {
        $small = $this->makeVariant($this->osh, '40000', ['name' => '0.7 porsiya', 'batch_consumption' => '0.7']);
        $this->plan(['product_variant_id' => $small->id, 'time' => '13:00'])->assertCreated();

        $osh = $this->tillDish();
        $byName = collect($osh['variants'])->keyBy('name');
        $this->assertSame('13:00', $byName['0.7 porsiya']['next_batch']['time']);
        $this->assertNull($byName['1 porsiya']['next_batch']);
        $this->plan(['time' => '15:00'])->assertCreated();
        $byName = collect($this->tillDish()['variants'])->keyBy('name');
        $this->assertSame('15:00', $byName['1 porsiya']['next_batch']['time']);
        $this->assertSame('13:00', $byName['0.7 porsiya']['next_batch']['time']);

        // Opening a batch of that variant finishes only the earliest plan that matches it
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/batches', ['branch_id' => $this->branch->id, 'product_id' => $this->osh->id, 'product_variant_id' => $small->id, 'quantity' => 5])->assertCreated();
        $this->assertSame(['started', 'planned'], MenuBatchPlan::orderBy('planned_at')->pluck('status')->all());
    }

    public function test_times_in_the_past_or_too_far_ahead_are_refused(): void
    {
        $this->plan(['time' => '10:00'])->assertStatus(422)->assertJsonValidationErrors('time');
        $this->plan(['time' => '25:99'])->assertStatus(422)->assertJsonValidationErrors('time');
        $this->plan(['time' => null])->assertStatus(422);
        $this->plan(['time' => null, 'planned_at' => now()->addHours(40)->toIso8601String()])->assertStatus(422)->assertJsonValidationErrors('time');
        $this->plan(['time' => null, 'planned_at' => now()->addHours(30)->toIso8601String()])->assertCreated();
        $this->plan(['quantity' => '-2'])->assertStatus(422);
        $this->assertSame(1, MenuBatchPlan::count());
    }

    public function test_a_stale_plan_is_hidden_from_guests_but_listed_as_overdue_and_a_canceled_one_is_gone(): void
    {
        $plan = $this->plan()->assertCreated()->json('data');

        // 12:45: the plan's time has passed without a batch
        Carbon::setTestNow(Carbon::parse('2026-11-14 07:45:00', 'UTC'));
        $this->assertSame([], $this->availability()['next_batches']);
        $listed = $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/batch-plans?branch_id={$this->branch->id}")->assertOk()->json('data.plans');
        $this->assertTrue($listed[0]['overdue']);

        Carbon::setTestNow(Carbon::parse('2026-11-14 06:30:00', 'UTC'));
        $this->assertCount(1, $this->availability()['next_batches']);
        $this->actingAs($this->owner, 'sanctum')->deleteJson("/api/v1/owner/batch-plans/{$plan['id']}")->assertOk();
        $this->assertSame([], $this->availability()['next_batches']);
        $this->assertSame('canceled', MenuBatchPlan::firstOrFail()->status);
        $this->assertTrue(AuditLog::where('action', 'menu.batch_plan_canceled')->exists());
        $this->actingAs($this->owner, 'sanctum')->deleteJson('/api/v1/owner/batch-plans/999999')->assertNotFound();
    }

    public function test_the_batches_module_gates_the_plans_and_the_cook_plans_on_the_terminal(): void
    {
        $this->enableModules(modules: ['batches' => false]);
        $this->plan()->assertForbidden()->assertJsonPath('code', 'module_disabled')->assertJsonPath('data.module', 'batches');
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/batch-plans?branch_id={$this->branch->id}")->assertForbidden();
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/batch-yield?branch_id={$this->branch->id}")->assertForbidden();
        $this->assertSame(0, MenuBatchPlan::count());
        // The bootstrap shows no plan of a branch without the module
        $this->app['auth']->forgetGuards();
        $this->assertSame([], $this->pos('GET', '/bootstrap', [], $this->managerToken)->json('data.next_batches'));

        $this->enableModules(modules: ['batches' => true]);
        $this->plan([], $this->managerToken)->assertCreated();
        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/batch-plans?branch_id={$this->branch->id}")->assertOk()
            ->assertJsonPath('data.modules', ['batches' => true, 'batch_writeoff' => false]);
        $this->assertSame($this->manager->id, (int) MenuBatchPlan::firstOrFail()->created_by);

        // A cashier cannot (a new session on the till replaces the manager's); another store's dish is not found
        $cashierToken = $this->pinSession($this->till, $this->cashier);
        $this->plan([], $cashierToken)->assertForbidden();
        $foreign = Product::create(['store_id' => $this->makeStore('Boshqa')->id, 'category_id' => $this->osh->category_id, 'name' => 'Begona', 'slug' => 'begona-q', 'status' => 'active']);
        $this->plan(['product_id' => $foreign->id])->assertNotFound();
    }
}
