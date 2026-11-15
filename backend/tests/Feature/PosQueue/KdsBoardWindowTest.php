<?php

namespace Tests\Feature\PosQueue;

use App\Domain\Pos\Takeaway\ScheduledPickup;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\PosTakeaway\PosTakeawayFixtures;
use Tests\TestCase;

/**
 * Wave M1 (docs/scale/RESULTS.md finding 11): a paid counter order whose number was never handed over stayed on the
 * kitchen board for a whole day, so a board nobody bumps grew with the day's orders.
 *
 * Review R1-1: the window is for a forgotten hand-over of a FINISHED order only. A ticket with a line the kitchen still
 * has to cook (sent and not bumped, a held pre-order line before or after its due time, a line fired later by the
 * pickup-for-later job, a recalled line) is never dropped; once every line is done, the finished number stays
 * orders.kitchen_board.closed_counter_minutes (default 240) after the latest of: closed, fired, its last line ready.
 * An order still open is never dropped.
 */
class KdsBoardWindowTest extends TestCase
{
    use PosTakeawayFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosTakeaway();
        $this->bootstrapQueue();
    }

    public function test_a_finished_paid_counter_order_leaves_the_board_after_the_window_and_an_open_one_stays(): void
    {
        $paid = $this->paidFirstTakeaway();
        $this->bumpAll($paid);
        $open = (string) $this->openTakeaway()->assertCreated()->json('data.uuid');
        $this->assertSame('ready', Order::where('uuid', $paid)->value('pickup_status'));

        $this->assertSame([$open], $this->board());
        $this->assertSame([$paid, $open], $this->board('?include_ready=1'));

        Carbon::setTestNow(now()->addMinutes(239));
        $this->assertSame([$paid, $open], $this->board('?include_ready=1'));

        // Past the window: the finished number nobody handed over is gone, the open one is still cooked
        Carbon::setTestNow(now()->addMinutes(2));
        $this->assertSame([$open], $this->board());
        $this->assertSame([$open], $this->board('?include_ready=1'));
    }

    public function test_an_unbumped_paid_counter_order_is_never_dropped(): void
    {
        $paid = $this->paidFirstTakeaway();
        $this->assertSame('preparing', Order::where('uuid', $paid)->value('pickup_status'));

        Carbon::setTestNow(now()->addMinutes(241));
        $this->assertSame([$paid], $this->board());
        Carbon::setTestNow(now()->addHours(30));
        $this->assertSame([$paid], $this->board());
        $this->assertSame([$paid], $this->board('?include_ready=1'));
    }

    public function test_the_window_is_configurable(): void
    {
        config(['orders.kitchen_board.closed_counter_minutes' => 30]);
        $paid = $this->paidFirstTakeaway();
        $this->bumpAll($paid);

        Carbon::setTestNow(now()->addMinutes(29));
        $this->assertSame([$paid], $this->board('?include_ready=1'));
        Carbon::setTestNow(now()->addMinutes(2));
        $this->assertSame([], $this->board('?include_ready=1'));

        // A day, as before the change
        config(['orders.kitchen_board.closed_counter_minutes' => 1440]);
        $this->assertSame([$paid], $this->board('?include_ready=1'));
    }

    /**
     * Review R1-1 (the reviewer's case): a paid-first counter order with a held pre-order line due hours later. The
     * line is still to be cooked after the window from closing has passed: the ticket stays until it is done.
     */
    public function test_a_held_line_due_after_the_window_keeps_the_ticket_until_it_is_cooked(): void
    {
        $uuid = $this->paidFirstTakeaway([
            ['variant_id' => $this->tea->uuid, 'quantity' => 1],
            ['variant_id' => $this->shashlik->uuid, 'quantity' => 2, 'fire_mode' => 'hold', 'due_at' => now()->addHours(5)->toIso8601String()],
        ]);
        $this->bumpAll($uuid);
        $this->assertSame('preparing', Order::where('uuid', $uuid)->value('pickup_status'));

        Carbon::setTestNow(now()->addMinutes(241));
        $this->assertSame([$uuid], $this->board(), 'held line not due yet');
        Carbon::setTestNow(now()->addMinutes(60));
        $this->assertSame([$uuid], $this->board(), 'held line due now');
        $this->assertSame([$uuid], $this->board('?include_ready=1'));
        Carbon::setTestNow(now()->addMinutes(300));
        $this->assertSame([$uuid], $this->board(), 'held line overdue, still not cooked');

        // Cooked at last: the window (a finished number nobody handed over) counts from that bump
        $this->kitchenLine($uuid, 'Shashlik', 'ready');
        $this->assertSame([], $this->board());
        $this->assertSame([$uuid], $this->board('?include_ready=1'));
        Carbon::setTestNow(now()->addMinutes(239));
        $this->assertSame([$uuid], $this->board('?include_ready=1'));
        Carbon::setTestNow(now()->addMinutes(2));
        $this->assertSame([], $this->board('?include_ready=1'));
    }

    /**
     * Review R1-1: a pickup for later paid first is fired by the minute job hours after closing; its lines are still
     * cooked when the window after the firing has passed.
     */
    public function test_a_pickup_for_later_fired_late_keeps_the_ticket_until_it_is_cooked(): void
    {
        $opened = $this->takeaway([[$this->shashlik, 1]], ['send' => true, 'pickup_at' => '2026-10-23T09:00:00Z'])->assertCreated();
        $uuid = (string) $opened->json('data.uuid');
        $this->payAll($uuid, (int) $opened->json('data.version'));
        $this->assertSame([], $this->board());

        Carbon::setTestNow((string) Order::where('uuid', $uuid)->value('kitchen_fire_at'));
        $this->assertSame(1, app(ScheduledPickup::class)->fireDue());
        $this->assertSame([$uuid], $this->board());

        Carbon::setTestNow(now()->addMinutes(241));
        $this->assertSame([$uuid], $this->board());

        $this->bumpAll($uuid);
        Carbon::setTestNow(now()->addMinutes(239));
        $this->assertSame([$uuid], $this->board('?include_ready=1'));
        Carbon::setTestNow(now()->addMinutes(2));
        $this->assertSame([], $this->board('?include_ready=1'));
    }

    /**
     * Review R1-1: a line bumped late and recalled after the window from closing is back on the board; a finished
     * number stays (include_ready) for the window after its last bump.
     */
    public function test_a_line_recalled_after_the_window_keeps_the_ticket(): void
    {
        $uuid = $this->paidFirstTakeaway();

        Carbon::setTestNow(now()->addMinutes(200));
        $this->bumpAll($uuid);
        Carbon::setTestNow(now()->addMinutes(50));
        $this->assertSame([], $this->board());
        $this->assertSame([$uuid], $this->board('?include_ready=1'), 'ready 50 minutes ago, not handed over');

        $this->kitchenLine($uuid, 'Shashlik', 'recall');
        $this->assertSame('preparing', Order::where('uuid', $uuid)->value('pickup_status'));
        $this->assertSame([$uuid], $this->board());

        Carbon::setTestNow(now()->addHours(10));
        $this->assertSame([$uuid], $this->board());
    }

    /**
     * Review R1-1: a voided line, or a held line with no due time the kitchen does not see, keeps nothing on the board.
     */
    public function test_a_voided_or_unseen_line_does_not_keep_the_ticket(): void
    {
        $uuid = $this->paidFirstTakeaway([
            ['variant_id' => $this->tea->uuid, 'quantity' => 1],
            ['variant_id' => $this->shashlik->uuid, 'quantity' => 2, 'fire_mode' => 'hold', 'due_at' => now()->addHours(5)->toIso8601String()],
            ['variant_id' => $this->soup->uuid, 'quantity' => 1, 'fire_mode' => 'hold'],
        ]);
        $this->bumpAll($uuid);
        $shashlik = OrderItem::where('order_id', Order::where('uuid', $uuid)->value('id'))->where('product_name', 'Shashlik')->firstOrFail();
        $shashlik->forceFill(['kitchen_status' => 'void', 'voided_at' => now()])->save();

        Carbon::setTestNow(now()->addMinutes(239));
        $this->assertSame([$uuid], $this->board('?include_ready=1'));
        Carbon::setTestNow(now()->addMinutes(2));
        $this->assertSame([], $this->board());
        $this->assertSame([], $this->board('?include_ready=1'));
    }

    /**
     * @param  list<array<string, mixed>>|null  $items
     */
    private function paidFirstTakeaway(?array $items = null): string
    {
        $response = $items === null ? $this->openTakeaway()->assertCreated()
            : $this->tillAs('POST', '/pos/orders', ['channel' => 'pos_takeaway', 'items' => $items, 'send' => true])->assertCreated();
        $uuid = (string) $response->json('data.uuid');
        $this->payAll($uuid, (int) $response->json('data.version'));
        $this->assertNotNull(Order::where('uuid', $uuid)->value('closed_at'));

        return $uuid;
    }

    private function kitchenLine(string $uuid, string $name, string $action): void
    {
        $item = OrderItem::where('order_id', Order::where('uuid', $uuid)->value('id'))->where('product_name', $name)->firstOrFail();
        $this->withBearer(null);
        $this->actingAs($this->cook, 'sanctum')->postJson("/api/v1/staff/orders/{$uuid}/items/{$item->id}/{$action}")->assertOk();
        $this->app['auth']->forgetGuards();
    }

    /**
     * @return list<string>
     */
    private function board(string $query = ''): array
    {
        $this->withBearer(null);

        $uuids = $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets' . $query)->assertOk()->json('data.*.uuid');
        $this->app['auth']->forgetGuards();

        return $uuids;
    }
}
