<?php

namespace App\Domain\Pos\Orders;

use App\Domain\Pos\Contracts\KitchenStationResolver;
use App\Domain\Pos\Contracts\ManagerApprovals;
use App\Domain\Pos\Billing\BillStatus;
use App\Domain\Pos\Billing\BillableOrder;
use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Contracts\PosOrderOpener;
use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Domain\Pos\Setup\PosSettings;
use App\Enums\DiningTableKind;
use App\Enums\FireMode;
use App\Enums\KitchenItemStatus;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Events\Broadcast\KitchenItemStatusChangedBroadcast;
use App\Events\Broadcast\OrderBillRequestedBroadcast;
use App\Events\Broadcast\OrderCreatedBroadcast;
use App\Events\Broadcast\OrderItemsChangedBroadcast;
use App\Events\Broadcast\OrderStatusChangedBroadcast;
use App\Models\AutoGuestItem;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\Modifier;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\OrderChargeWaiver;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PermissionService;
use App\Services\Order\OrderStatusService;
use App\Services\Order\OrderTransitionException;
use App\Support\Money;
use App\Support\Quantity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * POS hall orders (P3-12, docs/POS_SPEC.md §2.4–2.10, research R1–R4, R11, R26): open an order (a table, room or
 * tapchan, or none), add rounds of lines, send them to the kitchen (held lines only by name), void a line, change the
 * guests, move the order to another table, merge two orders, waive or restore a charge line. Every mutation runs under
 * the order's row lock, checks the version the client last saw (409 when another device changed it meanwhile), bumps
 * it, recalculates the charges (PosChargesCalculator), is audited (with the terminal) and announced on the branch's
 * channels (order.items_changed). P3-37: a discount (percent or amount) with the cashier's own limit.
 *
 * The staff member's access to the order's branch is the caller's check (StaffOrderScope); the permissions an action
 * needs beyond orders.manage (orders.void_item, orders.discount, orders.cancel) are checked here, where a one-time
 * manager approval token (ManagerApprovals, P3-18) stands in for a missing one. A waiter changes only their own
 * orders (WaiterAccess, POS_SPEC 11). Voids after sending, waived charges and cancellations alert the owner
 * (PosOrderAlerts, R20).
 */
class PosOrderService implements PosOrderOpener
{
    /** Statuses a POS order is still open (and editable) in. */
    public const OPEN_STATUSES = [OrderStatus::ACCEPTED, OrderStatus::PREPARING];

    public function __construct(
        protected PosLineBuilder $lines,
        protected PosChargesCalculator $charges,
        protected OrderStatusService $orderStatus,
        protected PermissionService $permissions,
        protected AuditLogger $audit,
        protected WaiterAccess $waiters,
        protected PosOrderAlerts $alerts,
    ) {}

    /**
     * The contract (P3-17's "Keldi" and the POS endpoints): `heldItems` are a booking's pre-ordered lines, added on
     * hold with their due time.
     */
    public function open(
        int $branchId,
        string $channel,
        ?int $tableId,
        int $guestsCount,
        ?int $waiterId,
        ?int $deviceId,
        int $openedByUserId,
        ?int $bookingId = null,
        array $heldItems = [],
    ): Order {
        $branch = Branch::findOrFail($branchId);
        $actor = User::findOrFail($openedByUserId);
        // The contract allows pos_hall and pos_takeaway only
        $orderChannel = OrderChannel::from($channel);

        $table = $tableId !== null ? DiningTable::where('branch_id', $branch->id)->find($tableId) : null;
        if ($tableId !== null && $table === null) {
            throw ValidationException::withMessages(['table_id' => 'Stol yoki xona topilmadi.']);
        }

        return $this->openOrder($branch, $orderChannel, $table, $guestsCount, $waiterId, $deviceId, $actor, [
            'booking_id' => $bookingId,
            'held' => $this->heldLines($branch, $heldItems),
        ]);
    }

    /**
     * Opens the order. Options: `booking_id` (P3-17's column), `held` (parsed held lines), `items` (parsed first round),
     * `external_id` (the device's own id for the order: a repeat returns the order already opened), `send` (send the
     * first round's `now` lines at once), `note`, `queue_number` (P6-13: the number the till took from its block;
     * kept when allowed, else the order gets the next one, QueueNumbers), `business_date` (Y-m-d: the business day the
     * order belongs to when it is not today's — an order taken offline before the day's start and synced after it;
     * OrderStamp numbers it within that day).
     *
     * @param  array{booking_id?: ?int, held?: list<array<string, mixed>>, items?: list<array<string, mixed>>, external_id?: ?string, send?: bool, note?: ?string, queue_number?: ?int, business_date?: ?string}  $options
     */
    public function openOrder(Branch $branch, OrderChannel $channel, ?DiningTable $table, int $guests, ?int $waiterId, ?int $deviceId, User $actor, array $options = []): Order
    {
        if ($table !== null && $channel !== OrderChannel::POS_HALL) {
            throw ValidationException::withMessages(['table_id' => "Olib ketish buyurtmasi stolga bog'lanmaydi."]);
        }

        $waiter = $this->waiter($branch, $waiterId, $actor);

        return DB::transaction(function () use ($branch, $channel, $table, $guests, $waiter, $deviceId, $actor, $options) {
            $externalId = $options['external_id'] ?? null;
            if ($externalId !== null) {
                $existing = Order::where('store_id', $branch->store_id)->where('channel', $channel->value)->where('external_id', $externalId)->first();
                if ($existing !== null) {
                    // The device's retry of an order it already opened; another branch's id is not this order
                    if ((int) $existing->branch_id !== (int) $branch->id) {
                        throw new PosOrderException("Bu external_id boshqa buyurtmaga tegishli.", 422, 'external_id_conflict');
                    }

                    return $existing;
                }
            }

            if ($table !== null) {
                // Two tills opening the same room at once: the table row is the lock
                $table = DiningTable::whereKey($table->id)->lockForUpdate()->firstOrFail();
                $this->assertSeatable($table, null);
            }

            $order = Order::create(array_filter([
                'store_id' => $branch->store_id,
                'branch_id' => $branch->id,
                // The staff member who opened it: a POS order has no customer account
                'user_id' => $actor->id,
                'short_code' => 'POS' . strtoupper(Str::random(6)),
                'status' => OrderStatus::ACCEPTED,
                'order_type' => $channel === OrderChannel::POS_HALL ? OrderType::DINE_IN->value : OrderType::PICKUP->value,
                'payment_method' => 'cash',
                'payment_owner' => 'STORE',
                'payment_status' => PaymentStatus::PENDING,
                'channel' => $channel->value,
                'external_id' => $externalId,
                'notes' => $options['note'] ?? null,
                'guests_count' => $guests,
                'hall_id' => $table?->hall_id,
                'table_id' => $table?->id,
                'hall_ref' => $table?->hall?->name,
                'table_ref' => $table?->name,
                'waiter_id' => $waiter?->id,
                'device_id' => $deviceId,
                'version' => 1,
                'subtotal' => '0.00',
                'total_price' => '0.00',
                'commission_amount' => '0.00',
                'booking_id' => $options['booking_id'] ?? null,
                // P6-13: the till's number (OrderStamp checks it on `creating`) and, offline, the day it was taken
                'queue_number' => $options['queue_number'] ?? null,
                'business_date' => $options['business_date'] ?? null,
            ], fn ($value) => $value !== null));

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'user_id' => $actor->id,
                'status' => OrderStatus::ACCEPTED->value,
                'notes' => "POS: buyurtma ochildi ({$actor->name})",
            ]);

            // Round 1: the booking's pre-orders (held), the first lines, and the guests' tea and bread
            $first = [...($options['held'] ?? []), ...($options['items'] ?? [])];
            $added = $this->lines->add($order, $branch, $first, 1, $actor);
            $auto = $this->lines->add($order, $branch, $this->autoGuestLines($order, 0, $guests), 1, $actor, true);

            $this->charges->recalculate($order);

            if (!empty($options['send'])) {
                $this->fire($order, $actor, null);
            }

            $this->audit->record('pos.order_opened', $order, [], [], array_filter([
                'channel' => $channel->value,
                'table' => $table?->name,
                'guests' => $guests,
                'waiter_id' => $waiter?->id,
                'booking_id' => $options['booking_id'] ?? null,
                'lines' => $added->count() + $auto->count(),
            ], fn ($value) => $value !== null), ['actor' => $actor, 'store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id]);

            // Queued, after commit; ids and status only
            event(new OrderCreatedBroadcast($order));

            return $order->refresh();
        });
    }

