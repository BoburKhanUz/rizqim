<?php

namespace Tests\Feature\PosRestaurant;

use App\Events\Broadcast\OrderCourseReadyBroadcast;
use App\Events\Broadcast\OrderItemsChangedBroadcast;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P6 wave 2 review C9: voiding the last line of a course that was still cooking completes the course when what is left
 * of it is ready: the waiter hears order.course_ready once (the void path asks the course tracker); voiding a line that
 * was already ready, or one whose course still cooks, says nothing.
 */
class PosCourseVoidTest extends TestCase
{
    use PosRestaurantFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRestaurant();
    }

    private function order(): Order
    {
        return $this->openedOrder($this->openPos($this->waiter, $this->table, 4, [
            'items' => [
                $this->line($this->tea, 2, ['course_no' => 1]),
                $this->line($this->bread, 2, ['course_no' => 1]),
                $this->line($this->fish, '1.2', ['course_no' => 1, 'awaiting_weight' => false]),
                $this->line($this->shashlik, 4, ['course_no' => 2]),
            ],
        ]));
    }

    private function void(Order $order, OrderItem $item): void
    {
        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/items/{$item->id}/void", ['version' => $this->versionOf($order), 'reason' => 'Mehmon voz kechdi'])->assertOk();
    }

    public function test_voiding_the_last_cooking_line_of_a_course_announces_it_ready_once(): void
    {
        Event::fake([OrderCourseReadyBroadcast::class, OrderItemsChangedBroadcast::class]);
        $order = $this->order();
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/send", ['version' => 1])->assertOk();
        [$tea, $bread, $fish] = OrderItem::where('order_id', $order->id)->where('course_no', 1)->orderBy('id')->get()->all();
        $shashlik = OrderItem::where('order_id', $order->id)->where('course_no', 2)->firstOrFail();

        $cook = $this->makeCook();
        $this->actingAs($cook, 'sanctum')->postJson("/api/v1/staff/orders/{$order->uuid}/items/{$tea->id}/ready")->assertOk();

        // Bread goes while the fish still cooks: nothing to say yet
        $this->void($order, $bread);
        Event::assertNotDispatched(OrderCourseReadyBroadcast::class);

        // The fish was the last line still cooking: the tea waits at the pass, course 1 is ready
        $this->void($order, $fish);
        Event::assertDispatched(OrderCourseReadyBroadcast::class, fn (OrderCourseReadyBroadcast $event) => $event->broadcastWith()['course_no'] === 1
            && $event->broadcastWith()['item_ids'] === [(int) $tea->id]
            && $event->broadcastWith()['waiter_id'] === (int) $this->waiter->id);
        Event::assertDispatchedTimes(OrderCourseReadyBroadcast::class, 1);

        // Voiding the ready tea (the course was announced) or the only, held line of course 2 says nothing more
        $this->void($order, $tea);
        $this->void($order, $shashlik);
        Event::assertDispatchedTimes(OrderCourseReadyBroadcast::class, 1);
    }
}
