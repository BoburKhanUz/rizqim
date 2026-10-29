<?php

namespace Tests\Feature\PosQueue;

use App\Domain\Pos\Shifts\QueueNumbers;
use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Order;
use App\Models\PosQueueBlock;
use App\Models\PosSyncOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * P6-13 (A2): order numbers by till block. Two tills of one branch, online and offline, never share a number of the
 * day; the server never hands two tills the same block; numbering restarts from 1 every business day per branch; a
 * Telegram pickup order takes its number from the branch's own block; the choyxona keeps its P3-18 numbering.
 */
class PosQueueNumbersTest extends TestCase
{
    use PosQueueFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosQueue();
    }

    public function test_two_tills_online_and_offline_never_share_a_number(): void
    {
        // Each till gets its own block in the bootstrap, one after the other
        $first = $this->bootstrapQueue();
        $this->assertTrue($first['numbers']);
        $this->assertTrue($first['calling']);
        $this->assertSame('2026-10-22', $first['business_date']);
        $this->assertSame([['start' => 1, 'end' => 100, 'next' => 1]], $first['blocks']);
        $this->assertSame(1, $first['next_number']);
        $second = $this->bootstrapQueue($this->cashier2Token);
        $this->assertSame([['start' => 101, 'end' => 200, 'next' => 101]], $second['blocks']);
        // Asking again changes nothing: enough numbers are left
        $this->assertSame($first['blocks'], $this->bootstrapQueue()['blocks']);

        // Online: the till's own number is kept, or the server gives the next of the till's block
        $this->openTakeaway(null, 1)->assertCreated()->assertJsonPath('data.queue.number', 1);
        $this->openTakeaway()->assertCreated()->assertJsonPath('data.queue.number', 2);
        $this->openTakeaway($this->cashier2Token)->assertCreated()->assertJsonPath('data.queue.number', 101);

        // Offline on both tills at once: each prints from its own block; the sync keeps the numbers
        $this->sync([$this->offlineOpen('K1-001', 3), $this->offlineOpen('K1-002', 4)])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.refs.queue_number', 3)
            ->assertJsonPath('data.results.1.refs.queue_number', 4);
        $this->sync([$this->offlineOpen('K2-001', 102, $this->cashier2), $this->offlineOpen('K2-002', 103, $this->cashier2)], [], $this->till2['token'])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.refs.queue_number', 102)
            ->assertJsonPath('data.results.1.refs.queue_number', 103);

        // The server moved its cursor past the offline numbers: the next online number follows them
        $this->openTakeaway()->assertCreated()->assertJsonPath('data.queue.number', 5);

        // Another till's number (or one already taken) is never given twice: the order gets the next own number and,
        // offline, the manager sees the talon's number was changed
        $this->openTakeaway(null, 150)->assertCreated()->assertJsonPath('data.queue.number', 6);
        $response = $this->sync([$this->offlineOpen('K2-003', 3, $this->cashier2)], [], $this->till2['token'])->assertOk();
        $response->assertJsonPath('data.results.0.status', 'conflict')
            ->assertJsonPath('data.results.0.reason', 'queue_number_taken')
            ->assertJsonPath('data.results.0.refs.queue_number', 104);
        $order = Order::where('local_number', 'K2-003')->sole();
        $this->assertSame(104, $order->queue_number);
        $this->assertSame('queue_number_taken', $order->sync_flags[0]['flag']);
        $this->assertSame(3, $order->sync_flags[0]['queue_number']);

        // No number twice in the day; the blocks never overlap
        $numbers = Order::where('branch_id', $this->branch->id)->pluck('queue_number')->all();
        $this->assertCount(10, $numbers);
        $this->assertSame(count($numbers), count(array_unique($numbers)));
        $blocks = PosQueueBlock::where('branch_id', $this->branch->id)->orderBy('start_number')->get();
        $this->assertSame([[1, 100, (int) $this->till['device']->id], [101, 200, (int) $this->till2['device']->id]], $blocks->map(fn (PosQueueBlock $block) => [$block->start_number, $block->end_number, $block->device_id])->all());
        $this->assertSame(7, $blocks[0]->next_number);
        $this->assertSame(105, $blocks[1]->next_number);
    }

    public function test_a_telegram_pickup_order_takes_its_number_from_the_branchs_block_when_accepted(): void
    {
        $this->bootstrapQueue();
        $this->bootstrapQueue($this->cashier2Token);
        $online = $this->orderWithItems(OrderStatus::NEW, ['order_type' => 'pickup']);
        $this->assertNull($online->queue_number);

        $this->tillAs('POST', "/pos/orders/{$online->uuid}/accept")->assertOk()
            ->assertJsonPath('data.queue.number', 201)
            ->assertJsonPath('data.queue.status', 'preparing');
        $online->refresh();
        $this->assertSame(201, $online->queue_number);
        $this->assertSame('2026-10-22', $online->business_date?->toDateString());
        $this->assertNull(PosQueueBlock::where('start_number', 201)->value('device_id'));

        // A delivery order is not in the pickup queue and gets no number
        $delivery = $this->orderWithItems(OrderStatus::NEW, ['order_type' => 'delivery']);
        $this->tillAs('POST', "/pos/orders/{$delivery->uuid}/accept")->assertOk()->assertJsonPath('data.queue', null);
    }

    public function test_numbering_restarts_every_business_day_per_branch(): void
    {
        $this->bootstrapQueue();
        $this->openTakeaway()->assertCreated()->assertJsonPath('data.queue.number', 1);
        $this->openTakeaway()->assertCreated()->assertJsonPath('data.queue.number', 2);

        // 03:00 the next calendar day is still the same business day (it starts at 05:00)
        Carbon::setTestNow(Carbon::parse('2026-10-22 22:00:00', 'UTC'));
        $this->openTakeaway()->assertCreated()->assertJsonPath('data.queue.number', 3);

        // 06:00: a new business day: a new block from 1 and the first number is 1 again
        Carbon::setTestNow(Carbon::parse('2026-10-23 01:00:00', 'UTC'));
        $state = $this->bootstrapQueue();
        $this->assertSame('2026-10-23', $state['business_date']);
        $this->assertSame([['start' => 1, 'end' => 100, 'next' => 1]], $state['blocks']);
        $this->openTakeaway()->assertCreated()->assertJsonPath('data.queue.number', 1)->assertJsonPath('data.queue.business_date', '2026-10-23');

        // Another branch counts on its own
        $this->useProfile($this->otherBranch, 'fast_food');
        $this->assertSame(1, $this->numberIn($this->otherBranch));
    }

    public function test_a_till_running_low_reserves_its_next_block_above_every_other(): void
    {
        $this->bootstrapQueue();
        $this->bootstrapQueue($this->cashier2Token);
        // The first till used most of its block
        PosQueueBlock::where('device_id', $this->till['device']->id)->update(['next_number' => 90]);

        $this->withBearer($this->cashierToken)->postJson('/api/v1/pos/queue/blocks')->assertStatus(428);
        $this->tillAs('POST', '/pos/queue/blocks', [])->assertOk()
            ->assertJsonPath('data.blocks', [['start' => 1, 'end' => 100, 'next' => 90], ['start' => 201, 'end' => 300, 'next' => 201]])
            ->assertJsonPath('data.next_number', 90);
        // Enough left now: nothing more is reserved
        $this->tillAs('POST', '/pos/queue/blocks', [])->assertOk()->assertJsonCount(2, 'data.blocks');

        // A block used up is left behind: the next number comes from the next block
        PosQueueBlock::where('device_id', $this->till['device']->id)->where('start_number', 1)->update(['next_number' => 101]);
        $this->openTakeaway()->assertCreated()->assertJsonPath('data.queue.number', 201);

        // Every block used up: the server cuts a new one above all of the day's
        PosQueueBlock::where('device_id', $this->till['device']->id)->update(['next_number' => 301]);
        $this->openTakeaway()->assertCreated()->assertJsonPath('data.queue.number', 301);
        $this->assertSame(400, PosQueueBlock::where('start_number', 301)->value('end_number'));
    }

    public function test_the_queue_numbers_module_gates_the_blocks_and_the_choyxona_keeps_its_numbering(): void
    {
        $this->useProfile($this->branch, 'choyxona');

        $state = $this->bootstrapQueue();
        $this->assertFalse($state['numbers']);
        $this->assertFalse($state['calling']);
        $this->assertSame([], $state['blocks']);
        $this->assertNull($state['next_number']);
        $this->tillAs('POST', '/pos/queue/blocks', [])->assertForbidden()->assertJsonPath('code', 'module_disabled');

        // P3-18 numbering: the next number of the day, nothing reserved; a till's proposal is ignored (review P6 C#L4).
        // The takeaway's pickup state is kept for the kitchen (review P6 C#H1), never shown on a board here
        $this->openTakeaway()->assertCreated()->assertJsonPath('data.queue.number', 1)->assertJsonPath('data.queue.status', 'preparing');
        $this->openTakeaway($this->cashier2Token)->assertCreated()->assertJsonPath('data.queue.number', 2);
        $this->openTakeaway(null, 2)->assertCreated()->assertJsonPath('data.queue.number', 3);
        $this->assertSame(0, PosQueueBlock::count());

        // A Telegram pickup order gets no number without the module
        $online = $this->orderWithItems(OrderStatus::NEW, ['order_type' => 'pickup']);
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/accept")->assertOk()->assertJsonPath('data.queue', null);
        $this->assertNull($online->refresh()->queue_number);
        $this->assertSame(0, PosSyncOperation::count());
    }

    private function numberIn(Branch $branch): int
    {
        $order = Order::create([
            'store_id' => $this->store->id,
            'branch_id' => $branch->id,
            'user_id' => $this->cashier->id,
            'status' => OrderStatus::ACCEPTED,
            'order_type' => 'pickup',
            'channel' => 'pos_takeaway',
            'payment_method' => 'cash',
            'total_price' => 0,
            'commission_amount' => 0,
        ]);

        return (int) $order->queue_number;
    }

    public function test_block_size_and_low_water_are_what_the_till_is_told(): void
    {
        $state = $this->bootstrapQueue();
        $this->assertSame(QueueNumbers::BLOCK_SIZE, $state['block_size']);
        $this->assertSame(QueueNumbers::LOW_WATER, $state['low_water']);
    }
}
