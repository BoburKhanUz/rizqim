<?php

namespace App\Domain\Pos\Booking;

use App\Enums\OrderChannel;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Modifier;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\ChannelMenu;
use App\Services\Order\OrderLineBuilder;
use App\Support\Quantity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Dishes ordered ahead with a booking (research report R15): N kg osh ready by 19:00, a kazan kabob, a set for N
 * people. Quantities and modifiers are checked like an order line (OrderLineBuilder: kg steps, modifier groups); the
 * price is only an estimate for the booking card (the order line takes the menu as it is on arrival). The stop-list is
 * not checked: it is about today, the dish is for later.
 */
class BookingItemService
{
    public const MAX_ITEMS = 100;

    public function __construct(
        protected OrderLineBuilder $lines,
        protected BookingService $bookings,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{product_variant_id: int, quantity: mixed, modifiers?: list<array{modifier_id: int, quantity?: int}>|null, note?: string|null, due_at?: Carbon|null}  $data
     */
    public function add(Booking $booking, array $data, User $actor, ?int $version = null): BookingItem
    {
        $item = DB::transaction(function () use ($booking, $data, $actor, $version) {
            $booking = $this->editable($booking, $version);
            if ($booking->items()->count() >= self::MAX_ITEMS) {
                throw new BookingException('Bitta bronga ko\'pi bilan ' . self::MAX_ITEMS . " ta taom qo'shiladi.", 'too_many_items');
            }

            $item = new BookingItem(['booking_id' => $booking->id, 'created_by' => $actor->id]);
            $this->fill($booking, $item, $data);
            $item->save();
            $this->touch($booking);

            $this->audit->record('booking.item_added', $booking, [], [], ['item_id' => $item->id, 'product_variant_id' => $item->product_variant_id, 'quantity' => $item->quantity], ['actor' => $actor]);

            return $item;
        });

        $this->bookings->changed($booking->refresh());

        return $item;
    }

    /**
     * @param  array<string, mixed>  $data  the fields of add() that were sent
     */
    public function update(Booking $booking, BookingItem $item, array $data, User $actor, ?int $version = null): BookingItem
    {
        $item = DB::transaction(function () use ($booking, $item, $data, $actor, $version) {
            $booking = $this->editable($booking, $version);
            $item = BookingItem::whereKey($item->id)->where('booking_id', $booking->id)->lockForUpdate()->firstOrFail();

            $this->fill($booking, $item, [
                'product_variant_id' => $data['product_variant_id'] ?? (int) $item->product_variant_id,
                'quantity' => $data['quantity'] ?? $item->quantity,
                'modifiers' => array_key_exists('modifiers', $data)
                    ? $data['modifiers']
                    : array_map(fn (array $modifier) => ['modifier_id' => (int) $modifier['id'], 'quantity' => (int) ($modifier['quantity'] ?? 1)], $item->modifiers ?? []),
                'note' => array_key_exists('note', $data) ? $data['note'] : $item->note,
                'due_at' => array_key_exists('due_at', $data) ? $data['due_at'] : $item->due_at,
            ]);
            $item->save();
            $this->touch($booking);

            $this->audit->record('booking.item_changed', $booking, [], [], ['item_id' => $item->id, 'quantity' => $item->quantity], ['actor' => $actor]);

            return $item;
        });

        $this->bookings->changed($booking->refresh());

        return $item;
    }

    public function remove(Booking $booking, BookingItem $item, User $actor, ?int $version = null): void
    {
        DB::transaction(function () use ($booking, $item, $actor, $version) {
            $booking = $this->editable($booking, $version);
            BookingItem::whereKey($item->id)->where('booking_id', $booking->id)->delete();
            $this->touch($booking);

            $this->audit->record('booking.item_removed', $booking, [], [], ['item_id' => $item->id], ['actor' => $actor]);
        });

        $this->bookings->changed($booking->refresh());
    }

    private function editable(Booking $booking, ?int $version): Booking
    {
        $booking = $this->bookings->lockFresh($booking, $version);
        if (!$booking->status->isEditable()) {
            throw new BookingException("Bron \"{$booking->status->label()}\" holatida: oldindan buyurtmani o'zgartirib bo'lmaydi.", 'booking_not_editable');
        }

        return $booking;
    }

    private function touch(Booking $booking): void
    {
        $booking->version = $booking->version + 1;
        $booking->save();
    }

    /**
     * @param  array{product_variant_id: int, quantity: mixed, modifiers?: list<array{modifier_id: int, quantity?: int}>|null, note?: string|null, due_at?: Carbon|null}  $data
     */
    private function fill(Booking $booking, BookingItem $item, array $data): void
    {
        $variant = ProductVariant::with('product')->find((int) $data['product_variant_id']);
        if ($variant === null || $variant->product === null || (int) $variant->product->store_id !== (int) $booking->store_id) {
            throw ValidationException::withMessages(['product_variant_id' => 'Taom topilmadi.']);
        }
        if (!$variant->is_active || $variant->product->status !== 'active') {
            throw ValidationException::withMessages(['product_variant_id' => "'{$this->lines->nameOf($variant)}' hozir sotilmaydi."]);
        }

        $quantity = Quantity::tryOf($data['quantity']);
        if ($quantity === null || !$quantity->isPositive()) {
            throw ValidationException::withMessages(['quantity' => "Miqdor noto'g'ri: musbat son, ko'pi bilan 3 ta kasr xona (masalan 1 yoki 0.5)."]);
        }

        try {
            $this->lines->checkQuantity($variant, $quantity, 0);

            // OrderLineBuilder takes modifiers by uuid; the POS sends their ids
            $chosen = [];
            $requested = $data['modifiers'] ?? [];
            $uuids = Modifier::whereIn('id', array_map(fn (array $choice) => (int) $choice['modifier_id'], $requested))->pluck('uuid', 'id');
            foreach ($requested as $choice) {
                $uuid = $uuids[(int) $choice['modifier_id']] ?? null;
                if ($uuid === null) {
                    throw ValidationException::withMessages(['modifiers' => "Qo'shimcha topilmadi."]);
                }
                $uuid = strtolower((string) $uuid);
                $chosen[$uuid] = ($chosen[$uuid] ?? 0) + max(1, (int) ($choice['quantity'] ?? 1));
            }
            $rules = $this->lines->modifierRules([(int) $variant->product_id])[(int) $variant->product_id] ?? [];
            $modifiers = $this->lines->resolveModifiers($variant, $chosen, $rules, [], 0);
        } catch (ValidationException $e) {
            // The builder names checkout fields (items.0.quantity); here the item's own fields
            $messages = [];
            foreach ($e->errors() as $field => $errors) {
                $messages[str_contains($field, 'modifiers') ? 'modifiers' : (str_contains($field, 'quantity') ? 'quantity' : $field)] = $errors;
            }
            throw ValidationException::withMessages($messages);
        }

        $item->product_variant_id = $variant->id;
        $item->product_name = (string) $variant->product->name;
        $item->variant_name = $variant->name;
        $item->unit = $variant->unit;
        $item->quantity = $quantity->toString();
        $item->modifiers = $modifiers['snapshot'] ?: null;
        $item->unit_price = ChannelMenu::for((int) $booking->store_id, OrderChannel::POS_HALL)->priceOf($variant)->toString();
        $item->note = $data['note'] ?? null;
        // Without a time of its own the dish is due when the guests come
        $item->due_at = ($data['due_at'] ?? null)?->copy()->utc() ?? $booking->starts_at;
    }
}
