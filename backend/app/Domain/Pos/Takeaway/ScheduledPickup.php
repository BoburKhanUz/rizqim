<?php

namespace App\Domain\Pos\Takeaway;

use App\Domain\Pos\Contracts\KitchenStationResolver;
use App\Domain\Pos\Orders\PickupQueue;
use App\Domain\Pos\Orders\PickupStatus;
use App\Domain\Pos\Orders\PosOrderException;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Domain\Pos\Setup\PosSettings;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Enums\FireMode;
use App\Enums\KitchenItemStatus;
use App\Enums\OrderStatus;
use App\Events\Broadcast\OrderItemsChangedBroadcast;
use App\Models\Branch;
use App\Models\BranchTakeawaySettings;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Order\OrderStatusService;
use App\Support\StoreTimezone;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P6-34 (module `scheduled_pickup`, docs/POS_SPEC.md "Olib ketish vaqti"): a counter order or a Telegram / Mini App
 * pickup order for a later time.
 *
 *  - `pickup_at` (orders.pickup_at): at least the branch's lead time from now (`pickup_min_lead_minutes`, 422
 *    `pickup_too_soon`), at most `pickup_max_days_ahead` business days ahead (0: today's business day; 422
 *    `pickup_too_far`), inside the store's opening hours (open_time–close_time; equal times, none set, or the
 *    `round_the_clock` module: any time; 422 `pickup_closed`);
 *  - the kitchen gets it at `kitchen_fire_at` = pickup_at − the prep time: the largest `wait_minutes` of its dishes,
 *    at least the branch's `pickup_prep_minutes`. Until then a POS order's lines are held (fire_mode `hold` with no due
 *    time: "Oshxonaga yuborish" never sends them, the kitchen display does not list them) and an online order stays off
 *    the kitchen display and out of the pickup queue; the minute job (pos:fire-scheduled-pickups) fires it: the held
 *    lines are sent with their stations, the queue number becomes `preparing` (the board shows it), the kitchen hears
 *    order.items_changed (`sent`). `kitchen_fired_at` says when;
 *  - the times offered (slots()): every `pickup_slot_minutes` from the earliest allowed time to the last.
 *
 * Changing or clearing the time on the till (PUT /pos/orders/{uuid}/pickup-time) works until the order fired; a cleared
 * time fires it at once. Nothing here blocks a payment, a bill, a close or a refund: a scheduled counter order is paid
 * first like any other and stays in the kitchen's future until it fires.
 */
final class ScheduledPickup
{
    public const SETTINGS = 'array{branch_id: int, configured: bool, pickup_min_lead_minutes: int, pickup_slot_minutes: int, pickup_max_days_ahead: int, pickup_prep_minutes: int, round_the_clock: bool, opening_hours: array{open: string|null, close: string|null}, updated_at: string|null}';

    /** An order's pickup time as the till, the kitchen and the order card show it. */
    public const PICKUP = 'array{pickup_at: string, kitchen_fire_at: string|null, kitchen_fired_at: string|null, waiting: bool, local_time: string}';

    public const SLOT = 'array{at: string, time: string, business_date: string}';

    public const FIELDS = ['pickup_min_lead_minutes', 'pickup_slot_minutes', 'pickup_max_days_ahead', 'pickup_prep_minutes'];

    public const DEFAULTS = ['pickup_min_lead_minutes' => 20, 'pickup_slot_minutes' => 15, 'pickup_max_days_ahead' => 1, 'pickup_prep_minutes' => 15];

    public const MAX_LEAD_MINUTES = 720;

    public const MAX_DAYS_AHEAD = 7;

    public const MAX_PREP_MINUTES = 240;

    /** The most slots one answer lists. */
    public const MAX_SLOTS = 300;

    public function __construct(
        private readonly PosModules $modules,
        private readonly AuditLogger $audit,
    ) {}

    // --- settings ------------------------------------------------------------------------------------------------

    /**
     * @return array{pickup_min_lead_minutes: int, pickup_slot_minutes: int, pickup_max_days_ahead: int, pickup_prep_minutes: int}
     */
    public function settings(Branch $branch): array
    {
        $row = BranchTakeawaySettings::where('branch_id', $branch->id)->first();
        $values = self::DEFAULTS;
        if ($row !== null) {
            foreach (self::FIELDS as $field) {
                $values[$field] = (int) $row->getAttribute($field);
            }
        }

        return $values;
    }

