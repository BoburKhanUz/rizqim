<?php

namespace App\Domain\Pos\Orders;

use App\Domain\Fiscal\FiscalException;
use App\Domain\Fiscal\FiscalReadiness;
use App\Enums\FireMode;
use App\Enums\KitchenItemStatus;
use App\Enums\StopListSubject;
use App\Models\Branch;
use App\Models\BranchProductVariant;
use App\Models\MenuBatch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use App\Services\Catalog\BatchService;
use App\Services\Catalog\ChannelMenu;
use App\Services\Catalog\MenuAvailability;
use App\Services\Order\OrderLineBuilder;
use App\Support\Quantity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Prices and stores a round of POS lines (P3-12), with the same menu rules as a Mini App checkout (docs/MENU.md,
 * OrderLineBuilder): the variant's unit (whole pieces; kg/l at least min_quantity in quantity_step steps), modifiers
 * checked against the product's groups, the price of the order's channel (pos_hall / pos_takeaway, channel_prices),
 * the branch's stop-list and availability overrides, the variant stock and the kazan (menu_batches: the open batch of
 * the variant or product, batch_consumption per unit, "tugadi" when it runs out), and fiscal readiness (P3-11's
 * FiscalReadiness: no MXIK code, no sale in fiscal mode). Lines are never merged: each keeps its own note, seat and
 * firing.
 *
 * Runs inside the caller's transaction, after the order's row lock: variants are locked in id order, then batches in id
 * order, as checkout locks them, so a POS round and a Telegram checkout never deadlock.
 */
class PosLineBuilder
{
    public function __construct(
        protected OrderLineBuilder $lines,
        protected BatchService $batches,
        protected FiscalReadiness $fiscal,
        protected PosCourses $courses,
        protected PosWeights $weights,
    ) {}

    /**
     * Reads request items ({variant_id: uuid, quantity, modifiers?, note?, seat_no?, fire_mode?, due_at?, course_no?,
     * awaiting_weight?}) into lines. P6-21: `course_no` 1–9 (PosCourses holds a later course); P6-25: `awaiting_weight`
     * true|false (null: the product's weight_mode decides, PosWeights).
     *
     * @param  array<int, mixed>  $items
     * @return list<array{index: int, variant_uuid: string, quantity: Quantity, modifiers: array<string, int>, note: ?string, seat_no: ?int, fire_mode: FireMode, due_at: ?Carbon, course_no: ?int, awaiting_weight: ?bool}>
     */
    public function parse(array $items, FireMode $defaultFire): array
    {
        $parsed = $this->lines->parse($items);
        $items = array_values($items);

        $lines = [];
        foreach ($parsed as $line) {
            $item = is_array($items[$line['index']] ?? null) ? $items[$line['index']] : [];
            $note = isset($item['note']) && is_string($item['note']) && trim($item['note']) !== '' ? mb_substr(trim($item['note']), 0, 255) : null;
            $fire = isset($item['fire_mode']) ? FireMode::tryFrom((string) $item['fire_mode']) : null;
            try {
                $due = isset($item['due_at']) && is_string($item['due_at']) && $item['due_at'] !== '' ? Carbon::parse($item['due_at']) : null;
            } catch (\Throwable) {
                // Review P3-35: a due time that is no date is the request's mistake, never a server error
                throw ValidationException::withMessages(["items.{$line['index']}.due_at" => "Tayyor bo'lish vaqti noto'g'ri."]);
            }

            $course = isset($item['course_no']) && is_numeric($item['course_no']) ? (int) $item['course_no'] : null;
            if ($course !== null && ($course < 1 || $course > PosCourses::MAX_COURSE)) {
                throw ValidationException::withMessages(["items.{$line['index']}.course_no" => 'Kurs raqami 1 dan ' . PosCourses::MAX_COURSE . ' gacha.']);
            }
            $awaiting = $item['awaiting_weight'] ?? null;

            $lines[] = $line + [
                'note' => $note,
                'seat_no' => isset($item['seat_no']) ? (int) $item['seat_no'] : null,
                'fire_mode' => $fire ?? $defaultFire,
                'due_at' => $due,
                'course_no' => $course,
                'awaiting_weight' => $awaiting === null ? null : filter_var($awaiting, FILTER_VALIDATE_BOOL),
            ];
        }

        return $lines;
    }

