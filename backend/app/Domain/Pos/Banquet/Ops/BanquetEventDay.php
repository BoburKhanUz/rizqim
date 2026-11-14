<?php

namespace App\Domain\Pos\Banquet\Ops;

use App\Domain\Pos\Banquet\BanquetEventService;
use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Domain\Pos\Banquet\BanquetException;
use App\Domain\Pos\Orders\PosCharge;
use App\Domain\Pos\Orders\PosChargesCalculator;
use App\Domain\Pos\Orders\PosOrderService;
use App\Enums\OrderChannel;
use App\Models\BanquetEvent;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderChargeWaiver;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * P6-54: the event day.
 *
 *  - start: a confirmed event opens its tab — a POS hall order (no table; the event's halls as its hall) bound to the
 *    event (banquet_events.order_id) — and becomes `in_progress`. Drinks and extra dishes ordered during the event are
 *    ordinary lines of that order (rounds, kitchen tickets, voids as usual). The place fee (joy narxi) is waived on it
 *    (the package covers the seats); the branch's service charge applies to those lines as on any hall order. Starting
 *    again returns the same tab;
 *  - guests_actual: the manager writes how many came (confirmed or in progress); the final bill uses it (never below
 *    the guaranteed or minimum guests);
 *  - staff: the waiters assigned to the event (their event_rate pay, StaffPayMetrics).
 *
 * Every step is audited and moves the event's version.
 */
final class BanquetEventDay
{
    public function __construct(
        private readonly BanquetEventService $events,
        private readonly PosOrderService $orders,
        private readonly PosChargesCalculator $charges,
        private readonly AuditLogger $audit,
    ) {}

    public function start(BanquetEvent $event, User $actor, ?int $deviceId, ?int $version = null, ?int $waiterId = null): BanquetEvent
    {
        $event = BanquetEvent::whereKey($event->id)->firstOrFail();
        if ($event->status === BanquetEventStatus::IN_PROGRESS && $event->order_id !== null) {
            return $event;
        }

        return $this->events->transition($event, BanquetEventStatus::IN_PROGRESS, $actor, $version, 'banquet.started', function (BanquetEvent $event) use ($actor, $deviceId, $waiterId) {
            $branch = Branch::findOrFail($event->branch_id);
            $halls = $event->halls()->orderBy('halls.id')->get(['halls.id', 'halls.name']);
            $guests = max((int) ($event->guests_actual ?? 0), (int) $event->guests_guaranteed, (int) $event->guests_minimum);

            // The guests are written after opening: the tea-and-bread lines per guest of an ordinary hall order are not
            // added for a banquet (the package has them)
            $order = $this->orders->openOrder($branch, OrderChannel::POS_HALL, null, 0, $waiterId, $deviceId, $actor, [
                'note' => mb_substr('Tadbir: ' . ($event->title ?: $event->customer_name) . ', ' . $event->event_date->format('d.m.Y'), 0, 255),
            ]);
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $order->forceFill([
                'guests_count' => $guests,
                'hall_id' => $halls->first()?->getAttribute('id'),
                'hall_ref' => $halls->isNotEmpty() ? mb_substr($halls->pluck('name')->implode(', '), 0, 255) : null,
            ])->save();
            OrderChargeWaiver::firstOrCreate(
                ['order_id' => $order->id, 'charge' => PosCharge::PLACE_FEE->value],
                ['reason' => 'Banket: joy narxi paketga kiradi', 'waived_by' => $actor->id],
            );
            $this->charges->recalculate($order);

            $event->order_id = $order->id;
            $event->started_at = now();
        });
    }

    public function setGuestsActual(BanquetEvent $event, int $guests, User $actor, ?int $version = null): BanquetEvent
    {
        $event = DB::transaction(function () use ($event, $guests, $actor, $version) {
            $event = $this->events->lockFresh($event, $version);
            if (!in_array($event->status, [BanquetEventStatus::CONFIRMED, BanquetEventStatus::IN_PROGRESS], true)) {
                throw new BanquetException("Tadbir \"{$event->status->label()}\" holatida: kelgan mehmonlar sonini o'zgartirib bo'lmaydi.", 'invalid_transition', 409, ['status' => $event->status->value]);
            }
            $before = $event->guests_actual;
            $event->guests_actual = $guests;
            $event->version = $event->version + 1;
            $event->save();
            $this->audit->record('banquet.guests_actual', $event, ['guests_actual' => $before], ['guests_actual' => $guests], [], ['actor' => $actor, 'store_id' => (int) $event->store_id, 'branch_id' => (int) $event->branch_id]);

            return $event;
        });
        event(new \App\Domain\Pos\Banquet\BanquetEventChanged($event, 'guests_actual', false));

        return $event;
    }

    /**
     * @param  list<int>  $userIds  staff of the event's store
     */
    public function setStaff(BanquetEvent $event, array $userIds, User $actor): BanquetEvent
    {
        return DB::transaction(function () use ($event, $userIds, $actor) {
            $event = $this->events->lockFresh($event, null);
            $valid = User::where('store_id', $event->store_id)->whereIn('id', $userIds === [] ? [0] : $userIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (count($valid) !== count(array_unique($userIds))) {
                throw new BanquetException('Xodim topilmadi.', 'staff_not_found', 422);
            }
            $before = self::staffIds($event);
            DB::table('banquet_event_staff')->where('banquet_event_id', $event->id)->whereNotIn('user_id', $valid === [] ? [0] : $valid)->delete();
            foreach ($valid as $userId) {
                DB::table('banquet_event_staff')->insertOrIgnore(['banquet_event_id' => $event->id, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);
            }
            $event->version = $event->version + 1;
            $event->save();
            $this->audit->record('banquet.staff_assigned', $event, ['staff' => $before], ['staff' => self::staffIds($event)], [], ['actor' => $actor, 'store_id' => (int) $event->store_id, 'branch_id' => (int) $event->branch_id]);

            return $event;
        });
    }

    /**
     * @return list<int>
     */
    public static function staffIds(BanquetEvent $event): array
    {
        return DB::table('banquet_event_staff')->where('banquet_event_id', $event->id)->orderBy('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }
}
