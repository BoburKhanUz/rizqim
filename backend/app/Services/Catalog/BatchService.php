<?php

namespace App\Services\Catalog;

use App\Domain\Pos\Menu\BatchPlans;
use App\Domain\Pos\Menu\WriteOffReason;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Events\Broadcast\MenuAvailabilityChangedBroadcast;
use App\Models\Branch;
use App\Models\MenuBatch;
use App\Models\MenuBatchWriteOff;
use App\Models\PosShift;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Quantity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Batch / kazan stock (P1-21): staff open a batch (how much was cooked), correct or top it up, and close it. Orders
 * take from it in CreateOrderAction and cancellations give back (OrderStatusService) under row locks, so concurrent
 * checkouts never sell more than is left. Every staff action is audited; availability changes are broadcast.
 */
class BatchService
{
    public function __construct(
        protected AuditLogger $audit,
    ) {}

    public function open(Branch $branch, Product $product, ?ProductVariant $variant, Quantity $produced, ?string $label, ?User $actor): MenuBatch
    {
        if ((int) $product->store_id !== (int) $branch->store_id || ($variant && (int) $variant->product_id !== (int) $product->id)) {
            throw ValidationException::withMessages(['product_id' => "Mahsulot bu filial do'koniga tegishli emas."]);
        }
        if (!$produced->isPositive()) {
            throw ValidationException::withMessages(['quantity' => "Miqdor noldan katta bo'lishi kerak."]);
        }

        try {
            return DB::transaction(function () use ($branch, $product, $variant, $produced, $label, $actor) {
                $exists = MenuBatch::query()
                    ->where('branch_id', $branch->id)
                    ->where('product_id', $product->id)
                    ->when($variant, fn ($query) => $query->where('product_variant_id', $variant->id), fn ($query) => $query->whereNull('product_variant_id'))
                    ->open()
                    ->lockForUpdate()
                    ->exists();
                if ($exists) {
                    throw $this->alreadyOpen();
                }

                $batch = MenuBatch::create([
                    'store_id' => $branch->store_id,
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'label' => $label !== null && trim($label) !== '' ? mb_substr(trim($label), 0, 100) : null,
                    'quantity_produced' => $produced->toString(),
                    'quantity_left' => $produced->toString(),
                    'started_at' => now(),
                    'opened_by' => $actor?->id,
                ]);

                $this->audit->record('menu.batch_opened', $batch, [], [], [
                    'product' => $product->name,
                    'variant' => $variant?->name,
                    'quantity' => $produced->toNumber(),
                    'label' => $batch->label,
                ], ['actor' => $actor, 'store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id]);

                // P6-15: the plan "keyingi partiya HH:MM" this batch keeps is done
                app(BatchPlans::class)->started($batch);

                $this->announce($batch, true);

                return $batch;
            });
        } catch (QueryException $e) {
            // Two staff opening the same batch at once: the partial unique index lets one through
            if (str_contains($e->getMessage(), 'menu_batches_one_open')) {
                throw $this->alreadyOpen();
            }

            throw $e;
        }
    }

    /**
     * Top up (`add`, e.g. a second kazan: produced and left both grow) or correct what is left (`left`).
     */
    public function adjust(MenuBatch $batch, ?Quantity $add, ?Quantity $left, ?string $reason, ?User $actor): MenuBatch
    {
        return DB::transaction(function () use ($batch, $add, $left, $reason, $actor) {
            /** @var MenuBatch $batch */
            $batch = MenuBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->closed_at !== null) {
                throw ValidationException::withMessages(['batch' => 'Yopilgan partiyani o\'zgartirib bo\'lmaydi.']);
            }

            $before = ['quantity_produced' => Quantity::normalize($batch->quantity_produced), 'quantity_left' => Quantity::normalize($batch->quantity_left)];
            $wasLeft = $batch->left();
            $produced = Quantity::of($batch->quantity_produced);
            $newLeft = $wasLeft;

            if ($add !== null) {
                if (!$add->isPositive()) {
                    throw ValidationException::withMessages(['add' => "Qo'shiladigan miqdor noldan katta bo'lishi kerak."]);
                }
                $produced = $produced->plus($add);
                $newLeft = $newLeft->plus($add);
            }
            if ($left !== null) {
                if ($left->compareTo(0) < 0) {
                    throw ValidationException::withMessages(['quantity_left' => "Qoldiq manfiy bo'lishi mumkin emas."]);
                }
                $newLeft = $left;
                if ($newLeft->isGreaterThan($produced)) {
                    $produced = $newLeft;
                }
            }

            $batch->forceFill([
                'quantity_produced' => $produced->toString(),
                'quantity_left' => $newLeft->toString(),
                'sold_out_at' => $newLeft->isZero() ? ($batch->sold_out_at ?? now()) : null,
            ])->save();

            $this->audit->record('menu.batch_adjusted', $batch, $before, [
                'quantity_produced' => $produced->toNumber(),
                'quantity_left' => $newLeft->toNumber(),
            ], array_filter(['reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null]), ['actor' => $actor]);

            if ($wasLeft->compareTo($newLeft) !== 0) {
                $this->announce($batch, $newLeft->isPositive());
            }

            return $batch;
        });
    }

    public function close(MenuBatch $batch, ?User $actor): MenuBatch
    {
        return DB::transaction(function () use ($batch, $actor) {
            /** @var MenuBatch $batch */
            $batch = MenuBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->closed_at !== null) {
                return $batch;
            }

            $batch->forceFill(['closed_at' => now(), 'closed_by' => $actor?->id])->save();

            $this->audit->record('menu.batch_closed', $batch, [], [], [
                'quantity_produced' => Quantity::normalize($batch->quantity_produced),
                'quantity_left' => Quantity::normalize($batch->quantity_left),
            ], ['actor' => $actor]);

            // Closing a tracked product's batch makes it "tugadi"; an untracked one is back to plain stock
            $tracked = (bool) Product::whereKey($batch->product_id)->value('batch_tracked');
            $this->announce($batch, !$tracked);

            return $batch;
        });
    }

    /**
     * P6-15: takes unsold portions off an open batch with a reason ("hisobdan chiqarish"): quantity_left goes down (never
     * below zero, under the batch's row lock, so a sale and a write-off cannot both take the last portion), the amount
     * is kept as its own record (and as the batch's running `written_off_quantity`) for the Z report and the yield report.
     * `$close` also closes the batch (the end-of-day "write off the rest and close"). The unit is the batch's own.
     */
    public function writeOff(MenuBatch $batch, Quantity $quantity, WriteOffReason $reason, ?string $note, ?PosShift $shift, ?User $actor, bool $close = false): MenuBatchWriteOff
    {
        if (!$quantity->isPositive()) {
            throw ValidationException::withMessages(['quantity' => "Miqdor noldan katta bo'lishi kerak."]);
        }
        $note = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null;
        if ($reason === WriteOffReason::OTHER && $note === null) {
            throw ValidationException::withMessages(['note' => '"Boshqa sabab" tanlansa, izoh yozing.']);
        }

        return DB::transaction(function () use ($batch, $quantity, $reason, $note, $shift, $actor, $close) {
            /** @var MenuBatch $batch */
            $batch = MenuBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->closed_at !== null) {
                throw ValidationException::withMessages(['batch' => "Yopilgan partiyadan hisobdan chiqarib bo'lmaydi."]);
            }

            $wasLeft = $batch->left();
            if ($quantity->isGreaterThan($wasLeft)) {
                throw ValidationException::withMessages(['quantity' => "Partiyada faqat {$wasLeft->format()} qolgan: undan ko'p chiqarib bo'lmaydi."]);
            }
            $left = $wasLeft->minus($quantity);

            $branch = Branch::query()->with('store')->findOrFail($batch->branch_id);
            $variant = $batch->product_variant_id !== null
                ? ProductVariant::withTrashed()->find($batch->product_variant_id)
                : ProductVariant::query()->where('product_id', $batch->product_id)->orderBy('id')->first();

            $batch->forceFill([
                'quantity_left' => $left->toString(),
                'written_off_quantity' => Quantity::of($batch->written_off_quantity ?? 0)->plus($quantity)->toString(),
                'sold_out_at' => $left->isZero() ? ($batch->sold_out_at ?? now()) : null,
            ])->save();

            $writeOff = MenuBatchWriteOff::create([
                'store_id' => $batch->store_id,
                'branch_id' => $batch->branch_id,
                'menu_batch_id' => $batch->id,
                'product_id' => $batch->product_id,
                'product_variant_id' => $batch->product_variant_id,
                'quantity' => $quantity->toString(),
                'unit' => $variant?->unit->value ?? 'piece',
                'reason' => $reason->value,
                'note' => $note,
                'business_date' => BusinessDay::of($branch),
                'shift_id' => $shift?->id,
                'written_off_by' => $actor?->id,
            ]);

            $this->audit->record('menu.batch_written_off', $batch, [
                'quantity_left' => $wasLeft->toNumber(),
            ], [
                'quantity_left' => $left->toNumber(),
            ], array_filter([
                'quantity' => $quantity->toNumber(),
                'reason' => $reason->value,
                'note' => $note,
                'shift_id' => $shift?->id,
                'closed' => $close ?: null,
            ]), ['actor' => $actor, 'store_id' => (int) $batch->store_id, 'branch_id' => (int) $batch->branch_id]);

            if ($close) {
                $this->close($batch, $actor);
            } else {
                $this->announce($batch, $left->isPositive());
            }

            return $writeOff;
        });
    }

    /**
     * Takes $quantity from an open batch locked by the caller; false when not that much is left (a concurrent order
     * got there first). Marks the batch sold out and announces it when its variants can no longer be sold.
     *
     * @param  Collection<int, ProductVariant>  $variants  the variants the batch serves (for the announcement)
     */
    public function consume(MenuBatch $batch, Quantity $quantity, Collection $variants): bool
    {
        $before = Quantity::of(MenuBatch::whereKey($batch->id)->value('quantity_left') ?? 0);

        // Conditional, like the variant stock: never below zero even if the row changed after it was read
        $taken = MenuBatch::whereKey($batch->id)
            ->whereNull('closed_at')
            ->where('quantity_left', '>=', $quantity->toString())
            ->decrement('quantity_left', $quantity->toString()); // @phpstan-ignore argument.type
        if ($taken !== 1) {
            return false;
        }

        $after = $before->minus($quantity);
        if ($after->isZero()) {
            MenuBatch::whereKey($batch->id)->whereNull('sold_out_at')->update(['sold_out_at' => now()]);
        }

        $ended = $variants->filter(function (ProductVariant $variant) use ($before, $after) {
            $consumption = MenuAvailability::consumptionOf($variant);

            return !$before->isLessThan($consumption) && $after->isLessThan($consumption);
        });
        if ($ended->isNotEmpty()) {
            event(new MenuAvailabilityChangedBroadcast((int) $batch->store_id, (int) $batch->branch_id, $ended->map(fn (ProductVariant $variant) => [
                'type' => 'variant',
                'id' => (int) $variant->id,
                'available' => false,
            ])->values()->all(), 'batch'));
        }

        return true;
    }

    /**
     * Gives a cancelled order's portions back to the batches they came from, while those are still open (a closed
     * batch's count is final). Batch id order, after the variants, like checkout locks them.
     *
     * @param  array<int, Quantity>  $quantities  batch id => quantity
     */
    public function restore(array $quantities): void
    {
        ksort($quantities);

        foreach ($quantities as $batchId => $quantity) {
            if (!$quantity->isPositive()) {
                continue;
            }

            $restored = MenuBatch::whereKey($batchId)
                ->whereNull('closed_at')
                ->increment('quantity_left', $quantity->toString(), ['sold_out_at' => null]); // @phpstan-ignore argument.type

            if ($restored === 1 && ($batch = MenuBatch::find($batchId))) {
                $this->announce($batch, true);
            }
        }
    }

    /**
     * menu.availability_changed for the variants the batch serves.
     */
    private function announce(MenuBatch $batch, bool $available): void
    {
        $ids = $batch->product_variant_id !== null
            ? [(int) $batch->product_variant_id]
            : ProductVariant::where('product_id', $batch->product_id)->pluck('id')->map(fn ($id) => (int) $id)->all();

        event(new MenuAvailabilityChangedBroadcast((int) $batch->store_id, (int) $batch->branch_id, array_map(fn (int $id) => [
            'type' => 'variant',
            'id' => $id,
            'available' => $available,
        ], $ids), 'batch'));
    }

    private function alreadyOpen(): ValidationException
    {
        return ValidationException::withMessages(['product_id' => "Bu taom uchun filialda ochiq partiya bor. Avval uni yoping yoki miqdorini o'zgartiring."]);
    }
}
