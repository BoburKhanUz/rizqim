<?php

namespace App\Domain\Pos\Booking;

use App\Domain\Pos\Contracts\PosOrderOpener;
use App\Enums\OrderStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingPayment;
use App\Models\Branch;
use App\Models\Order;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The booking book (research report R14–R15): create and change bookings without double-booking a room, move them
 * through their states, and "Keldi": open the order (PosOrderOpener) with the pre-ordered dishes on hold and the
 * deposits linked to it.
 *
 * Double booking: every write that can take a room locks the branch row first (one booking write per branch at a
 * time), then checks for a booking that still holds its slot (requested, confirmed, arrived) on any of the same tables
 * with an overlapping time. A slot is [starts_at, ends_at): one evening ending at 18:00 and the next starting at 18:00
 * do not overlap. A banquet merging rooms holds every one of them.
 */
class BookingService
{
    public const MAX_TABLES = 20;

    public function __construct(
        protected DiningTables $tables,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{dining_table_id?: int|null, extra_table_ids?: list<int>|null, starts_at: Carbon, ends_at?: Carbon|null, guests_planned?: int|null, event_type?: string|null, contact_name: string, contact_phone?: string|null, source?: string|null, min_spend?: string|null, note?: string|null, confirm?: bool}  $data
     */
    public function create(Branch $branch, array $data, User $actor): Booking
    {
        $start = $data['starts_at']->copy()->utc();
        $end = ($data['ends_at'] ?? null)?->copy()->utc() ?? $start->copy()->addMinutes(PosSettingsReader::slotMinutes($branch));
        $this->checkTimes($start, $end);

        $booking = DB::transaction(function () use ($branch, $data, $actor, $start, $end) {
            Branch::whereKey($branch->id)->lockForUpdate()->first();

            [$main, $tableIds] = $this->resolveTables($branch, $data['dining_table_id'] ?? null, $data['extra_table_ids'] ?? []);
            $this->assertFree($branch->id, $tableIds, $start, $end, null);

            $confirm = (bool) ($data['confirm'] ?? false);
            $booking = Booking::create([
                'store_id' => $branch->store_id,
                'branch_id' => $branch->id,
                'dining_table_id' => $main,
                'starts_at' => $start,
                'ends_at' => $end,
                'guests_planned' => max(1, (int) ($data['guests_planned'] ?? 1)),
                'event_type' => $data['event_type'] ?? BookingEventType::OTHER->value,
                'contact_name' => $data['contact_name'],
                'contact_phone' => $data['contact_phone'] ?? null,
                'source' => $data['source'] ?? BookingSource::PHONE->value,
                'status' => $confirm ? BookingStatus::CONFIRMED : BookingStatus::REQUESTED,
                'confirmed_at' => $confirm ? now() : null,
                'min_spend' => isset($data['min_spend']) ? Money::of($data['min_spend'])->toString() : null,
                'note' => $data['note'] ?? null,
                'created_by' => $actor->id,
            ]);
            $this->syncTables($booking, $tableIds);

            $this->audit->record('booking.created', $booking, [], $this->auditFields($booking), [], ['actor' => $actor]);

            return $booking;
        });

        $this->changed($booking);

        if ($booking->status === BookingStatus::CONFIRMED) {
            $this->confirmedNow($booking, $actor);
        }

        return $booking;
    }

    /**
     * Changes the details of a booking that is still requested or confirmed. `version`, when sent, must be the one the
     * client read (409 otherwise). Times and tables are checked for conflicts again.
     *
     * @param  array<string, mixed>  $data  the fields of create() that were sent (dining_table_id/extra_table_ids together)
     */
    public function update(Booking $booking, array $data, User $actor, ?int $version = null): Booking
    {
        $booking = DB::transaction(function () use ($booking, $data, $actor, $version) {
            $branch = Branch::whereKey($booking->branch_id)->lockForUpdate()->firstOrFail();
            $booking = $this->lockFresh($booking, $version);

            if (!$booking->status->isEditable()) {
                throw new BookingException("Bu bronni o'zgartirib bo'lmaydi: u {$this->statusText($booking)}.", 'booking_not_editable');
            }

            $before = $this->auditFields($booking);

            $start = array_key_exists('starts_at', $data) ? $data['starts_at']->copy()->utc() : $booking->starts_at->copy();
            if (array_key_exists('ends_at', $data) && $data['ends_at'] !== null) {
                $end = $data['ends_at']->copy()->utc();
            } elseif (array_key_exists('starts_at', $data)) {
                // Moved without a new end: the booking keeps its length
                $end = $start->copy()->addSeconds($booking->starts_at->diffInSeconds($booking->ends_at));
            } else {
                $end = $booking->ends_at->copy();
            }
            $this->checkTimes($start, $end);

            $tableIds = $this->tableIdsOf($booking);
            $main = $booking->dining_table_id !== null ? (int) $booking->dining_table_id : null;
            if (array_key_exists('dining_table_id', $data) || array_key_exists('extra_table_ids', $data)) {
                [$main, $tableIds] = $this->resolveTables(
                    $branch,
                    array_key_exists('dining_table_id', $data) ? $data['dining_table_id'] : $main,
                    array_key_exists('extra_table_ids', $data) ? ($data['extra_table_ids'] ?? []) : array_values(array_diff($tableIds, [$main])),
                );
            }
            $this->assertFree($branch->id, $tableIds, $start, $end, (int) $booking->id);

            foreach (['guests_planned', 'event_type', 'contact_name', 'contact_phone', 'source', 'note'] as $field) {
                if (array_key_exists($field, $data)) {
                    $booking->{$field} = $field === 'guests_planned' ? max(1, (int) $data[$field]) : $data[$field];
                }
            }
            if (array_key_exists('min_spend', $data)) {
                $booking->min_spend = $data['min_spend'] !== null ? Money::of($data['min_spend'])->toString() : null;
            }
            $booking->starts_at = $start;
            $booking->ends_at = $end;
            $booking->dining_table_id = $main;
            $booking->version = $booking->version + 1;
            $booking->save();
            $this->syncTables($booking, $tableIds);

            $this->audit->record('booking.updated', $booking, $before, $this->auditFields($booking), [], ['actor' => $actor]);

            return $booking;
        });

        $this->changed($booking);

        return $booking;
    }

    public function confirm(Booking $booking, User $actor, ?int $version = null): Booking
    {
        $booking = $this->transition($booking, BookingStatus::CONFIRMED, $actor, $version, 'booking.confirmed', function (Booking $booking) {
            $booking->confirmed_at = now();
        });
        $this->confirmedNow($booking, $actor);

        return $booking;
    }

    /**
     * P3-38: the booking just became confirmed (created confirmed, confirmed, or confirmed by its first deposit): the
     * guest is sent the confirmation by itself when the branch wants it. After the commit; never fails the action.
     * Changing the booking later sends nothing again: the cashier resends by hand.
     */
    public function confirmedNow(Booking $booking, User $actor): void
    {
        app(BookingConfirmationService::class)->autoSend($booking, $actor);
    }

    /**
     * Deposits stay as they are: the manager refunds or retains them afterwards (BookingDepositService).
     */
    public function cancel(Booking $booking, string $reason, User $actor, ?int $version = null): Booking
    {
        return $this->transition($booking, BookingStatus::CANCELED, $actor, $version, 'booking.canceled', function (Booking $booking) use ($reason) {
            $booking->canceled_at = now();
            $booking->cancel_reason = $reason;
        }, ['reason' => $reason]);
    }

    /**
     * Only once the booked time has come: before that the guests may still arrive.
     */
    public function noShow(Booking $booking, User $actor, ?int $version = null): Booking
    {
        return $this->transition($booking, BookingStatus::NO_SHOW, $actor, $version, 'booking.no_show', function (Booking $booking) {
            if (now()->lessThan($booking->starts_at)) {
                throw new BookingException("Bron vaqti hali kelmagan: \"kelmadi\" deb faqat boshlanish vaqtidan keyin belgilanadi.", 'booking_not_started');
            }
            $booking->canceled_at = now();
        });
    }

    /**
     * "Keldi": opens the order of the booking's room (PosOrderOpener, channel pos_hall) with the guests who came and the
     * pre-ordered dishes on hold with their due times, and links the deposits to it (the order's prepayment,
     * AdvanceLedger). `tableId` seats them in another room of the branch than the booked one.
     */
    public function arrive(Booking $booking, User $actor, ?int $deviceId, ?int $guests = null, ?int $tableId = null, ?int $version = null): Booking
    {
        $booking = DB::transaction(function () use ($booking, $actor, $deviceId, $guests, $tableId, $version) {
            $branch = Branch::whereKey($booking->branch_id)->lockForUpdate()->firstOrFail();
            $booking = $this->lockFresh($booking, $version);

            if ($booking->status === BookingStatus::ARRIVED && $booking->order_id !== null) {
                throw new BookingException('Mehmonlar allaqachon kelgan: buyurtma ochilgan.', 'booking_already_arrived', 409, [
                    'order_uuid' => Order::whereKey($booking->order_id)->value('uuid'),
                ]);
            }
            $this->assertCanMove($booking, BookingStatus::ARRIVED);

            $table = $booking->dining_table_id !== null ? (int) $booking->dining_table_id : null;
            if ($tableId !== null && $tableId !== $table) {
                $found = $this->tables->ofBranch((int) $branch->id, [$tableId])->get($tableId);
                if ($found === null || !$found->is_active) {
                    throw new BookingException('Xona yoki stol topilmadi.', 'table_not_found');
                }
                $table = $tableId;
            }

            $held = $booking->items()->orderBy('due_at')->orderBy('id')->get()->map(fn (BookingItem $item) => array_filter([
                'product_variant_id' => (int) $item->product_variant_id,
                'quantity' => Quantity::of($item->quantity)->toString(),
                'modifiers' => array_map(fn (array $modifier) => [
                    'modifier_id' => (int) $modifier['id'],
                    'quantity' => (int) ($modifier['quantity'] ?? 1),
                ], $item->modifiers ?? []),
                'note' => $item->note,
                'due_at' => $item->due_at?->toJSON(),
            ], fn ($value) => $value !== null && $value !== []))->values()->all();

            $order = app(PosOrderOpener::class)->open(
                (int) $booking->branch_id,
                'pos_hall',
                $table,
                max(1, $guests ?? (int) $booking->guests_planned),
                null,
                $deviceId,
                (int) $actor->id,
                (int) $booking->id,
                $held,
            );

            // The opener records the booking on the order (orders.booking_id); make sure of it
            if ((int) ($order->getAttribute('booking_id') ?? 0) !== (int) $booking->id) {
                $order->forceFill(['booking_id' => $booking->id])->save();
            }

            BookingPayment::where('booking_id', $booking->id)
                ->where('status', BookingPaymentStatus::TAKEN->value)
                ->update(['order_id' => $order->id, 'updated_at' => now()]);

            $before = $this->auditFields($booking);
            $booking->status = BookingStatus::ARRIVED;
            $booking->arrived_at = now();
            $booking->order_id = $order->id;
            $booking->dining_table_id = $table ?? $booking->dining_table_id;
            $booking->version = $booking->version + 1;
            $booking->save();

            $this->audit->record('booking.arrived', $booking, $before, $this->auditFields($booking), [
                'order_uuid' => $order->uuid,
                'held_items' => count($held),
            ], ['actor' => $actor]);

            return $booking;
        });

        $this->changed($booking);

        return $booking;
    }

    /**
     * The booking's order ended (closed, canceled): the booking is completed. Called when an order's status changes
     * (BookingServiceProvider listens to OrderStatusUpdated); P3-18 may call it directly when it closes an order.
     * Nothing for an order without a booking or a booking not in "arrived".
     */
    public function completeForOrder(Order $order): void
    {
        $bookingId = $order->getAttribute('booking_id');
        if ($bookingId === null || !in_array($order->status, [OrderStatus::DELIVERED, OrderStatus::CANCELED, OrderStatus::REJECTED, OrderStatus::REFUNDED], true)) {
            return;
        }

        $booking = DB::transaction(function () use ($bookingId) {
            $booking = Booking::whereKey($bookingId)->lockForUpdate()->first();
            if ($booking === null || $booking->status !== BookingStatus::ARRIVED) {
                return null;
            }

            $booking->status = BookingStatus::COMPLETED;
            $booking->completed_at = now();
            $booking->version = $booking->version + 1;
            $booking->save();

            $this->audit->record('booking.completed', $booking, ['status' => BookingStatus::ARRIVED->value], ['status' => BookingStatus::COMPLETED->value], [], ['actor' => null]);

            return $booking;
        });

        if ($booking !== null) {
            $this->changed($booking);
        }
    }

    /**
     * Bookings that still hold their slot on any of `$tableIds` and overlap [start, end).
     *
     * @param  list<int>  $tableIds
     * @return Collection<int, Booking>
     */
    public function conflicts(int $branchId, array $tableIds, Carbon $start, Carbon $end, ?int $exceptId = null): Collection
    {
        if ($tableIds === []) {
            return new Collection();
        }

        return Booking::query()
            ->where('branch_id', $branchId)
            ->whereIn('status', array_map(fn (BookingStatus $status) => $status->value, array_filter(BookingStatus::cases(), fn (BookingStatus $status) => $status->holdsSlot())))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->whereHas('tables', fn ($query) => $query->whereIn('dining_table_id', $tableIds))
            ->with('tables')
            ->orderBy('starts_at')
            ->get()
            ->toBase();
    }

    /**
     * Every table the booking occupies (the main one first).
     *
     * @return list<int>
     */
    public function tableIdsOf(Booking $booking): array
    {
        $ids = $booking->tables()->orderBy('id')->pluck('dining_table_id')->map(fn ($id) => (int) $id)->all();
        $main = $booking->dining_table_id !== null ? (int) $booking->dining_table_id : null;

        return array_values(array_unique(array_merge($main !== null ? [$main] : [], $ids)));
    }

    /**
     * The booking locked for this transaction, its version checked.
     */
    public function lockFresh(Booking $booking, ?int $version): Booking
    {
        $fresh = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
        if ($version !== null && $version !== $fresh->version) {
            throw new BookingException("Bron boshqa qurilmada o'zgartirilgan. Yangilab, qayta urinib ko'ring.", 'stale_version', 409, ['version' => $fresh->version]);
        }

        return $fresh;
    }

    public function changed(Booking $booking): void
    {
        event(new BookingChangedBroadcast($booking));
    }

    /**
     * @param  callable(Booking): void  $apply
     * @param  array<string, mixed>  $meta
     */
    private function transition(Booking $booking, BookingStatus $next, User $actor, ?int $version, string $action, callable $apply, array $meta = []): Booking
    {
        $booking = DB::transaction(function () use ($booking, $next, $actor, $version, $action, $apply, $meta) {
            $booking = $this->lockFresh($booking, $version);
            $this->assertCanMove($booking, $next);

            $before = $this->auditFields($booking);
            $apply($booking);
            $booking->status = $next;
            $booking->version = $booking->version + 1;
            $booking->save();

            $this->audit->record($action, $booking, $before, $this->auditFields($booking), $meta, ['actor' => $actor]);

            return $booking;
        });

        $this->changed($booking);

        return $booking;
    }

    private function assertCanMove(Booking $booking, BookingStatus $next): void
    {
        if (!$booking->status->canMoveTo($next)) {
            throw new BookingException(
                "Bron {$this->statusText($booking)}: uni \"{$next->label()}\" holatiga o'tkazib bo'lmaydi.",
                'invalid_transition',
                422,
                ['status' => $booking->status->value, 'to' => $next->value],
            );
        }
    }

    private function statusText(Booking $booking): string
    {
        return '"' . $booking->status->label() . '" holatida';
    }

    private function checkTimes(Carbon $start, Carbon $end): void
    {
        if (!$end->greaterThan($start)) {
            throw new BookingException("Tugash vaqti boshlanish vaqtidan keyin bo'lishi kerak.", 'invalid_time');
        }

        $max = (int) config('pos_bookings.max_slot_minutes', 1440);
        if ($start->diffInMinutes($end) > $max) {
            throw new BookingException('Bron ' . intdiv($max, 60) . " soatdan uzun bo'lishi mumkin emas.", 'invalid_time');
        }
    }

    /**
     * The main table and every table of the booking, each checked to be a live, active table of the branch.
     *
     * @param  list<int>|null  $extras
     * @return array{0: int|null, 1: list<int>}
     */
    private function resolveTables(Branch $branch, ?int $main, ?array $extras): array
    {
        $extras = array_values(array_unique(array_map('intval', $extras ?? [])));
        if ($main === null && $extras !== []) {
            $main = array_shift($extras);
        }

        $ids = array_values(array_unique(array_merge($main !== null ? [$main] : [], $extras)));
        if (count($ids) > self::MAX_TABLES) {
            throw new BookingException('Bitta bronga ko\'pi bilan ' . self::MAX_TABLES . ' ta xona yoki stol birlashtiriladi.', 'too_many_tables');
        }

        $found = $this->tables->ofBranch((int) $branch->id, $ids);
        foreach ($ids as $id) {
            $table = $found->get($id);
            if ($table === null) {
                throw new BookingException('Xona yoki stol topilmadi.', 'table_not_found', 422, ['dining_table_id' => $id]);
            }
            if (!$table->is_active) {
                throw new BookingException("\"{$table->name}\" hozir ishlamayapti (faol emas).", 'table_inactive', 422, ['dining_table_id' => $id]);
            }
        }

        return [$main, $ids];
    }

    /**
     * @param  list<int>  $tableIds
     */
    private function assertFree(int $branchId, array $tableIds, Carbon $start, Carbon $end, ?int $exceptId): void
    {
        $conflicts = $this->conflicts($branchId, $tableIds, $start, $end, $exceptId);
        if ($conflicts->isEmpty()) {
            return;
        }

        $names = $this->tables->ofBranch($branchId, $tableIds)->pluck('name', 'id');
        $busy = $conflicts->flatMap(fn (Booking $other) => $other->tables->pluck('dining_table_id'))
            ->map(fn ($id) => (int) $id)->intersect($tableIds)->unique()
            ->map(fn (int $id) => $names[$id] ?? "#{$id}")->implode(', ');

        throw new BookingException("Bu vaqtda band: {$busy}. Boshqa vaqt yoki xonani tanlang.", 'booking_conflict', 409, [
            'conflicts' => $conflicts->map(fn (Booking $other) => [
                'uuid' => $other->uuid,
                'status' => $other->status->value,
                'starts_at' => $other->starts_at->toJSON(),
                'ends_at' => $other->ends_at->toJSON(),
                'table_ids' => $other->tables->pluck('dining_table_id')->map(fn ($id) => (int) $id)->values()->all(),
            ])->values()->all(),
        ]);
    }

    /**
     * @param  list<int>  $tableIds
     */
    private function syncTables(Booking $booking, array $tableIds): void
    {
        $booking->tables()->whereNotIn('dining_table_id', $tableIds === [] ? [0] : $tableIds)->delete();
        $existing = $booking->tables()->pluck('dining_table_id')->map(fn ($id) => (int) $id)->all();
        foreach (array_diff($tableIds, $existing) as $id) {
            $booking->tables()->create(['dining_table_id' => $id]);
        }
        $booking->unsetRelation('tables');
    }

    /**
     * @return array<string, mixed>
     */
    private function auditFields(Booking $booking): array
    {
        return [
            'status' => $booking->status->value,
            'dining_table_id' => $booking->dining_table_id,
            'starts_at' => $booking->starts_at->toJSON(),
            'ends_at' => $booking->ends_at->toJSON(),
            'guests_planned' => $booking->guests_planned,
            'contact_name' => $booking->contact_name,
            'contact_phone' => $booking->contact_phone,
            'min_spend' => $booking->min_spend,
        ];
    }
}
