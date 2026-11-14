<?php

namespace App\Domain\Pos\Banquet;

use App\Domain\Pos\Booking\BookingStatus;
use App\Models\BanquetEvent;
use App\Models\BanquetPackage;
use App\Models\BanquetPayment;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Hall;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use App\Support\StoreTimezone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * P6-51: the event calendar — halls × date × slot. Creates holds, changes and moves events, freezes their prices
 * (P6-52, BanquetPricing) and moves them through their states (BanquetEventStatus).
 *
 * No double booking: every write that can take a hall locks the branch row first (the same lock room bookings take,
 * so a banquet and a room booking of the same branch are serialized too), then looks for an event that still holds
 * its slot on any of the same halls with an overlapping time [starts_at, ends_at) — 409 `slot_taken` — and for a room
 * booking of a room inside those halls at that time — 409 `hall_has_bookings`. BookingService asks the other way
 * round (hallConflicts): a room inside a banquet's hall is not sold while the banquet holds it.
 */
class BanquetEventService
{
    public const MAX_HALLS = 10;

    public const MAX_DAYS_AHEAD = 1095;

    public function __construct(
        protected BanquetSettings $settings,
        protected BanquetPricing $pricing,
        protected AuditLogger $audit,
    ) {}

    /**
     * A new event, held until `hold_expires_at` (the store's hold hours; 0: held until cancelled) unless the deposit
     * confirms it first (P6-53).
     *
     * @param  array<string, mixed>  $data  event_date (Y-m-d), slot, start_time/end_time (custom), hall_ids, event_type, title,
     *                                      customer_name, customer_phone, guests_guaranteed, guests_minimum, banquet_package_id,
     *                                      extras, quote_currency, quote_rate, note
     */
    public function create(Branch $branch, array $data, User $actor): BanquetEvent
    {
        $event = DB::transaction(function () use ($branch, $data, $actor) {
            Branch::whereKey($branch->id)->lockForUpdate()->first();

            $slot = BanquetSlot::from((string) $data['slot']);
            $date = (string) $data['event_date'];
            $this->assertDate((int) $branch->store_id, $date);
            [$start, $end] = $this->settings->instants((int) $branch->store_id, $date, $slot, $data['start_time'] ?? null, $data['end_time'] ?? null);
            $hallIds = $this->resolveHalls($branch, (array) ($data['hall_ids'] ?? []));
            $this->assertFree((int) $branch->id, $hallIds, $start, $end, null);

            $package = isset($data['banquet_package_id']) ? $this->package((int) $branch->store_id, (int) $data['banquet_package_id']) : null;
            $holdHours = $this->settings->view((int) $branch->store_id)['hold_hours'];

            $event = new BanquetEvent([
                'store_id' => $branch->store_id,
                'branch_id' => $branch->id,
                'event_date' => $date,
                'slot' => $slot,
                'starts_at' => $start,
                'ends_at' => $end,
                'event_type' => $data['event_type'] ?? BanquetEventType::TOY->value,
                'status' => BanquetEventStatus::HOLD,
                'title' => $data['title'] ?? null,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'] ?? null,
                'guests_guaranteed' => (int) ($data['guests_guaranteed'] ?? 0),
                'guests_minimum' => (int) ($data['guests_minimum'] ?? ($package->minimum_guests ?? 0)),
                'banquet_package_id' => $package?->id,
                'quote_currency' => $data['quote_currency'] ?? null,
                'quote_rate' => isset($data['quote_rate']) ? (string) $data['quote_rate'] : null,
                'hold_expires_at' => $holdHours > 0 ? now()->addHours($holdHours) : null,
                'note' => $data['note'] ?? null,
                'created_by' => $actor->id,
            ]);
            $event->save();
            $event->halls()->sync($hallIds);
            $this->pricing->apply($event, $data['extras'] ?? []);
            $event->save();

            $this->audit->record('banquet.created', $event, [], $this->auditFields($event), [], ['actor' => $actor]);

            return $event;
        });

        event(new BanquetEventChanged($event, 'created', true));

        return $event;
    }

