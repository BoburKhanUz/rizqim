<?php

namespace Tests\Feature\PosDisplay;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\PosQueue\PosQueueFixtures;
use Tests\TestCase;

/**
 * P6-14: the kitchen display shows a counter order paid first (closed, `delivered`) as a normal ticket while its queue
 * number is still being cooked: the ticket carries the number and the pickup state, the waiting time keeps running
 * until the number is ready, and the expo bump (POST /pos/orders/{uuid}/ready) marks it ready from the kitchen's PIN
 * session.
 */
class PosKitchenQueueTicketTest extends TestCase
{
    use PosQueueFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosQueue();
    }

    private function payFirst(): string
    {
        $response = $this->openTakeaway()->assertCreated();
        $uuid = (string) $response->json('data.uuid');
        $bill = (string) $this->tillAs('POST', "/pos/orders/{$uuid}/bills", ['version' => $response->json('data.version'), 'mode' => 'single'])->assertSuccessful()->json('data.bills.0.uuid');
        $this->tillAs('POST', "/pos/bills/{$bill}/payments", ['shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '36000']]])->assertSuccessful();

        return $uuid;
    }

    private function tickets(string $query = '')
    {
        $this->withBearer(null);

        return $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets' . $query);
    }

    public function test_a_paid_first_order_is_a_normal_ticket_with_its_number_and_a_running_timer(): void
    {
        $uuid = $this->payFirst();
        $this->assertSame('delivered', Order::where('uuid', $uuid)->firstOrFail()->status->value);

        $first = $this->tickets()->assertOk()
            ->assertJsonPath('data.0.uuid', $uuid)
            ->assertJsonPath('data.0.status', 'delivered')
            ->assertJsonPath('data.0.queue_number', 1)
            ->assertJsonPath('data.0.pickup_status', 'preparing')
            ->json('data.0.waiting_seconds');

        // The timer keeps running while the number is cooked (it would stop at the payment otherwise)
        Carbon::setTestNow(now()->addMinutes(7));
        $later = $this->tickets()->assertOk()->json('data.0.waiting_seconds');
        $this->assertGreaterThanOrEqual($first + 7 * 60, $later);
    }

    public function test_the_expo_bump_marks_the_number_ready_and_the_ticket_leaves_the_board(): void
    {
        $uuid = $this->payFirst();

        $this->withBearer(null);
        $this->actingAs($this->cook, 'sanctum')->withHeader('Idempotency-Key', (string) \Illuminate\Support\Str::uuid())
            ->postJson("/api/v1/pos/orders/{$uuid}/ready")->assertOk()->assertJsonPath('data.status', 'ready');

        $this->assertSame('ready', Order::where('uuid', $uuid)->firstOrFail()->pickup_status);
        $this->tickets()->assertOk()->assertJsonCount(0, 'data');
        $ready = $this->tickets('?include_ready=1')->assertOk()
            ->assertJsonPath('data.0.pickup_status', 'ready')->assertJsonPath('data.0.queue_number', 1)
            ->json('data.0.waiting_seconds');

        // Ready: the wait is over, it does not grow any more
        Carbon::setTestNow(now()->addMinutes(9));
        $this->assertSame($ready, $this->tickets('?include_ready=1')->json('data.0.waiting_seconds'));
    }

    public function test_an_open_takeaway_ticket_carries_its_number_too(): void
    {
        $this->openTakeaway()->assertCreated();
        $this->tickets()->assertOk()->assertJsonPath('data.0.status', 'preparing')->assertJsonPath('data.0.queue_number', 1)->assertJsonPath('data.0.pickup_status', 'preparing');
    }
}
