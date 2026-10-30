<?php

namespace App\Domain\Pos\Menu;

use App\Domain\Pos\Contracts\KitchenStationResolver;
use App\Enums\KitchenItemStatus;
use App\Models\OrderItem;

/**
 * P6-31: the kitchen side of a combo line (docs/MENU.md "Kombo", docs/POS_SPEC.md §20). A combo is ONE priced order line
 * (`combo_parts`); each part also gets a zero-priced kitchen line (`combo_parent_id`) routed to its own station
 * (KitchenStationResolver by the part's dish), shown and bumped there with the combo's name as context. The part lines
 * are hidden from every other query (OrderItem's global scope): bills, receipts, refunds, splits and reports see the
 * combo line only, so the money of a combo is never counted twice.
 *
 * The two stay in step through OrderItem's saved hook:
 *  - a part bumped or recalled rolls up: the combo line is `ready` once every part (not voided) is ready or served, and
 *    goes back to `sent` when one is recalled (so "all lines ready → order ready", the pickup queue and courses keep
 *    reading the order's own lines);
 *  - the combo line cascades down: sent (its parts get sent_at and their stations), voided, served, bumped or recalled
 *    as a whole, moved to another order (merge), its round, firing, course, seat or due time changed.
 * Mass updates of kitchen state (OrderStatusService, PickupQueue) and line moves (merge) include the part lines
 * (OrderItem::withComboParts()). The combo line itself goes to no station.
 */
class ComboKitchen
{
    /** Fields the part lines copy from their combo line. */
    private const COPIED = ['order_id', 'round_no', 'fire_mode', 'due_at', 'course_no', 'seat_no', 'added_by'];

    private static bool $busy = false;

    /**
     * Stores the kitchen lines of a combo line just created, and records their ids in its parts.
     *
     * @param  list<array<string, mixed>>  $parts
     */
    public function createParts(OrderItem $parent, array $parts): void
    {
        $resolver = app()->bound(KitchenStationResolver::class) ? app(KitchenStationResolver::class) : null;
        $comboQuantity = (int) max(1, (int) round((float) ($parent->getRawOriginal('quantity') ?? $parent->getAttributes()['quantity'] ?? 1)));

        self::$busy = true;
        try {
            foreach ($parts as $k => $part) {
                $child = new OrderItem([
                    'order_id' => $parent->order_id,
                    'combo_parent_id' => $parent->id,
                    'product_variant_id' => $part['variant_id'],
                    'product_name' => $part['name'],
                    'variant_name' => $part['variant_name'],
                    'quantity' => (string) ((int) $part['quantity'] * $comboQuantity),
                    'unit' => $part['unit'] ?? 'piece',
                    'unit_price' => '0.00',
                    // The kitchen reads the part's modifiers; their money is in the combo line
                    'modifiers' => array_map(fn (array $modifier) => [...$modifier, 'price_delta' => '0.00', 'total' => '0.00'], $part['modifiers'] ?? []) ?: null,
                    'modifiers_total' => '0.00',
                    'total_price' => '0.00',
                    'kitchen_status' => $parent->kitchen_status->value,
                    'sent_at' => $parent->sent_at,
                ] + array_filter(array_intersect_key($parent->getAttributes(), array_flip(self::COPIED)), fn ($value) => $value !== null));
                $child->setRelation('order', $parent->relationLoaded('order') ? $parent->order : $parent->order()->first());
                $child->station_id = $resolver?->stationIdFor($child);
                $child->save();
                $parts[$k]['item_id'] = (int) $child->id;
            }

            $parent->forceFill(['combo_parts' => $parts])->saveQuietly();
        } finally {
            self::$busy = false;
        }
    }

    /**
     * The lines as the kitchen cooks them: each combo line replaced by its parts' kitchen lines (in its place, each with
     * the combo line as `comboParent`), every other line as it is. One query for all the combos of the lines.
     *
     * @param  \Illuminate\Support\Collection<int, OrderItem>  $items
     * @return \Illuminate\Support\Collection<int, OrderItem>
     */
    public static function forKitchen(\Illuminate\Support\Collection $items): \Illuminate\Support\Collection
    {
        $combos = $items->filter(fn (OrderItem $item) => $item->isComboLine());
        if ($combos->isEmpty()) {
            return $items->values();
        }

        $parts = OrderItem::withComboParts()->whereIn('combo_parent_id', $combos->pluck('id')->all())->orderBy('id')->get()->groupBy('combo_parent_id');

        return $items->flatMap(function (OrderItem $item) use ($parts) {
            if (!$item->isComboLine()) {
                return [$item];
            }

            return ($parts->get($item->id) ?? collect())->map(function (OrderItem $part) use ($item) {
                $part->setRelation('comboParent', $item);
                if ($item->relationLoaded('order')) {
                    $part->setRelation('order', $item->order);
                }

                return $part;
            })->all();
        })->values();
    }

    /**
     * OrderItem saving: the combo line itself is cooked nowhere (its parts are).
     */
    public function saving(OrderItem $item): void
    {
        if ($item->isComboLine() && $item->station_id !== null) {
            $item->station_id = null;
        }
    }