    /**
     * Adds a round of lines (R2): `now` lines go with the next send (or at once with $send), `hold` lines only when
     * sent by name. Returns the order and the new lines.
     *
     * @param  list<array<string, mixed>>  $lines  PosLineBuilder::parse()
     * @return array{order: Order, items: Collection<int, OrderItem>}
     */
    public function addRound(Order $order, int $version, User $actor, array $lines, bool $send = false, ?string $approvalToken = null): array
    {
        $items = collect();

        $order = $this->mutate($order, $version, $actor, function (Order $order) use ($actor, $lines, $send, &$items) {
            $round = (int) $order->items()->max('round_no') + 1;
            /** @var Branch $branch */
            $branch = $order->branch;
            $items = $this->lines->add($order, $branch, $lines, $round, $actor);

            $this->charges->recalculate($order);
            $sent = [];
            if ($send) {
                $sent = $this->fire($order, $actor, $items->filter(fn (OrderItem $item) => $item->fire_mode === FireMode::NOW)->pluck('id')->map(fn ($id) => (int) $id)->all());
            }

            $this->audit->record('pos.round_added', $order, [], [], [
                'round_no' => $round,
                'lines' => $items->map(fn (OrderItem $item) => $item->displayLine())->all(),
                'sent' => $send,
            ], ['actor' => $actor]);

            // Review P3-35 (P3W3 #8): lines sent with the round are announced as sent too
            return ['round_added', $items->pluck('id')->map(fn ($id) => (int) $id)->all(), $sent !== [] ? [['sent', $sent]] : []];
        }, $approvalToken);

        return ['order' => $order, 'items' => $items];
    }