    /**
     * Prices, checks and stores the lines on the order as round $roundNo, added by $actor; takes their stock and kazan
     * portions. Strict: any line that cannot be sold is a 422 for the whole round. Lenient (the automatic per-guest
     * items): such lines are left out and the rest is added.
     *
     * @param  list<array{index: int, variant_uuid: string, quantity: Quantity, modifiers: array<string, int>, note: ?string, seat_no: ?int, fire_mode: FireMode, due_at: ?Carbon, course_no?: ?int, awaiting_weight?: ?bool}>  $lines
     * @return Collection<int, OrderItem>
     *
     * @throws ValidationException
     */
    public function add(Order $order, Branch $branch, array $lines, int $roundNo, User $actor, bool $lenient = false): Collection
    {
        if ($lines === []) {
            return collect();
        }
        // P6-21: a later course is held until it is sent by its number
        $lines = $this->courses->prepare($order, $branch, $lines, !$lenient);

        /** @var Store $store */
        $store = $order->store;
        $channelMenu = ChannelMenu::for((int) $order->store_id, $order->channel);

        $variants = ProductVariant::with('product')
            ->whereIn('uuid', array_values(array_unique(array_map(fn (array $line) => $line['variant_uuid'], $lines))))
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('store_id', $order->store_id)->where('status', 'active'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (ProductVariant $variant) => strtolower((string) $variant->uuid));

        $stopList = $this->lines->stopList($branch);
        $hiddenOnBranch = BranchProductVariant::where('branch_id', $branch->id)
            ->whereIn('variant_id', $variants->pluck('id')->all())
            ->where('is_active', false)
            ->get(['variant_id', 'hidden_reason'])
            ->keyBy('variant_id');
        $modifierRules = $this->lines->modifierRules($variants->map(fn (ProductVariant $variant) => (int) $variant->product_id)->unique()->values()->all());

        // Per line: the variant exists and may be sold here, its quantity and modifiers are right
        $priced = [];
        foreach ($lines as $line) {
            try {
                $variant = $variants->get($line['variant_uuid']);
                if ($variant === null) {
                    throw ValidationException::withMessages(["items.{$line['index']}.variant_id" => "Taom topilmadi yoki hozir sotilmaydi. Menyuni yangilang."]);
                }

                $this->assertSellable($variant, $channelMenu, $stopList, $hiddenOnBranch->get($variant->id));
                // P3-11: "MXIK'siz sotilmaydi" in fiscal mode (FiscalException, 422 `mxik_missing`)
                $this->fiscal->assertSellable($variant->product, $store);
                $this->lines->checkQuantity($variant, $line['quantity'], $line['index']);
                $modifiers = $this->lines->resolveModifiers(
                    $variant,
                    $line['modifiers'],
                    $modifierRules[(int) $variant->product_id] ?? [],
                    $stopList[StopListSubject::MODIFIER->value],
                    $line['index'],
                );

                $price = $channelMenu->priceOf($variant);
                $priced[] = $line + [
                    'variant' => $variant,
                    'price' => $price,
                    // P6-25: a dish weighed after it is chosen
                    'weight_basis' => $this->weights->basisFor($line, $variant, $branch, !$lenient),
                    'modifiers_snapshot' => $modifiers['snapshot'],
                    'modifiers_total' => $modifiers['total'],
                    'total' => $this->lines->lineTotal($price, $line['quantity'], $modifiers['total'], $variant->unit),
                ];
            } catch (ValidationException|FiscalException $e) {
                if (!$lenient) {
                    throw $e;
                }
            }
        }

        // The variant stock, against the round's combined quantity of each variant
        $needed = [];
        foreach ($priced as $line) {
            $id = (int) $line['variant']->id;
            $needed[$id] = ($needed[$id] ?? Quantity::zero())->plus($line['quantity']);
        }
        foreach ($needed as $id => $quantity) {
            $variant = $variants->first(fn (ProductVariant $variant) => (int) $variant->id === $id);
            if ($variant !== null && Quantity::of($variant->stock)->isLessThan($quantity)) {
                if (!$lenient) {
                    throw $this->outOfStock($variant);
                }
                $priced = array_values(array_filter($priced, fn (array $line) => (int) $line['variant']->id !== $id));
            }
        }

        $priced = $this->assignBatches($priced, $branch, $lenient);
        if ($priced['lines'] === []) {
            return collect();
        }

        $created = collect();
        foreach ($priced['lines'] as $line) {
            /** @var ProductVariant $variant */
            $variant = $line['variant'];
            $batch = $line['batch'];

            $created->push($this->createLine($order, [
                'order_id' => $order->id,
                'product_variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->name,
                'quantity' => $line['quantity']->toString(),
                'unit' => $variant->unit->value,
                'unit_price' => $line['price']->toString(),
                'modifiers' => $line['modifiers_snapshot'] ?: null,
                'modifiers_total' => $line['modifiers_total']->toString(),
                'total_price' => $line['total']->toString(),
                'batch_id' => $batch?->id,
                'batch_quantity' => $batch ? $line['quantity']->times(MenuAvailability::consumptionOf($variant))->toString() : null,
                'set_parts' => app(\App\Domain\Pos\Menu\MealSets::class)->partsFor($variant), // P6-15: a kompleks keeps its parts
                'kitchen_status' => KitchenItemStatus::PENDING->value,
                'round_no' => $roundNo,
                'fire_mode' => $line['fire_mode']->value,
                'due_at' => $line['due_at'],
                'note' => $line['note'],
                'seat_no' => $line['seat_no'],
                'added_by' => $actor->id,
            ] + $this->restaurantFields($line)));

            // Conditional, as checkout: never below zero even if the row changed after it was read
            $deducted = ProductVariant::whereKey($variant->id)
                ->where('stock', '>=', $line['quantity']->toString())
                ->decrement('stock', $line['quantity']->toString()); // @phpstan-ignore argument.type
            if ($deducted !== 1) {
                throw $this->outOfStock($variant);
            }
        }

        foreach ($priced['batches'] as $need) {
            if (!$this->batches->consume($need['batch'], $need['quantity'], $need['variants'])) {
                throw $this->soldOut($need['variants']->first());
            }
        }

        return $created;
    }