    /**
     * @return array<string, mixed> SETTINGS
     */
    public function view(Branch $branch): array
    {
        $row = BranchTakeawaySettings::where('branch_id', $branch->id)->first();
        $store = Store::find($branch->store_id);

        return [
            'branch_id' => (int) $branch->id,
            'configured' => $row !== null,
            ...$this->settings($branch),
            'round_the_clock' => $this->modules->enabled($branch, PosModule::ROUND_THE_CLOCK),
            'opening_hours' => [
                'open' => self::hhmm($store?->getAttribute('open_time')),
                'close' => self::hhmm($store?->getAttribute('close_time')),
            ],
            'updated_at' => $row?->updated_at?->toJSON(),
        ];
    }

    /**
     * Saves the fields sent (validated), audited.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> SETTINGS
     */
    public function update(Branch $branch, array $data, User $actor): array
    {
        $before = $this->view($branch);
        $attributes = array_map('intval', array_intersect_key($data, array_flip(self::FIELDS)));
        $row = BranchTakeawaySettings::where('branch_id', $branch->id)->first();
        if ($row === null) {
            BranchTakeawaySettings::create($attributes + self::DEFAULTS + ['store_id' => $branch->store_id, 'branch_id' => $branch->id]);
        } else {
            $row->update($attributes);
        }

        $after = $this->view($branch);
        $this->audit->record('settings.pickup_rules_changed', $branch, $before, $after, [], ['store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id, 'actor' => $actor]);

        return $after;
    }

    // --- the time --------------------------------------------------------------------------------------------------

    /**
     * Whether the store takes pickups at $at: the `round_the_clock` module, no hours set or equal open and close times:
     * always; else open_time ≤ local time ≤ close_time (a close before the open runs past midnight).
     */
    public function isOpenAt(Branch $branch, CarbonInterface $at): bool
    {
        if ($this->modules->enabled($branch, PosModule::ROUND_THE_CLOCK)) {
            return true;
        }
        $store = Store::find($branch->store_id);
        $open = self::hhmm($store?->getAttribute('open_time'));
        $close = self::hhmm($store?->getAttribute('close_time'));
        if ($open === null || $close === null || $open === $close) {
            return true;
        }
        $time = CarbonImmutable::instance($at)->setTimezone(new DateTimeZone(StoreTimezone::of($store)))->format('H:i');

        return $close > $open ? ($time >= $open && $time <= $close) : ($time >= $open || $time <= $close);
    }

    /**
     * Why $pickupAt cannot be taken (null: it can): [reason, message, data].
     *
     * @return array{0: string, 1: string, 2: array<string, mixed>}|null
     */
    public function refusal(Branch $branch, CarbonInterface $pickupAt, ?CarbonInterface $now = null): ?array
    {
        $now = CarbonImmutable::instance($now ?? now());
        $settings = $this->settings($branch);
        $pickupAt = CarbonImmutable::instance($pickupAt);
        $timezone = new DateTimeZone(StoreTimezone::of(Store::find($branch->store_id)));

        $earliest = $now->addMinutes($settings['pickup_min_lead_minutes']);
        // One minute of grace for the clocks of the till and the phone
        if ($pickupAt->lessThan($earliest->subMinute())) {
            return ['pickup_too_soon', "Olib ketish vaqti kamida {$settings['pickup_min_lead_minutes']} daqiqadan keyin bo'lishi kerak. Eng erta: " . $earliest->setTimezone($timezone)->format('H:i') . '.', [
                'min_lead_minutes' => $settings['pickup_min_lead_minutes'],
                'earliest_at' => $earliest->utc()->toJSON(),
            ]];
        }
        $latest = $this->latest($branch, $now);
        if (!$pickupAt->lessThan($latest)) {
            $days = $settings['pickup_max_days_ahead'];

            return ['pickup_too_far', $days === 0 ? "Olib ketish vaqtini faqat bugun uchun tanlash mumkin." : "Olib ketish vaqtini ko'pi bilan {$days} kun oldin tanlash mumkin.", [
                'max_days_ahead' => $days,
                'latest_at' => $latest->utc()->toJSON(),
            ]];
        }
        if (!$this->isOpenAt($branch, $pickupAt)) {
            return ['pickup_closed', "Bu vaqtda filial yopiq: ish vaqti ichidagi vaqtni tanlang.", []];
        }

        return null;
    }

    /**
     * The till's check (PUT /pos/orders/{uuid}/pickup-time, POST /pos/orders): the module (403) and the time (422).
     *
     * @throws PosOrderException
     */
    public function assertForPos(Branch $branch, CarbonInterface $pickupAt): void
    {
        $this->modules->assertEnabled($branch, PosModule::SCHEDULED_PICKUP);
        $refusal = $this->refusal($branch, $pickupAt);
        if ($refusal !== null) {
            throw new PosOrderException($refusal[1], 422, $refusal[0], $refusal[2]);
        }
    }

    /**
     * The online check (CreateOrderAction): the module and the time, as a 422 on `pickup_at`.
     *
     * @throws ValidationException
     */
    public function assertForOnline(Branch $branch, CarbonInterface $pickupAt): void
    {
        if (!$this->modules->enabled($branch, PosModule::SCHEDULED_PICKUP)) {
            throw ValidationException::withMessages(['pickup_at' => "Bu filialda olib ketish vaqtini oldindan tanlab bo'lmaydi."]);
        }
        $refusal = $this->refusal($branch, $pickupAt);
        if ($refusal !== null) {
            throw ValidationException::withMessages(['pickup_at' => $refusal[1]]);
        }
    }

    /**
     * An online pickup's time at checkout (CreateOrderAction): null without one; else checked (422 on `pickup_at`) and
     * returned with its kitchen time (pickup_at − the largest wait_minutes of the dishes, at least the branch's prep).
     *
     * @param  iterable<array<string, mixed>>  $lines  CreateOrderAction's lines (`variant_model` with its product)
     * @return array{pickup_at: CarbonImmutable, kitchen_fire_at: CarbonImmutable}|null
     *
     * @throws ValidationException
     */
    public function forCheckout(Branch $branch, ?string $type, mixed $pickupAt, iterable $lines): ?array
    {
        if ($pickupAt === null || $pickupAt === '') {
            return null;
        }
        if ($type !== \App\Enums\OrderType::PICKUP->value) {
            throw ValidationException::withMessages(['pickup_at' => "Olib ketish vaqti faqat olib ketish buyurtmasiga tanlanadi."]);
        }
        try {
            $at = CarbonImmutable::parse((string) $pickupAt)->utc();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['pickup_at' => "Olib ketish vaqti noto'g'ri."]);
        }
        $this->assertForOnline($branch, $at);

        $wait = 0;
        foreach ($lines as $line) {
            $variant = $line['variant_model'] ?? null;
            if ($variant instanceof \App\Models\ProductVariant) {
                $wait = max($wait, (int) ($variant->product?->getAttribute('wait_minutes') ?? 0));
            }
        }
        $prep = max($this->settings($branch)['pickup_prep_minutes'], $wait);

        return ['pickup_at' => $at, 'kitchen_fire_at' => $at->subMinutes($prep)];
    }

    /**
     * The end of the window: the start of the business day after the last one allowed (today + max_days_ahead).
     */
    public function latest(Branch $branch, ?CarbonInterface $now = null): CarbonImmutable
    {
        $now = CarbonImmutable::instance($now ?? now());
        $store = Store::find($branch->store_id);
        $timezone = new DateTimeZone(StoreTimezone::of($store));
        [$hour, $minute] = array_map('intval', explode(':', PosSettings::timeOf($branch->getAttribute('business_day_starts_at'))));
        $today = BusinessDay::of($branch, $now);

        return CarbonImmutable::createFromFormat('!Y-m-d', $today, $timezone)
            ->addDays($this->settings($branch)['pickup_max_days_ahead'] + 1)
            ->setTime($hour, $minute);
    }

    /**
     * The pickup times offered from now: every slot from the earliest allowed one (rounded up to the step) to the end
     * of the window, inside the opening hours; empty while the module is off.
     *
     * @return list<array{at: string, time: string, business_date: string}>
     */
    public function slots(Branch $branch, ?CarbonInterface $now = null): array
    {
        if (!$this->modules->enabled($branch, PosModule::SCHEDULED_PICKUP)) {
            return [];
        }
        $now = CarbonImmutable::instance($now ?? now());
        $settings = $this->settings($branch);
        $timezone = new DateTimeZone(StoreTimezone::of(Store::find($branch->store_id)));
        $step = max(5, $settings['pickup_slot_minutes']);

        $earliest = $now->addMinutes($settings['pickup_min_lead_minutes'])->setTimezone($timezone)->setSecond(0)->setMicrosecond(0);
        $minuteOfDay = $earliest->hour * 60 + $earliest->minute;
        $rounded = (int) (ceil($minuteOfDay / $step) * $step);
        $at = $earliest->startOfDay()->addMinutes($rounded);
        $latest = $this->latest($branch, $now);

        $slots = [];
        while ($at->lessThan($latest) && count($slots) < self::MAX_SLOTS) {
            if ($this->isOpenAt($branch, $at)) {
                $slots[] = [
                    'at' => $at->utc()->toJSON(),
                    'time' => $at->format('H:i'),
                    'business_date' => BusinessDay::of($branch, $at),
                ];
            }
            $at = $at->addMinutes($step);
        }

        return $slots;
    }

    /**
     * The prep time of the order: the largest wait_minutes of its live dishes, at least the branch's prep minutes.
     */
    public function prepMinutes(Order $order, Branch $branch): int
    {
        $wait = (int) DB::table('order_items')
            ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('order_items.order_id', $order->id)
            ->whereNull('order_items.deleted_at')
            ->where('order_items.kitchen_status', '!=', KitchenItemStatus::VOID->value)
            ->max('products.wait_minutes');

        return max($this->settings($branch)['pickup_prep_minutes'], $wait);
    }

    /**
     * Whether the order is still waiting for its kitchen time (its lines held, off the kitchen display and the board).
     */
    public static function waiting(Order $order, ?CarbonInterface $now = null): bool
    {
        if ($order->getAttribute('pickup_at') === null || $order->getAttribute('kitchen_fired_at') !== null) {
            return false;
        }
        $fireAt = $order->getAttribute('kitchen_fire_at');

        return $fireAt === null || CarbonImmutable::parse($fireAt)->greaterThan($now ?? now());
    }

    /**
     * A POS order's pickup lines are held while it waits (PosLineBuilder, with the course rule).
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    public static function prepare(Order $order, array $lines): array
    {
        if (!$order->channel->isPos() || $order->getAttribute('pickup_at') === null || $order->getAttribute('kitchen_fired_at') !== null) {
            return $lines;
        }

        return array_map(fn (array $line) => ['fire_mode' => FireMode::HOLD, 'due_at' => null] + $line, $lines);
    }

    /**
     * The order's pickup time for the till, the kitchen and the order card; null without one.
     *
     * @return array{pickup_at: string, kitchen_fire_at: string|null, kitchen_fired_at: string|null, waiting: bool, local_time: string}|null
     */
    public static function present(Order $order): ?array
    {
        $pickupAt = $order->getAttribute('pickup_at');
        if ($pickupAt === null) {
            return null;
        }
        $pickupAt = CarbonImmutable::parse($pickupAt);
        $fireAt = $order->getAttribute('kitchen_fire_at');
        $firedAt = $order->getAttribute('kitchen_fired_at');
        $store = $order->relationLoaded('store') ? $order->store : Store::find($order->store_id);

        return [
            'pickup_at' => $pickupAt->utc()->toJSON(),
            'kitchen_fire_at' => $fireAt !== null ? CarbonImmutable::parse($fireAt)->utc()->toJSON() : null,
            'kitchen_fired_at' => $firedAt !== null ? CarbonImmutable::parse($firedAt)->utc()->toJSON() : null,
            'waiting' => self::waiting($order),
            'local_time' => $pickupAt->setTimezone(new DateTimeZone(StoreTimezone::of($store)))->format('d.m H:i'),
        ];
    }

    // --- scheduling and firing -----------------------------------------------------------------------------------

    /**
     * Works out when the kitchen gets the order (pickup_at − prep time) and stores it; due already: fires it now. Call
     * with the order row locked, after its lines are added.
     */
    public function schedule(Order $order, Branch $branch): void
    {
        $pickupAt = $order->getAttribute('pickup_at');
        if ($pickupAt === null || $order->getAttribute('kitchen_fired_at') !== null) {
            return;
        }
        $fireAt = CarbonImmutable::parse($pickupAt)->subMinutes($this->prepMinutes($order, $branch));
        $order->forceFill(['kitchen_fire_at' => $fireAt])->save();

        if (!$fireAt->greaterThan(now())) {
            $this->fire($order);
        }
    }

    /**
     * The till changes the pickup time of an order that has not fired yet (null: no time, the kitchen gets it now).
     * orders.manage; the order's version; audited.
     *
     * @throws PosOrderException
     */
    public function change(Order $order, int $version, ?CarbonInterface $pickupAt, User $actor): Order
    {
        return DB::transaction(function () use ($order, $version, $pickupAt, $actor) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if ((int) $locked->version !== $version) {
                throw PosOrderException::versionConflict((int) $locked->version);
            }
            if (in_array($locked->status, [OrderStatus::CANCELED, OrderStatus::REJECTED, OrderStatus::REFUNDED], true)) {
                throw PosOrderException::closed();
            }
            /** @var Branch $branch */
            $branch = Branch::findOrFail($locked->branch_id);
            if ($locked->getAttribute('kitchen_fired_at') !== null || ($locked->getAttribute('pickup_at') === null && $this->hasSentLines($locked))) {
                throw new PosOrderException("Buyurtma allaqachon oshxonada: olib ketish vaqtini o'zgartirib bo'lmaydi.", 409, 'already_in_kitchen');
            }

            $before = $locked->getAttribute('pickup_at') !== null ? CarbonImmutable::parse($locked->getAttribute('pickup_at'))->utc()->toJSON() : null;
            if ($pickupAt !== null) {
                $this->assertForPos($branch, $pickupAt);
                $locked->forceFill(['pickup_at' => CarbonImmutable::instance($pickupAt)->utc(), 'kitchen_fire_at' => null])->save();
                // The lines not sent yet wait for the time now
                OrderItem::where('order_id', $locked->id)
                    ->where('kitchen_status', KitchenItemStatus::PENDING->value)
                    ->whereNull('sent_at')
                    ->where('fire_mode', FireMode::NOW->value)
                    ->update(['fire_mode' => FireMode::HOLD->value]);
                $locked->forceFill(['version' => (int) $locked->version + 1])->save();
                $this->schedule($locked, $branch);
            } else {
                $locked->forceFill(['pickup_at' => null, 'kitchen_fire_at' => null, 'version' => (int) $locked->version + 1])->save();
                $this->sendHeld($locked);
            }

            $this->audit->record('pos.pickup_time_changed', $locked, ['pickup_at' => $before], ['pickup_at' => $pickupAt !== null ? CarbonImmutable::instance($pickupAt)->utc()->toJSON() : null], [], ['actor' => $actor]);

            return $locked->refresh();
        });
    }

