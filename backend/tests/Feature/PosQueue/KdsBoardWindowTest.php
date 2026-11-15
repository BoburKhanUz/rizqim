<?php

namespace Tests\Feature\PosQueue;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Wave M1 (docs/scale/RESULTS.md finding 11): a paid counter order whose number was never handed over stayed on the
 * kitchen board for a whole day, so a board nobody bumps grew with the day's orders. It now stays
 * orders.kitchen_board.closed_counter_minutes (default 240) after it was closed; an order still open is never dropped.
 */
class KdsBoardWindowTest extends TestCase
{
    use PosQueueFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosQueue();
        $this->bootstrapQueue();
    }

    public function test_an_unbumped_paid_counter_order_leaves_the_board_after_the_window_and_an_open_one_stays(): void
    {
        $paid = $this->paidFirstTakeaway();
        $open = (string) $this->openTakeaway()->assertCreated()->json('data.uuid');
        $this->assertSame('preparing', Order::where('uuid', $paid)->value('pickup_status'));

        $this->assertSame([$paid, $open], $this->board());
        $this->assertSame([$paid, $open], $this->board('?include_ready=1'));

        Carbon::setTestNow(now()->addMinutes(239));
        $this->assertSame([$paid, $open], $this->board());

        // Past the window: the paid order (closed hours ago) is gone, the open one is still cooked
        Carbon::setTestNow(now()->addMinutes(2));
        $this->assertSame([$open], $this->board());
        $this->assertSame([$open], $this->board('?include_ready=1'));
    }

    public function test_the_window_is_configurable(): void
    {
        config(['orders.kitchen_board.closed_counter_minutes' => 30]);
        $paid = $this->paidFirstTakeaway();

        Carbon::setTestNow(now()->addMinutes(29));
        $this->assertSame([$paid], $this->board());
        Carbon::setTestNow(now()->addMinutes(2));
        $this->assertSame([], $this->board());

        // A day, as before the change
        config(['orders.kitchen_board.closed_counter_minutes' => 1440]);
        $this->assertSame([$paid], $this->board());
    }

    private function paidFirstTakeaway(): string
    {
        $response = $this->openTakeaway()->assertCreated();
        $uuid = (string) $response->json('data.uuid');
        $bill = (string) $this->tillAs('POST', "/pos/orders/{$uuid}/bills", ['version' => $response->json('data.version'), 'mode' => 'single'])->assertSuccessful()->json('data.bills.0.uuid');
        $this->tillAs('POST', "/pos/bills/{$bill}/payments", ['shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '36000']]])->assertSuccessful();
        $this->assertNotNull(Order::where('uuid', $uuid)->value('closed_at'));

        return $uuid;
    }

    /**
     * @return list<string>
     */
    private function board(string $query = ''): array
    {
        $this->withBearer(null);

        return $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets' . $query)->assertOk()->json('data.*.uuid');
    }
}