    /**
     * P3-31: stores a round a till sold offline (docs/POS_SPEC.md §5.3). The sale happened and the guest paid the
     * device's price, so nothing that could have changed meanwhile refuses it: the device's unit price (and modifier
     * prices) are kept (`price_source = offline_snapshot`), the stop-list, a hidden variant and the quantity rules are not
     * checked again, the variant stock and the kazan give what they still have (never below zero) and whatever was
     * missing is recorded (menu_batches.oversold_quantity) and flagged. Only a variant that is not this store's at all
     * is refused (PosSyncException `variant_not_found`). Each line keeps the device's id (`client_line_id`).
     *
     * Returns the new lines and the flags for the manager: `offline_price` (the device's price differs from today's),
     * `sold_while_unavailable` (stop-list, hidden or inactive), `oversold` (kazan), `stock_short`.
     *
     * P6-21 / P6-25: the device's course is kept while the module is on (dropped otherwise); a line waits for its weight
     * when the device says so (or by the product's default) and the module is on.
     *
     * @param  list<array{index: int, variant_uuid: string, quantity: Quantity, modifiers: array<string, int>, note: ?string, seat_no: ?int, fire_mode: FireMode, due_at: ?Carbon, course_no?: ?int, awaiting_weight?: ?bool, client_line_id?: ?string, unit_price?: ?\App\Support\Money, modifier_prices?: array<string, \App\Support\Money>}>  $lines
     * @return array{items: Collection<int, OrderItem>, flags: list<array<string, mixed>>}
     */
    public function addOffline(Order $order, Branch $branch, array $lines, int $roundNo, User $actor, ?Carbon $menuTakenAt = null): array
    {
        if ($lines === []) {
            return ['items' => collect(), 'flags' => []];
        }
        $lines = $this->courses->prepare($order, $branch, $lines, false);

        $channelMenu = ChannelMenu::for((int) $order->store_id, $order->channel);
        $variants = ProductVariant::withTrashed()
            ->with(['product' => fn ($query) => $query->withTrashed()])
            ->whereIn('uuid', array_values(array_unique(array_map(fn (array $line) => $line['variant_uuid'], $lines))))
            ->whereHas('product', fn ($query) => $query->withTrashed()->where('store_id', $order->store_id))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (ProductVariant $variant) => strtolower((string) $variant->uuid));

