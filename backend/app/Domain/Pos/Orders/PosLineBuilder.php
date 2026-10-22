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
    ) {}

    /**
     * Reads request items ({variant_id: uuid, quantity, modifiers?, note?, seat_no?, fire_mode?, due_at?}) into lines.
     *
     * @param  array<int, mixed>  $items
     * @return list<array{index: int, variant_uuid: string, quantity: Quantity, modifiers: array<string, int>, note: ?string, seat_no: ?int, fire_mode: FireMode, due_at: ?Carbon}>
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
            $due = isset($item['due_at']) && is_string($item['due_at']) && $item['due_at'] !== '' ? Carbon::parse($item['due_at']) : null;

            $lines[] = $line + [
                'note' => $note,
                'seat_no' => isset($item['seat_no']) ? (int) $item['seat_no'] : null,
                'fire_mode' => $fire ?? $defaultFire,
                'due_at' => $due,
            ];
        }

        return $lines;
    }

    /**
     * Prices, checks and stores the lines on the order as round $roundNo, added by $actor; takes their stock and kazan
     * portions. Strict: any line that cannot be sold is a 422 for the whole round. Lenient (the automatic per-guest
     * items): such lines are left out and the rest is added.
     *
     * @param  list<array{index: int, variant_uuid: string, quantity: Quantity, modifiers: array<string, int>, note: ?string, seat_no: ?int, fire_mode: FireMode, due_at: ?Carbon}>  $lines
     * @return Collection<int, OrderItem>
     *
     * @throws ValidationException
     */
    public function add(Order $order, Branch $branch, array $lines, int $roundNo, User $actor, bool $lenient = false): Collection
    {
        if ($lines === []) {
            return collect();
        }

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

            $created->push(OrderItem::create([
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
                'kitchen_status' => KitchenItemStatus::PENDING->value,
                'round_no' => $roundNo,
                'fire_mode' => $line['fire_mode']->value,
                'due_at' => $line['due_at'],
                'note' => $line['note'],
                'seat_no' => $line['seat_no'],
                'added_by' => $actor->id,
            ]));

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
