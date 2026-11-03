<?php

namespace App\Domain\Pos\Shifts;

use App\Enums\OrderChannel;
use App\Models\Branch;
use App\Models\Order;
use App\Models\PosShift;

/**
 * Stamps a new POS order (pos_hall, pos_takeaway) with its business day, its queue number within that day and branch
 * (1, 2, 3... restarting every business day, POS_SPEC 2.3) and the open shift of the terminal that opened it. Runs on
 * the Order `creating` event (PosBillingServiceProvider), so whoever opens POS orders (P3-12's PosOrderOpener, a
 * booking's "Keldi") gets it without calling anything. Values already set are kept. The number is taken under a
 * per branch and day advisory lock held until the opening transaction commits, so two terminals never share one.
 * P6-13: the number itself comes from QueueNumbers (till blocks with the `queue_numbers` module). P6-34: a takeaway for
 * a later pickup takes its pickup's business day (and that day's number), so a number never repeats within a day.
 */
final class OrderStamp
{
    public const CHANNELS = [OrderChannel::POS_HALL, OrderChannel::POS_TAKEAWAY];

    public function stamp(Order $order): void
    {
        if (!in_array($order->channel, self::CHANNELS, true) || $order->branch_id === null) {
            return;
        }

        $branch = Branch::find($order->branch_id);
        if ($branch === null) {
            return;
        }

        $date = $order->getAttribute('business_date');
        // P6-34 (review P6W3 C5): a takeaway for a later pickup belongs to its pickup's business day (ScheduledPickup::dayOf)
        $pickupAt = $order->getAttribute('pickup_at');
        $date = $date !== null ? \Illuminate\Support\Carbon::parse($date)->toDateString()
            : \App\Domain\Pos\Takeaway\ScheduledPickup::dayOf($branch, $pickupAt !== null ? \Illuminate\Support\Carbon::parse($pickupAt) : null);
        $order->setAttribute('business_date', $date);

        if ($order->getAttribute('shift_id') === null && $order->getAttribute('device_id') !== null) {
            $order->setAttribute('shift_id', PosShift::where('device_id', $order->getAttribute('device_id'))
                ->where('status', ShiftStatus::OPEN->value)
                ->value('id'));
        }

        // P6-13: blocks per till with the queue_numbers module, the P3-18 numbering without it (QueueNumbers). A number
        // the till proposes (printed offline, picked online) is kept when it is allowed, else the order gets the next
        // one (PosSyncApplier flags the difference: `queue_number_taken`)
        app(QueueNumbers::class)->number($order, $branch, $date);
    }
}