    /**
     * The minute job: every order whose kitchen time has come and that did not fire yet. Returns how many fired.
     */
    public function fireDue(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $ids = Order::query()
            ->whereNotNull('pickup_at')
            ->whereNull('kitchen_fired_at')
            ->whereNotNull('kitchen_fire_at')
            ->where('kitchen_fire_at', '<=', $now)
            ->whereNotIn('status', [OrderStatus::NEW->value, OrderStatus::CANCELED->value, OrderStatus::REJECTED->value, OrderStatus::REFUNDED->value])
            ->orderBy('kitchen_fire_at')
            ->limit(500)
            ->pluck('id');

        $fired = 0;
        foreach ($ids as $id) {
            try {
                $fired += DB::transaction(function () use ($id) {
                    $order = Order::whereKey($id)->lockForUpdate()->first();

                    return $order !== null && $this->fire($order) ? 1 : 0;
                });
            } catch (\Throwable $e) {
                // One order that cannot fire (a broken row) never holds up the others; it is tried again next minute
                report($e);
            }
        }

        return $fired;
    }

    /**
     * Fires an order whose kitchen time has come (row locked by the caller): a POS order's held lines go to the
     * kitchen, an online order joins the kitchen display and the pickup queue. Returns whether it fired now.
     */
    public function fire(Order $order): bool
    {
        if ($order->getAttribute('pickup_at') === null || $order->getAttribute('kitchen_fired_at') !== null) {
            return false;
        }
        if (in_array($order->status, [OrderStatus::NEW, OrderStatus::CANCELED, OrderStatus::REJECTED, OrderStatus::REFUNDED], true)) {
            return false;
        }

        $order->forceFill(['kitchen_fired_at' => now()])->save();

        if ($order->channel->isPos()) {
            $order->forceFill(['version' => (int) $order->version + 1])->save();
            $sent = $this->sendHeld($order);
        } else {
            $sent = OrderItem::where('order_id', $order->id)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            // P6-39: the kitchen display shows an online order from its kitchen time; one cooked before this minute job
            // came round (ready, handed over) only gets its fired time — its number never goes back to `preparing`
            $cooked = in_array($order->status, [OrderStatus::READY, OrderStatus::COURIER_ASSIGNED, OrderStatus::ON_THE_WAY, OrderStatus::DELIVERY, OrderStatus::DELIVERED], true)
                || in_array(PickupQueue::statusOf($order), [PickupStatus::READY, PickupStatus::HANDED], true);
            if (!$cooked) {
                app(PickupQueue::class)->linesSent($order);
                event(new OrderItemsChangedBroadcast($order, 'sent', $sent));
            }
        }

        $this->audit->record('pos.pickup_fired', $order, [], [], [
            'pickup_at' => CarbonImmutable::parse($order->getAttribute('pickup_at'))->utc()->toJSON(),
            'items' => $sent,
        ], ['actor' => null, 'store_id' => (int) $order->store_id, 'branch_id' => $order->branch_id !== null ? (int) $order->branch_id : null]);

        return true;
    }

