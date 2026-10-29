<?php

namespace App\Domain\Pos\Orders;

use App\Domain\Pos\Contracts\PickupTracker;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Domain\Pos\Shifts\QueueNumbers;
use App\Enums\KitchenItemStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Events\Broadcast\QueueNumberBroadcast;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Notifications\TelegramNotificationService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use Illuminate\Support\Facades\DB;

/**
 * P6-13 (A2, POS_SPEC 2.14): the pickup queue of the counter orders with a queue number — `preparing → ready →
 * handed` (orders.pickup_status), announced on `pos-branch.{id}` (QueueNumberBroadcast) for the tills, the kitchen
 * display and the customer display ("Tayyorlanmoqda / Tayyor").
 *
 * Which orders: a takeaway (`pos_takeaway`), a hall order without a table (`pos_hall`, eaten at the counter's tables)
 * and an online pickup order (Telegram, mobile), with a queue number, in a branch with the `queue_calling` module.
 *
 * How they move:
 *  - POS: its lines sent to the kitchen → `preparing`; the last sent line bumped ready on the kitchen display (or the
 *    takeaway becoming `ready`) → `ready`; a line recalled → `preparing` again; POST /pos/orders/{uuid}/ready (a
 *    counter without a kitchen display) → `ready`; POST .../handed → `handed` (its lines served). The order's own
 *    status stays the POS one (a paid order is closed, `delivered`, while the kitchen still cooks it: keepsKitchen);
 *  - online pickup: it gets its number from the branch's block when accepted, then follows its status (accepted /
 *    preparing → `preparing`, ready → `ready`, delivered → `handed`); POST .../ready and .../handed move its status;
 *    the customer is told "Buyurtmangiz tayyor, raqam N" with the status alert and again on every call;
 *  - a cancelled or rejected order leaves the queue (`queue.canceled`).
 * POST .../call re-announces a ready number (`queue.ready` with `called: true`, the call counted). None of this ever
 * blocks a payment, a bill or a close.
 */
final class PickupQueue implements PickupTracker
{
    /** The `queue` object of a POS order (PosOrderPresenter, the till's order lists). */
    public const QUEUE = 'array{number: int, business_date: string|null, status: \\App\\Domain\\Pos\\Orders\\PickupStatus|null, ready_at: string|null, called_at: string|null, call_count: int, handed_at: string|null}';

    /** The talon ("navbat talonchasi") the till prints with the receipt (BillPresenter `ticket`). */
    public const TICKET = 'array{number: int, business_date: string|null, order_label: string, message: string}';

    /** The talon's line under the number (pos_core QueueSlip). */
    public const TICKET_MESSAGE = "Buyurtmangiz tayyor bo'lganda raqamingiz ekranda chiqadi va chaqiriladi.";