        $stopList = $this->lines->stopList($branch);
        $hiddenOnBranch = BranchProductVariant::where('branch_id', $branch->id)
            ->whereIn('variant_id', $variants->pluck('id')->all())
            ->where('is_active', false)
            ->pluck('variant_id')
            ->flip();
        $modifierRows = $this->offlineModifiers($order, $lines);

        // Review P3-35: the prices the till may have had, from its menu snapshot (a day back when it did not say) on
        $from = $menuTakenAt !== null && $menuTakenAt->lt(now()) ? $menuTakenAt : now()->subHours(\App\Domain\Pos\Sync\PosSyncApplier::STALE_MENU_HOURS);
        $history = app(\App\Services\Catalog\PriceHistory::class);
        $variantPrices = $history->variantPrices($variants->pluck('id')->map(fn ($id) => (int) $id)->values()->all(), $order->channel, $from);
        $modifierPrices = $history->modifierPrices($modifierRows->pluck('id')->map(fn ($id) => (int) $id)->values()->all(), $from);
        $had = fn (array $prices, \App\Support\Money $price) => array_filter($prices, fn (\App\Support\Money $known) => $known->equals($price)) !== [];

        $flags = [];
        $priced = [];
        foreach ($lines as $line) {
            $variant = $variants->get($line['variant_uuid']);
            if ($variant === null || $variant->product === null) {
                throw new \App\Domain\Pos\Sync\PosSyncException('variant_not_found', "Taom topilmadi (menyudan butunlay o'chirilgan yoki boshqa do'konniki).", ['variant_id' => $line['variant_uuid']]);
            }

            $name = $this->lines->nameOf($variant);
            $unavailable = !$variant->is_active || $variant->trashed() || $variant->product->trashed() || $variant->product->status !== 'active'
                || $hiddenOnBranch->has($variant->id) || $channelMenu->isVariantHidden($variant)
                || array_key_exists((int) $variant->product_id, $stopList[StopListSubject::PRODUCT->value])
                || array_key_exists((int) $variant->id, $stopList[StopListSubject::VARIANT->value]);
            if ($unavailable) {
                $flags[] = ['flag' => 'sold_while_unavailable', 'line_id' => $line['client_line_id'] ?? null, 'name' => $name];
            }

            $current = $channelMenu->priceOf($variant);
            $device = $line['unit_price'] ?? null;
            $price = $device ?? $current;
            $source = 'offline_snapshot';
            if (!$price->equals($current)) {
                if ($had($variantPrices[(int) $variant->id] ?? [], $price)) {
                    $flags[] = ['flag' => 'offline_price', 'line_id' => $line['client_line_id'] ?? null, 'name' => $name, 'offline_price' => $price->toString(), 'current_price' => $current->toString()];
                } else {
                    // Review P3-35: a price the dish never had while the till's menu was taken: the server's applies
                    $flags[] = ['flag' => 'offline_price_mismatch', 'line_id' => $line['client_line_id'] ?? null, 'name' => $name, 'device_price' => $price->toString(), 'server_price' => $current->toString()];
                    $price = $current;
                    $source = 'online';
                }
            }

            $snapshot = [];
            $modifiersTotal = \App\Support\Money::zero();
            foreach ($line['modifiers'] as $uuid => $count) {
                /** @var \App\Models\Modifier $row */
                $row = $modifierRows->get(strtolower((string) $uuid));
                $serverDelta = \App\Support\Money::of($row->price_delta);
                $delta = ($line['modifier_prices'] ?? [])[strtolower((string) $uuid)] ?? $serverDelta;
                if (!$delta->equals($serverDelta) && !$had($modifierPrices[(int) $row->id] ?? [], $delta)) {
                    $flags[] = ['flag' => 'offline_price_mismatch', 'line_id' => $line['client_line_id'] ?? null, 'name' => "{$name}: {$row->name}", 'device_price' => $delta->toString(), 'server_price' => $serverDelta->toString()];
                    $delta = $serverDelta;
                    $source = 'online';
                }
                $total = $delta->times($count);
                $modifiersTotal = $modifiersTotal->plus($total);
                $snapshot[] = [
                    'id' => (int) $row->id,
                    'uuid' => $uuid,
                    'group_id' => (int) $row->modifier_group_id,
                    'group_name' => $row->group?->name,
                    'name' => $row->name,
                    'price_delta' => $delta->toString(),
                    'quantity' => $count,
                    'total' => $total->toString(),
                ];
            }

            $priced[] = $line + [
                'variant' => $variant,
                'price' => $price,
                'price_source' => $source,
                'weight_basis' => $this->weights->basisFor($line, $variant, $branch, false),
                'modifiers_snapshot' => $snapshot,
                'modifiers_total' => $modifiersTotal,
                'total' => $this->lines->lineTotal($price, $line['quantity'], $modifiersTotal, $variant->unit),
            ];
        }

