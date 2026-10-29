<?php

namespace App\Services\Order;

use App\Domain\Pos\Menu\DailyMenuService;
use App\Domain\Pos\Menu\MealSets;
use App\Enums\MenuUnit;
use App\Enums\StopListSubject;
use App\Models\Branch;
use App\Models\BranchProductVariant;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\ProductVariant;
use App\Models\StopListEntry;
use App\Services\Catalog\MenuAvailability;
use App\Support\Money;
use App\Support\Quantity;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Turns checkout lines into priced order lines (P1-21), on the server only: quantities by the variant's unit (whole
 * pieces; kg/l at least min_quantity, in quantity_step steps), modifiers checked against the groups the product
 * offers (belonging, active, not stop-listed, per-modifier max, group min/max/required), and the price:
 *
 *   line total = round_half_up_to_whole_som(unit price × quantity + modifiers × (pieces ? quantity : 1))
 *
 * where the unit price is the variant's price on the order's channel and "modifiers" is the sum of the chosen
 * modifiers' price deltas × their quantities (per piece; once per line for an item sold by weight). docs/MENU.md
 */
class OrderLineBuilder
{
    /**
     * Reads the request's items into lines, one per item as sent (merge() then joins the identical piece lines once
     * the variants' units are known).
     *
     * @param  array<int, mixed>  $items  [{variant_id, quantity, modifiers?: [uuid | {id, quantity?}]}]
     * @return list<array{index: int, variant_uuid: string, quantity: Quantity, modifiers: array<string, int>}>
     */
    public function parse(array $items): array
    {
        $lines = [];

        foreach (array_values($items) as $index => $item) {
            $item = is_array($item) ? $item : [];
            $quantity = Quantity::tryOf($item['quantity'] ?? null);
            if ($quantity === null || !$quantity->isPositive()) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => "Miqdor noto'g'ri: musbat son, ko'pi bilan 3 ta kasr xona (masalan 1 yoki 0.5).",
                ]);
            }

            $modifiers = [];
            foreach ((array) ($item['modifiers'] ?? []) as $choice) {
                $uuid = is_array($choice) ? ($choice['id'] ?? null) : $choice;
                $count = is_array($choice) ? ($choice['quantity'] ?? 1) : 1;
                if (!is_string($uuid) || !preg_match('/^[a-f\d]{8}-(?:[a-f\d]{4}-){3}[a-f\d]{12}$/i', $uuid) || filter_var($count, FILTER_VALIDATE_INT) === false || (int) $count < 1) {
                    throw ValidationException::withMessages([
                        "items.{$index}.modifiers" => "Qo'shimchalar noto'g'ri yuborilgan.",
                    ]);
                }
                $uuid = strtolower($uuid);
                $modifiers[$uuid] = ($modifiers[$uuid] ?? 0) + (int) $count;
            }
            ksort($modifiers);

            $lines[] = ['index' => $index, 'variant_uuid' => strtolower((string) ($item['variant_id'] ?? '')), 'quantity' => $quantity, 'modifiers' => $modifiers];
        }

        return $lines;
    }

    /**
     * Lines of the same variant with the same modifiers are merged (their quantities added) when the variant is sold
     * by the piece: the merged line costs exactly what the separate ones did. A weighed (kg/l) line is never merged:
     * it is its own portion (its own minimum, its modifiers once per line, its own rounding), so the order costs the
     * sum of the lines the customer saw. Variants not found are left to the caller's checks.
     *
     * @param  list<array{index: int, variant_uuid: string, quantity: Quantity, modifiers: array<string, int>}>  $lines
     * @param  Collection<string, ProductVariant>  $variants  keyed by lower-case uuid
     * @return list<array{index: int, variant_uuid: string, quantity: Quantity, modifiers: array<string, int>}>
     */
    public function merge(array $lines, Collection $variants): array
    {
        $merged = [];

        foreach ($lines as $line) {
            $variant = $variants->get($line['variant_uuid']);
            if ($variant === null || $variant->unit->isMeasured()) {
                $merged[] = $line;
                continue;
            }

            $signature = $line['variant_uuid'] . '|' . http_build_query($line['modifiers']);
            if (isset($merged[$signature])) {
                $merged[$signature]['quantity'] = $merged[$signature]['quantity']->plus($line['quantity']);
                continue;
            }

            $merged[$signature] = $line;
        }

        return array_values($merged);
    }

    /**
     * The branch's stop-list in force now, as [type => [id => reason|null]].
     *
     * @return array<string, array<int, ?string>>
     */
    public function stopList(?Branch $branch): array
    {
        $stopped = [StopListSubject::PRODUCT->value => [], StopListSubject::VARIANT->value => [], StopListSubject::MODIFIER->value => []];
        if (!$branch) {
            return $stopped;
        }

        foreach (StopListEntry::where('branch_id', $branch->id)->active()->get(['subject_type', 'subject_id', 'reason']) as $entry) {
            $stopped[$entry->subject_type->value][(int) $entry->subject_id] = $entry->reason;
        }

        // P6-15: dishes off today's "Bugungi menyu" count as stopped, and so does a kompleks with a stopped, sold-out or switched-off part
        $products = StopListSubject::PRODUCT->value;
        foreach (app(DailyMenuService::class)->offMenuProductIds($branch) as $productId) {
            if (!array_key_exists($productId, $stopped[$products])) {
                $stopped[$products][$productId] = DailyMenuService::OFF_MENU_REASON;
            }
        }
        $sets = app(MealSets::class);
        if ($sets->hasSets((int) $branch->store_id)) {
            // A part that is sold out (batch stock) or switched off on this branch closes the kompleks like a stopped one
            $variants = $stopped[StopListSubject::VARIANT->value];
            foreach (app(MenuAvailability::class)->soldOutVariantIds($branch) as $variantId) {
                $variants[$variantId] ??= null;
            }
            foreach (BranchProductVariant::where('branch_id', $branch->id)->where('is_active', false)->pluck('variant_id') as $variantId) {
                $variants[(int) $variantId] ??= null;
            }
            foreach ($sets->closedByParts((int) $branch->store_id, $stopped[$products], $variants) as $productId => $reason) {
                if (!array_key_exists($productId, $stopped[$products])) {
                    $stopped[$products][$productId] = $reason;
                }
            }
        }

        return $stopped;
    }

    /**
     * The modifier groups each product offers (active groups only), with their effective rules and active modifiers.
     *
     * @param  list<int>  $productIds
     * @return array<int, list<array{group: ModifierGroup, min: int, max: ?int, modifiers: Collection<string, Modifier>}>>
     */
    public function modifierRules(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $groups = ModifierGroup::query()
            ->where('is_active', true)
            ->join('product_modifier_groups', 'product_modifier_groups.modifier_group_id', '=', 'modifier_groups.id')
            ->whereIn('product_modifier_groups.product_id', $productIds)
            ->orderBy('product_modifier_groups.sort_order')
            ->orderBy('modifier_groups.id')
            ->with(['modifiers' => fn ($query) => $query->where('is_active', true)])
            ->get([
                'modifier_groups.*',
                'product_modifier_groups.product_id as pivot_product_id',
                'product_modifier_groups.min_select as pivot_min_select',
                'product_modifier_groups.max_select as pivot_max_select',
                'product_modifier_groups.is_required as pivot_is_required',
            ]);

        $rules = [];
        foreach ($groups as $group) {
            $min = $group->getAttribute('pivot_min_select') ?? $group->min_select;
            $max = $group->getAttribute('pivot_max_select') ?? $group->max_select;
            $required = $group->getAttribute('pivot_is_required') ?? $group->is_required;
            $min = (int) $min;
            if ((bool) $required) {
                $min = max($min, 1);
            }

            $rules[(int) $group->getAttribute('pivot_product_id')][] = [
                'group' => $group,
                'min' => $min,
                'max' => $max !== null ? (int) $max : null,
                'modifiers' => $group->modifiers->keyBy(fn (Modifier $modifier) => strtolower($modifier->uuid)),
            ];
        }

        return $rules;
    }

    /**
     * Checks a line's quantity against the variant's unit.
     */
    public function checkQuantity(ProductVariant $variant, Quantity $quantity, int $index): void
    {
        $name = $this->nameOf($variant);
        $unit = $variant->unit;

        if (!$unit->isMeasured()) {
            if (!$quantity->isWhole()) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => "'{$name}' dona bilan sotiladi: miqdor butun son bo'lishi kerak.",
                ]);
            }

            return;
        }

        $minimum = $variant->minimumQuantity();
        if ($quantity->isLessThan($minimum)) {
            throw ValidationException::withMessages([
                "items.{$index}.quantity" => "'{$name}' kamida {$minimum->format()} {$unit->label()} buyurtma qilinadi.",
            ]);
        }

        $step = $variant->quantityStep();
        if (!$quantity->isMultipleOf($step)) {
            throw ValidationException::withMessages([
                "items.{$index}.quantity" => "'{$name}' miqdori {$step?->format()} {$unit->label()} qadam bilan bo'lishi kerak (masalan 0.5 yoki 1.2).",
            ]);
        }
    }

    /**
     * The line's chosen modifiers, checked, as the snapshot stored on the order item, and their total (per piece, or
     * per line for a weighed item).
     *
     * @param  array<string, int>  $chosen  modifier uuid => quantity
     * @param  list<array{group: ModifierGroup, min: int, max: ?int, modifiers: Collection<string, Modifier>}>  $rules
     * @param  array<int, ?string>  $stoppedModifiers  modifier id => reason
     * @return array{snapshot: list<array<string, mixed>>, total: Money}
     */
    public function resolveModifiers(ProductVariant $variant, array $chosen, array $rules, array $stoppedModifiers, int $index): array
    {
        $name = $this->nameOf($variant);
        $key = "items.{$index}.modifiers";
        $snapshot = [];
        $total = Money::zero();
        $counts = [];

        foreach ($chosen as $uuid => $count) {
            $found = null;
            foreach ($rules as $rule) {
                if ($modifier = $rule['modifiers']->get($uuid)) {
                    $found = [$rule['group'], $modifier];
                    break;
                }
            }

            if ($found === null) {
                throw ValidationException::withMessages([
                    $key => "Tanlangan qo'shimcha '{$name}' taomiga tegishli emas yoki hozir sotilmaydi. Iltimos, savatni yangilang.",
                ]);
            }

            [$group, $modifier] = $found;
            if (array_key_exists((int) $modifier->id, $stoppedModifiers)) {
                throw ValidationException::withMessages([
                    $key => "Uzr, '{$modifier->name}' hozir ushbu filialda mavjud emas. Iltimos, boshqasini tanlang.",
                ]);
            }
            if ($count > max(1, (int) $modifier->max_quantity)) {
                throw ValidationException::withMessages([
                    $key => "'{$modifier->name}' ko'pi bilan {$modifier->max_quantity} marta tanlanishi mumkin.",
                ]);
            }

            $lineTotal = Money::of($modifier->price_delta)->times($count);
            $total = $total->plus($lineTotal);
            $counts[(int) $group->id] = ($counts[(int) $group->id] ?? 0) + $count;

            $snapshot[] = [
                'id' => (int) $modifier->id,
                'uuid' => $modifier->uuid,
                'group_id' => (int) $group->id,
                'group_name' => $group->name,
                'name' => $modifier->name,
                'price_delta' => Money::of($modifier->price_delta)->toString(),
                'quantity' => $count,
                'total' => $lineTotal->toString(),
            ];
        }

        foreach ($rules as $rule) {
            $count = $counts[(int) $rule['group']->id] ?? 0;
            $groupName = $rule['group']->name;

            if ($count < $rule['min']) {
                throw ValidationException::withMessages([
                    $key => $rule['min'] === 1
                        ? "'{$name}': '{$groupName}' tanlovi majburiy."
                        : "'{$name}': '{$groupName}' guruhidan kamida {$rule['min']} ta tanlang.",
                ]);
            }
            if ($rule['max'] !== null && $count > $rule['max']) {
                throw ValidationException::withMessages([
                    $key => "'{$name}': '{$groupName}' guruhidan ko'pi bilan {$rule['max']} ta tanlash mumkin.",
                ]);
            }
        }

        // In the group order the product shows them
        $order = array_flip(array_map(fn (array $rule) => (int) $rule['group']->id, $rules));
        usort($snapshot, fn (array $a, array $b) => ($order[$a['group_id']] ?? 0) <=> ($order[$b['group_id']] ?? 0));

        return ['snapshot' => $snapshot, 'total' => $total];
    }

    /**
     * unit price × quantity + modifiers × (pieces ? quantity : 1), computed exactly and rounded once, half-up, to
     * whole so'm.
     */
    public function lineTotal(Money $unitPrice, Quantity $quantity, Money $modifiers, MenuUnit $unit): Money
    {
        $exact = BigDecimal::of($unitPrice->toString())->multipliedBy($quantity->toBigDecimal())
            ->plus(BigDecimal::of($modifiers->toString())->multipliedBy($unit->isMeasured() ? BigDecimal::one() : $quantity->toBigDecimal()));

        return Money::of($exact->toScale(0, RoundingMode::HalfUp));
    }

    public function nameOf(ProductVariant $variant): string
    {
        return ($variant->product->name ?? '') . ($variant->name ? " ({$variant->name})" : '');
    }
}
