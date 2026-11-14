<?php

namespace App\Domain\Pos\Banquet;

use App\Models\BanquetEvent;
use App\Models\BanquetEventLine;
use App\Models\BanquetExtra;
use App\Models\BanquetHallRate;
use App\Models\BanquetPackage;
use App\Support\Money;
use App\Support\Quantity;

/**
 * P6-52: what an event costs. total = package × max(guests_guaranteed, guests_minimum) + the rent of each hall for
 * the slot + the extras (per event: price × quantity; per person: price × quantity × the billed guests) + the
 * event-day order (P6-54, its lines are left as they are).
 *
 * Before the contract day the prices come from the catalogue on every change. `freeze` stamps them: frozen lines keep
 * their unit price whatever the catalogue does later; only the quantities follow the event (guests, a moved hall).
 * A line added to a frozen event (a new extra, a new hall) takes today's price and is frozen at once.
 */
class BanquetPricing
{
    /**
     * Rebuilds the package, hall and extra lines and the totals (in the caller's transaction, the event locked).
     *
     * @param  list<array{extra_id?: int|null, name?: string|null, unit_price?: string|null, per_person?: bool|null, quantity?: string|null, note?: string|null}>|null  $extras
     *                                                                                                                                                                       the extras to keep (null: the current ones)
     */
    public function apply(BanquetEvent $event, ?array $extras = null): void
    {
        $frozen = $event->isFrozen();
        $guests = $event->billedGuests();
        /** @var \Illuminate\Support\Collection<int, BanquetEventLine> $lines */
        $lines = BanquetEventLine::where('banquet_event_id', $event->id)->orderBy('sort_order')->orderBy('id')->get();
        $sort = 0;

        // The package
        $packageLine = $lines->first(fn (BanquetEventLine $line) => $line->kind === BanquetLineKind::PACKAGE);
        $package = $event->banquet_package_id !== null ? BanquetPackage::with('items')->find($event->banquet_package_id) : null;
        if ($event->banquet_package_id === null) {
            $packageLine?->delete();
            $event->package_snapshot = null;
        } else {
            if ($packageLine !== null && $packageLine->banquet_package_id !== $event->banquet_package_id) {
                $packageLine->delete();
                $packageLine = null;
            }
            $keepPrice = $packageLine !== null && $packageLine->frozen;
            $price = $keepPrice ? $packageLine->unit_price : Money::of($package->per_person_price ?? '0')->toString();
            $packageLine ??= new BanquetEventLine(['banquet_event_id' => $event->id, 'kind' => BanquetLineKind::PACKAGE]);
            $packageLine->fill([
                'banquet_package_id' => $event->banquet_package_id,
                'name' => $keepPrice ? $packageLine->name : ($package !== null ? $package->name : ((string) ($event->package_snapshot['name'] ?? 'Paket'))),
                'quantity' => '1',
                'persons' => $guests,
                'unit_price' => $price,
                'frozen' => $frozen,
                'sort_order' => $sort++,
            ]);
            $packageLine->amount = Money::of($price)->times($guests)->toString();
            $packageLine->save();
            if (!$keepPrice && $package !== null) {
                $event->package_snapshot = self::snapshot($package);
            }
        }

        // The halls' rent for the slot
        $hallIds = $event->halls()->pluck('halls.id')->map(fn ($id) => (int) $id)->all();
        $rates = BanquetHallRate::whereIn('hall_id', $hallIds === [] ? [0] : $hallIds)->where('slot', $event->slot->value)->get()->keyBy('hall_id');
        $hallNames = \App\Models\Hall::withTrashed()->whereIn('id', $hallIds === [] ? [0] : $hallIds)->pluck('name', 'id');
        foreach ($lines->filter(fn (BanquetEventLine $line) => $line->kind === BanquetLineKind::HALL_RENT) as $line) {
            if (!in_array((int) $line->hall_id, $hallIds, true)) {
                $line->delete();
            }
        }
        foreach ($hallIds as $hallId) {
            $line = $lines->first(fn (BanquetEventLine $line) => $line->kind === BanquetLineKind::HALL_RENT && (int) $line->hall_id === $hallId && $line->exists);
            // A frozen rent stays while the slot does not change (the note keeps the slot it was priced for)
            $keepPrice = $line !== null && $line->frozen && $line->note === $event->slot->value;
            $price = $keepPrice ? $line->unit_price : Money::of($rates->get($hallId)->price ?? '0')->toString();
            $line ??= new BanquetEventLine(['banquet_event_id' => $event->id, 'kind' => BanquetLineKind::HALL_RENT, 'hall_id' => $hallId]);
            $line->fill([
                'name' => 'Zal ijarasi: ' . ($hallNames[$hallId] ?? "#{$hallId}"),
                'quantity' => '1',
                'persons' => null,
                'unit_price' => $price,
                'amount' => $price,
                'frozen' => $frozen,
                'note' => $event->slot->value,
                'sort_order' => $sort++,
            ]);
            $line->save();
        }

        // Extras
        $current = $lines->filter(fn (BanquetEventLine $line) => $line->kind === BanquetLineKind::EXTRA)->values();
        if ($extras === null) {
            $extras = $current->map(fn (BanquetEventLine $line) => [
                'line' => $line,
                'quantity' => $line->quantity,
                'note' => $line->note,
            ])->all();
        } else {
            $extras = $this->matchExtras($current, $extras, (int) $event->store_id);
        }
        $kept = [];
        foreach ($extras as $extra) {
            /** @var BanquetEventLine|null $line */
            $line = $extra['line'] ?? null;
            $catalogue = $extra['catalogue'] ?? null;
            $keepPrice = $line !== null && $line->frozen;
            if ($line === null) {
                $line = new BanquetEventLine(['banquet_event_id' => $event->id, 'kind' => BanquetLineKind::EXTRA]);
            }
            if (!$keepPrice) {
                if ($catalogue instanceof BanquetExtra) {
                    $line->banquet_extra_id = $catalogue->id;
                    $line->name = $catalogue->name;
                    $line->unit_price = Money::of($catalogue->price)->toString();
                    $perPerson = $catalogue->pricing === BanquetExtraPricing::PER_PERSON;
                } elseif ($line->exists && $line->banquet_extra_id !== null && ($found = BanquetExtra::find($line->banquet_extra_id)) !== null) {
                    $line->name = $found->name;
                    $line->unit_price = Money::of($found->price)->toString();
                    $perPerson = $found->pricing === BanquetExtraPricing::PER_PERSON;
                } else {
                    if (!$line->exists && (!isset($extra['name']) || !isset($extra['unit_price']))) {
                        throw new BanquetException("Qo'shimchaning nomi va narxini yozing.", 'extra_name_required', 422);
                    }
                    if (isset($extra['name'])) {
                        $line->name = (string) $extra['name'];
                    }
                    if (isset($extra['unit_price'])) {
                        $line->unit_price = Money::of($extra['unit_price'])->toString();
                    }
                    $perPerson = array_key_exists('per_person', $extra) ? (bool) $extra['per_person'] : $line->persons !== null;
                }
            } else {
                $perPerson = $line->persons !== null;
            }
            $quantity = Quantity::of($extra['quantity'] ?? $line->quantity ?? '1')->toString();
            $line->quantity = $quantity;
            $line->persons = $perPerson ? $guests : null;
            $line->amount = Money::of($line->unit_price)->times($quantity)->times($perPerson ? $guests : 1)->toString();
            $line->frozen = $frozen;
            if (array_key_exists('note', $extra)) {
                $line->note = $extra['note'];
            }
            $line->sort_order = $sort++;
            $line->save();
            $kept[] = $line->id;
        }
        foreach ($current as $line) {
            if (!in_array($line->id, $kept, true)) {
                $line->delete();
            }
        }

        $this->totals($event);
    }