    /**
     * P3-31: a round a till sold offline, applied at sync (POS_SPEC 5.3): no version check (the till could not see the
     * server's version; additions never clash with what changed meanwhile), the device's prices (those the dish had
     * since $menuTakenAt, review P3-35) and an oversold kazan accepted (PosLineBuilder::addOffline), then the charges, the send when the till sent it, the audit and the
     * announcement as for any round. $roundNo: the round to add to (1 when it is the round the order was opened with).
     *
     * @param  list<array<string, mixed>>  $lines  PosLineBuilder::parse() lines with client_line_id, unit_price, modifier_prices
     * @return array{order: Order, items: Collection<int, OrderItem>, flags: list<array<string, mixed>>, sent: list<int>}
     */
    public function offlineRound(Order $order, User $actor, array $lines, bool $send, ?int $roundNo = null, ?Carbon $menuTakenAt = null): array
    {
        return DB::transaction(function () use ($order, $actor, $lines, $send, $roundNo, $menuTakenAt) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $this->assertMutable($locked);

            $round = $roundNo ?? (int) $locked->items()->max('round_no') + 1;
            /** @var Branch $branch */
            $branch = $locked->branch;
            $added = $this->lines->addOffline($locked, $branch, $lines, $round, $actor, $menuTakenAt);
            $items = $added['items'];

            $this->charges->recalculate($locked);
            $sent = $send ? $this->fire($locked, $actor, $items->filter(fn (OrderItem $item) => $item->fire_mode === FireMode::NOW)->pluck('id')->map(fn ($id) => (int) $id)->all()) : [];

            $this->audit->record('pos.round_added', $locked, [], [], [
                'round_no' => $round,
                'lines' => $items->map(fn (OrderItem $item) => $item->displayLine())->all(),
                'sent' => $send,
                'offline' => true,
            ], ['actor' => $actor]);

            $locked->forceFill(['version' => (int) $locked->version + 1])->save();
            $locked->refresh();
            event(new OrderItemsChangedBroadcast($locked, 'round_added', $items->pluck('id')->map(fn ($id) => (int) $id)->all()));
            if ($sent !== []) {
                event(new OrderItemsChangedBroadcast($locked, 'sent', $sent));
            }

            return ['order' => $locked, 'items' => $items, 'flags' => $added['flags'], 'sent' => $sent];
        });
    }

    /**
     * Sends lines to the kitchen (docs/POS_SPEC.md §2.8): without ids every unsent `now` line; held lines only when
     * their ids are given. Each gets its station (KitchenStationResolver, P3-13) and sent_at; the order's first send
     * starts it (accepted → preparing). 422 `nothing_to_send` when nothing is left to send.
     *
     * @param  list<int>|null  $itemIds
     */
    public function send(Order $order, int $version, User $actor, ?array $itemIds, ?string $approvalToken = null): Order
    {
        return $this->mutate($order, $version, $actor, function (Order $order) use ($actor, $itemIds) {
            $sent = $this->fire($order, $actor, $itemIds);
            if ($sent === []) {
                throw new PosOrderException("Oshxonaga yuboriladigan yangi taom yo'q.", 422, 'nothing_to_send');
            }

            $this->audit->record('pos.items_sent', $order, [], [], ['items' => $sent], ['actor' => $actor]);

            return ['sent', $sent];
        }, $approvalToken);
    }

    /**
     * Voids a line ("O'CHIRILDI", docs/POS_SPEC.md §2.10). Before it reached the kitchen: orders.manage, the stock and
     * kazan portion go back. Once sent (or after the bill was printed): orders.void_item or a manager's approval token,
     * a reason is required, and the portion goes back only when $restock says so (it was not cooked yet).
     */
    public function void(Order $order, int $version, User $actor, int $itemId, ?string $reason, ?string $approvalToken, ?bool $restock = null): Order
    {
        return $this->mutate($order, $version, $actor, function (Order $order) use ($actor, $itemId, $reason, $approvalToken, $restock) {
            $item = OrderItem::where('order_id', $order->id)->whereKey($itemId)->lockForUpdate()->first();
            if ($item === null) {
                throw new PosOrderException('Taom topilmadi.', 404, 'item_not_found');
            }
            if ($item->isVoid()) {
                throw new PosOrderException("Bu taom allaqachon o'chirilgan.", 422, 'already_void');
            }

            $sent = $item->sent_at !== null;
            $guarded = $sent || $this->billLocked($order);
            $reason = $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null;
            $approvedBy = null;
            if ($guarded) {
                if ($reason === null) {
                    throw ValidationException::withMessages(['reason' => "Oshxonaga yuborilgan taomni o'chirish sababini yozing."]);
                }
                $approvedBy = $this->authorizeOrApprove($actor, $order, Permission::ORDERS_VOID_ITEM, $approvalToken);
            }
            // A manager who approved the void also covers another waiter's order; else the token is the waiter rule's
            $approvedBy = $this->waiters->guard($order, $actor, $approvedBy === null ? $approvalToken : null, $approvedBy) ?? $approvedBy;

            $item->forceFill([
                'kitchen_status' => KitchenItemStatus::VOID->value,
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ])->save();

            $returned = $restock ?? !$sent;
            if ($returned) {
                $this->lines->release($item);
            }

            $this->charges->recalculate($order);

            $this->audit->record('pos.item_voided', $order, [], [], array_filter([
                'item_id' => (int) $item->id,
                'line' => $item->displayLine(),
                'amount' => (string) $item->total_price,
                'sent' => $sent,
                'reason' => $reason,
                'approved_by' => $approvedBy,
                'restocked' => $returned,
            ], fn ($value) => $value !== null), ['actor' => $actor]);

            if ($sent) {
                // The kitchen shows it struck through ("O'CHIRILDI")
                event(new KitchenItemStatusChangedBroadcast($order, $item));
                $this->alerts->voidedAfterSend($order, $item, $actor, $approvedBy, $reason);
            }

            return ['voided', [(int) $item->id]];
        }, null, false);
    }

    /**
     * Changes the number of guests: the per-guest charges follow, and guests who joined get the "choy-non" items
     * (AutoGuestItem) as a new round. Guests leaving take nothing off (it was served; void a line instead).
     */
    public function setGuests(Order $order, int $version, User $actor, int $guests, ?string $approvalToken = null): Order
    {
        return $this->mutate($order, $version, $actor, function (Order $order) use ($actor, $guests) {
            $before = (int) ($order->guests_count ?? 0);
            $order->forceFill(['guests_count' => $guests])->save();

            $items = collect();
            if ($guests > $before) {
                /** @var Branch $branch */
                $branch = $order->branch;
                $round = (int) $order->items()->max('round_no') + 1;
                $items = $this->lines->add($order, $branch, $this->autoGuestLines($order, $before, $guests), $round, $actor, true);
            }

            $this->charges->recalculate($order);
            $this->audit->record('pos.guests_changed', $order, ['guests_count' => $before], ['guests_count' => $guests], [], ['actor' => $actor]);

            return ['guests_changed', $items->pluck('id')->map(fn ($id) => (int) $id)->all()];
        }, $approvalToken);
    }

    /**
     * Moves the order to another table, room or tapchan of its branch (R26): the hall's service charge and the new
     * place's room tariff and minimum spend apply from now on (the day type stays the one the order opened on).
     */
    public function move(Order $order, int $version, User $actor, DiningTable $target, ?string $approvalToken = null): Order
    {
        return $this->mutate($order, $version, $actor, function (Order $order) use ($actor, $target) {
            if ($order->channel !== OrderChannel::POS_HALL) {
                throw new PosOrderException("Olib ketish buyurtmasini stolga ko'chirib bo'lmaydi.", 422, 'not_hall_order');
            }
            if ((int) $target->branch_id !== (int) $order->branch_id) {
                throw new PosOrderException('Stol topilmadi.', 404, 'table_not_found');
            }
            if ($order->table_id !== null && (int) $order->table_id === (int) $target->id) {
                throw new PosOrderException('Buyurtma allaqachon shu stolda.', 422, 'same_table');
            }

            $target = DiningTable::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->assertSeatable($target, (int) $order->id);

            $before = ['table_id' => $order->table_id, 'table_ref' => $order->table_ref, 'hall_ref' => $order->hall_ref];
            $order->forceFill([
                'table_id' => $target->id,
                'hall_id' => $target->hall_id,
                'table_ref' => $target->name,
                'hall_ref' => $target->hall?->name,
            ])->save();

            $this->charges->recalculate($order);
            $this->audit->record('pos.order_moved', $order, $before, [
                'table_id' => (int) $target->id, 'table_ref' => $target->name, 'hall_ref' => $target->hall?->name,
            ], [], ['actor' => $actor]);

            return ['moved', []];
        }, $approvalToken);
    }

    /**
     * Merges $source into $target (R26: two tables become one bill): the source's lines move over (their rounds after
     * the target's), its guests are added, its charge waivers are dropped, and the source is cancelled quietly ("merged
     * into #…", no customer alert, no stock movement: the lines live on in the target). Both must be open POS hall
     * orders of the same branch with no printed bill and no payment.
     */
    public function merge(Order $target, int $version, Order $source, int $sourceVersion, User $actor, ?string $approvalToken = null): Order
    {
        if ((int) $target->id === (int) $source->id) {
            throw new PosOrderException("Buyurtmani o'zi bilan birlashtirib bo'lmaydi.", 422, 'same_order');
        }

        return DB::transaction(function () use ($target, $version, $source, $sourceVersion, $actor, $approvalToken) {
            // Both rows locked in id order, so two merges the other way round cannot deadlock
            $locked = Order::whereIn('id', [$target->id, $source->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            /** @var Order $source */
            $source = $locked->get($source->id);

            $sourceItems = [];
            $movedBooking = null;
            $target = $this->mutate($locked->get($target->id), $version, $actor, function (Order $target) use ($source, $sourceVersion, $actor, $approvalToken, &$sourceItems, &$movedBooking) {
                // The waiter rule for both orders: one manager's approval covers the pair
                $approvedBy = $this->waiters->guard($target, $actor, $approvalToken);
                $this->assertMutable($source);
                $this->waiters->guard($source, $actor, $approvedBy === null ? $approvalToken : null, $approvedBy);
                if ((int) $source->version !== $sourceVersion) {
                    throw new PosOrderException("Qo'shiladigan buyurtma boshqa qurilmada o'zgartirildi. Qayta yuklang.", 409, 'version_conflict', ['source_version' => (int) $source->version]);
                }
                if ((int) $source->branch_id !== (int) $target->branch_id || $source->channel !== OrderChannel::POS_HALL || $target->channel !== OrderChannel::POS_HALL) {
                    throw new PosOrderException("Faqat bitta filialdagi zal buyurtmalarini birlashtirish mumkin.", 422, 'merge_not_allowed');
                }
                if ($this->billLocked($source) || $this->billLocked($target)) {
                    throw new PosOrderException("Schyoti chiqarilgan buyurtmani birlashtirib bo'lmaydi.", 422, 'bill_locked');
                }
                if (Payment::where('order_id', $source->id)->where('status', PaymentStatus::PAID->value)->exists()) {
                    throw new PosOrderException("To'lovi bor buyurtmani birlashtirib bo'lmaydi.", 422, 'has_payments');
                }

                // Review P3-35: a booking's order merged into another keeps its booking and deposits with the guests
                $sourceBooking = $source->getAttribute('booking_id') !== null ? (int) $source->getAttribute('booking_id') : null;
                $targetBooking = $target->getAttribute('booking_id') !== null ? (int) $target->getAttribute('booking_id') : null;
                if ($sourceBooking !== null && $targetBooking !== null && $sourceBooking !== $targetBooking) {
                    throw new PosOrderException("Ikkala buyurtma ham boshqa-boshqa bronga tegishli: ularni birlashtirib bo'lmaydi.", 422, 'merge_bookings_conflict');
                }
                if ($sourceBooking !== null && $targetBooking === null) {
                    $movedBooking = $this->moveBooking($sourceBooking, $source, $target, $actor);
                }

                $offset = (int) $target->items()->max('round_no');
                $sourceItems = $source->items()->pluck('id')->map(fn ($id) => (int) $id)->all();
                OrderItem::where('order_id', $source->id)->update([
                    'order_id' => $target->id,
                    'round_no' => DB::raw('COALESCE(round_no, 1) + ' . $offset),
                    'updated_at' => now(),
                ]);

                $target->forceFill([
                    'guests_count' => (int) ($target->guests_count ?? 0) + (int) ($source->guests_count ?? 0),
                ])->save();
                $this->charges->recalculate($target);

                OrderChargeWaiver::where('order_id', $source->id)->delete();
                $from = $source->status;
                $source->forceFill([
                    'status' => OrderStatus::CANCELED->value,
                    'cancel_reason' => \App\Enums\CancelReason::OTHER->value,
                    'cancel_note' => "#{$target->short_code} buyurtmasiga qo'shildi",
                    'cancelled_by' => $actor->id,
                    'version' => (int) $source->version + 1,
                ])->save();
                $this->charges->recalculate($source);
                OrderStatusHistory::create([
                    'order_id' => $source->id,
                    'user_id' => $actor->id,
                    'from_status' => $from->value,
                    'status' => OrderStatus::CANCELED->value,
                    'notes' => "POS: #{$target->short_code} buyurtmasiga birlashtirildi ({$actor->name})",
                ]);

                $this->audit->record('pos.orders_merged', $target, [], [], array_filter([
                    'source' => $source->uuid,
                    'source_table' => $source->table_ref,
                    'items' => count($sourceItems),
                    'booking_id' => $movedBooking?->id,
                ], fn ($value) => $value !== null), ['actor' => $actor]);

                event(new OrderStatusChangedBroadcast($source->refresh()));

                return ['merged', $sourceItems];
            }, null, false);

            if ($movedBooking !== null) {
                // The booking's screens follow it to the order it lives on now (after commit)
                app(\App\Domain\Pos\Booking\BookingService::class)->changed($movedBooking);
            }

            return $target;
        });
    }

    /**
     * The merged-away order's booking moves to the order its guests sit at now: orders.booking_id, bookings.order_id
     * and the deposits not settled yet (booking_payments.order_id), so the target's bills can take the deposit and the
     * booking completes when the target ends. The source loses it: its quiet cancellation completes nothing.
     */
    protected function moveBooking(int $bookingId, Order $source, Order $target, User $actor): ?\App\Models\Booking
    {
        $booking = \App\Models\Booking::whereKey($bookingId)->lockForUpdate()->first();
        if ($booking === null) {
            return null;
        }

        $target->forceFill(['booking_id' => $booking->id])->save();
        $source->forceFill(['booking_id' => null])->save();
        \App\Models\BookingPayment::where('booking_id', $booking->id)
            ->where(fn ($query) => $query->where('order_id', $source->id)->orWhereNull('order_id'))
            ->update(['order_id' => $target->id, 'updated_at' => now()]);

        $booking->order_id = $target->id;
        $booking->version = $booking->version + 1;
        $booking->save();

        $this->audit->record('booking.order_merged', $booking, ['order_id' => (int) $source->id], ['order_id' => (int) $target->id], [
            'source' => $source->uuid,
            'target' => $target->uuid,
        ], ['actor' => $actor]);

        return $booking;
    }

    /**
     * Takes a charge line off the order with a reason (R6, R20): orders.discount or a manager's approval token.
     */
    public function waive(Order $order, int $version, User $actor, PosCharge $charge, string $reason, ?string $approvalToken): Order
    {
        return $this->mutate($order, $version, $actor, function (Order $order) use ($actor, $charge, $reason, $approvalToken) {
            $approvedBy = $this->authorizeOrApprove($actor, $order, Permission::ORDERS_DISCOUNT, $approvalToken);
            $approvedBy = $this->waiters->guard($order, $actor, $approvedBy === null ? $approvalToken : null, $approvedBy) ?? $approvedBy;
            $amount = (string) $order->getAttribute($charge->column());

            OrderChargeWaiver::updateOrCreate(
                ['order_id' => $order->id, 'charge' => $charge->value],
                ['reason' => mb_substr(trim($reason), 0, 255), 'waived_by' => $actor->id, 'approved_by' => $approvedBy],
            );
            $this->charges->recalculate($order);

            $this->audit->record('pos.charge_waived', $order, [], [], array_filter([
                'charge' => $charge->value,
                'amount' => $amount,
                'reason' => trim($reason),
                'approved_by' => $approvedBy,
            ], fn ($value) => $value !== null), ['actor' => $actor]);
            $this->alerts->chargeWaived($order, $charge, $amount, trim($reason), $actor, $approvedBy);

            return ['charges_changed', []];
        }, null, false);
    }

    /**
     * P3-37: a discount on the order's dishes (POS_SPEC 2.10): `percent` (0 < value ≤ 100) stays a percent — worked out
     * again with every line change (PosChargesCalculator::discountOf) — or `amount` (0 < value ≤ the dishes); value 0
     * takes the discount off (that raises the bill: orders.manage is enough). Who: orders.discount, any discount; a
     * cashier (pos.take_payment) up to the branch's pos_settings.cashier_discount_max_percent (an amount by its
     * percent of the dishes) while no bill was printed; anyone else, or above it, a manager's approval token for
     * orders.discount. Refused once a bill of the order is being paid (409 `paid_bill_affected`: refund it first).
     * Audited (old and new), the owner alerted at pos_settings.alerts.discount_percent and above.
     */
    public function discount(Order $order, int $version, User $actor, string $kind, string $value, ?string $reason, ?string $approvalToken): Order
    {
        if (!in_array($kind, ['percent', 'amount'], true)) {
            throw ValidationException::withMessages(['kind' => "Chegirma turi percent yoki amount bo'lishi kerak."]);
        }
        $value = Money::of($value);
        $removing = $value->isZero();
        $reason = $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null;
        if ($value->isNegative()) {
            throw ValidationException::withMessages(['value' => "Chegirma manfiy bo'lishi mumkin emas."]);
        }
        if (!$removing && $reason === null) {
            throw ValidationException::withMessages(['reason' => 'Chegirma sababini yozing.']);
        }
        if (!$removing && $kind === 'percent' && $value->isGreaterThan(100)) {
            throw ValidationException::withMessages(['value' => "Chegirma 100 foizdan oshmasligi kerak."]);
        }

        return $this->mutate($order, $version, $actor, function (Order $order) use ($actor, $kind, $value, $removing, $reason, $approvalToken) {
            if ($this->hasTakenBills($order)) {
                throw new PosOrderException(
                    "Buyurtmaning schyoti to'lanmoqda yoki to'langan: chegirmani o'zgartirish uchun avval to'lovni qaytaring.",
                    409,
                    'paid_bill_affected',
                );
            }

            $items = $this->charges->compute($order)->itemsSubtotal;
            $before = $this->discountState($order);

            $approvedBy = null;
            $percent = null;
            if (!$removing) {
                if ($kind === 'amount' && $value->isGreaterThan($items)) {
                    throw ValidationException::withMessages(['value' => "Chegirma taomlar summasidan (" . PosOwnerAlerts::sum($items) . ") katta bo'lmasligi kerak."]);
                }
                // The discount as a percent of the dishes: what the cashier's limit and the owner's alert read
                $percent = $kind === 'percent' ? $value : ($items->isPositive() ? Money::of(BigDecimal::of($value->toString())->multipliedBy(100)->dividedBy($items->toString(), 2, RoundingMode::Up)) : Money::of(100));

                if (!$this->cashierMayDiscount($actor, $order, $kind, $value, $items)) {
                    $approvedBy = $this->authorizeOrApprove($actor, $order, Permission::ORDERS_DISCOUNT, $approvalToken);
                }
            }
            $approvedBy = $this->waiters->guard($order, $actor, $approvedBy === null ? $approvalToken : null, $approvedBy) ?? $approvedBy;

            $order->forceFill($removing ? [
                'discount_kind' => null,
                'discount_value' => null,
                'discount_reason' => null,
                'discount_by' => null,
                'discount' => '0.00',
            ] : [
                'discount_kind' => $kind,
                'discount_value' => $value->toString(),
                'discount_reason' => $reason,
                'discount_by' => $actor->id,
            ])->save();
            $this->charges->recalculate($order);
            $after = $this->discountState($order);

            $this->audit->record('pos.order.discount', $order, $before, $after, array_filter([
                'items_subtotal' => $items->toString(),
                'percent' => $percent?->toString(),
                'approved_by' => $approvedBy,
                'removed' => $removing ?: null,
            ], fn ($value) => $value !== null), ['actor' => $actor]);

            if ($percent !== null) {
                $this->alerts->discountGiven($order, $kind, $value, $percent, (string) $reason, $actor, $approvedBy);
            }

            return ['charges_changed', []];
        }, null, false);
    }

    /**
     * Whether $actor gives this discount without a manager: orders.discount always; a cashier (pos.take_payment) up
     * to the branch's cashier_discount_max_percent, and only while no bill of the order was printed (the bill lock:
     * from then on a discount needs a manager, POS_SPEC 2.9).
     */
    protected function cashierMayDiscount(User $actor, Order $order, string $kind, Money $value, Money $items): bool
    {
        if ($this->permissions->allows($actor, Permission::ORDERS_DISCOUNT, $order)) {
            return true;
        }
        if ($this->billLocked($order) || !$this->permissions->allows($actor, Permission::POS_TAKE_PAYMENT, $order)) {
            return false;
        }

        $branch = $order->branch;
        $stored = $branch?->getAttribute('pos_settings');
        $limit = PosSettings::resolve(is_array($stored) ? $stored : null)['cashier_discount_max_percent'];
        if ($limit === null || !Money::of($limit)->isPositive()) {
            return false;
        }

        // An amount by its share of the dishes: amount × 100 ≤ items × limit, exactly
        return $kind === 'percent'
            ? $value->isLessThanOrEqualTo($limit)
            : BigDecimal::of($value->toString())->multipliedBy(100)->isLessThanOrEqualTo(BigDecimal::of($items->toString())->multipliedBy($limit));
    }

    /**
     * The order's discount as the audit log records it.
     *
     * @return array{kind: string|null, value: string|null, amount: string, reason: string|null}
     */
    protected function discountState(Order $order): array
    {
        $value = $order->getAttribute('discount_value');

        return [
            'kind' => $order->getAttribute('discount_kind'),
            'value' => $value !== null ? Money::of((string) $value)->toString() : null,
            'amount' => Money::of($order->discount)->toString(),
            'reason' => $order->getAttribute('discount_reason'),
        ];
    }

    /**
     * Puts a waived charge back (it raises the bill: orders.manage is enough).
     */
    public function restoreCharge(Order $order, int $version, User $actor, PosCharge $charge, ?string $approvalToken = null): Order
    {
        return $this->mutate($order, $version, $actor, function (Order $order) use ($actor, $charge) {
            $deleted = OrderChargeWaiver::where('order_id', $order->id)->where('charge', $charge->value)->delete();
            if ($deleted === 0) {
                throw new PosOrderException("Bu qator olib tashlanmagan.", 422, 'not_waived');
            }

            $this->charges->recalculate($order);
            $this->audit->record('pos.charge_restored', $order, [], [], ['charge' => $charge->value, 'amount' => (string) $order->getAttribute($charge->column())], ['actor' => $actor]);

            return ['charges_changed', []];
        }, $approvalToken);
    }

    /**
     * Marks a line served at the table (POS_SPEC 2.8: sent → ready → served): a line the kitchen marked ready, or one
     * sent to a station without a kitchen display (tea, bread) that never will be. orders.manage; the waiter rule.
     */
    public function serve(Order $order, int $version, User $actor, int $itemId, ?string $approvalToken = null): Order
    {
        return $this->mutate($order, $version, $actor, function (Order $order) use ($actor, $itemId) {
            $item = OrderItem::where('order_id', $order->id)->whereKey($itemId)->lockForUpdate()->first();
            if ($item === null) {
                throw new PosOrderException('Taom topilmadi.', 404, 'item_not_found');
            }
            if ($item->kitchen_status === KitchenItemStatus::SERVED) {
                throw new PosOrderException('Bu taom allaqachon berilgan.', 422, 'already_served');
            }
            if (!in_array($item->kitchen_status, [KitchenItemStatus::READY, KitchenItemStatus::SENT], true) || $item->sent_at === null) {
                throw new PosOrderException("Taom hali oshxonaga yuborilmagan yoki o'chirilgan: uni berib bo'lmaydi.", 422, 'item_not_ready');
            }

            $from = $item->kitchen_status;
            $item->forceFill([
                'kitchen_status' => KitchenItemStatus::SERVED->value,
                'ready_at' => $item->ready_at ?? now(),
            ])->save();

            $this->audit->record('pos.item_served', $order, ['kitchen_status' => $from->value], ['kitchen_status' => KitchenItemStatus::SERVED->value], [
                'item_id' => (int) $item->id,
                'line' => $item->displayLine(),
            ], ['actor' => $actor]);
            event(new KitchenItemStatusChangedBroadcast($order, $item));

            return ['served', [(int) $item->id]];
        }, $approvalToken);
    }

    /**
     * P3-26: the waiter asks the till for the bill ("Schyot so'raldi"): stamps bill_requested_at / _by, bumps the
     * version and announces order.bill_requested on the branch's channels. Idempotent: asked once already (and no bill
     * printed since), it answers the order unchanged, nothing is announced again. $version is checked when given
     * (409 when stale). orders.manage; the waiter rule (another waiter's order: a manager's approval token).
     */
    public function requestBill(Order $order, ?int $version, User $actor, ?string $approvalToken = null): Order
    {
        return DB::transaction(function () use ($order, $version, $actor, $approvalToken) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $this->assertMutable($locked);
            if ($version !== null && (int) $locked->version !== $version) {
                throw PosOrderException::versionConflict((int) $locked->version);
            }
            if ($locked->bill_requested_at !== null) {
                return $locked;
            }
            $this->waiters->guard($locked, $actor, $approvalToken);

            $locked->forceFill([
                'bill_requested_at' => now(),
                'bill_requested_by' => $actor->id,
                'version' => (int) $locked->version + 1,
            ])->save();
            $locked->refresh();

            $this->audit->record('pos.bill_requested', $locked, [], [], array_filter([
                'short_code' => $locked->short_code,
                'table' => $locked->table_ref,
            ], fn ($value) => $value !== null), ['actor' => $actor]);
            event(new OrderBillRequestedBroadcast($locked));

            return $locked;
        });
    }

    /**
     * Cancels a whole POS order (POS_SPEC 2.10, 3): `reason` required; orders.cancel or a manager's approval token
     * (which also covers another waiter's order). An order with a payment is refused (409 `has_payments`: refund it
     * first, the refund receipt belongs to its sale receipt). Every line still on it is voided: lines already sent get
     * a kitchen void ("O'CHIRILDI") and keep their portion (it was cooked); unsent ones give their stock and kazan
     * portion back. Open or printed bills are voided, the order becomes `canceled` (its table is free), its booking is
     * completed (PosBookingServiceProvider: the booking's deposits stay `taken`, to be refunded or retained), the
     * change is audited and the owner alerted. P3-37: a closed order whose every payment was refunded in full is
     * cancelled too (cancelRefunded); any other closed order stays 422 `order_closed`.
     */
    public function cancel(Order $order, int $version, User $actor, string $reason, ?string $approvalToken): Order
    {
        $reason = mb_substr(trim($reason), 0, 255);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Bekor qilish sababini yozing.']);
        }

        [$cancelled, $alert] = DB::transaction(function () use ($order, $version, $actor, $reason, $approvalToken) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if (!$locked->channel->isPos()) {
                throw PosOrderException::notPos();
            }
            if ($locked->getAttribute('closed_at') !== null || !in_array($locked->status, [...self::OPEN_STATUSES, OrderStatus::READY], true)) {
                // P3-37: a closed order whose every payment was refunded may still be cancelled; nothing else closed
                if (!$this->fullyRefunded($locked)) {
                    throw PosOrderException::closed();
                }
                if ((int) $locked->version !== $version) {
                    throw PosOrderException::versionConflict((int) $locked->version);
                }

                return $this->cancelRefunded($locked, $actor, $reason, $approvalToken);
            }
            if ((int) $locked->version !== $version) {
                throw PosOrderException::versionConflict((int) $locked->version);
            }

            $approvedBy = $this->authorizeOrApprove($actor, $locked, Permission::ORDERS_CANCEL, $approvalToken);
            $approvedBy = $this->waiters->guard($locked, $actor, $approvedBy === null ? $approvalToken : null, $approvedBy) ?? $approvedBy;

            $paid = Payment::where('order_id', $locked->id)->where('status', PaymentStatus::PAID->value)->pluck('amount');
            if ($paid->isNotEmpty()) {
                throw new PosOrderException(
                    "Buyurtmada to'lov bor: avval to'lovni qaytaring (qaytarish cheki bilan), keyin bekor qiling.",
                    409,
                    'has_payments',
                    ['paid_amount' => Money::sum(...$paid->all())->toString()],
                );
            }

            $bills = OrderBill::where('order_id', $locked->id)
                ->whereIn('status', [BillStatus::OPEN->value, BillStatus::PRINTED->value])
                ->lockForUpdate()
                ->get();
            foreach ($bills as $bill) {
                $bill->forceFill(['status' => BillStatus::VOID->value, 'voided_at' => now()])->save();
            }

            $items = OrderItem::where('order_id', $locked->id)
                ->where('kitchen_status', '!=', KitchenItemStatus::VOID->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $sentItems = [];
            $restocked = 0;
            foreach ($items as $item) {
                $sent = $item->sent_at !== null;
                $item->forceFill([
                    'kitchen_status' => KitchenItemStatus::VOID->value,
                    'voided_at' => now(),
                    'voided_by' => $actor->id,
                    'void_reason' => $reason,
                ])->save();
                if ($sent) {
                    $sentItems[] = $item;
                } else {
                    $this->lines->release($item);
                    $restocked++;
                }
            }

            $amount = (string) $locked->total_price;
            $locked->forceFill(['version' => (int) $locked->version + 1])->save();

            try {
                // The system's move (null actor): who may cancel was checked above, an approval token included
                $this->orderStatus->transition($locked, OrderStatus::CANCELED, null, [
                    'cancel_reason' => \App\Enums\CancelReason::OTHER,
                    'cancel_note' => $reason,
                    'notes' => "POS: bekor qilindi ({$actor->name})",
                ]);
            } catch (OrderTransitionException $e) {
                throw new PosOrderException($e->getMessage(), $e->status, 'status');
            }
            $locked->refresh();
            $locked->forceFill(['cancelled_by' => $actor->id])->save();
            OrderStatusHistory::where('order_id', $locked->id)->where('status', OrderStatus::CANCELED->value)->whereNull('user_id')->update(['user_id' => $actor->id]);

            $this->audit->record('pos.order_cancelled', $locked, [], [], array_filter([
                'short_code' => $locked->short_code,
                'table' => $locked->table_ref,
                'amount' => $amount,
                'reason' => $reason,
                'approved_by' => $approvedBy,
                'voided_items' => $items->count(),
                'restocked_items' => $restocked,
                'voided_bills' => $bills->pluck('uuid')->all() ?: null,
            ], fn ($value) => $value !== null), ['actor' => $actor]);

            foreach ($sentItems as $item) {
                // The stations see the line struck through ("O'CHIRILDI")
                event(new KitchenItemStatusChangedBroadcast($locked, $item));
            }
            event(new OrderItemsChangedBroadcast($locked, 'cancelled', $items->pluck('id')->map(fn ($id) => (int) $id)->all()));

            return [$locked, ['amount' => $amount, 'approved_by' => $approvedBy]];
        });

        $leftDeposit = $cancelled->getAttribute('booking_id') !== null
            ? Money::of((string) \App\Models\BookingPayment::where('booking_id', $cancelled->getAttribute('booking_id'))
                ->where('status', 'taken')
                ->selectRaw('coalesce(sum(amount - applied_amount - refunded_amount - retained_amount), 0) as left_amount')
                ->value('left_amount'))
            : Money::zero();
        $this->alerts->orderCancelled($cancelled, $alert['amount'], $reason, $actor, $alert['approved_by'], $leftDeposit);

        return $cancelled;
    }

    /**
     * P3-37: a closed (paid) POS order whose payments were all refunded in full: payment_status `refunded`, a refunded
     * payment and none still paid.
     */
    protected function fullyRefunded(Order $order): bool
    {
        return $order->getAttribute('closed_at') !== null
            && $order->status === OrderStatus::DELIVERED
            && $order->payment_status === PaymentStatus::REFUNDED
            && Payment::where('order_id', $order->id)->where('status', PaymentStatus::REFUNDED->value)->exists()
            && !Payment::where('order_id', $order->id)->where('status', PaymentStatus::PAID->value)->exists();
    }

    /**
     * P3-37: cancels a closed order refunded in full (the guests got their money back; the refund receipts already
     * reverse its sale receipts): the same permission (orders.cancel or a manager's approval token) and waiter rule as
     * any cancel; the order becomes `canceled` and keeps `closed_at`, its served lines and paid bills stay as they
     * were, no receipt is asked for. Review P6 C#M3: a counter order paid first may still be cooking: its lines still in
     * the kitchen are voided like any cancel's (a sent one keeps its portion, the station sees "O'CHIRILDI"; one never
     * sent gives its stock back) and its number leaves the pickup queue (PickupQueue: `queue.canceled`). Audited and
     * alerted like any cancel. Runs under the order's lock.
     *
     * @return array{0: Order, 1: array{amount: string, approved_by: int|null}}
     */
    protected function cancelRefunded(Order $locked, User $actor, string $reason, ?string $approvalToken): array
    {
        $approvedBy = $this->authorizeOrApprove($actor, $locked, Permission::ORDERS_CANCEL, $approvalToken);
        $approvedBy = $this->waiters->guard($locked, $actor, $approvedBy === null ? $approvalToken : null, $approvedBy) ?? $approvedBy;

        $from = $locked->status;
        $amount = (string) $locked->total_price;

        $cooking = OrderItem::where('order_id', $locked->id)
            ->whereIn('kitchen_status', KitchenItemStatus::cookingValues())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        foreach ($cooking as $item) {
            $sent = $item->sent_at !== null;
            $item->forceFill([
                'kitchen_status' => KitchenItemStatus::VOID->value,
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ])->save();
            if (!$sent) {
                $this->lines->release($item);
            }
        }

        $locked->forceFill([
            'status' => OrderStatus::CANCELED->value,
            'cancel_reason' => \App\Enums\CancelReason::OTHER->value,
            'cancel_note' => $reason,
            'cancelled_by' => $actor->id,
            'version' => (int) $locked->version + 1,
        ])->save();
        OrderStatusHistory::create([
            'order_id' => $locked->id,
            'user_id' => $actor->id,
            'from_status' => $from->value,
            'status' => OrderStatus::CANCELED->value,
            'notes' => "POS: to'liq qaytarilgan buyurtma bekor qilindi ({$actor->name}) | Sabab: {$reason}",
        ]);
        $locked->refresh();
        // The number leaves the pickup queue like any cancelled order's (OrderStatusService tells the tracker there)
        app(PickupQueue::class)->statusChanged($locked, $from, OrderStatus::CANCELED);

        $this->audit->record('pos.order_cancelled', $locked, ['status' => $from->value], ['status' => OrderStatus::CANCELED->value], array_filter([
            'short_code' => $locked->short_code,
            'table' => $locked->table_ref,
            'amount' => $amount,
            'reason' => $reason,
            'approved_by' => $approvedBy,
            'after_refund' => true,
            'voided_items' => $cooking->isNotEmpty() ? $cooking->count() : null,
        ], fn ($value) => $value !== null), ['actor' => $actor]);

        foreach ($cooking as $item) {
            if ($item->sent_at !== null) {
                event(new KitchenItemStatusChangedBroadcast($locked, $item));
            }
        }
        event(new OrderStatusChangedBroadcast($locked));
        event(new OrderItemsChangedBroadcast($locked, 'cancelled', $cooking->pluck('id')->map(fn ($id) => (int) $id)->all()));

        return [$locked, ['amount' => $amount, 'approved_by' => $approvedBy]];
    }

    /**
     * Whether the order may still be changed on the POS (open, POS channel).
     */
    public function assertMutable(Order $order): void
    {
        if (!$order->channel->isPos()) {
            throw PosOrderException::notPos();
        }
        if (!in_array($order->status, self::OPEN_STATUSES, true)) {
            throw PosOrderException::closed();
        }
    }

    /**
     * Runs a change under the order's row lock: open POS order, the version the client saw, the waiter rule
     * (WaiterAccess, with $approvalToken; an action that checks it itself passes $guardWaiter false), then the
     * change, then the version bump and the order.items_changed announcement. The closure returns [change, item ids]
     * and, optionally, further changes announced after it at the same version ([[change, item ids], ...]: a round sent
     * at once is `round_added`, then `sent`).
     *
     * @param  Closure(Order): array{0: string, 1: list<int>, 2?: list<array{0: string, 1: list<int>}>}  $change
     */
    protected function mutate(Order $order, int $version, User $actor, Closure $change, ?string $approvalToken = null, bool $guardWaiter = true): Order
    {
        return DB::transaction(function () use ($order, $version, $actor, $change, $approvalToken, $guardWaiter) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            $this->assertMutable($locked);
            if ((int) $locked->version !== $version) {
                throw PosOrderException::versionConflict((int) $locked->version);
            }
            if ($guardWaiter) {
                $this->waiters->guard($locked, $actor, $approvalToken);
            }

            // What the order's bills are made from, when a bill of it is being paid (review P3-35)
            $basis = $this->hasTakenBills($locked) ? BillableOrder::fingerprint($locked) : null;
            $result = $change($locked);
            [$what, $itemIds] = $result;
            if ($basis !== null && BillableOrder::fingerprint($locked) !== $basis) {
                $this->assertPaidBillsFit($locked);
            }

            $locked->forceFill(['version' => (int) $locked->version + 1])->save();
            $locked->refresh();

            event(new OrderItemsChangedBroadcast($locked, $what, $itemIds));
            foreach ($result[2] ?? [] as [$also, $alsoIds]) {
                event(new OrderItemsChangedBroadcast($locked, $also, $alsoIds));
            }

            return $locked;
        });
    }

    /**
     * Review P3-35: a change may not take away what a bill already being paid carries (a paid line voided, a charge
     * waived or lowered by fewer guests or another table): the order would no longer fit its paid bills and could be
     * neither billed, paid nor cancelled. Checked after the change, inside its transaction, so a refused change rolls
     * back whole: 409 `paid_bill_affected` (refund the payment first, POST /pos/refunds). Only a change that moved what
     * the bills are made from is checked: sending or serving a line never is.
     */
    protected function assertPaidBillsFit(Order $order): void
    {
        try {
            BillableOrder::of($order);
        } catch (PosActionException $e) {
            throw new PosOrderException(
                "Bu o'zgarish to'lovi olingan schyotdagi taom yoki xizmat haqiga tegadi: avval o'sha to'lovni qaytaring, keyin o'zgartiring.",
                409,
                'paid_bill_affected',
                ['bills_reason' => $e->reason],
            );
        }
    }

    /**
     * Whether a bill of the order is being paid (a payment taken on it, or paid).
     */
    protected function hasTakenBills(Order $order): bool
    {
        return OrderBill::query()
            ->where('order_id', $order->id)
            ->where('status', '!=', BillStatus::VOID->value)
            ->where(fn ($query) => $query->where('status', BillStatus::PAID->value)
                ->orWhereExists(fn ($payments) => $payments->selectRaw('1')->from('payments')->whereColumn('payments.bill_id', 'order_bills.id')))
            ->exists();
    }

    /**
     * Sends unsent lines: $itemIds null = every `now` line; else exactly those (held ones included). Returns the ids
     * sent. Starts the order on its first send.
     *
     * @param  list<int>|null  $itemIds
     * @return list<int>
     */
    protected function fire(Order $order, User $actor, ?array $itemIds): array
    {
        $query = OrderItem::where('order_id', $order->id)
            ->where('kitchen_status', KitchenItemStatus::PENDING->value)
            ->whereNull('sent_at')
            ->whereNotNull('fire_mode')
            ->orderBy('id');
        if ($itemIds === null) {
            $query->where('fire_mode', FireMode::NOW->value);
        } else {
            $query->whereIn('id', $itemIds === [] ? [0] : $itemIds);
        }

        $items = $query->lockForUpdate()->get();
        if ($itemIds !== null && $itemIds !== [] && $items->count() !== count(array_unique($itemIds))) {
            throw new PosOrderException("Ba'zi taomlar topilmadi yoki allaqachon yuborilgan.", 422, 'items_not_sendable');
        }
        if ($items->isEmpty()) {
            return [];
        }

        $resolver = app()->bound(KitchenStationResolver::class) ? app(KitchenStationResolver::class) : null;
        $now = now();
        foreach ($items as $item) {
            $item->forceFill([
                'kitchen_status' => KitchenItemStatus::SENT->value,
                'sent_at' => $now,
                // Resolved again: a held line got its station when it was added, the routes may have changed since
                'station_id' => $resolver !== null ? $resolver->stationIdFor($item) : $item->station_id,
            ])->save();
        }

        if ($order->status === OrderStatus::ACCEPTED) {
            try {
                $this->orderStatus->transition($order, OrderStatus::PREPARING, $actor, [
                    'notes' => "POS: oshxonaga yuborildi ({$actor->name})",
                ]);
            } catch (OrderTransitionException $e) {
                throw new PosOrderException($e->getMessage(), $e->status, 'status');
            }
            $order->status = OrderStatus::PREPARING;
            $order->syncOriginalAttribute('status');
        }

        // P6-13: a counter order's queue number is being prepared (the customer display's "Tayyorlanmoqda")
        app(PickupQueue::class)->linesSent($order);

        return $items->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * The approving manager's id when $actor lacks $permission and a valid one-time approval token is given; null when
     * the actor holds it. 403 `approval_required` / `approval_invalid` otherwise.
     */
    protected function authorizeOrApprove(User $actor, Order $order, Permission $permission, ?string $token): ?int
    {
        if ($this->permissions->allows($actor, $permission, $order)) {
            return null;
        }

        if ($token === null || trim($token) === '' || !app()->bound(ManagerApprovals::class)) {
            throw new PosOrderException("Bu amal uchun menejer tasdig'i kerak.", 403, 'approval_required', ['permission' => $permission->value]);
        }

        try {
            return app(ManagerApprovals::class)->consume(trim($token), $permission->value, $this->permissions->terminalOf($actor)?->id);
        } catch (QueryException $e) {
            throw $e;
        } catch (Throwable $e) {
            // The approvals' own reason when it gives one (approval_expired, approval_used, approval_wrong_device...)
            $reason = property_exists($e, 'reason') && is_string($e->reason) && str_starts_with($e->reason, 'approval_') ? $e->reason : 'approval_invalid';
            $message = $reason !== 'approval_invalid' ? $e->getMessage() : "Menejer tasdig'i noto'g'ri, eskirgan yoki boshqa amal uchun.";

            throw new PosOrderException($message, 403, $reason, ['permission' => $permission->value]);
        }
    }

    /**
     * P3-18's bill lock (orders.bill_locked_at): after the first bill, voids need a manager.
     */
    public function billLocked(Order $order): bool
    {
        return $order->getAttribute('bill_locked_at') !== null;
    }

    /**
     * A room seats one open order at a time (use merge for a second party); tables and tapchans take several.
     */
    protected function assertSeatable(DiningTable $table, ?int $exceptOrderId): void
    {
        if (!$table->is_active || $table->hall === null || $table->hall->trashed() || !$table->hall->is_active) {
            throw new PosOrderException('Bu stol yoki xona hozir ishlamaydi (yopilgan zona).', 422, 'table_inactive');
        }

        if ($table->kind === DiningTableKind::ROOM) {
            $busy = Order::where('table_id', $table->id)
                ->whereIn('status', array_map(fn (OrderStatus $status) => $status->value, self::OPEN_STATUSES))
                ->when($exceptOrderId !== null, fn ($query) => $query->whereKeyNot($exceptOrderId))
                ->exists();
            if ($busy) {
                throw new PosOrderException("Xona band: unda ochiq buyurtma bor. Ko'chirish o'rniga birlashtiring.", 422, 'table_occupied');
            }
        }
    }

    /**
     * The waiter of a new order: the one named (staff of the store who works in the branch), else the opener when
     * they are a waiter.
     */
    protected function waiter(Branch $branch, ?int $waiterId, User $actor): ?User
    {
        if ($waiterId === null) {
            return $actor->role === \App\Enums\UserRole::WAITER ? $actor : null;
        }

        $waiter = User::where('store_id', $branch->store_id)
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $branch->id))
            ->whereIn('role', array_map(fn ($role) => $role->value, \App\Enums\UserRole::storeRoles()))
            ->find($waiterId);
        if ($waiter === null) {
            throw ValidationException::withMessages(['waiter_id' => 'Ofitsiant topilmadi.']);
        }

        return $waiter;
    }

    /**
     * The "choy-non" lines for guests $from → $to (R11): for each active rule of the branch or the order's hall (the
     * hall's rule replaces the branch's for the same item), quantity_per_guest × guests, rounded up to whole pieces;
     * the difference between the two counts. Hall orders only.
     *
     * @return list<array<string, mixed>>
     */
    protected function autoGuestLines(Order $order, int $from, int $to): array
    {
        if ($order->channel !== OrderChannel::POS_HALL || $to <= $from) {
            return [];
        }

        $rules = AutoGuestItem::with('variant')
            ->where('branch_id', $order->branch_id)
            ->where(fn ($query) => $query->whereNull('hall_id')->when($order->hall_id !== null, fn ($query) => $query->orWhere('hall_id', $order->hall_id)))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            // The hall's own rule of an item wins over the branch's
            ->sortBy(fn (AutoGuestItem $rule) => $rule->hall_id === null ? 1 : 0)
            ->unique('product_variant_id')
            ->filter(fn (AutoGuestItem $rule) => $rule->is_active && $rule->variant !== null && !$rule->variant->trashed())
            ->sortBy(fn (AutoGuestItem $rule) => sprintf('%010d%020d', $rule->sort_order, $rule->id));

        $lines = [];
        foreach ($rules->values() as $index => $rule) {
            /** @var ProductVariant $variant */
            $variant = $rule->variant;
            $count = fn (int $guests) => $this->guestQuantity(Quantity::of($rule->quantity_per_guest), $guests, $variant->unit->isMeasured());
            $delta = $count($to)->minus($count($from));
            if (!$delta->isPositive()) {
                continue;
            }

            $lines[] = [
                'index' => $index,
                'variant_uuid' => strtolower((string) $variant->uuid),
                'quantity' => $delta,
                'modifiers' => [],
                'note' => null,
                'seat_no' => null,
                'fire_mode' => FireMode::NOW,
                'due_at' => null,
            ];
        }

        return $lines;
    }

    private function guestQuantity(Quantity $perGuest, int $guests, bool $measured): Quantity
    {
        $total = $perGuest->times($guests);

        return $measured ? $total : Quantity::of($total->toBigDecimal()->toScale(0, RoundingMode::Up));
    }

    /**
     * A booking's pre-ordered lines (the contract's ids) as held lines with their due time.
     *
     * @param  list<array{product_variant_id: int, quantity: string, modifiers?: list<array{modifier_id: int, quantity?: int}>, note?: string|null, due_at?: string|null}>  $items
     * @return list<array<string, mixed>>
     */
    protected function heldLines(Branch $branch, array $items): array
    {
        if ($items === []) {
            return [];
        }

        $variantUuids = ProductVariant::whereIn('id', array_map(fn (array $item) => (int) $item['product_variant_id'], $items))
            ->whereHas('product', fn ($query) => $query->where('store_id', $branch->store_id))
            ->pluck('uuid', 'id');
        $modifierIds = [];
        foreach ($items as $item) {
            foreach ($item['modifiers'] ?? [] as $modifier) {
                $modifierIds[] = (int) $modifier['modifier_id'];
            }
        }
        $modifierUuids = $modifierIds === [] ? collect() : Modifier::whereIn('id', $modifierIds)->pluck('uuid', 'id');

        $lines = [];
        foreach ($items as $index => $item) {
            $quantity = Quantity::tryOf($item['quantity']);
            if ($quantity === null || !$quantity->isPositive()) {
                throw ValidationException::withMessages(["items.{$index}.quantity" => "Miqdor noto'g'ri."]);
            }

            $modifiers = [];
            foreach ($item['modifiers'] ?? [] as $modifier) {
                $uuid = $modifierUuids->get((int) $modifier['modifier_id']);
                if ($uuid === null) {
                    throw ValidationException::withMessages(["items.{$index}.modifiers" => "Qo'shimcha topilmadi."]);
                }
                $uuid = strtolower((string) $uuid);
                $modifiers[$uuid] = ($modifiers[$uuid] ?? 0) + max(1, (int) ($modifier['quantity'] ?? 1));
            }
            ksort($modifiers);

            $lines[] = [
                'index' => $index,
                'variant_uuid' => strtolower((string) ($variantUuids->get((int) $item['product_variant_id']) ?? '')),
                'quantity' => $quantity,
                'modifiers' => $modifiers,
                'note' => isset($item['note']) && trim((string) $item['note']) !== '' ? mb_substr(trim((string) $item['note']), 0, 255) : null,
                'seat_no' => null,
                'fire_mode' => FireMode::HOLD,
                'due_at' => !empty($item['due_at']) ? Carbon::parse((string) $item['due_at']) : null,
            ];
        }

        return $lines;
    }
}
