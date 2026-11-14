<?php

namespace App\Domain\Pos\Banquet\Ops;

use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Domain\Pos\Banquet\BanquetEventType;
use App\Domain\Pos\Setup\KitchenStationRouter;
use App\Models\BanquetEvent;
use App\Models\BanquetPackageItem;
use App\Models\Branch;
use App\Models\KitchenStation;
use App\Models\Product;
use App\Support\StoreTimezone;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * P6-54 (E4): the kitchen's production sheet of an event and the shopping list (bozorlik) of several.
 *
 *  - guests: base = max(actual, guaranteed, minimum) — the guests the kitchen cooks for are never fewer than billed;
 *    planned = base × (1 + reserve %), rounded up to a whole guest (the store's reserve setting);
 *  - dishes: each menu item of the event's package (the frozen snapshot's items, else the package's) × planned:
 *    quantity = quantity per person × planned (portions, pieces, kg or litres; 3 decimals, half-up); kg = the quantity
 *    for kg/l items, portions × the product's portion weight (settings) for portion/piece items, else null; grouped by
 *    the kitchen station of the branch the product is routed to (no route: "Umumiy");
 *  - osh: the package items whose product is marked as osh (settings) run the osh calculator for their portions; an
 *    `osh` event without such an item runs it for the planned guests;
 *  - shopping list: the events of a branch taking place on the dates (hold, confirmed or in progress), their dishes
 *    summed by product and unit, the osh ingredients summed, and each event's planned guests.
 *
 * No customer data on either: the kitchen's paper (the event's title and type only).
 */
final class BanquetProduction
{
    public const DISH = 'array{product_id: int|null, name: string, unit: string, quantity_per_person: string|null, quantity: string, kg: string|null}';

    public const SHEET = 'array{event: array{uuid: string, title: string|null, event_type: string, event_type_label: string, event_date: string, slot: string, starts_at: string|null, ends_at: string|null, local_time: string, status: string, halls: list<string>}, guests: array{guaranteed: int, minimum: int, actual: int|null, base: int, reserve_percent: string, planned: int}, stations: list<array{station_id: int|null, name: string, dishes: list<' . self::DISH . '>}>, osh: ' . OshCalculator::RESULT . '|null, warnings: list<' . BanquetWarnings::WARNING . '>}';

    public const SHOPPING = 'array{branch_id: int, from: string, to: string, events: list<array{uuid: string, title: string|null, event_type: string, event_date: string, slot: string, status: string, planned_guests: int}>, dishes: list<' . self::DISH . '>, osh: array{persons: int, osh_kg: string, ingredients: list<array{key: string, name: string, unit: string, per_kg: string, quantity: string}>}}';

    public function __construct(
        private readonly BanquetOpsSettings $settings,
        private readonly KitchenStationRouter $stations,
        private readonly BanquetWarnings $warnings,
    ) {}

    /**
     * @return array<string, mixed> SHEET
     */
    public function sheet(BanquetEvent $event): array
    {
        $settings = $this->settings->of((int) $event->store_id);
        $guests = self::guests($event, $settings['reserve_percent']);
        [$dishes, $oshPersons] = $this->dishes($event, $guests['planned'], $settings);

        $byStation = [];
        foreach ($dishes as $dish) {
            $key = $dish['station_id'] ?? 0;
            $byStation[$key] ??= ['station_id' => $dish['station_id'], 'name' => $dish['station_name'], 'dishes' => []];
            unset($dish['station_id'], $dish['station_name'], $dish['category_id']);
            $byStation[$key]['dishes'][] = $dish;
        }
        // Named stations first (by name), the unrouted ones last
        uasort($byStation, fn (array $a, array $b) => [$a['station_id'] === null, $a['name']] <=> [$b['station_id'] === null, $b['name']]);

        return [
            'event' => self::eventSummary($event),
            'guests' => $guests,
            'stations' => array_values($byStation),
            'osh' => $oshPersons > 0 ? OshCalculator::calculate($oshPersons, $settings['osh']) : null,
            'warnings' => $this->warnings->of($event),
        ];
    }