    /**
     * Changes an event that is still held or confirmed; moving it (date, slot, custom times, halls) keeps its payments
     * and is checked for conflicts again. On a frozen event the package cannot change (unfreeze first: 409
     * `event_frozen`); the guests can (the frozen per-person price × the new count).
     *
     * @param  array<string, mixed>  $data  the fields of create() that were sent
     */
    public function update(BanquetEvent $event, array $data, User $actor, ?int $version = null): BanquetEvent
    {
        $totalChanged = false;
        $event = DB::transaction(function () use ($event, $data, $actor, $version, &$totalChanged) {
            $branch = Branch::whereKey($event->branch_id)->lockForUpdate()->firstOrFail();
            $event = $this->lockFresh($event, $version);
            if (!$event->status->isEditable()) {
                throw new BanquetException("Bu tadbirni o'zgartirib bo'lmaydi: u \"{$event->status->label()}\" holatida.", 'event_not_editable', 409, ['status' => $event->status->value]);
            }
            $before = $this->auditFields($event);
            $totalBefore = Money::of($event->total)->toString();

            $moves = array_intersect(array_keys($data), ['event_date', 'slot', 'start_time', 'end_time', 'hall_ids']) !== [];
            if ($moves) {
                $slot = BanquetSlot::from((string) ($data['slot'] ?? $event->slot->value));
                $date = (string) ($data['event_date'] ?? $event->event_date->toDateString());
                if ($slot === BanquetSlot::CUSTOM) {
                    $tz = StoreTimezone::of(Store::find($event->store_id));
                    $startTime = $data['start_time'] ?? $event->starts_at->copy()->setTimezone($tz)->format('H:i');
                    $endTime = $data['end_time'] ?? $event->ends_at->copy()->setTimezone($tz)->format('H:i');
                } else {
                    $startTime = $endTime = null;
                }
                if (array_key_exists('event_date', $data)) {
                    $this->assertDate((int) $event->store_id, $date);
                }
                [$start, $end] = $this->settings->instants((int) $event->store_id, $date, $slot, $startTime, $endTime);
                $hallIds = array_key_exists('hall_ids', $data)
                    ? $this->resolveHalls($branch, (array) $data['hall_ids'])
                    : $event->halls()->pluck('halls.id')->map(fn ($id) => (int) $id)->all();
                $this->assertFree((int) $branch->id, $hallIds, $start, $end, (int) $event->id);

                $event->event_date = Carbon::parse($date);
                $event->slot = $slot;
                $event->starts_at = $start;
                $event->ends_at = $end;
                $event->halls()->sync($hallIds);
            }

            if (array_key_exists('banquet_package_id', $data) && (int) ($data['banquet_package_id'] ?? 0) !== (int) ($event->banquet_package_id ?? 0)) {
                if ($event->isFrozen()) {
                    throw new BanquetException("Narxlar shartnoma kuni muzlatilgan: paketni almashtirish uchun avval muzlatishni bekor qiling.", 'event_frozen', 409);
                }
                $package = $data['banquet_package_id'] !== null ? $this->package((int) $event->store_id, (int) $data['banquet_package_id']) : null;
                $event->banquet_package_id = $package?->id;
                if ($package !== null && !array_key_exists('guests_minimum', $data)) {
                    $event->guests_minimum = (int) $package->minimum_guests;
                }
            }
            foreach (['event_type', 'title', 'customer_name', 'customer_phone', 'quote_currency', 'note'] as $field) {
                if (array_key_exists($field, $data)) {
                    $event->{$field} = $data[$field];
                }
            }
            foreach (['guests_guaranteed', 'guests_minimum'] as $field) {
                if (array_key_exists($field, $data)) {
                    $event->{$field} = (int) ($data[$field] ?? 0);
                }
            }
            if (array_key_exists('quote_rate', $data)) {
                $event->quote_rate = $data['quote_rate'] !== null ? (string) $data['quote_rate'] : null;
            }

            $this->pricing->apply($event, array_key_exists('extras', $data) ? (array) ($data['extras'] ?? []) : null);
            $event->version = $event->version + 1;
            $event->save();
            $totalChanged = Money::of($event->total)->toString() !== $totalBefore;

            $this->audit->record($moves ? 'banquet.rescheduled' : 'banquet.updated', $event, $before, $this->auditFields($event), [], ['actor' => $actor]);

            return $event;
        });

        event(new BanquetEventChanged($event, 'updated', $totalChanged));

        return $event;
    }