    public function __construct(
        private readonly QueueNumbers $numbers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * A counter order: takeaway, a hall order without a table, an online pickup order.
     */
    public static function isCounterOrder(Order $order): bool
    {
        return match (true) {
            $order->channel === OrderChannel::POS_TAKEAWAY => true,
            $order->channel === OrderChannel::POS_HALL => $order->table_id === null,
            default => !$order->channel->isPos() && $order->order_type === OrderType::PICKUP,
        };
    }

    /**
     * Whether the order takes part in the pickup queue (a counter order with a number, `queue_calling` on).
     */
    public function applies(Order $order): bool
    {
        if ($order->queue_number === null || $order->branch_id === null || !self::isCounterOrder($order)) {
            return false;
        }
        $branch = Branch::find($order->branch_id);

        return $branch !== null && QueueNumbers::callingOn($branch);
    }

    /**
     * The order's number and where it is in the pickup queue; null without a number.
     *
     * @return array{number: int, business_date: string|null, status: PickupStatus|null, ready_at: string|null, called_at: string|null, call_count: int, handed_at: string|null}|null
     */
    public static function present(Order $order): ?array
    {
        if ($order->queue_number === null) {
            return null;
        }

        return [
            'number' => (int) $order->queue_number,
            'business_date' => $order->business_date?->toDateString(),
            // The enum itself: it serializes to its value and documents as the named PickupStatus schema
            'status' => self::statusOf($order),
            'ready_at' => $order->pickup_ready_at?->toJSON(),
            'called_at' => $order->pickup_called_at?->toJSON(),
            'call_count' => (int) $order->getAttribute('pickup_call_count'),
            'handed_at' => $order->pickup_handed_at?->toJSON(),
        ];
    }

    /**
     * The talon of a counter order with a number in a branch with the `queue_numbers` module; null otherwise (a
     * choyxona room keeps its plain receipt).
     *
     * @return array{number: int, business_date: string|null, order_label: string, message: string}|null
     */
    public static function ticket(Order $order): ?array
    {
        if ($order->queue_number === null || $order->branch_id === null || !self::isCounterOrder($order)) {
            return null;
        }
        $branch = Branch::find($order->branch_id);
        if ($branch === null || !QueueNumbers::numbersOn($branch)) {
            return null;
        }

        return [
            'number' => (int) $order->queue_number,
            'business_date' => $order->business_date?->toDateString(),
            'order_label' => $order->order_type === OrderType::DINE_IN ? 'Zalda' : 'Olib ketish',
            'message' => self::TICKET_MESSAGE,
        ];
    }

    public static function statusOf(Order $order): ?PickupStatus
    {
        $value = $order->getAttribute('pickup_status');

        return is_string($value) ? PickupStatus::tryFrom($value) : null;
    }

    // --- hooks of the order core ---------------------------------------------------------------------------------

    /**
     * POS lines went to the kitchen (PosOrderService::fire, the order locked): the number is being prepared (again,
     * when the customer added to an order already ready or handed).
     */
    public function linesSent(Order $order): void
    {
        if (self::statusOf($order) === PickupStatus::PREPARING || !$this->applies($order)) {
            return;
        }
        $this->move($order, PickupStatus::PREPARING);
    }

    public function kitchenChanged(Order $order): void
    {
        $status = self::statusOf($order);
        if ($status !== PickupStatus::PREPARING && $status !== PickupStatus::READY) {
            return;
        }

        $cooking = $this->cooking($order);
        if ($status === PickupStatus::PREPARING && !$cooking && $this->hasKitchenLines($order)) {
            $this->move($order, PickupStatus::READY);
        } elseif ($status === PickupStatus::READY && $cooking) {
            $this->move($order, PickupStatus::PREPARING);
        }
    }

    public function statusChanged(Order $order, OrderStatus $from, OrderStatus $to): void
    {
        $status = self::statusOf($order);
        if ($to->isCancellation()) {
            if ($status !== null) {
                $order->forceFill(['pickup_status' => null])->save();
                event(new QueueNumberBroadcast($order, 'queue.canceled'));
            }

            return;
        }

        if ($order->channel->isPos()) {
            // The till and the kitchen move a POS number; the takeaway's own `ready` (every line bumped) is the
            // kitchen's ready too. Its `delivered` is the payment, not the hand-over
            if ($to === OrderStatus::READY && $status === PickupStatus::PREPARING) {
                $this->move($order, PickupStatus::READY);
            }

            return;
        }

        if (!self::isCounterOrder($order) || $order->branch_id === null) {
            return;
        }

        if (in_array($to, [OrderStatus::ACCEPTED, OrderStatus::PREPARING], true)) {
            $this->numberOnline($order);
            if ($status !== PickupStatus::PREPARING && $this->applies($order)) {
                $this->move($order, PickupStatus::PREPARING);
            }
        } elseif ($to === OrderStatus::READY && $status !== PickupStatus::READY && $this->applies($order)) {
            $this->move($order, PickupStatus::READY);
        } elseif ($to === OrderStatus::DELIVERED && $status !== null && $status !== PickupStatus::HANDED) {
            $this->move($order, PickupStatus::HANDED);
        }
    }

    public function keepsKitchen(Order $order): bool
    {
        $status = self::statusOf($order);

        return $order->channel->isPos() && ($status === PickupStatus::PREPARING || $status === PickupStatus::READY);
    }

    // --- the till's actions ----------------------------------------------------------------------------------------

    /**
     * POST /pos/orders/{uuid}/ready: the number is ready (a counter without a kitchen display, or the cashier
     * overriding it). An online order moves to `ready` (the customer is told); a POS takeaway still open too; any other
     * POS order has its lines marked ready. Already ready: a no-op; handed: 422.
     */
    public function markReady(Order $order, User $actor): Order
    {
        return $this->locked($order, function (Order $order) use ($actor) {
            $this->assertInQueue($order);
            $status = self::statusOf($order);
            if ($status === PickupStatus::READY) {
                return $order;
            }
            if ($status === PickupStatus::HANDED) {
                throw new PosOrderException('Bu raqam allaqachon mijozga berilgan.', 422, 'already_handed');
            }

            if (!$order->channel->isPos()) {
                if ($order->status === OrderStatus::ACCEPTED) {
                    $this->transition($order, OrderStatus::PREPARING, $actor, "Navbat: tayyorlanmoqda ({$actor->name})");
                }
                $this->transition($order->refresh(), OrderStatus::READY, $actor, "Navbat: tayyor ({$actor->name})");

                return $order->refresh();
            }

            if ($order->status === OrderStatus::PREPARING && $order->closed_at === null && $order->order_type !== OrderType::DINE_IN) {
                // The takeaway is ready like a kitchen display would make it (its lines ready, the status hook moves the number)
                $this->transition($order, OrderStatus::READY, $actor, "Navbat: tayyor ({$actor->name})");
                $order->refresh();
            }
            if (self::statusOf($order) !== PickupStatus::READY) {
                OrderItem::where('order_id', $order->id)->whereNotNull('sent_at')
                    ->whereIn('kitchen_status', KitchenItemStatus::cookingValues())
                    ->update(['kitchen_status' => KitchenItemStatus::READY->value, 'ready_at' => now()]);
                $this->move($order, PickupStatus::READY);
            }
            $this->record('pos.queue_ready', $order, $actor);

            return $order;
        });
    }

    /**
     * POST /pos/orders/{uuid}/call: announce a ready number again (the customer display and the till's speaker; an
     * online customer gets the message again). 422 `not_ready` unless it is ready.
     */
    public function call(Order $order, User $actor): Order
    {
        return $this->locked($order, function (Order $order) use ($actor) {
            $this->assertInQueue($order);
            if (self::statusOf($order) !== PickupStatus::READY) {
                throw new PosOrderException("Raqam hali tayyor emas: faqat tayyor raqam chaqiriladi.", 422, 'not_ready');
            }

            $order->forceFill([
                'pickup_called_at' => now(),
                'pickup_call_count' => (int) $order->getAttribute('pickup_call_count') + 1,
            ])->save();
            event(new QueueNumberBroadcast($order, 'queue.ready', true));
            $this->notifyCustomer($order);
            $this->record('pos.queue_called', $order, $actor);

            return $order;
        });
    }

    /**
     * POST /pos/orders/{uuid}/handed: the customer took it. An online order is delivered (OrderStatusService); a POS
     * order's lines are served (its bill and payment are not touched). Already handed: a no-op.
     */
    public function hand(Order $order, User $actor): Order
    {
        return $this->locked($order, function (Order $order) use ($actor) {
            $this->assertInQueue($order);
            if (self::statusOf($order) === PickupStatus::HANDED) {
                return $order;
            }

            if (!$order->channel->isPos()) {
                $this->transition($order, OrderStatus::DELIVERED, $actor, "Navbat: mijozga berildi ({$actor->name})");

                return $order->refresh();
            }

            OrderItem::where('order_id', $order->id)->whereNotNull('sent_at')
                ->whereIn('kitchen_status', [KitchenItemStatus::SENT->value, KitchenItemStatus::READY->value])
                ->update(['kitchen_status' => KitchenItemStatus::SERVED->value]);
            $this->move($order, PickupStatus::HANDED);
            $this->record('pos.queue_handed', $order, $actor);

            return $order;
        });
    }

    // --- internals -------------------------------------------------------------------------------------------------

    /**
     * An online pickup order entering the queue gets the next number of its branch's day (the branch's block).
     */
    private function numberOnline(Order $order): void
    {
        if ($order->queue_number !== null) {
            return;
        }
        $branch = Branch::find($order->branch_id);
        if ($branch === null || !QueueNumbers::numbersOn($branch)) {
            return;
        }

        $date = $order->business_date?->toDateString() ?? BusinessDay::of($branch);
        $order->setAttribute('business_date', $date);
        $this->numbers->number($order, $branch, $date);
        $order->save();
    }

    private function move(Order $order, PickupStatus $to): void
    {
        $order->forceFill(match ($to) {
            PickupStatus::PREPARING => ['pickup_status' => $to->value, 'pickup_ready_at' => null, 'pickup_handed_at' => null],
            PickupStatus::READY => ['pickup_status' => $to->value, 'pickup_ready_at' => now(), 'pickup_handed_at' => null],
            PickupStatus::HANDED => ['pickup_status' => $to->value, 'pickup_handed_at' => now()],
        })->save();

        event(new QueueNumberBroadcast($order, match ($to) {
            PickupStatus::PREPARING => 'queue.preparing',
            PickupStatus::READY => 'queue.ready',
            PickupStatus::HANDED => 'queue.handed',
        }));
    }

    /**
     * A line still to cook (not sent yet, or in the kitchen); voided and served lines are done.
     */
    private function cooking(Order $order): bool
    {
        return OrderItem::where('order_id', $order->id)->whereIn('kitchen_status', KitchenItemStatus::cookingValues())->exists();
    }

    private function hasKitchenLines(Order $order): bool
    {
        return OrderItem::where('order_id', $order->id)->whereIn('kitchen_status', KitchenItemStatus::doneValues())->exists();
    }

    private function assertInQueue(Order $order): void
    {
        if (!$this->applies($order)) {
            throw new PosOrderException(
                "Bu buyurtma navbat raqami bilan olib ketish navbatida emas.",
                422,
                'not_in_queue',
            );
        }
    }

    private function transition(Order $order, OrderStatus $to, User $actor, string $notes): void
    {
        try {
            app(OrderStatusService::class)->transition($order, $to, $actor, ['notes' => $notes]);
        } catch (OrderTransitionException $e) {
            throw new PosOrderException($e->getMessage(), $e->status, 'status');
        }
    }

    /**
     * The order's row lock for the action, in one transaction.
     *
     * @param  \Closure(Order): Order  $change
     */
    private function locked(Order $order, \Closure $change): Order
    {
        return DB::transaction(fn () => $change(Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail()));
    }

    /**
     * An online customer hears "Buyurtmangiz tayyor, raqam N" again (queued, after commit; the status alert's text).
     */
    private function notifyCustomer(Order $order): void
    {
        if ($order->channel->isPos()) {
            return;
        }
        $id = (int) $order->id;
        dispatch(function () use ($id) {
            $order = Order::with(['user', 'branch', 'store'])->find($id);
            if ($order !== null && $order->status === OrderStatus::READY) {
                app(TelegramNotificationService::class)->sendOrderStatusChanged($order);
            }
        })->afterCommit();
    }

    private function record(string $action, Order $order, User $actor): void
    {
        $this->audit->record($action, $order, [], ['pickup_status' => $order->getAttribute('pickup_status')], [
            'short_code' => $order->short_code,
            'queue_number' => $order->queue_number,
        ], ['actor' => $actor]);
    }
}