    /**
     * @return array<string, mixed> SHOPPING
     */
    public function shoppingList(Branch $branch, string $from, string $to): array
    {
        $settings = $this->settings->of((int) $branch->store_id);
        $events = BanquetEvent::where('branch_id', $branch->id)
            ->whereBetween('event_date', [$from, $to])
            ->whereIn('status', array_map(fn (BanquetEventStatus $status) => $status->value, BanquetEventStatus::holding()))
            ->orderBy('event_date')
            ->orderBy('starts_at')
            ->limit(500)
            ->get();

        $summaries = [];
        $dishes = [];
        $osh = ['persons' => 0, 'osh_kg' => '0.0', 'ingredients' => []];
        foreach ($events as $event) {
            $guests = self::guests($event, $settings['reserve_percent']);
            $summaries[] = [
                'uuid' => (string) $event->uuid,
                'title' => $event->title,
                'event_type' => $event->event_type->value,
                'event_date' => $event->event_date->toDateString(),
                'slot' => $event->slot->value,
                'status' => $event->status->value,
                'planned_guests' => $guests['planned'],
            ];
            [$eventDishes, $oshPersons] = $this->dishes($event, $guests['planned'], $settings);
            foreach ($eventDishes as $dish) {
                $key = ($dish['product_id'] ?? 'n:' . mb_strtolower($dish['name'])) . '|' . $dish['unit'];
                if (!isset($dishes[$key])) {
                    $dishes[$key] = ['product_id' => $dish['product_id'], 'name' => $dish['name'], 'unit' => $dish['unit'], 'quantity_per_person' => $dish['quantity_per_person'], 'quantity' => '0.000', 'kg' => null];
                }
                $dishes[$key]['quantity'] = BigDecimal::of($dishes[$key]['quantity'])->plus($dish['quantity'])->toScale(3)->__toString();
                if ($dish['kg'] !== null) {
                    $dishes[$key]['kg'] = BigDecimal::of($dishes[$key]['kg'] ?? '0')->plus($dish['kg'])->toScale(3)->__toString();
                }
            }
            if ($oshPersons > 0) {
                // Each event cooks its own kazans: the ingredients add up event by event
                $result = OshCalculator::calculate($oshPersons, $settings['osh']);
                $osh['persons'] += $oshPersons;
                $osh['osh_kg'] = BigDecimal::of($osh['osh_kg'])->plus($result['osh_kg'])->toScale(1)->__toString();
                $osh['ingredients'] = OshCalculator::sumIngredients($osh['ingredients'], $result['ingredients']);
            }
        }
        $dishes = array_values($dishes);
        usort($dishes, fn (array $a, array $b) => strcmp($a['name'], $b['name']));
        // The same dish is its own line per event; the shopping list's per-person figure means nothing once summed
        foreach ($dishes as &$dish) {
            $dish['quantity_per_person'] = null;
        }
        unset($dish);

        return ['branch_id' => (int) $branch->id, 'from' => $from, 'to' => $to, 'events' => $summaries, 'dishes' => $dishes, 'osh' => $osh];
    }

    /**
     * @return array{guaranteed: int, minimum: int, actual: int|null, base: int, reserve_percent: string, planned: int}
     */
    public static function guests(BanquetEvent $event, string $reservePercent): array
    {
        $base = max((int) ($event->guests_actual ?? 0), (int) $event->guests_guaranteed, (int) $event->guests_minimum);
        $planned = BigDecimal::of($base)->multipliedBy(BigDecimal::of($reservePercent)->plus(100))->dividedBy(100, 0, RoundingMode::Ceiling)->toInt();

        return [
            'guaranteed' => (int) $event->guests_guaranteed,
            'minimum' => (int) $event->guests_minimum,
            'actual' => $event->guests_actual !== null ? (int) $event->guests_actual : null,
            'base' => $base,
            'reserve_percent' => $reservePercent,
            'planned' => $planned,
        ];
    }