    /**
     * The contract day: the prices are stamped into the lines (later catalogue edits do not change the event).
     */
    public function freeze(BanquetEvent $event, User $actor, ?int $version = null): BanquetEvent
    {
        $event = DB::transaction(function () use ($event, $actor, $version) {
            $event = $this->lockFresh($event, $version);
            if (!$event->status->isEditable()) {
                throw new BanquetException("Bu tadbir narxini muzlatib bo'lmaydi: u \"{$event->status->label()}\" holatida.", 'event_not_editable', 409);
            }
            if ($event->isFrozen()) {
                throw new BanquetException('Narxlar allaqachon muzlatilgan.', 'already_frozen', 409);
            }
            $before = $this->auditFields($event);
            $this->pricing->apply($event);
            $this->pricing->freeze($event);
            $event->frozen_at = now();
            $event->frozen_by = $actor->id;
            $event->version = $event->version + 1;
            $event->save();

            $this->audit->record('banquet.frozen', $event, $before, $this->auditFields($event), ['total' => $event->total], ['actor' => $actor]);

            return $event;
        });

        event(new BanquetEventChanged($event, 'frozen', false));

        return $event;
    }

    /**
     * Lifts the stamp (banquets.manage and a reason, audited): the event is priced from the catalogue again.
     */
    public function unfreeze(BanquetEvent $event, string $reason, User $actor, ?int $version = null): BanquetEvent
    {
        $totalChanged = false;
        $event = DB::transaction(function () use ($event, $reason, $actor, $version, &$totalChanged) {
            $event = $this->lockFresh($event, $version);
            if (!$event->status->isEditable()) {
                throw new BanquetException("Bu tadbirni o'zgartirib bo'lmaydi: u \"{$event->status->label()}\" holatida.", 'event_not_editable', 409);
            }
            if (!$event->isFrozen()) {
                throw new BanquetException('Narxlar muzlatilmagan.', 'not_frozen', 409);
            }
            $before = $this->auditFields($event);
            $totalBefore = Money::of($event->total)->toString();
            $this->pricing->unfreeze($event);
            $event->frozen_at = null;
            $event->frozen_by = null;
            $this->pricing->apply($event);
            $event->version = $event->version + 1;
            $event->save();
            $totalChanged = Money::of($event->total)->toString() !== $totalBefore;

            $this->audit->record('banquet.unfrozen', $event, $before, $this->auditFields($event), ['reason' => $reason], ['actor' => $actor]);

            return $event;
        });

        event(new BanquetEventChanged($event, 'unfrozen', $totalChanged));

        return $event;
    }

    /**
     * hold → confirmed: the payment schedule calls it when the deposit row is paid (P6-53). Nothing for an event that
     * is already past the hold.
     */
    public function markConfirmed(BanquetEvent $event, ?User $actor): BanquetEvent
    {
        $fresh = BanquetEvent::whereKey($event->id)->firstOrFail();
        if ($fresh->status !== BanquetEventStatus::HOLD) {
            return $fresh;
        }

        return $this->transition($fresh, BanquetEventStatus::CONFIRMED, $actor, null, 'banquet.confirmed', function (BanquetEvent $event) {
            $event->confirmed_at = now();
            $event->hold_expires_at = null;
        });
    }

    /**
     * Cancels the event and releases its halls. Money already paid is settled by the payment schedule's cancellation
     * (P6-53: refund and retained parts); this alone only moves the state.
     */
    public function cancel(BanquetEvent $event, string $reason, ?User $actor, ?int $version = null): BanquetEvent
    {
        return $this->transition($event, BanquetEventStatus::CANCELLED, $actor, $version, 'banquet.cancelled', function (BanquetEvent $event) use ($reason) {
            $event->cancelled_at = now();
            $event->cancel_reason = $reason;
        }, ['reason' => $reason]);
    }

    /**
     * The minute job: holds whose time ran out without a paid row are cancelled and their halls released.
     *
     * @return int how many were released
     */
    public function releaseExpiredHolds(?Carbon $now = null): int
    {
        $now ??= now();
        $released = 0;
        $ids = BanquetEvent::where('status', BanquetEventStatus::HOLD->value)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', $now)
            ->orderBy('id')
            ->limit(500)
            ->pluck('id');

        foreach ($ids as $id) {
            $event = DB::transaction(function () use ($id, $now) {
                $event = BanquetEvent::whereKey($id)->lockForUpdate()->first();
                if ($event === null || $event->status !== BanquetEventStatus::HOLD || $event->hold_expires_at === null || $event->hold_expires_at->greaterThan($now)) {
                    return null;
                }
                // Money taken against it: the schedule decides (it confirms the event); never released with money on it
                if (BanquetPayment::where('banquet_event_id', $event->id)->where('status', BanquetPaymentStatus::PAID->value)->exists()) {
                    return null;
                }
                $before = $this->auditFields($event);
                $event->status = BanquetEventStatus::CANCELLED;
                $event->cancelled_at = $now;
                $event->released_at = $now;
                $event->cancel_reason = "Bron muddati tugadi: zaklad olinmadi.";
                $event->version = $event->version + 1;
                $event->save();
                $this->audit->record('banquet.hold_expired', $event, $before, $this->auditFields($event), [], ['actor' => null]);

                return $event;
            });
            if ($event !== null) {
                $released++;
                event(new BanquetEventChanged($event, 'hold_expired', false));
            }
        }

        return $released;
    }