        // The kazan: what the open batch still holds; the rest is oversold (never below zero)
        $productIds = array_values(array_unique(array_map(fn (array $line) => (int) $line['variant']->product_id, $priced)));
        $open = MenuBatch::query()->where('branch_id', $branch->id)->whereIn('product_id', $productIds)->open()->orderBy('id')->lockForUpdate()->get();

        $created = collect();
        foreach ($priced as $line) {
            /** @var ProductVariant $variant */
            $variant = $line['variant'];
            $name = $this->lines->nameOf($variant);
            $batch = MenuAvailability::batchFor($variant, $open->where('product_id', $variant->product_id));
            $need = $line['quantity']->times(MenuAvailability::consumptionOf($variant));
            $taken = Quantity::zero();

            if ($batch !== null) {
                $left = Quantity::of(MenuBatch::whereKey($batch->id)->value('quantity_left') ?? 0);
                $taken = $left->isLessThan($need) ? $left : $need;
                if ($taken->isPositive() && !$this->batches->consume($batch, $taken, ProductVariant::where('product_id', $batch->product_id)->get(['id', 'product_id', 'batch_consumption']))) {
                    $taken = Quantity::zero();
                }
                $short = $need->minus($taken);
                if ($short->isPositive()) {
                    MenuBatch::whereKey($batch->id)->increment('oversold_quantity', $short->toString()); // @phpstan-ignore argument.type
                    MenuBatch::whereKey($batch->id)->whereNull('sold_out_at')->update(['sold_out_at' => now()]);
                    $flags[] = ['flag' => 'oversold', 'line_id' => $line['client_line_id'] ?? null, 'name' => $name, 'batch_id' => (int) $batch->id, 'quantity' => $short->toString()];
                }
            } elseif ($variant->product->batch_tracked) {
                // No kazan open any more: the portions came from one that was closed meanwhile
                $last = MenuBatch::where('branch_id', $branch->id)->where('product_id', $variant->product_id)->latest('id')->first();
                if ($last !== null) {
                    MenuBatch::whereKey($last->id)->increment('oversold_quantity', $need->toString()); // @phpstan-ignore argument.type
                }
                $flags[] = ['flag' => 'oversold', 'line_id' => $line['client_line_id'] ?? null, 'name' => $name, 'batch_id' => $last !== null ? (int) $last->id : null, 'quantity' => $need->toString()];
            }

            // The variant stock: what is there, never below zero
            $stock = Quantity::of(ProductVariant::withTrashed()->whereKey($variant->id)->value('stock') ?? 0);
            $deduct = $stock->isLessThan($line['quantity']) ? $stock : $line['quantity'];
            if ($deduct->isPositive()) {
                ProductVariant::withTrashed()->whereKey($variant->id)->where('stock', '>=', $deduct->toString())->decrement('stock', $deduct->toString()); // @phpstan-ignore argument.type
            }
            if ($deduct->isLessThan($line['quantity'])) {
                $flags[] = ['flag' => 'stock_short', 'line_id' => $line['client_line_id'] ?? null, 'name' => $name, 'quantity' => $line['quantity']->minus($deduct)->toString()];
            }

            $created->push($this->createLine($order, [
                'order_id' => $order->id,
                'product_variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_name' => $variant->name,
                'quantity' => $line['quantity']->toString(),
                'unit' => $variant->unit->value,
                'unit_price' => $line['price']->toString(),
                'modifiers' => $line['modifiers_snapshot'] ?: null,
                'modifiers_total' => $line['modifiers_total']->toString(),
                'total_price' => $line['total']->toString(),
                'batch_id' => $batch !== null && $taken->isPositive() ? $batch->id : null,
                'batch_quantity' => $batch !== null && $taken->isPositive() ? $taken->toString() : null,
                'set_parts' => app(\App\Domain\Pos\Menu\MealSets::class)->partsFor($variant), // P6-15: a kompleks keeps its parts
                'kitchen_status' => KitchenItemStatus::PENDING->value,
                'round_no' => $roundNo,
                'fire_mode' => $line['fire_mode']->value,
                'due_at' => $line['due_at'],
                'note' => $line['note'],
                'seat_no' => $line['seat_no'],
                'added_by' => $actor->id,
                'client_line_id' => $line['client_line_id'] ?? null,
                'price_source' => $line['price_source'],
            ] + $this->restaurantFields($line)));
        }

