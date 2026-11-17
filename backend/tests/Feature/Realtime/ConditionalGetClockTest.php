<?php

namespace Tests\Feature\Realtime;

use App\Domain\Pos\Shifts\BusinessDay;
use App\Enums\OrderStatus;
use App\Models\DiningTable;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosQueue\PosQueueFixtures;
use Tests\TestCase;

/**
 * Wave M2 follow-up: what the till's conditional GETs show by the clock alone ends their ETag at that moment
 * (ConditionalGet::validUntil), not only after the route's max-age (120 s): an order turning overdue and a booking
 * entering "reserved soon" (GET /pos/floor), the business-day rollover (GET /pos/queue, GET /pos/orders?scope=closed),
 * a pickup for later that stops waiting (GET /pos/orders), a stop that ends (GET /owner/ingredients). Each test takes
 * a tag shortly before the moment, gets a 304 before it and a 200 with the new body right after it, with no write.
 * The kitchen tickets' own moments: tests/Feature/Kds/KdsConditionalClockTest.
 */
class ConditionalGetClockTest extends TestCase
{
    use PosQueueFixtures;
    use RefreshDatabase;

    private DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosQueue('kafe');
        $this->table = DiningTable::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $this->hall->id, 'name' => 'Stol 1', 'kind' => 'table', 'seats' => 4]);
    }

    private function poll(string $uri, ?string $etag = null, ?string $token = null): TestResponse
    {
        $this->flushHeaders();
        $this->withBearer($token ?? $this->cashierToken);
        if ($etag !== null) {
            $this->withHeader('If-None-Match', $etag);
        }

        return $this->getJson('/api/v1' . $uri);
    }

    private function tagOf(TestResponse $response): string
    {
        $response->assertOk();
        $tag = $response->headers->get('ETag');
        $this->assertIsString($tag, 'a 200 carries an ETag');

        return $tag;
    }

    /**
     * @return array<string, mixed>
     */
    private function tableIn(TestResponse $response): array
    {
        return collect($response->json('data.halls'))->flatMap(fn ($hall) => $hall['tables'])->firstWhere('id', $this->table->id);
    }

    public function test_the_floor_ends_its_tag_when_an_order_turns_overdue(): void
    {
        $this->tillAs('POST', '/pos/orders', [
            'channel' => 'pos_hall', 'table_id' => $this->table->id, 'guests_count' => 2,
            'items' => [['variant_id' => $this->tea->uuid, 'quantity' => 1]], 'send' => true,
        ])->assertSuccessful();
        $hours = (int) $this->poll('/pos/floor')->json('data.overdue_after_hours');

        Carbon::setTestNow(now()->addHours($hours)->subSeconds(60));
        $first = $this->poll('/pos/floor');
        $this->assertSame('occupied', $this->tableIn($first)['status']);
        $tag = $this->tagOf($first);

        Carbon::setTestNow(now()->addSeconds(30));
        $this->poll('/pos/floor', $tag)->assertStatus(304);

        Carbon::setTestNow(now()->addSeconds(31));
        $response = $this->poll('/pos/floor', $tag);
        $response->assertOk();
        $this->assertSame('overdue', $this->tableIn($response)['status']);
    }

    public function test_the_floor_ends_its_tag_when_a_booking_enters_and_leaves_reserved_soon(): void
    {
        $startsAt = now()->addMinutes(61)->startOfSecond();
        DB::table('bookings')->insert([
            'uuid' => (string) Str::uuid(), 'store_id' => $this->store->id, 'branch_id' => $this->branch->id,
            'dining_table_id' => $this->table->id, 'starts_at' => $startsAt, 'ends_at' => $startsAt->copy()->addHours(2),
            'guests_planned' => 4, 'contact_name' => 'Aziz', 'status' => 'confirmed', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $first = $this->poll('/pos/floor');
        $this->assertSame('free', $this->tableIn($first)['status']);
        $tag = $this->tagOf($first);

        Carbon::setTestNow(now()->addSeconds(30));
        $this->poll('/pos/floor', $tag)->assertStatus(304);

        // An hour before it: reserved soon
        Carbon::setTestNow($startsAt->copy()->subMinutes(60)->addSecond());
        $response = $this->poll('/pos/floor', $tag);
        $response->assertOk();
        $this->assertSame('reserved_soon', $this->tableIn($response)['status']);

        // 30 minutes after its start (nobody came, nobody wrote): free again
        Carbon::setTestNow($startsAt->copy()->addMinutes(29));
        $tag = $this->tagOf($this->poll('/pos/floor'));
        Carbon::setTestNow(now()->addSeconds(30));
        $this->poll('/pos/floor', $tag)->assertStatus(304);
        Carbon::setTestNow($startsAt->copy()->addMinutes(30)->addSecond());
        $response = $this->poll('/pos/floor', $tag);
        $response->assertOk();
        $this->assertSame('free', $this->tableIn($response)['status']);
    }

    public function test_the_pickup_board_and_the_closed_list_end_their_tag_at_the_business_day_rollover(): void
    {
        // A minute before the branch's next day start (05:00 local by default)
        $rollover = Carbon::instance(BusinessDay::nextStart($this->branch));
        $today = BusinessDay::of($this->branch, $rollover->copy()->subMinute());
        Carbon::setTestNow($rollover->copy()->subSeconds(60));
        // The evening's PIN session (the fixture's is from the afternoon)
        $this->cashierToken = $this->pinSession($this->till, $this->cashier);
        $board = $this->poll('/pos/queue');
        $this->assertSame($today, $board->json('data.business_date'));
        $boardTag = $this->tagOf($board);
        $closedTag = $this->tagOf($this->poll('/pos/orders?scope=closed'));
        $fixedTag = $this->tagOf($this->poll('/pos/orders?scope=closed&business_date=' . $today));

        Carbon::setTestNow(now()->addSeconds(30));
        $this->poll('/pos/queue', $boardTag)->assertStatus(304);
        $this->poll('/pos/orders?scope=closed', $closedTag)->assertStatus(304);

        Carbon::setTestNow($rollover->copy()->addSecond());
        $response = $this->poll('/pos/queue', $boardTag);
        $response->assertOk();
        $this->assertNotSame($today, $response->json('data.business_date'));
        $this->assertSame(BusinessDay::of($this->branch), $response->json('data.business_date'));
        $this->poll('/pos/orders?scope=closed', $closedTag)->assertOk();

        // An explicit business day does not roll over: that tag lives its max-age
        $this->poll('/pos/orders?scope=closed&business_date=' . $today, $fixedTag)->assertStatus(304);
    }

    public function test_the_tills_order_list_ends_its_tag_when_a_pickup_for_later_stops_waiting(): void
    {
        $order = $this->orderWithItems(OrderStatus::ACCEPTED, ['pickup_at' => now()->addMinutes(20), 'kitchen_fire_at' => now()->addSeconds(60)]);
        $pickupOf = fn (TestResponse $response) => collect($response->json('data'))->firstWhere('uuid', $order->uuid)['pickup'];

        $first = $this->poll('/pos/orders');
        $this->assertTrue($pickupOf($first)['waiting']);
        $tag = $this->tagOf($first);

        Carbon::setTestNow(now()->addSeconds(30));
        $this->poll('/pos/orders', $tag)->assertStatus(304);

        // Its kitchen time passes before the scheduler fires it: no longer waiting
        Carbon::setTestNow(now()->addSeconds(31));
        $response = $this->poll('/pos/orders', $tag);
        $response->assertOk();
        $this->assertFalse($pickupOf($response)['waiting']);
    }

    public function test_the_ingredient_list_ends_its_tag_when_a_stop_ends(): void
    {
        $this->useProfile($this->branch, 'dark_kitchen');
        $this->withBearer(null);
        $id = (int) $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/owner/ingredients', ['name' => 'Pomidor'])->assertSuccessful()->json('data.id');
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/ingredients/{$id}/stop", ['branch_id' => $this->branch->id, 'until' => now()->addSeconds(60)->toJSON()])->assertSuccessful();
        $this->app['auth']->forgetGuards();
        $stopped = fn (TestResponse $response) => collect($response->json('data.ingredients'))->firstWhere('id', $id)['stopped'];

        $first = $this->poll('/owner/ingredients');
        $this->assertCount(1, $stopped($first));
        $tag = $this->tagOf($first);

        Carbon::setTestNow(now()->addSeconds(30));
        $this->poll('/owner/ingredients', $tag)->assertStatus(304);

        Carbon::setTestNow(now()->addSeconds(31));
        $response = $this->poll('/owner/ingredients', $tag);
        $response->assertOk();
        $this->assertSame([], $stopped($response));
    }
}