    /**
     * A state change under the event's lock (P6-53/P6-54 use it: in_progress, settled).
     *
     * @param  callable(BanquetEvent): void  $apply
     * @param  array<string, mixed>  $meta
     */
    public function transition(BanquetEvent $event, BanquetEventStatus $next, ?User $actor, ?int $version, string $action, callable $apply, array $meta = []): BanquetEvent
    {
        $event = DB::transaction(function () use ($event, $next, $actor, $version, $action, $apply, $meta) {
            $event = $this->lockFresh($event, $version);
            if (!$event->status->canMoveTo($next)) {
                throw new BanquetException(
                    "Tadbir \"{$event->status->label()}\" holatida: uni \"{$next->label()}\" holatiga o'tkazib bo'lmaydi.",
                    'invalid_transition',
                    409,
                    ['status' => $event->status->value, 'to' => $next->value],
                );
            }
            $before = $this->auditFields($event);
            $apply($event);
            $event->status = $next;
            $event->version = $event->version + 1;
            $event->save();

            $this->audit->record($action, $event, $before, $this->auditFields($event), $meta, ['actor' => $actor]);

            return $event;
        });

        event(new BanquetEventChanged($event, $next->value, false));

        return $event;
    }

    /**
     * Events that still hold their slot on any of `$hallIds` and overlap [start, end).
     *
     * @param  list<int>  $hallIds
     * @return Collection<int, BanquetEvent>
     */
    public function conflicts(int $branchId, array $hallIds, Carbon $start, Carbon $end, ?int $exceptId = null): Collection
    {
        if ($hallIds === []) {
            return new Collection();
        }

        return BanquetEvent::query()
            ->where('branch_id', $branchId)
            ->whereIn('status', array_map(fn (BanquetEventStatus $status) => $status->value, BanquetEventStatus::holding()))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->whereHas('halls', fn ($query) => $query->whereIn('halls.id', $hallIds))
            ->with('halls:id,name')
            ->orderBy('starts_at')
            ->get()
            ->toBase();
    }

    /**
     * For BookingService: the banquets holding the hall of any of these rooms or tables during [start, end). The
     * caller holds the branch lock.
     *
     * @param  list<int>  $tableIds
     * @return Collection<int, BanquetEvent>
     */
    public function hallConflicts(int $branchId, array $tableIds, Carbon $start, Carbon $end): Collection
    {
        if ($tableIds === []) {
            return new Collection();
        }
        $hallIds = DB::table('dining_tables')->whereIn('id', $tableIds)->whereNotNull('hall_id')->pluck('hall_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        return $this->conflicts($branchId, $hallIds, $start, $end);
    }

    /**
     * The event locked for this transaction, its version checked.
     */
    public function lockFresh(BanquetEvent $event, ?int $version): BanquetEvent
    {
        $fresh = BanquetEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
        if ($version !== null && $version !== $fresh->version) {
            throw new BanquetException("Tadbir boshqa joyda o'zgartirilgan. Yangilab, qayta urinib ko'ring.", 'stale_version', 409, ['version' => $fresh->version]);
        }

        return $fresh;
    }

    /**
     * Booking months ahead is normal; the past is not (the store's local date).
     */
    private function assertDate(int $storeId, string $date): void
    {
        $tz = StoreTimezone::of(Store::find($storeId));
        $day = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay();
        $today = Carbon::now($tz)->startOfDay();
        if ($day->lessThan($today)) {
            throw new BanquetException("O'tgan kunga tadbir yozib bo'lmaydi.", 'event_in_past', 422);
        }
        if ($today->diffInDays($day) > self::MAX_DAYS_AHEAD) {
            throw new BanquetException('Tadbir ko\'pi bilan 3 yil oldin yoziladi.', 'event_too_far', 422);
        }
    }

    /**
     * @param  list<mixed>  $ids
     * @return list<int>
     */
    private function resolveHalls(Branch $branch, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            throw new BanquetException('Kamida bitta zalni tanlang.', 'halls_required', 422);
        }
        if (count($ids) > self::MAX_HALLS) {
            throw new BanquetException("Bitta tadbirga ko'pi bilan " . self::MAX_HALLS . ' ta zal birlashtiriladi.', 'too_many_halls', 422);
        }
        $found = Hall::where('branch_id', $branch->id)->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            $hall = $found->get($id);
            if ($hall === null) {
                throw new BanquetException('Zal topilmadi.', 'hall_not_found', 422, ['hall_id' => $id]);
            }
            if (!$hall->is_active) {
                throw new BanquetException("\"{$hall->name}\" zali faol emas.", 'hall_inactive', 422, ['hall_id' => $id]);
            }
        }

