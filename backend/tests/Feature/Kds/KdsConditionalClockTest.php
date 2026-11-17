<?php

namespace Tests\Feature\Kds;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Wave M2 follow-up: GET /staff/kitchen/tickets is a conditional GET (ConditionalGet, max-age 120 s), and its body
 * changes by the clock alone: a line turns `late` once its target passed, a finished counter number leaves the board
 * after the closed-counter window, a cancelled order leaves the `canceled_within` list. Each of those moments ends
 * the ETag's life (ConditionalGet::validUntil), so a client polling with If-None-Match never gets a 304 that replays
 * the body from before it.
 */
class KdsConditionalClockTest extends TestCase
{
    use KdsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpKds();
    }

    private function poll(string $uri, ?string $etag = null): TestResponse
    {
        $this->withBearer(null);
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        if ($etag !== null) {
            $this->withHeader('If-None-Match', $etag);
        }

        return $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1' . $uri);
    }

    private function tagOf(TestResponse $response): string
    {
        $response->assertOk();
        $tag = $response->headers->get('ETag');
        $this->assertIsString($tag, 'a 200 carries an ETag');

        return $tag;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ticketIn(TestResponse $response, string $uuid): ?array
    {
        return collect((array) $response->json('data'))->firstWhere('uuid', $uuid);
    }

    public function test_a_line_turning_late_ends_the_etag_without_any_write(): void
    {
        $uuid = $this->openCombo();

        $first = $this->poll('/staff/kitchen/tickets');
        $tag = $this->tagOf($first);
        $this->assertSame([false, false], array_column($this->ticketIn($first, $uuid)['items'], 'late'));

        // Before the drink's 90 s target: nothing changed, a 304
        Carbon::setTestNow(now()->addSeconds(60));
        $this->poll('/staff/kitchen/tickets', $tag)->assertStatus(304);

        // Past it, with no write anywhere: the same tag must not validate, the drink is late
        Carbon::setTestNow(now()->addSeconds(31));
        $response = $this->poll('/staff/kitchen/tickets', $tag);
        $response->assertOk();
        $this->assertNotSame($tag, $response->headers->get('ETag'));
        $this->assertSame([false, true], array_column($this->ticketIn($response, $uuid)['items'], 'late'));

        // The new tag lives until the shashlik's 300 s target, then the shashlik is late too
        $tag = $this->tagOf($response);
        $this->poll('/staff/kitchen/tickets', $tag)->assertStatus(304);
        Carbon::setTestNow(now()->addSeconds(300 - 91 + 1));
        $response = $this->poll('/staff/kitchen/tickets', $tag);
        $response->assertOk();
        $this->assertSame([true, true], array_column($this->ticketIn($response, $uuid)['items'], 'late'));
    }

    public function test_a_late_or_done_line_does_not_shorten_the_etag(): void
    {
        $uuid = $this->openCombo();
        $order = Order::where('uuid', $uuid)->firstOrFail();
        [$shashlik, $tea] = $order->items()->orderBy('id')->get()->all();
        $this->bump($uuid, (int) $tea->id)->assertOk();

        // Both lines' moments are behind (the drink is done, the shashlik late): the tag lives its max-age
        Carbon::setTestNow(now()->addSeconds(400));
        $tag = $this->tagOf($this->poll('/staff/kitchen/tickets'));
        Carbon::setTestNow(now()->addSeconds(119));
        $this->poll('/staff/kitchen/tickets', $tag)->assertStatus(304);
    }

    public function test_a_finished_counter_number_leaves_the_board_at_the_end_of_its_window_without_a_write(): void
    {
        config(['orders.kitchen_board.closed_counter_minutes' => 10]);
        $uuid = $this->openCombo();
        $order = Order::where('uuid', $uuid)->firstOrFail();
        // Paid first and closed while the number was being cooked, every line done since: on the board for 10 minutes
        $order->items()->update(['kitchen_status' => 'ready', 'ready_at' => now()]);
        $order->forceFill(['status' => 'delivered', 'closed_at' => now(), 'pickup_status' => 'preparing'])->save();

        // 9 minutes later (no write since): still listed, a tag that may live only one more minute
        Carbon::setTestNow(now()->addMinutes(9));
        $first = $this->poll('/staff/kitchen/tickets');
        $this->assertNotNull($this->ticketIn($first, $uuid));
        $tag = $this->tagOf($first);

        Carbon::setTestNow(now()->addSeconds(30));
        $this->poll('/staff/kitchen/tickets', $tag)->assertStatus(304);

        Carbon::setTestNow(now()->addSeconds(32));
        $response = $this->poll('/staff/kitchen/tickets', $tag);
        $response->assertOk();
        $this->assertNull($this->ticketIn($response, $uuid));
    }

    public function test_a_cancelled_order_leaves_the_canceled_within_list_at_its_time_without_a_write(): void
    {
        $uuid = $this->openCombo();
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $order->forceFill(['status' => 'canceled'])->save();
        OrderStatusHistory::create(['order_id' => $order->id, 'status' => 'canceled', 'from_status' => 'preparing', 'created_at' => now(), 'updated_at' => now()]);

        // 4 minutes later (no write since): still struck through, a tag that may live only one more minute
        $uri = '/staff/kitchen/tickets?canceled_within=5';
        Carbon::setTestNow(now()->addMinutes(4));
        $first = $this->poll($uri);
        $this->assertNotNull($this->ticketIn($first, $uuid));
        $tag = $this->tagOf($first);

        Carbon::setTestNow(now()->addSeconds(30));
        $this->poll($uri, $tag)->assertStatus(304);

        Carbon::setTestNow(now()->addSeconds(32));
        $response = $this->poll($uri, $tag);
        $response->assertOk();
        $this->assertNull($this->ticketIn($response, $uuid));
    }
}
