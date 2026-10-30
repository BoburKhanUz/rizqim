<?php

namespace App\Domain\Pos\Takeaway;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\KitchenItemStatus;
use App\Models\Branch;
use App\Models\BranchPackagingRule;
use App\Models\Order;
use App\Models\OrderPackagingRemoval;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * P6-34 (B8, module `packaging`): the packaging lines of an order (docs/POS_SPEC.md "Qadoq"). A branch's rules each name
 * one container with its own price and MXIK:
 *
 *   basis order   quantity × 1 per order that has at least one live dish
 *   basis item    quantity × the portions of the dishes of its category or product: a piece line counts its quantity
 *                 (rounded up: 1.5 portions take 2 bowls), a kg or litre line counts 1
 *   amount        quantity × unit_price, whole tiyin (prices are whole so'm in practice)
 *
 * only for the order types the rule lists (PackagingOrderType: POS takeaway, online pickup, delivery; never the hall).
 * The lines are one per rule, in the rules' order; a line the cashier took off (order_packaging_removals) stays on the
 * list with `removed` and its reason, amount 0. The packaging total is a charge after the discount (never discounted):
 * PosChargesCalculator for POS orders, CreateOrderAction for online ones; the same rules run offline in pos_data
 * (OfflinePricing.packaging).
 *
 * The module gates the rules, not an order that has packaging: with the module off a POS order keeps the lines it
 * already has (frozen as stored) and gets no new ones. Rule changes reach open orders on their next recalculation.
 */
final class PackagingRules
{
    /** One stored packaging line (orders.packaging_lines, the bill and the receipt). */
    public const LINE = 'array{rule_id: int, name: string, basis: string, quantity: int, unit: string, unit_price: string, amount: string, mxik: string|null, package_code: string|null, removed: bool, removed_reason: string|null}';

    /** A rule as the owner API and the bootstrap show it. */
    public const RULE = 'array{id: int, branch_id: int, name: string, basis: string, basis_label: string, category_id: int|null, product_id: int|null, quantity: int, unit_price: string, mxik: string|null, package_code: string|null, unit: string, order_types: list<string>, is_active: bool, sort_order: int}';

    public const MAX_RULES = 50;

    public const MAX_QUANTITY = 20;

    public const MAX_PRICE = '9999999.99';

    public function __construct(
        private readonly PosModules $modules,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * The branch's rules in their order (sort_order, then id).
     *
     * @return Collection<int, BranchPackagingRule>
     */
    public function rules(int $branchId, bool $activeOnly = false): Collection
    {
        return BranchPackagingRule::query()
            ->where('branch_id', $branchId)
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<string, mixed> RULE
     */
    public static function view(BranchPackagingRule $rule): array
    {
        return [
            'id' => (int) $rule->id,
            'branch_id' => (int) $rule->branch_id,
            'name' => $rule->name,
            'basis' => $rule->basis->value,
            'basis_label' => $rule->basis->label(),
            'category_id' => $rule->category_id,
            'product_id' => $rule->product_id,
            'quantity' => (int) $rule->quantity,
            'unit_price' => Money::of($rule->unit_price)->toString(),
            'mxik' => $rule->mxik,
            'package_code' => $rule->package_code,
            'unit' => $rule->unit,
            'order_types' => array_values(array_filter((array) $rule->order_types, fn ($type) => PackagingOrderType::tryFrom((string) $type) !== null)),
            'is_active' => (bool) $rule->is_active,
            'sort_order' => (int) $rule->sort_order,
        ];
    }

    /**
     * Creates a rule (validated input), audited.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Branch $branch, array $data, User $actor): BranchPackagingRule
    {
        $rule = BranchPackagingRule::create(self::attributes($data) + [
            'store_id' => $branch->store_id,
            'branch_id' => $branch->id,
            'sort_order' => $data['sort_order'] ?? ((int) BranchPackagingRule::where('branch_id', $branch->id)->max('sort_order') + 1),
        ]);
        $this->audit->record('settings.packaging_rule_created', $rule, [], self::view($rule), [], ['store_id' => (int) $branch->store_id, 'branch_id' => (int) $branch->id, 'actor' => $actor]);

        return $rule;
    }

    /**
     * Changes what is sent (validated input), audited.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(BranchPackagingRule $rule, array $data, User $actor): BranchPackagingRule
    {
        $before = self::view($rule);
        $rule->fill(self::attributes($data));
        if (array_key_exists('sort_order', $data)) {
            $rule->sort_order = (int) $data['sort_order'];
        }
        // An order rule names no dishes; an item rule names exactly one category or product
        if ($rule->basis === PackagingBasis::ORDER) {
            $rule->category_id = null;
            $rule->product_id = null;
        }
        $rule->save();
        $this->audit->record('settings.packaging_rule_changed', $rule, $before, self::view($rule), [], ['store_id' => (int) $rule->store_id, 'branch_id' => (int) $rule->branch_id, 'actor' => $actor]);

        return $rule;
    }

    public function delete(BranchPackagingRule $rule, User $actor): void
    {
        $before = self::view($rule);
        $rule->delete();
        $this->audit->record('settings.packaging_rule_deleted', $rule, $before, [], [], ['store_id' => (int) $rule->store_id, 'branch_id' => (int) $rule->branch_id, 'actor' => $actor]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function attributes(array $data): array
    {
        $attributes = [];
        foreach (['name', 'unit', 'mxik', 'package_code'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                $attributes[$field] = is_string($value) && trim($value) !== '' ? trim($value) : ($field === 'unit' ? 'dona' : null);
            }
        }
        if (array_key_exists('basis', $data)) {
            $attributes['basis'] = PackagingBasis::from((string) $data['basis']);
        }
        foreach (['category_id', 'product_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field] !== null ? (int) $data[$field] : null;
            }
        }
        if (array_key_exists('quantity', $data)) {
            $attributes['quantity'] = (int) $data['quantity'];
        }
        if (array_key_exists('unit_price', $data)) {
            $attributes['unit_price'] = Money::of($data['unit_price'])->toString();
        }
        if (array_key_exists('order_types', $data)) {
            $attributes['order_types'] = array_values(array_unique(array_map('strval', (array) $data['order_types'])));
        }
        if (array_key_exists('is_active', $data)) {
            $attributes['is_active'] = (bool) $data['is_active'];
        }

        return $attributes;
    }

    /**
     * The packaging lines for dishes of a branch's order of $type. $dishes: the live lines, each with its product, its
     * category, its quantity and whether it is weighed. $removed: rule id => the reason it was taken off.
     *
     * @param  iterable<array{product_id: int|null, category_id: int|null, quantity: Quantity, measured: bool}>  $dishes
     * @param  array<int, string>  $removed
     * @return list<array<string, mixed>> LINE
     */
    public function linesFor(int $branchId, PackagingOrderType $type, iterable $dishes, array $removed = []): array
    {
        $rules = $this->rules($branchId, true);
        $dishes = is_array($dishes) ? $dishes : iterator_to_array($dishes, false);
        if ($dishes === []) {
            return [];
        }

        $lines = [];
        foreach ($rules as $rule) {
            if (!in_array($type->value, (array) $rule->order_types, true)) {
                continue;
            }

            $portions = $rule->basis === PackagingBasis::ORDER ? 1 : 0;
            if ($rule->basis === PackagingBasis::ITEM) {
                foreach ($dishes as $dish) {
                    $matches = ($rule->product_id !== null && $dish['product_id'] === (int) $rule->product_id)
                        || ($rule->product_id === null && $rule->category_id !== null && $dish['category_id'] === (int) $rule->category_id);
                    if ($matches) {
                        $portions += self::portions($dish['quantity'], $dish['measured']);
                    }
                }
            }
            if ($portions < 1) {
                continue;
            }

            $quantity = $portions * max(1, (int) $rule->quantity);
            $isRemoved = array_key_exists((int) $rule->id, $removed);
            $price = Money::of($rule->unit_price);
            $lines[] = [
                'rule_id' => (int) $rule->id,
                'name' => $rule->name,
                'basis' => $rule->basis->value,
                'quantity' => $quantity,
                'unit' => $rule->unit,
                'unit_price' => $price->toString(),
                'amount' => $isRemoved ? '0.00' : $price->times($quantity)->toString(),
                'mxik' => $rule->mxik,
                'package_code' => $rule->package_code,
                'removed' => $isRemoved,
                'removed_reason' => $isRemoved ? $removed[(int) $rule->id] : null,
            ];
        }

        return $lines;
    }

    /**
     * The portions a dish line counts: a piece line its quantity rounded up, a weighed one 1.
     */
    public static function portions(Quantity $quantity, bool $measured): int
    {
        if (!$quantity->isPositive()) {
            return 0;
        }
        if ($measured) {
            return 1;
        }

        return (int) $quantity->toBigDecimal()->toScale(0, \Brick\Math\RoundingMode::Ceiling)->toInt();
    }

    /**
     * The total of the lines that are not removed.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    public static function total(array $lines): Money
    {
        return Money::sum(...array_map(fn (array $line) => (string) $line['amount'], $lines));
    }

    /**
     * A POS order's packaging now (PosChargesCalculator): the rules over its live lines while the module is on, the
     * lines it already has (as stored) while it is off.
     *
     * @return array{lines: list<array<string, mixed>>, amount: Money}
     */
    public function forOrder(Order $order): array
    {
        $stored = self::stored($order);
        $branch = $order->branch_id !== null ? Branch::query()->whereKey((int) $order->branch_id)->first(['id', 'store_id', 'pos_settings']) : null;
        $type = PackagingOrderType::ofOrder($order);

        if ($branch === null || !$this->modules->enabled($branch, PosModule::PACKAGING)) {
            return ['lines' => $stored, 'amount' => self::total($stored)];
        }
        if ($type === null) {
            return ['lines' => [], 'amount' => Money::zero()];
        }

        $lines = $this->linesFor((int) $branch->id, $type, $this->dishesOf($order), $this->removedOf($order));

        return ['lines' => $lines, 'amount' => self::total($lines)];
    }

    /**
     * An online order's packaging at checkout (CreateOrderAction): the branch's rules over the priced lines, while the
     * module is on; none for a dine-in order.
     *
     * @param  iterable<array<string, mixed>>  $lines  CreateOrderAction's lines: `variant_model` (with its product) and `quantity`
     * @return array{lines: list<array<string, mixed>>, amount: Money}
     */
    public function forCheckout(Branch $branch, ?string $type, iterable $lines): array
    {
        $packagingType = PackagingOrderType::ofOnline($type);
        if ($packagingType === null || !$this->modules->enabled($branch, PosModule::PACKAGING)) {
            return ['lines' => [], 'amount' => Money::zero()];
        }

        $dishes = [];
        foreach ($lines as $line) {
            $variant = $line['variant_model'] ?? null;
            if (!$variant instanceof \App\Models\ProductVariant) {
                continue;
            }
            $product = $variant->product;
            $dishes[] = [
                'product_id' => (int) $variant->product_id,
                'category_id' => $product?->category_id !== null ? (int) $product->category_id : null,
                'quantity' => $line['quantity'] instanceof Quantity ? $line['quantity'] : Quantity::of((string) $line['quantity']),
                'measured' => $variant->unit->isMeasured(),
            ];
        }
        $packaging = $this->linesFor((int) $branch->id, $packagingType, $dishes);

        return ['lines' => $packaging, 'amount' => self::total($packaging)];
    }

    /**
     * The order's packaging lines as stored.
     *
     * @return list<array<string, mixed>>
     */
    public static function stored(Order $order): array
    {
        $lines = $order->getAttribute('packaging_lines');
        if (is_string($lines)) {
            $lines = json_decode($lines, true);
        }

        return is_array($lines) ? array_values(array_filter($lines, 'is_array')) : [];
    }

    /**
     * The packaging lines a bill carrying $share tiyin of the order's packaging shows (a split bill, part of it): the
     * live lines' amounts shared in proportion (largest remainder, Allocation), each line's quantity the same way (at
     * least 1); the whole packaging: the lines as they are. Removed and zero lines are left out.
     *
     * @return list<array<string, mixed>> LINE with the share's quantity and amount
     */
    public static function shareLines(Order $order, int $share): array
    {
        $lines = array_values(array_filter(self::stored($order), fn (array $line) => empty($line['removed']) && Money::of((string) $line['amount'])->isPositive()));
        if ($share <= 0 || $lines === []) {
            return [];
        }

        $weights = array_map(fn (array $line) => Money::of((string) $line['amount'])->toTiyin(), $lines);
        $amounts = \App\Domain\Pos\Billing\Allocation::proportional($share, $weights);
        $shared = [];
        foreach ($lines as $k => $line) {
            if ($amounts[$k] === 0) {
                continue;
            }
            $quantity = (int) $line['quantity'];
            $shared[] = [
                ...$line,
                'quantity' => $amounts[$k] === $weights[$k] ? $quantity : max(1, (int) round($quantity * $amounts[$k] / max(1, $weights[$k]))),
                'amount' => Money::fromTiyin($amounts[$k])->toString(),
            ];
        }

        return $shared;
    }

    /**
     * The live dish lines of an order with their product and category (deleted variants and products included: the line
     * is a snapshot).
     *
     * @return list<array{product_id: int|null, category_id: int|null, quantity: Quantity, measured: bool}>
     */
    public function dishesOf(Order $order): array
    {
        // Eloquent: the kitchen lines of combo parts (P6-31) are not dishes of their own, the combo line is
        return \App\Models\OrderItem::query()
            ->leftJoin('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->leftJoin('products', 'products.id', '=', 'product_variants.product_id')
            ->where('order_items.order_id', $order->id)
            ->where('order_items.kitchen_status', '!=', KitchenItemStatus::VOID->value)
            ->whereNull('order_items.voided_at')
            ->orderBy('order_items.id')
            ->get(['order_items.quantity', 'order_items.unit', 'products.id as dish_product_id', 'products.category_id as dish_category_id'])
            ->map(fn (\App\Models\OrderItem $row) => [
                'product_id' => $row->getAttribute('dish_product_id') !== null ? (int) $row->getAttribute('dish_product_id') : null,
                'category_id' => $row->getAttribute('dish_category_id') !== null ? (int) $row->getAttribute('dish_category_id') : null,
                'quantity' => Quantity::of((string) ($row->getRawOriginal('quantity') ?? '0')),
                'measured' => $row->unit->isMeasured(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string> rule id => reason
     */
    public function removedOf(Order $order): array
    {
        if (!$order->exists) {
            return [];
        }

        return OrderPackagingRemoval::where('order_id', $order->id)->pluck('reason', 'rule_id')
            ->mapWithKeys(fn ($reason, $ruleId) => [(int) $ruleId => (string) $reason])
            ->all();
    }

    /**
     * The cashier takes a packaging line off the order (the guest brought their own bag): a reason, orders.manage, the
     * order's version; refused once a bill of the order is being paid (409 `paid_bill_affected`: refund it first) and
     * for a line the order does not have (422 `packaging_not_found`). Audited; order.items_changed (`charges_changed`).
     *
     * @throws \App\Domain\Pos\Orders\PosOrderException
     */
    public function remove(Order $order, int $version, int $ruleId, string $reason, User $actor): Order
    {
        return $this->change($order, $version, $ruleId, $actor, function (Order $order, array $line) use ($ruleId, $reason, $actor) {
            if (!empty($line['removed'])) {
                throw new \App\Domain\Pos\Orders\PosOrderException("Bu qadoq allaqachon olib tashlangan.", 422, 'already_removed');
            }
            OrderPackagingRemoval::updateOrCreate(
                ['order_id' => $order->id, 'rule_id' => $ruleId],
                ['reason' => mb_substr(trim($reason), 0, 255), 'removed_by' => $actor->id],
            );

            return ['pos.packaging_removed', ['rule_id' => $ruleId, 'name' => $line['name'], 'amount' => $line['amount'], 'reason' => trim($reason)]];
        });
    }

    /**
     * Puts a packaging line taken off back (it raises the bill: orders.manage is enough). 422 `not_removed`.
     *
     * @throws \App\Domain\Pos\Orders\PosOrderException
     */
    public function restore(Order $order, int $version, int $ruleId, User $actor): Order
    {
        return $this->change($order, $version, $ruleId, $actor, function (Order $order, array $line) use ($ruleId) {
            if (OrderPackagingRemoval::where('order_id', $order->id)->where('rule_id', $ruleId)->delete() === 0) {
                throw new \App\Domain\Pos\Orders\PosOrderException("Bu qadoq olib tashlanmagan.", 422, 'not_removed');
            }

            return ['pos.packaging_restored', ['rule_id' => $ruleId, 'name' => $line['name']]];
        });
    }

    /**
     * @param  \Closure(Order, array<string, mixed>): array{0: string, 1: array<string, mixed>}  $apply
     */
    private function change(Order $order, int $version, int $ruleId, User $actor, \Closure $apply): Order
    {
        return DB::transaction(function () use ($order, $version, $ruleId, $actor, $apply) {
            $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            app(\App\Domain\Pos\Orders\PosOrderService::class)->assertMutable($locked);
            if ((int) $locked->version !== $version) {
                throw \App\Domain\Pos\Orders\PosOrderException::versionConflict((int) $locked->version);
            }
            $taken = \App\Models\OrderBill::query()
                ->where('order_id', $locked->id)
                ->where('status', '!=', \App\Domain\Pos\Billing\BillStatus::VOID->value)
                ->where(fn ($query) => $query->where('status', \App\Domain\Pos\Billing\BillStatus::PAID->value)
                    ->orWhereExists(fn ($payments) => $payments->selectRaw('1')->from('payments')->whereColumn('payments.bill_id', 'order_bills.id')))
                ->exists();
            if ($taken) {
                throw new \App\Domain\Pos\Orders\PosOrderException("Buyurtmaning schyoti to'lanmoqda yoki to'langan: qadoqni o'zgartirish uchun avval to'lovni qaytaring.", 409, 'paid_bill_affected');
            }

            $line = collect(self::stored($locked))->first(fn (array $line) => (int) $line['rule_id'] === $ruleId);
            if ($line === null) {
                throw new \App\Domain\Pos\Orders\PosOrderException('Buyurtmada bunday qadoq qatori yo\'q.', 422, 'packaging_not_found');
            }

            [$action, $meta] = $apply($locked, $line);
            app(\App\Domain\Pos\Orders\PosChargesCalculator::class)->recalculate($locked);
            $locked->forceFill(['version' => (int) $locked->version + 1])->save();
            $this->audit->record($action, $locked, [], [], $meta, ['actor' => $actor]);
            event(new \App\Events\Broadcast\OrderItemsChangedBroadcast($locked, 'charges_changed'));

            return $locked->refresh();
        });
    }

    /**
     * The rules the till prices offline with (bootstrap `takeaway.packaging`): the active ones, only while the module is on.
     *
     * @return list<array<string, mixed>> RULE
     */
    public function offlineRules(Branch $branch): array
    {
        if (!$this->modules->enabled($branch, PosModule::PACKAGING)) {
            return [];
        }

        return $this->rules((int) $branch->id, true)->map(fn (BranchPackagingRule $rule) => self::view($rule))->values()->all();
    }
}