        return $ids;
    }

    private function package(int $storeId, int $id): BanquetPackage
    {
        $package = BanquetPackage::where('store_id', $storeId)->find($id);
        if ($package === null) {
            throw new BanquetException('Paket topilmadi.', 'package_not_found', 422);
        }

        return $package;
    }

    /**
     * @param  list<int>  $hallIds
     */
    private function assertFree(int $branchId, array $hallIds, Carbon $start, Carbon $end, ?int $exceptId): void
    {
        $taken = $this->conflicts($branchId, $hallIds, $start, $end, $exceptId);
        if ($taken->isNotEmpty()) {
            $names = $taken->flatMap(fn (BanquetEvent $other) => $other->halls->filter(fn (Hall $hall) => in_array((int) $hall->id, $hallIds, true))->pluck('name'))->unique()->implode(', ');
            throw new BanquetException("Bu vaqtda zal band: {$names}. Boshqa sana, vaqt yoki zalni tanlang.", 'slot_taken', 409, [
                'conflicts' => $taken->map(fn (BanquetEvent $other) => [
                    'uuid' => $other->uuid,
                    'status' => $other->status->value,
                    'event_date' => $other->event_date->toDateString(),
                    'slot' => $other->slot->value,
                    'starts_at' => $other->starts_at->toJSON(),
                    'ends_at' => $other->ends_at->toJSON(),
                    'hall_ids' => $other->halls->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                ])->values()->all(),
            ]);
        }

        // Room bookings of a room inside these halls at that time
        $bookings = Booking::query()
            ->where('branch_id', $branchId)
            ->whereIn('status', array_map(fn (BookingStatus $status) => $status->value, array_filter(BookingStatus::cases(), fn (BookingStatus $status) => $status->holdsSlot())))
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->whereHas('tables', fn ($query) => $query->whereIn('dining_table_id', DB::table('dining_tables')->whereIn('hall_id', $hallIds)->select('id')))
            ->orderBy('starts_at')
            ->get(['id', 'uuid', 'status', 'starts_at', 'ends_at']);
        if ($bookings->isNotEmpty()) {
            throw new BanquetException("Bu vaqtda zaldagi xona bron qilingan. Avval bronni ko'chiring yoki boshqa vaqtni tanlang.", 'hall_has_bookings', 409, [
                'bookings' => $bookings->map(fn (Booking $booking) => [
                    'uuid' => $booking->uuid,
                    'status' => $booking->status->value,
                    'starts_at' => $booking->starts_at->toJSON(),
                    'ends_at' => $booking->ends_at->toJSON(),
                ])->values()->all(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function auditFields(BanquetEvent $event): array
    {
        return [
            'status' => $event->status->value,
            'event_date' => $event->event_date->toDateString(),
            'slot' => $event->slot->value,
            'starts_at' => $event->starts_at->toJSON(),
            'ends_at' => $event->ends_at->toJSON(),
            'hall_ids' => $event->halls()->pluck('halls.id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
            'customer_name' => $event->customer_name,
            'customer_phone' => $event->customer_phone,
            'guests_guaranteed' => $event->guests_guaranteed,
            'guests_minimum' => $event->guests_minimum,
            'banquet_package_id' => $event->banquet_package_id,
            'total' => Money::of($event->total)->toString(),
            'frozen' => $event->frozen_at !== null,
        ];
    }
}
