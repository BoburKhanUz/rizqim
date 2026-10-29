<?php

namespace Tests\Feature\PosRestaurant;

use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosModule;
use App\Events\Broadcast\OrderCourseReadyBroadcast;
use App\Events\Broadcast\OrderItemsChangedBroadcast;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P6-21 (B5): courses on a restaurant order — a later course is held (the ordinary send never takes it early), "N-kursni
 * yuborish" sends it, the kitchen ticket carries the course, the waiter hears when a course is ready, a course line
 * added after its course went out goes with the next send, and the module gates the new flow only.
 */
class PosCoursesTest extends TestCase
{
    use PosRestaurantFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRestaurant();
    }

    private function threeCourses(): Order
    {
        return $this->openedOrder($this->openPos($this->waiter, $this->table, 4, [
            'items' => [
                $this->line($this->tea, 2, ['course_no' => 1]),
                $this->line($this->bread, 2, ['course_no' => 1]),
                $this->line($this->shashlik, 4, ['course_no' => 2]),
                $this->line($this->fish, '1.2', ['course_no' => 3, 'awaiting_weight' => false]),
            ],
        ]));
    }

    public function test_the_restaurant_profile_switches_the_wave_two_modules_on(): void
    {
        $modules = FormatProfile::RESTORAN->modules();
        foreach ([PosModule::COURSES, PosModule::TIPS, PosModule::STAFF_PAY, PosModule::GUEST_SPLIT, PosModule::WAITER_TRANSFER, PosModule::HOSTESS, PosModule::TABLE_BOOKING, PosModule::SERVICE_CHARGE_RULES] as $module) {
            $this->assertContains($module, $modules, $module->value);
        }
        $this->assertContains(PosModule::SKEWER_BUTTONS, FormatProfile::KABOBXONA->modules());
        $this->assertContains(PosModule::COMBO_MEAL, FormatProfile::KABOBXONA->modules());
        $this->assertContains(PosModule::WEIGHT_LATER, FormatProfile::BALIQXONA->modules());
        $this->assertContains(PosModule::TABLE_SERVICE, FormatProfile::KAFE->modules());
        // The choyxona flow does not change
        $this->assertSame(
            ['quick_counter', 'rooms', 'room_fee', 'guest_items', 'bookings', 'customer_accounts', 'service_charge', 'kds', 'batches', 'terminal_card_flow'],
            array_map(fn (PosModule $module) => $module->value, FormatProfile::CHOYXONA->modules()),
        );
        // §7: courses, hostess, booking, guest split, the service charge rules, tips and staff pay are Asosiy+;
        // skewer buttons, weight later, table service and the waiter transfer are on Start
        $this->assertNotNull(PosModule::COURSES->planFeature());
        $this->assertNotNull(PosModule::TIPS->planFeature());
        $this->assertNull(PosModule::SKEWER_BUTTONS->planFeature());
        $this->assertNull(PosModule::WEIGHT_LATER->planFeature());
        $this->assertNull(PosModule::TABLE_SERVICE->planFeature());

        $format = $this->pos($this->cashier, 'GET', '/pos/bootstrap')->assertOk()->assertJsonPath('data.format.profile', 'restoran')->json('data.format');
        $this->assertContains('courses', $format['modules']);
        $this->assertContains('waiter_transfer', $format['modules']);
    }

    public function test_a_later_course_is_held_until_it_is_sent_by_its_number(): void
    {
        $order = $this->threeCourses();
        $items = OrderItem::where('order_id', $order->id)->orderBy('id')->get();
        $this->assertSame([1, 1, 2, 3], $items->pluck('course_no')->all());
        $this->assertSame(['now', 'now', 'hold', 'hold'], $items->map(fn (OrderItem $item) => $item->fire_mode->value)->all());

        // The ordinary send takes the first course only
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/send", ['version' => 1])->assertOk()
            ->assertJsonPath('data.courses.0.course_no', 1)
            ->assertJsonPath('data.courses.0.status', 'sent')
            ->assertJsonPath('data.courses.1.status', 'held')
            ->assertJsonPath('data.courses.2.status', 'held');
        $this->assertSame(2, OrderItem::where('order_id', $order->id)->whereNotNull('sent_at')->count());

        // The kitchen sees the first course with its mark, nothing of the held ones
        $cook = $this->makeCook();
        $ticket = $this->actingAs($cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertOk()->json('data.0');
        $this->assertSame(['Choy', 'Non'], array_column($ticket['items'], 'product_name'));
        $this->assertSame([1, 1], array_column($ticket['items'], 'course_no'));

        // "Oshxonaga yuborish" again: nothing new (course 2 waits)
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/send", ['version' => $this->versionOf($order)])
            ->assertStatus(422)->assertJsonPath('data.reason', 'nothing_to_send');

        // "2-kursni yuborish"
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/courses/2/send", ['version' => $this->versionOf($order)])->assertOk()
            ->assertJsonPath('data.courses.1.status', 'sent')
            ->assertJsonPath('data.courses.2.status', 'held');
        $this->assertNotNull(OrderItem::where('order_id', $order->id)->where('course_no', 2)->value('sent_at'));
        $this->assertNull(OrderItem::where('order_id', $order->id)->where('course_no', 3)->value('sent_at'));
        $log = AuditLog::where('action', 'pos.course_sent')->latest('id')->firstOrFail();
        $this->assertSame(2, $log->payload_json['course_no']);

        // Sent already: nothing left in course 2
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/courses/2/send", ['version' => $this->versionOf($order)])
            ->assertStatus(422)->assertJsonPath('data.reason', 'nothing_to_send')->assertJsonPath('data.course_no', 2);

        // A line added to course 2 now goes with the next send; a course-3 line is still held
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/rounds", [
            'version' => $this->versionOf($order),
            'items' => [$this->line($this->shashlik, 2, ['course_no' => 2]), $this->line($this->bread, 1, ['course_no' => 3])],
            'send' => true,
        ])->assertOk();
        $round = OrderItem::where('order_id', $order->id)->where('round_no', 2)->orderBy('id')->get();
        $this->assertSame(['now', 'hold'], $round->map(fn (OrderItem $item) => $item->fire_mode->value)->all());
        $this->assertNotNull($round[0]->sent_at);
        $this->assertNull($round[1]->sent_at);

        // Course 3 goes with its own send, both its lines
        $response = $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/courses/3/send", ['version' => $this->versionOf($order)])->assertOk();
        $this->assertSame(0, OrderItem::where('order_id', $order->id)->whereNull('sent_at')->count());
        $this->assertSame(['sent', 'sent', 'sent'], array_column($response->json('data.courses'), 'status'));
    }

    public function test_the_waiter_hears_when_a_course_is_ready(): void
    {
        Event::fake([OrderCourseReadyBroadcast::class, OrderItemsChangedBroadcast::class]);
        $order = $this->threeCourses();
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/send", ['version' => 1])->assertOk();
        [$tea, $bread] = OrderItem::where('order_id', $order->id)->where('course_no', 1)->orderBy('id')->get()->all();

        $cook = $this->makeCook();
        $this->actingAs($cook, 'sanctum')->postJson("/api/v1/staff/orders/{$order->uuid}/items/{$tea->id}/ready")->assertOk();
        Event::assertNotDispatched(OrderCourseReadyBroadcast::class);

        $this->actingAs($cook, 'sanctum')->postJson("/api/v1/staff/orders/{$order->uuid}/items/{$bread->id}/ready")->assertOk();
        Event::assertDispatched(OrderCourseReadyBroadcast::class, function (OrderCourseReadyBroadcast $event) use ($order, $tea, $bread) {
            $payload = $event->broadcastWith();

            return $event->broadcastAs() === 'order.course_ready'
                && $payload['order']['uuid'] === $order->uuid
                && $payload['course_no'] === 1
                && $payload['item_ids'] === [(int) $tea->id, (int) $bread->id]
                && $payload['waiter_id'] === (int) $this->waiter->id
                && $payload['table_id'] === (int) $this->table->id
                && in_array("private-pos-branch.{$this->branch->id}", array_map(fn ($channel) => $channel->name, $event->broadcastOn()), true);
        });
        Event::assertDispatchedTimes(OrderCourseReadyBroadcast::class, 1);

        $this->pos($this->waiter, 'GET', "/pos/orders/{$order->uuid}")->assertOk()->assertJsonPath('data.courses.0.status', 'ready');
    }

    public function test_courses_need_the_module_and_an_order_keeps_its_courses_when_it_is_switched_off(): void
    {
        $order = $this->threeCourses();

        $this->profile(FormatProfile::RESTORAN, ['courses' => false]);
        $this->openPos($this->waiter, $this->tapchan, 2, ['items' => [$this->line($this->tea, 1, ['course_no' => 2])]])
            ->assertForbidden()->assertJsonPath('code', 'module_disabled')->assertJsonPath('module', 'courses');
        // Without a course the order opens as before
        $this->openPos($this->waiter, $this->tapchan, 2, ['items' => [$this->line($this->tea, 1)]])->assertCreated();

        // The open order still sends its held course
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/courses/2/send", ['version' => 1])->assertOk();
    }

    public function test_a_course_send_checks_the_version_the_number_and_the_waiter(): void
    {
        $order = $this->threeCourses();

        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/courses/2/send", ['version' => 99])->assertStatus(409)
            ->assertJsonPath('data.reason', 'version_conflict');
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/courses/12/send", ['version' => 1])->assertStatus(422)
            ->assertJsonPath('data.reason', 'course_invalid');
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'items' => [$this->line($this->tea, 1, ['course_no' => 10])]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.course_no');

        // Another waiter's order: a manager's approval
        $other = $this->makeStaff(\App\Enums\UserRole::WAITER, $this->branch);
        $this->pos($other, 'POST', "/pos/orders/{$order->uuid}/courses/2/send", ['version' => 1])->assertForbidden()
            ->assertJsonPath('data.reason', 'approval_required');

        // The Idempotency-Key replays the answer
        $key = (string) \Illuminate\Support\Str::uuid();
        $first = $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/courses/2/send", ['version' => 1], $key)->assertOk();
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/courses/2/send", ['version' => 1], $key)->assertOk()
            ->assertJsonPath('data.version', $first->json('data.version'));
        $this->assertSame(1, AuditLog::where('action', 'pos.course_sent')->count());
    }
}
