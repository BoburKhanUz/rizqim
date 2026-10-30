<?php

namespace App\Domain\Pos\Orders;

use App\Domain\Pos\Contracts\CourseTracker;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\FireMode;
use App\Enums\KitchenItemStatus;
use App\Events\Broadcast\OrderCourseReadyBroadcast;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * P6-21 (B5, module `courses`): the courses of a POS hall order (docs/POS_SPEC.md "Kurslar").
 *
 *  - A line carries its course (`course_no` 1–9; none: an ordinary line). A course after the one the kitchen already
 *    has is held (`fire_mode = hold`): "Oshxonaga yuborish" (every `now` line) never sends it early;
 *  - "N-kursni yuborish" (PosOrderService::sendCourse) sends the held lines of that course; a line added later to a
 *    course that is already in the kitchen goes with the next send, like any `now` line;
 *  - the kitchen sees the course on the ticket (`course_no`); when every line of a course that reached the kitchen is
 *    ready, the waiter hears it (order.course_ready, via KitchenService → CourseTracker); voiding the last line of a
 *    course that was still cooking announces it too (PosOrderService::void → lineVoided).
 *
 * The module gates the new flow only: an order that has courses keeps them (and can still send them) after the owner
 * switches the module off; offline lines keep the device's course when it is on, else lose it (a sale is never
 * refused, POS_SPEC 5.3).
 */
class PosCourses implements CourseTracker
{
    public const MAX_COURSE = 9;

    public function __construct(
        private readonly PosModules $modules,
    ) {}

    /**
     * The course rule over a round's parsed lines (PosLineBuilder::parse): strict (online) refuses courses while the
     * module is off (403 module_disabled); lenient (offline sync, automatic lines) drops them.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    public function prepare(Order $order, Branch $branch, array $lines, bool $strict): array
    {
        $courses = array_filter($lines, fn (array $line) => ($line['course_no'] ?? null) !== null);
        if ($courses === []) {
            return $lines;
        }

        if (!$this->modules->enabled($branch, PosModule::COURSES)) {
            if ($strict) {
                $this->modules->assertEnabled($branch, PosModule::COURSES);
            }

            return array_map(fn (array $line) => ['course_no' => null] + $line, $lines);
        }

        $fired = $this->firedCourse($order);
        foreach ($lines as $k => $line) {
            $course = $line['course_no'] ?? null;
            if ($course !== null && $course > $fired) {
                $lines[$k]['fire_mode'] = FireMode::HOLD;
            }
        }

        return $lines;
    }

    /**
     * The last course the kitchen has (at least 1: the first course is always fired with the next send).
     */
    public function firedCourse(Order $order): int
    {
        $sent = OrderItem::query()
            ->where('order_id', $order->id)
            ->whereNotNull('course_no')
            ->whereNotNull('sent_at')
            ->where('kitchen_status', '!=', KitchenItemStatus::VOID->value)
            ->max('course_no');

        return max(1, (int) $sent);
    }

    /**
     * The held lines of a course, not sent yet (ids in line order).
     *
     * @return list<int>
     */
    public function heldLines(Order $order, int $courseNo): array
    {
        return OrderItem::query()
            ->where('order_id', $order->id)
            ->where('course_no', $courseNo)
            ->where('kitchen_status', KitchenItemStatus::PENDING->value)
            ->whereNull('sent_at')
            ->whereNotNull('fire_mode')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * The order's courses for the till and the waiter: per course its live lines and where they are — `held` (none
     * sent yet), `sent` (in the kitchen), `ready` (every sent line ready or served, nothing left to send), `served`.
     *
     * @param  iterable<OrderItem>  $items
     * @return list<array{course_no: int, status: string, items: int, sent: int, ready: int, served: int}>
     */
    public static function summary(iterable $items): array
    {
        $courses = [];
        foreach ($items as $item) {
            if ($item->course_no === null || $item->isVoid()) {
                continue;
            }
            $row = $courses[$item->course_no] ?? ['course_no' => (int) $item->course_no, 'status' => 'held', 'items' => 0, 'sent' => 0, 'ready' => 0, 'served' => 0];
            $row['items']++;
            if ($item->sent_at !== null) {
                $row['sent']++;
            }
            if ($item->kitchen_status === KitchenItemStatus::READY) {
                $row['ready']++;
            }
            if ($item->kitchen_status === KitchenItemStatus::SERVED) {
                $row['served']++;
            }
            $courses[$item->course_no] = $row;
        }
        ksort($courses);

        foreach ($courses as $no => $row) {
            $courses[$no]['status'] = match (true) {
                $row['served'] === $row['items'] => 'served',
                $row['ready'] + $row['served'] === $row['items'] => 'ready',
                $row['sent'] > 0 => 'sent',
                default => 'held',
            };
        }

        return array_values($courses);
    }

    /**
     * CourseTracker: a line bumped ready completes its course when no live line of the course is still to cook (held,
     * sent, or not sent yet): the waiter hears order.course_ready (after commit). A recall says nothing.
     */
    public function kitchenChanged(Order $order, OrderItem $item): void
    {
        if ($item->course_no === null || $item->kitchen_status !== KitchenItemStatus::READY || !$order->channel->isPos()) {
            return;
        }

        $this->announceIfReady($order, (int) $item->course_no, false);
    }

    /**
     * Review C9: a line voided while it was still to cook (`$was`: its status before the void) may have been the last
     * thing its course waited for: when what is left of the course is ready (at least one line waiting at the pass),
     * the waiter hears order.course_ready, once — a line voided after it was ready or served never announces again.
     * Called by PosOrderService::void inside its transaction, the order locked.
     */
    public function lineVoided(Order $order, OrderItem $item, KitchenItemStatus $was): void
    {
        if ($item->course_no === null || !in_array($was->value, KitchenItemStatus::cookingValues(), true) || !$order->channel->isPos()) {
            return;
        }

        $this->announceIfReady($order, (int) $item->course_no, true);
    }

    /**
     * Announces the course when none of its live lines is still to cook (held, sent, or not sent yet); `$needsReady`:
     * only when one of them is ready (a course served in full has nothing left to fetch).
     */
    private function announceIfReady(Order $order, int $courseNo, bool $needsReady): void
    {
        $lines = OrderItem::query()
            ->where('order_id', $order->id)
            ->where('course_no', $courseNo)
            ->where('kitchen_status', '!=', KitchenItemStatus::VOID->value)
            ->get(['id', 'kitchen_status']);
        $cooking = $lines->contains(fn (OrderItem $line) => in_array($line->kitchen_status->value, KitchenItemStatus::cookingValues(), true));
        if ($lines->isEmpty() || $cooking) {
            return;
        }
        if ($needsReady && !$lines->contains(fn (OrderItem $line) => $line->kitchen_status === KitchenItemStatus::READY)) {
            return;
        }

        event(new OrderCourseReadyBroadcast($order, $courseNo, $lines->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all()));
    }
}