    /**
     * The totals from the lines (package, halls, extras, event-day order).
     */
    public function totals(BanquetEvent $event): void
    {
        $sums = [];
        foreach (BanquetLineKind::cases() as $kind) {
            $sums[$kind->value] = Money::sum(...BanquetEventLine::where('banquet_event_id', $event->id)->where('kind', $kind->value)->pluck('amount')->all());
        }
        $event->package_total = $sums[BanquetLineKind::PACKAGE->value]->toString();
        $event->hall_total = $sums[BanquetLineKind::HALL_RENT->value]->toString();
        $event->extras_total = $sums[BanquetLineKind::EXTRA->value]->toString();
        $event->orders_total = $sums[BanquetLineKind::ORDER->value]->toString();
        $event->total = Money::sum(...array_values($sums))->toString();
    }

    /**
     * Stamps the prices (the contract day): every line frozen, the package snapshot taken now.
     */
    public function freeze(BanquetEvent $event): void
    {
        $package = $event->banquet_package_id !== null ? BanquetPackage::with('items')->find($event->banquet_package_id) : null;
        if ($package !== null) {
            $event->package_snapshot = self::snapshot($package);
        }
        BanquetEventLine::where('banquet_event_id', $event->id)->whereIn('kind', [BanquetLineKind::PACKAGE->value, BanquetLineKind::HALL_RENT->value, BanquetLineKind::EXTRA->value])
            ->update(['frozen' => true, 'updated_at' => now()]);
    }