        return ['items' => $created, 'flags' => $flags];
    }

    /**
     * The modifiers an offline round names, by uuid, with their group (deleted ones too: the device sold them); only
     * this store's (review P3-35): one the store never had refuses the operation (PosSyncException
     * `modifier_not_found`), like an unknown variant.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return Collection<string, \App\Models\Modifier>
     */
    private function offlineModifiers(Order $order, array $lines): Collection
    {
        $uuids = [];
        foreach ($lines as $line) {
            foreach (array_keys($line['modifiers']) as $uuid) {
                $uuids[] = strtolower((string) $uuid);
            }
        }
        if ($uuids === []) {
            return collect();
        }

        $rows = \App\Models\Modifier::withTrashed()
            ->with(['group' => fn ($query) => $query->withTrashed()])
            ->whereIn('uuid', array_values(array_unique($uuids)))
            ->whereHas('group', fn ($query) => $query->withTrashed()->where('store_id', $order->store_id))
            ->get()
            ->keyBy(fn ($modifier) => strtolower((string) $modifier->uuid));
        foreach (array_unique($uuids) as $uuid) {
            if (!$rows->has($uuid)) {
                throw new \App\Domain\Pos\Sync\PosSyncException('modifier_not_found', "Qo'shimcha topilmadi (menyudan butunlay o'chirilgan yoki boshqa do'konniki).", ['modifier_id' => $uuid]);
            }
        }

        return $rows;
    }

    /**
     * P6-21 / P6-25: a line's course and, for a dish weighed later, `weight_status = awaiting` with its basis.
     *
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function restaurantFields(array $line): array
    {
        $basis = $line['weight_basis'] ?? null;

        return array_filter([
            'course_no' => $line['course_no'] ?? null,
            'weight_status' => $basis instanceof WeightBasis ? WeightStatus::AWAITING->value : null,
            'weight_basis' => $basis instanceof WeightBasis ? $basis->value : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * P6-25: the real weight of a line (PosOrderService::setWeight, under the order's lock): the quantity, the line's
     * total at its own unit price and modifiers, and the variant stock moved by the difference (taken only from what is
     * there: the fish was weighed, the sale is never refused for the stock; given back when it weighed less).
     */
    public function reweigh(OrderItem $item, Quantity $weight): void
    {
        $before = Quantity::of($item->getRawOriginal('quantity') ?? 0);
        $unit = $item->unit ?? \App\Enums\MenuUnit::KG;
        $total = $this->lines->lineTotal(\App\Support\Money::of($item->unit_price), $weight, \App\Support\Money::of($item->modifiers_total), $unit);

        if ($item->product_variant_id !== null) {
            $delta = $weight->minus($before);
            if ($delta->isPositive()) {
                $stock = Quantity::of(ProductVariant::withTrashed()->whereKey($item->product_variant_id)->value('stock') ?? 0);
                $take = $stock->isLessThan($delta) ? $stock : $delta;
                if ($take->isPositive()) {
                    ProductVariant::withTrashed()->whereKey($item->product_variant_id)->where('stock', '>=', $take->toString())->decrement('stock', $take->toString()); // @phpstan-ignore argument.type
                }
            } elseif ($delta->isLessThan(0)) {
                ProductVariant::withTrashed()->whereKey($item->product_variant_id)->increment('stock', $before->minus($weight)->toString()); // @phpstan-ignore argument.type
            }
        }

        $item->forceFill([
            'quantity' => $weight->toString(),
            'total_price' => $total->toString(),
        ]);
    }

    /**
     * Stores a line with its kitchen station (KitchenStationResolver, P3-13) from the start (review P3-35): a held
     * pre-order shows on its station's display before it is sent; sending resolves it again (the routes may have
     * changed meanwhile).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createLine(Order $order, array $attributes): OrderItem
    {
        $item = new OrderItem($attributes);
        $item->setRelation('order', $order);
        if (app()->bound(\App\Domain\Pos\Contracts\KitchenStationResolver::class)) {
            $item->station_id = app(\App\Domain\Pos\Contracts\KitchenStationResolver::class)->stationIdFor($item);
        }
        $item->save();

        return $item;
    }

    /**
     * Gives a voided line's stock and kazan portions back (the batch only while it is still open).
     */
    public function release(OrderItem $item): void
    {
        if ($item->product_variant_id !== null) {
            ProductVariant::withTrashed()->whereKey($item->product_variant_id)
                ->increment('stock', Quantity::of($item->getRawOriginal('quantity') ?? 0)->toString()); // @phpstan-ignore argument.type
        }

        if ($item->batch_id !== null) {
            $this->batches->restore([(int) $item->batch_id => Quantity::of($item->batch_quantity ?? $item->getRawOriginal('quantity') ?? 0)]);
        }
    }

    /**
     * The open batch of each line (the variant's, else the product's), locked in id order; a batch-tracked product
     * without one, or a batch with less left than the round needs, is "tugadi" (strict: 422; lenient: those lines are
     * left out).
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{lines: list<array<string, mixed>>, batches: list<array{batch: MenuBatch, quantity: Quantity, variants: Collection<int, ProductVariant>}>}
     */
    private function assignBatches(array $lines, Branch $branch, bool $lenient): array
    {
        $productIds = array_values(array_unique(array_map(fn (array $line) => (int) $line['variant']->product_id, $lines)));
        $open = $productIds === [] ? collect() : MenuBatch::query()
            ->where('branch_id', $branch->id)
            ->whereIn('product_id', $productIds)
            ->open()
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $kept = [];
        $needs = [];
        foreach ($lines as $line) {
            /** @var ProductVariant $variant */
            $variant = $line['variant'];
            $batch = MenuAvailability::batchFor($variant, $open->where('product_id', $variant->product_id));

            if ($batch === null && $variant->product->batch_tracked) {
                if (!$lenient) {
                    throw $this->soldOut($variant);
                }
                continue;
            }

            $line['batch'] = $batch;
            $kept[] = $line;
            if ($batch !== null) {
                $needs[$batch->id] ??= ['batch' => $batch, 'quantity' => Quantity::zero(), 'variants' => collect()];
                $needs[$batch->id]['quantity'] = $needs[$batch->id]['quantity']->plus($line['quantity']->times(MenuAvailability::consumptionOf($variant)));
                $needs[$batch->id]['variants']->push($variant);
            }
        }

        ksort($needs);
        foreach ($needs as $batchId => $need) {
            if ($need['batch']->left()->isLessThan($need['quantity'])) {
                if (!$lenient) {
                    throw $this->soldOut($need['variants']->first(), $need['batch']);
                }
                $kept = array_values(array_filter($kept, fn (array $line) => $line['batch'] === null || (int) $line['batch']->id !== (int) $batchId));
                unset($needs[$batchId]);
                continue;
            }

            // Every variant the batch serves, for the "now sold out" announcement
            $needs[$batchId]['variants'] = $need['batch']->product_variant_id !== null
                ? $need['variants']->unique('id')->values()
                : ProductVariant::where('product_id', $need['batch']->product_id)->get(['id', 'product_id', 'batch_consumption']);
        }

        return ['lines' => $kept, 'batches' => array_values($needs)];
    }

    /**
     * @param  array<string, array<int, ?string>>  $stopList
     */
    private function assertSellable(ProductVariant $variant, ChannelMenu $channelMenu, array $stopList, ?BranchProductVariant $hidden): void
    {
        $name = $this->lines->nameOf($variant);

        if ($hidden !== null) {
            $reason = $hidden->hidden_reason ? " ({$hidden->hidden_reason})" : '';

            throw ValidationException::withMessages(['items' => "'{$name}' ushbu filialda vaqtincha mavjud emas{$reason}."]);
        }

        if ($channelMenu->isVariantHidden($variant)) {
            throw ValidationException::withMessages(['items' => "'{$name}' bu kanalda (zal yoki olib ketish) sotilmaydi."]);
        }

        $products = $stopList[StopListSubject::PRODUCT->value];
        $variants = $stopList[StopListSubject::VARIANT->value];
        if (array_key_exists((int) $variant->product_id, $products) || array_key_exists((int) $variant->id, $variants)) {
            $reason = $variants[(int) $variant->id] ?? $products[(int) $variant->product_id] ?? null;

            throw ValidationException::withMessages(['items' => "'{$name}' stop-listda: hozir mavjud emas" . ($reason ? " ({$reason})" : '') . '.']);
        }
    }

    private function outOfStock(ProductVariant $variant): ValidationException
    {
        $unit = $variant->unit->isMeasured() ? $variant->unit->label() : 'ta';
        $left = Quantity::of($variant->stock)->format();

        return ValidationException::withMessages([
            'items' => "'{$this->lines->nameOf($variant)}' yetarli emas (qoldiq: {$left} {$unit}).",
        ]);
    }

    private function soldOut(ProductVariant $variant, ?MenuBatch $batch = null): ValidationException
    {
        $name = $this->lines->nameOf($variant);
        $left = $batch?->left();

        return ValidationException::withMessages([
            'items' => $left !== null && $left->isPositive()
                ? "'{$name}' qozonda yetarli emas (qoldi: {$left->format()})."
                : "'{$name}' tugadi.",
        ]);
    }
}