    /**
     * OrderItem saved: a part rolls up, a combo line cascades down.
     */
    public function saved(OrderItem $item): void
    {
        if (self::$busy) {
            return;
        }

        if ($item->isComboPart()) {
            if ($item->wasChanged(['kitchen_status', 'ready_at'])) {
                $this->rollUp((int) $item->combo_parent_id);
            }

            return;
        }

        // getChanges() is only filled by an update (a new line has no parts yet)
        if ($item->isComboLine() && $item->getChanges() !== []) {
            $this->cascade($item);
        }
    }

    /**
     * The combo line's kitchen state from its parts: ready (with the last part's time) when every part not voided is
     * done; back to sent (or pending, never sent) when one is cooking again. A voided or served combo stays as it is.
     */
    public function rollUp(int $parentId): void
    {
        $parent = OrderItem::query()->whereKey($parentId)->first();
        if ($parent === null || in_array($parent->kitchen_status, [KitchenItemStatus::VOID, KitchenItemStatus::SERVED], true)) {
            return;
        }
        $parts = $parent->comboChildren()->get()->reject(fn (OrderItem $part) => $part->isVoid());
        if ($parts->isEmpty()) {
            return;
        }

        $done = $parts->every(fn (OrderItem $part) => in_array($part->kitchen_status->value, KitchenItemStatus::doneValues(), true));
        $cooking = in_array($parent->kitchen_status->value, KitchenItemStatus::cookingValues(), true);
        if ($done && $cooking) {
            $parent->forceFill(['kitchen_status' => KitchenItemStatus::READY->value, 'ready_at' => $parts->max('ready_at') ?? now()]);
        } elseif (!$done && $parent->kitchen_status === KitchenItemStatus::READY) {
            $parent->forceFill(['kitchen_status' => ($parent->sent_at !== null ? KitchenItemStatus::SENT : KitchenItemStatus::PENDING)->value, 'ready_at' => null]);
        } else {
            return;
        }

        self::$busy = true;
        try {
            $parent->save();
        } finally {
            self::$busy = false;
        }
    }

    /**
     * The combo line changed: its parts follow.
     */
    private function cascade(OrderItem $parent): void
    {
        $changes = $parent->getChanges();
        $copied = array_intersect_key($changes, array_flip(self::COPIED));
        $statusChanged = array_key_exists('kitchen_status', $changes);
        $sentNow = array_key_exists('sent_at', $changes) && $parent->sent_at !== null;
        if ($copied === [] && !$statusChanged && !$sentNow) {
            return;
        }

        self::$busy = true;
        try {
            $parts = $parent->comboChildren()->get();
            $resolver = app()->bound(KitchenStationResolver::class) ? app(KitchenStationResolver::class) : null;
            foreach ($parts as $part) {
                $part->forceFill(array_intersect_key($parent->getAttributes(), $copied));
                $to = $parent->kitchen_status;
                $was = $part->kitchen_status;
                $partDone = in_array($was->value, KitchenItemStatus::doneValues(), true);

                if ($to === KitchenItemStatus::VOID && $was !== KitchenItemStatus::VOID) {
                    $part->forceFill(['kitchen_status' => KitchenItemStatus::VOID->value, 'voided_at' => $parent->voided_at ?? now(), 'voided_by' => $parent->voided_by, 'void_reason' => $parent->void_reason]);
                } elseif ($was !== KitchenItemStatus::VOID && $statusChanged) {
                    match (true) {
                        $to === KitchenItemStatus::SERVED => $part->forceFill(['kitchen_status' => KitchenItemStatus::SERVED->value]),
                        $to === KitchenItemStatus::READY && !$partDone => $part->forceFill(['kitchen_status' => KitchenItemStatus::READY->value, 'ready_at' => $parent->ready_at ?? now()]),
                        // A recall of the whole combo (its parts back to the kitchen)
                        in_array($to, [KitchenItemStatus::SENT, KitchenItemStatus::PENDING], true) && ($partDone || $sentNow) => $part->forceFill([
                            'kitchen_status' => ($parent->sent_at !== null ? KitchenItemStatus::SENT : KitchenItemStatus::PENDING)->value,
                            'ready_at' => null,
                        ]),
                        default => null,
                    };
                }
                if ($sentNow && $part->sent_at === null && $part->kitchen_status !== KitchenItemStatus::VOID) {
                    $part->setRelation('order', $parent->relationLoaded('order') ? $parent->order : $parent->order()->first());
                    $part->forceFill([
                        'sent_at' => $parent->sent_at,
                        'kitchen_status' => in_array($part->kitchen_status->value, KitchenItemStatus::doneValues(), true) ? $part->kitchen_status->value : KitchenItemStatus::SENT->value,
                        'station_id' => $resolver !== null ? $resolver->stationIdFor($part) : $part->station_id,
                    ]);
                }
                if ($part->isDirty()) {
                    $part->save();
                }
            }
        } finally {
            self::$busy = false;
        }
    }
}