    public function unfreeze(BanquetEvent $event): void
    {
        BanquetEventLine::where('banquet_event_id', $event->id)->whereIn('kind', [BanquetLineKind::PACKAGE->value, BanquetLineKind::HALL_RENT->value, BanquetLineKind::EXTRA->value])
            ->update(['frozen' => false, 'updated_at' => now()]);
    }

    /**
     * @return array{id: int, uuid: string, name: string, per_person_price: string, minimum_guests: int, included: list<string>, items: list<array{product_id: int|null, product_variant_id: int|null, name: string, quantity_per_person: string, unit: string}>}
     */
    public static function snapshot(BanquetPackage $package): array
    {
        return [
            'id' => (int) $package->id,
            'uuid' => (string) $package->uuid,
            'name' => $package->name,
            'per_person_price' => Money::of($package->per_person_price)->toString(),
            'minimum_guests' => (int) $package->minimum_guests,
            'included' => array_map('strval', $package->included ?? []),
            'items' => $package->items->map(fn ($item) => [
                'product_id' => $item->product_id !== null ? (int) $item->product_id : null,
                'product_variant_id' => $item->product_variant_id !== null ? (int) $item->product_variant_id : null,
                'name' => $item->name,
                'quantity_per_person' => Quantity::of($item->quantity_per_person)->toString(),
                'unit' => $item->unit,
            ])->values()->all(),
        ];
    }

    /**
     * Pairs the extras sent with the event's lines: by line uuid, else by catalogue extra (one line per extra), else a
     * new line.
     *
     * @param  \Illuminate\Support\Collection<int, BanquetEventLine>  $current
     * @param  list<array<string, mixed>>  $extras
     * @return list<array<string, mixed>>
     */
    private function matchExtras(\Illuminate\Support\Collection $current, array $extras, int $storeId): array
    {
        $used = [];
        $out = [];
        foreach ($extras as $extra) {
            $line = null;
            if (isset($extra['line_uuid'])) {
                $line = $current->first(fn (BanquetEventLine $line) => $line->uuid === $extra['line_uuid'] && !in_array($line->id, $used, true));
            }
            $catalogue = null;
            if (isset($extra['extra_id'])) {
                $catalogue = BanquetExtra::where('store_id', $storeId)->find((int) $extra['extra_id']);
                if ($catalogue === null) {
                    throw new BanquetException("Qo'shimcha topilmadi.", 'extra_not_found', 422, ['extra_id' => (int) $extra['extra_id']]);
                }
                $line ??= $current->first(fn (BanquetEventLine $line) => (int) $line->banquet_extra_id === (int) $extra['extra_id'] && !in_array($line->id, $used, true));
            }
            if ($line !== null) {
                $used[] = $line->id;
            }
            $out[] = ['line' => $line, 'catalogue' => $catalogue] + $extra;
        }

        return $out;
    }
}
