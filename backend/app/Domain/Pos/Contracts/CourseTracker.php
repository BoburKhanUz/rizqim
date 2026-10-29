<?php

namespace App\Domain\Pos\Contracts;

use App\Models\Order;
use App\Models\OrderItem;

/**
 * P6-21: what the kitchen core (KitchenService) tells the POS courses. Bound by PosQueueServiceProvider to
 * App\Domain\Pos\Orders\PosCourses; unbound, the core behaves as before.
 */
interface CourseTracker
{
    /**
     * A line was bumped ready or recalled (inside the transaction, the order locked): when every line of its course
     * that went to the kitchen is ready, the waiter hears "N-kurs tayyor" (order.course_ready, after commit).
     */
    public function kitchenChanged(Order $order, OrderItem $item): void;
}