    /**
     * Sends a POS order's held lines that wait for no course or due time: stations, sent_at, the order to preparing,
     * the queue number to `preparing`, order.items_changed (`sent`). Returns the lines sent.
     *
     * @return list<int>
     */
    private function sendHeld(Order $order): array
    {
        $items = OrderItem::where('order_id', $order->id)
            ->where('kitchen_status', KitchenItemStatus::PENDING->value)
            ->whereNull('sent_at')
            ->whereNotNull('fire_mode')
            ->whereNull('due_at')
            ->where(fn ($query) => $query->whereNull('course_no')->orWhere('course_no', '<=', 1))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        if ($items->isEmpty()) {
            return [];
        }

        $resolver = app()->bound(KitchenStationResolver::class) ? app(KitchenStationResolver::class) : null;
        $now = now();
        foreach ($items as $item) {
            $item->forceFill([
                'kitchen_status' => KitchenItemStatus::SENT->value,
                'sent_at' => $now,
                'fire_mode' => FireMode::NOW->value,
                'station_id' => $resolver !== null ? $resolver->stationIdFor($item) : $item->station_id,
            ])->save();
        }

        if ($order->status === OrderStatus::ACCEPTED) {
            app(OrderStatusService::class)->transition($order, OrderStatus::PREPARING, null, ['notes' => 'Oldindan buyurtma: oshxonaga yuborildi']);
            $order->status = OrderStatus::PREPARING;
            $order->syncOriginalAttribute('status');
        }

        app(PickupQueue::class)->linesSent($order);
        $ids = $items->pluck('id')->map(fn ($id) => (int) $id)->all();
        event(new OrderItemsChangedBroadcast($order, 'sent', $ids));

        return $ids;
    }

    private function hasSentLines(Order $order): bool
    {
        return OrderItem::where('order_id', $order->id)->whereNotNull('sent_at')->exists();
    }

    private static function hhmm(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^(\d{1,2}):(\d{2})/', $value, $match) === 1
            ? str_pad($match[1], 2, '0', STR_PAD_LEFT) . ':' . $match[2]
            : null;
    }
}