    /**
     * The event's package items (the frozen snapshot's, else the package's own).
     *
     * @return list<array{product_id: int|null, name: string, quantity_per_person: string, unit: string}>
     */
    public static function packageItems(BanquetEvent $event): array
    {
        $snapshot = $event->package_snapshot;
        $items = is_array($snapshot) && is_array($snapshot['items'] ?? null) ? $snapshot['items'] : null;
        if ($items === null) {
            $items = $event->banquet_package_id !== null
                ? BanquetPackageItem::where('banquet_package_id', $event->banquet_package_id)->orderBy('sort_order')->orderBy('id')->get()->map(fn (BanquetPackageItem $item) => $item->only(['product_id', 'name', 'quantity_per_person', 'unit']))->all()
                : [];
        }

        $out = [];
        foreach ($items as $item) {
            if (!is_array($item) || !is_numeric($item['quantity_per_person'] ?? null)) {
                continue;
            }
            $out[] = [
                'product_id' => isset($item['product_id']) && is_numeric($item['product_id']) ? (int) $item['product_id'] : null,
                'name' => (string) ($item['name'] ?? ''),
                'quantity_per_person' => BigDecimal::of((string) $item['quantity_per_person'])->toScale(3, RoundingMode::HalfUp)->__toString(),
                'unit' => in_array($item['unit'] ?? null, ['portion', 'piece', 'kg', 'l'], true) ? (string) $item['unit'] : 'portion',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $settings  BanquetOpsSettings::SHAPE
     * @return array{0: list<array{product_id: int|null, category_id: int|null, station_id: int|null, station_name: string, name: string, unit: string, quantity_per_person: string, quantity: string, kg: string|null}>, 1: int}
     */
    private function dishes(BanquetEvent $event, int $planned, array $settings): array
    {
        $items = self::packageItems($event);
        $products = Product::withTrashed()->where('store_id', $event->store_id)->whereIn('id', array_filter(array_column($items, 'product_id')))->get(['id', 'category_id', 'name'])->keyBy('id');
        $weights = [];
        foreach ($settings['portion_weights'] as $weight) {
            $weights[$weight['product_id']] = $weight['grams'];
        }
        $oshProducts = $settings['osh']['product_ids'];
        $stationNames = KitchenStation::where('branch_id', $event->branch_id)->pluck('name', 'id')->all();

        $dishes = [];
        $oshPersons = 0;
        foreach ($items as $item) {
            $product = $item['product_id'] !== null ? $products->get($item['product_id']) : null;
            $quantity = BigDecimal::of($item['quantity_per_person'])->multipliedBy($planned)->toScale(3, RoundingMode::HalfUp);
            $kg = match (true) {
                in_array($item['unit'], ['kg', 'l'], true) => $quantity->__toString(),
                $product !== null && isset($weights[$product->id]) => $quantity->multipliedBy($weights[$product->id])->dividedBy(1000, 3, RoundingMode::HalfUp)->__toString(),
                default => null,
            };
            $stationId = $product !== null ? $this->stations->stationIdForProduct((int) $event->branch_id, (int) $product->id, (int) $product->category_id) : null;
            $dishes[] = [
                'product_id' => $product !== null ? (int) $product->id : null,
                'category_id' => $product !== null ? (int) $product->category_id : null,
                'station_id' => $stationId,
                'station_name' => $stationId !== null ? (string) ($stationNames[$stationId] ?? 'Sex') : 'Umumiy',
                'name' => $item['name'] !== '' ? $item['name'] : (string) ($product->name ?? ''),
                'unit' => $item['unit'],
                'quantity_per_person' => $item['quantity_per_person'],
                'quantity' => $quantity->__toString(),
                'kg' => $kg,
            ];
            if ($product !== null && in_array((int) $product->id, $oshProducts, true) && in_array($item['unit'], ['portion', 'piece'], true)) {
                $oshPersons += $quantity->toScale(0, RoundingMode::Ceiling)->toInt();
            }
        }
        if ($oshPersons === 0 && $event->event_type === BanquetEventType::OSH) {
            $oshPersons = $planned;
        }

        return [$dishes, $oshPersons];
    }

    /**
     * @return array{uuid: string, title: string|null, event_type: string, event_type_label: string, event_date: string, slot: string, starts_at: string|null, ends_at: string|null, local_time: string, status: string, halls: list<string>}
     */
    public static function eventSummary(BanquetEvent $event): array
    {
        $tz = StoreTimezone::of($event->store()->first());

        return [
            'uuid' => (string) $event->uuid,
            'title' => $event->title,
            'event_type' => $event->event_type->value,
            'event_type_label' => $event->event_type->label(),
            'event_date' => $event->event_date->toDateString(),
            'slot' => $event->slot->value,
            'starts_at' => $event->starts_at->toJSON(),
            'ends_at' => $event->ends_at->toJSON(),
            'local_time' => $event->starts_at->copy()->setTimezone($tz)->format('H:i') . '–' . $event->ends_at->copy()->setTimezone($tz)->format('H:i'),
            'status' => $event->status->value,
            'halls' => $event->halls()->orderBy('halls.id')->pluck('halls.name')->map(fn ($name) => (string) $name)->all(),
        ];
    }
}
