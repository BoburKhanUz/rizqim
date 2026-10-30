<?php

namespace App\Domain\Pos\Menu;

use App\Enums\StopListSubject;
use App\Models\Branch;
use App\Models\BranchProductVariant;
use App\Models\Combo;
use App\Models\ComboSlot;
use App\Models\ComboSlotOption;
use App\Models\DailySet;
use App\Models\Modifier;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\ChannelMenu;
use App\Services\Catalog\MenuAvailability;
use App\Services\Order\OrderLineBuilder;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Kombo" (P6-31, module `combo`, docs/MENU.md "Kombo"): a menu product (price, MXIK code, channel prices, daily menu as
 * for any dish) whose buyer picks its parts in ordered slots. Unlike the kompleks (MealSets, a fixed set that stays as
 * it is) a slot offers a choice: min/max picks (min 0: optional), options (a dish variant, pieces, an optional modifier
 * preset, a price delta ≥ 0, a default, a receipt label), and may let the buyer change the part's own modifiers.
 *
 * Price of one combo = the combo product's channel price + Σ(option delta × picks) + the deltas of modifiers the buyer
 * ADDED to a part in a slot that allows it (the preset is included in the option's price; taking a preset modifier away
 * gives nothing back). The line stores it as unit_price (the combo's) + modifiers_total (its own modifiers + the parts'
 * deltas), so every existing total, bill and receipt formula prices it unchanged.
 *
 * resolve() is the one check of a pick, for the POS (PosLineBuilder) and Telegram/Mini App (CreateOrderAction): every
 * option belongs to this combo, is available now (else 422 `combo_option_unavailable`: a stale pick), each slot's picks
 * are within min/max (a slot left out takes its default; 422 `combo_slot_required` / `combo_slot_limit`), and the
 * modifiers of a part are the part dish's own (validated like a line's). A combo closes (closedBySlots, on the stop-list
 * as the kompleks does) while a required slot has no available option; an unavailable option is not offered.
 */
class Combos
{
    public const MAX_SLOTS = 8;

    public const MAX_OPTIONS = 20;

    public const MAX_PICKS = 10;

    /** @var array<int, bool> store id => has active combos (per request) */
    private array $has = [];

    public function __construct(
        protected AuditLogger $audit,
        protected OrderLineBuilder $lines,
    ) {}

    public function hasCombos(int $storeId): bool
    {
        return $this->has[$storeId] ??= Combo::query()->where('store_id', $storeId)->where('is_active', true)->exists();
    }

    /**
     * The active combo of a product, with its slots, options, their dishes and preset modifiers.
     */
    public function forProduct(int $productId): ?Combo
    {
        return Combo::query()->where('product_id', $productId)->where('is_active', true)->with(self::RELATIONS)->first();
    }

    /** @var list<string> */
    public const RELATIONS = ['slots.options.variant.product'];

    /**
     * The combos of products (active ones), by product id, for the catalogs (CatalogPresenter): what the product card and
     * the till offer. Options whose dish is deleted or switched off in the menu are left out; the branch's availability
     * (stop-list, sold out) is the caller's (PosCatalog marks `is_available`, the Mini App reads its availability).
     *
     * @param  list<int>  $productIds
     * @return array<int, array<string, mixed>>
     */
    public function catalogFor(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $combos = Combo::query()->whereIn('product_id', $productIds)->where('is_active', true)->with(self::RELATIONS)->get();
        $modifiers = $this->presetModifiers($combos);

        $result = [];
        foreach ($combos as $combo) {
            $result[(int) $combo->product_id] = $this->present($combo, $modifiers, true);
        }

        return $result;
    }

    /**
     * POS catalog (PosCatalog): each option of a product's `combo` gets `is_available` from the branch's availability
     * (its dish or variant stopped, sold out, off today's menu or switched off on the branch, a preset modifier
     * stopped: false). The till offers only available options; the server refuses a stale one at checkout.
     *
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     * @param  array<string, mixed>  $availability  MenuAvailability's branch entry
     */
    public static function markAvailability(\Illuminate\Support\Collection $products, array $availability): void
    {
        $stoppedProducts = array_flip(array_map('intval', (array) ($availability['stopped']['products'] ?? [])));
        $variants = array_flip(array_map('intval', array_merge((array) ($availability['stopped']['variants'] ?? []), (array) ($availability['sold_out']['variants'] ?? []))));
        $modifiers = array_flip(array_map('intval', (array) ($availability['stopped']['modifiers'] ?? [])));

        foreach ($products as $product) {
            $combo = $product->getAttribute('combo');
            if (!is_array($combo)) {
                continue;
            }
            foreach ($combo['slots'] as $s => $slot) {
                foreach ($slot['options'] as $o => $option) {
                    $stoppedModifier = false;
                    foreach ($option['modifiers'] as $modifier) {
                        $stoppedModifier = $stoppedModifier || isset($modifiers[(int) $modifier['id']]);
                    }
                    $combo['slots'][$s]['options'][$o]['is_available'] = !isset($stoppedProducts[(int) $option['product_id']])
                        && !isset($variants[(int) $option['variant_id']]) && !$stoppedModifier;
                }
            }
            $product->setAttribute('combo', $combo);
        }
    }

    /**
     * The combo as the owner's constructor and the catalogs show it. $sellable: only options whose dish is on the menu.
     *
     * @param  Collection<int, Modifier>|null  $modifiers  preset modifiers by id
     * @return array<string, mixed>
     */
    public function present(Combo $combo, ?Collection $modifiers = null, bool $sellable = false): array
    {
        $combo->loadMissing(self::RELATIONS);
        $modifiers ??= $this->presetModifiers(new EloquentCollection([$combo]));

        return [
            'id' => (int) $combo->id,
            'uuid' => (string) $combo->uuid,
            'product_id' => (int) $combo->product_id,
            'is_active' => (bool) $combo->is_active,
            'slots' => $combo->slots->map(fn (ComboSlot $slot) => [
                'id' => (int) $slot->id,
                'uuid' => (string) $slot->uuid,
                'name' => (string) $slot->name,
                'min_picks' => (int) $slot->min_picks,
                'max_picks' => (int) $slot->max_picks,
                'is_required' => (int) $slot->min_picks > 0,
                'allow_modifiers' => (bool) $slot->allow_modifiers,
                'options' => $slot->options
                    ->filter(fn (ComboSlotOption $option) => !$sellable || $this->onMenu($option))
                    ->map(fn (ComboSlotOption $option) => $this->presentOption($slot, $option, $modifiers))
                    ->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Modifier>  $modifiers
     * @return array<string, mixed>
     */
    private function presentOption(ComboSlot $slot, ComboSlotOption $option, Collection $modifiers): array
    {
        $variant = $option->variant;
        $product = $variant?->product;
        $preset = $this->presetSnapshot($option, $modifiers);

        return [
            'id' => (int) $option->id,
            'uuid' => (string) $option->uuid,
            'variant_id' => (int) $option->product_variant_id,
            'variant_uuid' => (string) ($variant->uuid ?? ''),
            'product_id' => $product !== null ? (int) $product->id : null,
            'name' => (string) ($product->name ?? ''),
            'variant_name' => $variant !== null && filled($variant->name) ? (string) $variant->name : null,
            'quantity' => (int) $option->quantity,
            'price_delta' => Money::of($option->price_delta)->toString(),
            'modifiers' => $preset,
            'is_default' => (bool) $option->is_default,
            'label' => $option->label,
            // The text under the combo on the bill and receipt (no price)
            'receipt_label' => self::partLabel((string) $slot->name, (string) ($product->name ?? ''), $variant?->name, (int) $option->quantity, $preset, $option->label),
        ];
    }

    /**
     * Product ids of active combos that cannot be sold now: a required slot has no available option (its dish stopped,
     * sold out, off today's menu, switched off on the branch or in the menu, or a preset modifier stopped).
     *
     * @param  array<int, ?string>  $stoppedProducts  product id => reason
     * @param  array<int, ?string>  $stoppedVariants  variant id => reason (stop-list, branch override, sold out)
     * @param  array<int, ?string>  $stoppedModifiers  modifier id => reason
     * @return array<int, string> combo product id => reason
     */
    public function closedBySlots(int $storeId, array $stoppedProducts, array $stoppedVariants, array $stoppedModifiers = []): array
    {
        if (!$this->hasCombos($storeId)) {
            return [];
        }

        $closed = [];
        foreach (Combo::query()->where('store_id', $storeId)->where('is_active', true)->with(self::RELATIONS)->get() as $combo) {
            foreach ($combo->slots as $slot) {
                if ((int) $slot->min_picks < 1) {
                    continue;
                }
                $open = $slot->options->contains(fn (ComboSlotOption $option) => $this->available($option, $stoppedProducts, $stoppedVariants, $stoppedModifiers));
                if (!$open) {
                    $closed[(int) $combo->product_id] = 'Kombodagi "' . $slot->name . '" tanlovida hozir mavjud taom yo\'q';
                    break;
                }
            }
        }

        return $closed;
    }

    /**
     * What a branch cannot sell now, for the option checks: the stop-list as OrderLineBuilder::stopList reads it (with
     * today's menu and closed sets) plus the sold-out (batch) and branch-hidden variants.
     *
     * @param  array<string, array<int, ?string>>  $stopList
     * @return array{products: array<int, ?string>, variants: array<int, ?string>, modifiers: array<int, ?string>}
     */
    public function blockedFor(?Branch $branch, array $stopList): array
    {
        $variants = $stopList[StopListSubject::VARIANT->value];
        if ($branch !== null) {
            foreach (app(MenuAvailability::class)->soldOutVariantIds($branch) as $variantId) {
                $variants[(int) $variantId] ??= 'tugadi';
            }
            foreach (BranchProductVariant::where('branch_id', $branch->id)->where('is_active', false)->get(['variant_id', 'hidden_reason']) as $row) {
                $variants[(int) $row->variant_id] ??= $row->hidden_reason;
            }
        }

        return [
            'products' => $stopList[StopListSubject::PRODUCT->value],
            'variants' => $variants,
            'modifiers' => $stopList[StopListSubject::MODIFIER->value],
        ];
    }

    /**
     * Checks a combo line's picks and prices its parts. Null when the variant's product is no active combo (a plain
     * line). $strict false (a sale the till made offline): availability is not asked again and an option of a combo
     * since changed is still taken while it exists in the store (the device sold it); limits and membership still are.
     *
     * Each pick: {option_id: uuid, quantity?: 1..MAX_PICKS, modifiers?: [uuid | {id, quantity}]}.
     *
     * @param  array<int, mixed>  $picks
     * @param  array{products: array<int, ?string>, variants: array<int, ?string>, modifiers: array<int, ?string>}  $blocked
     * @return array{parts: list<array<string, mixed>>, delta: Money}|null
     *
     * @throws ValidationException
     */
    public function resolve(ProductVariant $variant, array $picks, array $blocked, ChannelMenu $menu, int $index, bool $strict = true): ?array
    {
        $combo = $this->forProduct((int) $variant->product_id);
        if ($combo === null) {
            if ($picks !== []) {
                throw ComboPickException::invalid($index, "'{$this->lines->nameOf($variant)}' kombo emas: tarkib tanlab bo'lmaydi.");
            }

            return null;
        }
        $comboName = (string) ($variant->product->name ?? 'Kombo');

        // The picks as sent: option uuid => [count, modifiers|null], in the order sent
        $chosen = [];
        foreach ($picks as $k => $pick) {
            $pick = is_array($pick) ? $pick : [];
            $uuid = is_string($pick['option_id'] ?? null) ? strtolower($pick['option_id']) : '';
            $count = filter_var($pick['quantity'] ?? 1, FILTER_VALIDATE_INT);
            if ($uuid === '' || $count === false || $count < 1 || $count > self::MAX_PICKS) {
                throw ComboPickException::invalid($index, "'{$comboName}': tanlov noto'g'ri yuborilgan.");
            }
            $modifiers = array_key_exists('modifiers', $pick) ? $this->lines->parse([['quantity' => 1, 'modifiers' => (array) ($pick['modifiers'] ?? [])]])[0]['modifiers'] : null;
            if (isset($chosen[$uuid])) {
                $chosen[$uuid]['count'] += (int) $count;
            } else {
                $chosen[$uuid] = ['count' => (int) $count, 'modifiers' => $modifiers];
            }
        }

        /** @var array<string, array{slot: ComboSlot, option: ComboSlotOption}> $options */
        $options = [];
        foreach ($combo->slots as $slot) {
            foreach ($slot->options as $option) {
                $options[strtolower((string) $option->uuid)] = ['slot' => $slot, 'option' => $option];
            }
        }
        if (!$strict) {
            // Offline: an option removed from the combo since the till took its menu is still the store's sale
            $missing = array_diff(array_keys($chosen), array_keys($options));
            if ($missing !== []) {
                $stale = ComboSlotOption::query()->whereIn('uuid', $missing)->with(['slot', 'variant.product'])
                    ->whereHas('slot.combo', fn ($query) => $query->where('store_id', $combo->store_id))->get();
                foreach ($stale as $option) {
                    $options[strtolower((string) $option->uuid)] = ['slot' => $option->slot, 'option' => $option];
                }
            }
        }

        $modifierRows = $this->presetModifiers(new EloquentCollection([$combo]));
        $bySlot = [];
        foreach ($chosen as $uuid => $pick) {
            $found = $options[$uuid] ?? null;
            if ($found === null) {
                throw ComboPickException::invalid($index, "'{$comboName}': tanlangan taom bu komboga tegishli emas. Menyuni yangilang.");
            }
            if ($strict && !$this->available($found['option'], $blocked['products'], $blocked['variants'], $blocked['modifiers'])) {
                throw ComboPickException::unavailable($index, $comboName, $this->optionName($found['option']));
            }
            $bySlot[(int) $found['slot']->id][] = ['option' => $found['option'], 'count' => $pick['count'], 'modifiers' => $pick['modifiers']];
        }

        $parts = [];
        $delta = Money::zero();
        foreach ($combo->slots as $slot) {
            $slotPicks = $bySlot[(int) $slot->id] ?? [];
            if ($slotPicks === [] && (int) $slot->min_picks > 0) {
                // A slot left out takes its default (one tap sells "Kombo 1" as the owner set it up)
                $default = $slot->options->first(fn (ComboSlotOption $option) => $option->is_default
                    && ($strict ? $this->available($option, $blocked['products'], $blocked['variants'], $blocked['modifiers']) : $this->onMenu($option)));
                if ($default !== null) {
                    $slotPicks = [['option' => $default, 'count' => min((int) $slot->min_picks, max(1, (int) $slot->max_picks)), 'modifiers' => null]];
                }
            }

            $count = array_sum(array_column($slotPicks, 'count'));
            if ($count < (int) $slot->min_picks) {
                throw ComboPickException::required($index, $comboName, (string) $slot->name, (int) $slot->min_picks);
            }
            if ($count > max(1, (int) $slot->max_picks)) {
                throw ComboPickException::limit($index, $comboName, (string) $slot->name, max(1, (int) $slot->max_picks));
            }

            foreach ($slotPicks as $pick) {
                $part = $this->part($slot, $pick['option'], $pick['count'], $pick['modifiers'], $blocked, $menu, $modifierRows, $index, $strict);
                $delta = $delta->plus($part['price_delta']);
                $parts[] = $part;
            }
        }

        return ['parts' => $parts, 'delta' => $delta];
    }

    /**
     * One part as sold (order_items.combo_parts element).
     *
     * @param  array<string, int>|null  $chosenModifiers  null: the option's preset
     * @param  array{products: array<int, ?string>, variants: array<int, ?string>, modifiers: array<int, ?string>}  $blocked
     * @param  Collection<int, Modifier>  $presetRows
     * @return array<string, mixed>
     */
    private function part(ComboSlot $slot, ComboSlotOption $option, int $count, ?array $chosenModifiers, array $blocked, ChannelMenu $menu, Collection $presetRows, int $index, bool $strict): array
    {
        $variant = $option->variant;
        if ($variant === null || $variant->product === null) {
            throw ComboPickException::unavailable($index, '', 'tanlov');
        }
        $pieces = (int) $option->quantity * $count;
        $preset = $this->presetSnapshot($option, $presetRows);
        $presetCounts = [];
        foreach ($preset as $modifier) {
            $presetCounts[strtolower((string) $modifier['uuid'])] = (int) $modifier['quantity'];
        }

        $snapshot = $preset;
        $extra = Money::zero();
        if ($slot->allow_modifiers && $chosenModifiers !== null) {
            // The buyer's own choice, checked against the dish's groups like a line's
            $rules = $this->lines->modifierRules([(int) $variant->product_id])[(int) $variant->product_id] ?? [];
            $resolved = $this->lines->resolveModifiers($variant, $chosenModifiers, $rules, $strict ? $blocked['modifiers'] : [], $index);
            $snapshot = $resolved['snapshot'];
            foreach ($snapshot as $modifier) {
                $added = (int) $modifier['quantity'] - ($presetCounts[strtolower((string) $modifier['uuid'])] ?? 0);
                if ($added > 0) {
                    $extra = $extra->plus(Money::of($modifier['price_delta'])->times($added));
                }
            }
        }

        $priceDelta = Money::of($option->price_delta)->times($count)->plus($extra->times($pieces));
        $modifiersPerPiece = Money::zero();
        foreach ($snapshot as $modifier) {
            $modifiersPerPiece = $modifiersPerPiece->plus(Money::of($modifier['price_delta'])->times((int) $modifier['quantity']));
        }

        return [
            'slot_id' => (int) $slot->id,
            'slot_uuid' => (string) $slot->uuid,
            'slot_name' => (string) $slot->name,
            'option_id' => (int) $option->id,
            'option_uuid' => (string) $option->uuid,
            'variant_id' => (int) $variant->id,
            'variant_uuid' => (string) $variant->uuid,
            'product_id' => (int) $variant->product_id,
            'name' => (string) $variant->product->name,
            'variant_name' => filled($variant->name) ? (string) $variant->name : null,
            'quantity' => $pieces,
            'unit' => $variant->unit->value,
            'modifiers' => array_map(fn (array $modifier) => [
                'id' => (int) $modifier['id'],
                'uuid' => (string) $modifier['uuid'],
                'group_id' => isset($modifier['group_id']) ? (int) $modifier['group_id'] : null,
                'group_name' => $modifier['group_name'] ?? null,
                'name' => (string) $modifier['name'],
                'quantity' => (int) $modifier['quantity'],
                'price_delta' => Money::of($modifier['price_delta'] ?? 0)->toString(),
            ], $snapshot),
            // What this part adds to one combo's price
            'price_delta' => $priceDelta->toString(),
            // The part's own menu price in one combo (its dish and modifiers): the weight of its fiscal share in `split` mode
            'list_price' => $menu->priceOf($variant)->plus($modifiersPerPiece)->times($pieces)->toString(),
            'label' => self::partLabel((string) $slot->name, (string) $variant->product->name, $variant->name, $pieces, $snapshot, $option->label),
            'item_id' => null,
        ];
    }

    /**
     * The receipt text of a part, never with a price (owner decision 30.10.2026): the option's own label when the owner
     * wrote one ("Ichimlik — 0,4 l Pepsi"), else "<slot> — <pieces> ta <dish> (<variant>), <modifiers>".
     *
     * @param  list<array<string, mixed>>  $modifiers
     */
    public static function partLabel(string $slot, string $name, ?string $variant, int $pieces, array $modifiers, ?string $label = null): string
    {
        if ($label !== null && trim($label) !== '') {
            return $pieces > 1 && !preg_match('/^\d/', trim($label)) ? "{$pieces} ta " . trim($label) : trim($label);
        }

        $text = ($pieces > 1 ? "{$pieces} ta " : '') . $name . (filled($variant) ? " ({$variant})" : '');
        $names = [];
        foreach ($modifiers as $modifier) {
            $quantity = (int) ($modifier['quantity'] ?? 1);
            $names[] = (string) ($modifier['name'] ?? '') . ($quantity > 1 ? " x{$quantity}" : '');
        }
        if ($names !== []) {
            $text .= ', ' . implode(', ', $names);
        }

        return trim($slot) !== '' ? trim($slot) . ' — ' . $text : $text;
    }

    /**
     * The parts of a combo line without prices, as the bill, receipt and order card list them under it.
     *
     * @param  list<array<string, mixed>>|null  $parts
     * @return list<array{slot_name: string, name: string, variant_name: string|null, quantity: int, label: string}>
     */
    public static function receiptParts(?array $parts): array
    {
        return array_map(fn (array $part) => [
            'slot_name' => (string) ($part['slot_name'] ?? ''),
            'name' => (string) ($part['name'] ?? ''),
            'variant_name' => isset($part['variant_name']) ? (string) $part['variant_name'] : null,
            'quantity' => (int) ($part['quantity'] ?? 1),
            'label' => (string) ($part['label'] ?? ''),
        ], array_values(array_filter($parts ?? [], 'is_array')));
    }

    // --- Owner: the constructor ------------------------------------------------------------------------------------

    /**
     * Creates the combo of a product, or replaces its slots and options. 422 for a product that already is a kompleks, a
     * part outside the store or sold by weight, the combo itself or another combo or kompleks as a part, a preset modifier
     * that is not the dish's, a slot whose limits do not fit its options, or more than one default where one is picked.
     *
     * @param  list<array<string, mixed>>  $slots  [{name, min_picks?, max_picks?, allow_modifiers?, options: [{variant_id, quantity?, price_delta?, modifiers?: [{modifier_id, quantity?}], is_default?, label?}]}]
     */
    public function save(int $storeId, Product $product, array $slots, bool $active, ?User $actor): Combo
    {
        if ((int) $product->store_id !== $storeId) {
            throw ValidationException::withMessages(['product_id' => 'Mahsulot topilmadi.']);
        }
        if (DailySet::query()->where('product_id', $product->id)->exists()) {
            throw ValidationException::withMessages(['product_id' => "Bu mahsulot kompleks: uni kombo qilib bo'lmaydi (avval kompleksni o'chiring)."]);
        }
        if ($product->variants()->where('unit', '!=', 'piece')->exists()) {
            throw ValidationException::withMessages(['product_id' => "Kombo dona bilan sotiladi: kg yoki litr variantli mahsulotni kombo qilib bo'lmaydi."]);
        }
        if ($slots === [] || count($slots) > self::MAX_SLOTS) {
            throw ValidationException::withMessages(['slots' => 'Komboda 1 tadan ' . self::MAX_SLOTS . ' tagacha slot bo\'lishi kerak.']);
        }

        $variantIds = [];
        foreach ($slots as $slot) {
            foreach ((array) ($slot['options'] ?? []) as $option) {
                $variantIds[] = (int) ($option['variant_id'] ?? 0);
            }
        }
        $variants = ProductVariant::query()->with('product')->whereIn('id', $variantIds)->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('store_id', $storeId))->get()->keyBy('id');
        $blockedProducts = array_merge(
            Combo::query()->where('store_id', $storeId)->where('product_id', '!=', $product->id)->pluck('product_id')->map(fn ($id) => (int) $id)->all(),
            DailySet::query()->where('store_id', $storeId)->pluck('product_id')->map(fn ($id) => (int) $id)->all(),
            [(int) $product->id],
        );
        $allowedModifiers = $this->lines->modifierRules($variants->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values()->all());

        $rows = [];
        foreach ($slots as $s => $slot) {
            $name = trim((string) ($slot['name'] ?? ''));
            $options = array_values((array) ($slot['options'] ?? []));
            $min = (int) ($slot['min_picks'] ?? 1);
            $max = (int) ($slot['max_picks'] ?? max(1, $min));
            if ($name === '') {
                throw ValidationException::withMessages(["slots.{$s}.name" => 'Slot nomini yozing (masalan "Ichimlik").']);
            }
            if ($options === [] || count($options) > self::MAX_OPTIONS) {
                throw ValidationException::withMessages(["slots.{$s}.options" => "Slotda 1 tadan " . self::MAX_OPTIONS . " tagacha tanlov bo'lishi kerak."]);
            }
            if ($min < 0 || $max < 1 || $max > self::MAX_PICKS || $min > $max) {
                throw ValidationException::withMessages(["slots.{$s}.max_picks" => "Tanlovlar soni: eng kami 0–{$max}, eng ko'pi 1–" . self::MAX_PICKS . '.']);
            }

            $optionRows = [];
            $defaults = 0;
            foreach ($options as $o => $option) {
                $key = "slots.{$s}.options.{$o}";
                $variant = $variants->get((int) ($option['variant_id'] ?? 0));
                if ($variant === null) {
                    throw ValidationException::withMessages(["{$key}.variant_id" => 'Taom topilmadi yoki faol emas.']);
                }
                if (in_array((int) $variant->product_id, $blockedProducts, true)) {
                    throw ValidationException::withMessages(["{$key}.variant_id" => "Kombo o'zini, boshqa kombo yoki kompleksni o'z ichiga ololmaydi."]);
                }
                if ($variant->unit->isMeasured()) {
                    throw ValidationException::withMessages(["{$key}.variant_id" => "Komboga faqat dona bilan sotiladigan taom qo'shiladi."]);
                }
                $quantity = (int) ($option['quantity'] ?? 1);
                if ($quantity < 1 || $quantity > 20) {
                    throw ValidationException::withMessages(["{$key}.quantity" => "Dona soni 1 dan 20 gacha."]);
                }
                $delta = Money::of($option['price_delta'] ?? 0);
                if ($delta->isNegative()) {
                    throw ValidationException::withMessages(["{$key}.price_delta" => "Narx farqi manfiy bo'lmaydi (0 yoki qo'shimcha)."]);
                }
                $preset = [];
                $own = collect($allowedModifiers[(int) $variant->product_id] ?? [])->flatMap(fn (array $rule) => $rule['modifiers']->values())->keyBy('id');
                foreach ((array) ($option['modifiers'] ?? []) as $m => $modifier) {
                    $id = (int) (is_array($modifier) ? ($modifier['modifier_id']) : $modifier);
                    if (!$own->has($id)) {
                        throw ValidationException::withMessages(["{$key}.modifiers.{$m}" => "Qo'shimcha bu taomga tegishli emas."]);
                    }
                    $preset[] = ['modifier_id' => $id, 'quantity' => max(1, (int) (is_array($modifier) ? ($modifier['quantity'] ?? 1) : 1))];
                }
                $isDefault = (bool) ($option['is_default'] ?? false);
                $defaults += $isDefault ? 1 : 0;
                $rawLabel = $option['label'] ?? null;
                $optionRows[] = [
                    'product_variant_id' => (int) $variant->id,
                    'quantity' => $quantity,
                    'price_delta' => $delta->toString(),
                    'modifiers' => $preset === [] ? null : $preset,
                    'is_default' => $isDefault,
                    'label' => is_string($rawLabel) && trim($rawLabel) !== '' ? mb_substr(trim($rawLabel), 0, 80) : null,
                    'sort_order' => $o,
                ];
            }
            if ($defaults > 1) {
                throw ValidationException::withMessages(["slots.{$s}.options" => 'Slotda bitta standart tanlov bo\'ladi.']);
            }

            $rows[] = [
                'slot' => ['name' => mb_substr($name, 0, 60), 'min_picks' => $min, 'max_picks' => $max, 'allow_modifiers' => (bool) ($slot['allow_modifiers'] ?? false), 'sort_order' => $s],
                'options' => $optionRows,
            ];
        }

        $combo = DB::transaction(function () use ($storeId, $product, $rows, $active, $actor) {
            $combo = Combo::query()->where('product_id', $product->id)->lockForUpdate()->first();
            $created = $combo === null;
            $before = $created ? [] : ['is_active' => (bool) $combo->is_active, 'slots' => $this->auditShape($combo)];

            if ($created) {
                $combo = Combo::create(['store_id' => $storeId, 'product_id' => $product->id, 'is_active' => $active, 'created_by' => $actor?->id]);
            } else {
                $combo->forceFill(['is_active' => $active])->save();
                ComboSlot::query()->where('combo_id', $combo->id)->delete();
            }
            foreach ($rows as $row) {
                $slot = ComboSlot::create($row['slot'] + ['combo_id' => $combo->id]);
                foreach ($row['options'] as $option) {
                    ComboSlotOption::create($option + ['combo_slot_id' => $slot->id]);
                }
            }

            $combo->unsetRelation('slots');
            $this->audit->record('menu.combo_saved', $combo, $before, ['is_active' => $active, 'slots' => $this->auditShape($combo)], [
                'product' => $product->name,
                'created' => $created,
            ], ['actor' => $actor, 'store_id' => $storeId]);

            return $combo;
        });

        unset($this->has[$storeId]);

        return $combo->load(self::RELATIONS);
    }

    public function delete(Combo $combo, ?User $actor): void
    {
        $product = Product::withTrashed()->find($combo->product_id);
        $this->audit->record('menu.combo_deleted', $combo, ['slots' => $this->auditShape($combo)], [], [
            'product' => $product?->name,
        ], ['actor' => $actor, 'store_id' => (int) $combo->store_id]);

        unset($this->has[(int) $combo->store_id]);
        $combo->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function auditShape(Combo $combo): array
    {
        $combo->loadMissing(self::RELATIONS);

        return $combo->slots->map(fn (ComboSlot $slot) => [
            'name' => $slot->name,
            'min_picks' => (int) $slot->min_picks,
            'max_picks' => (int) $slot->max_picks,
            'options' => $slot->options->map(fn (ComboSlotOption $option) => [
                'variant_id' => (int) $option->product_variant_id,
                'name' => $option->variant?->product?->name,
                'quantity' => (int) $option->quantity,
                'price_delta' => Money::of($option->price_delta)->toString(),
                'is_default' => (bool) $option->is_default,
            ])->values()->all(),
        ])->values()->all();
    }

    // --- Availability ------------------------------------------------------------------------------------------------

    /**
     * The option's dish is on the menu (not deleted, switched on, the product active).
     */
    private function onMenu(ComboSlotOption $option): bool
    {
        $variant = $option->variant;
        $product = $variant?->product;

        return $variant !== null && $product !== null && !$variant->trashed() && $variant->is_active
            && !$product->trashed() && $product->status === 'active';
    }

    /**
     * @param  array<int, ?string>  $stoppedProducts
     * @param  array<int, ?string>  $stoppedVariants
     * @param  array<int, ?string>  $stoppedModifiers
     */
    public function available(ComboSlotOption $option, array $stoppedProducts, array $stoppedVariants, array $stoppedModifiers = []): bool
    {
        if (!$this->onMenu($option)) {
            return false;
        }
        if (array_key_exists((int) $option->variant->product_id, $stoppedProducts) || array_key_exists((int) $option->product_variant_id, $stoppedVariants)) {
            return false;
        }
        foreach ((array) ($option->modifiers ?? []) as $modifier) {
            if (array_key_exists((int) ($modifier['modifier_id']), $stoppedModifiers)) {
                return false;
            }
        }

        return true;
    }

    private function optionName(ComboSlotOption $option): string
    {
        return $option->variant !== null ? $this->lines->nameOf($option->variant) : 'tanlov';
    }

    /**
     * The preset modifiers of the combos' options, by id (deleted ones too: a sold line may name them).
     *
     * @param  EloquentCollection<int, Combo>  $combos
     * @return Collection<int, Modifier>
     */
    private function presetModifiers(EloquentCollection $combos): Collection
    {
        $ids = [];
        foreach ($combos as $combo) {
            foreach ($combo->slots as $slot) {
                foreach ($slot->options as $option) {
                    foreach ((array) ($option->modifiers ?? []) as $modifier) {
                        $ids[] = (int) ($modifier['modifier_id']);
                    }
                }
            }
        }
        if ($ids === []) {
            return collect();
        }

        return Modifier::withTrashed()->with(['group' => fn ($query) => $query->withTrashed()])->whereIn('id', array_values(array_unique($ids)))->get()->keyBy('id');
    }

    /**
     * @param  Collection<int, Modifier>  $rows
     * @return list<array{id: int, uuid: string, group_id: int, group_name: string|null, name: string, quantity: int, price_delta: string}>
     */
    private function presetSnapshot(ComboSlotOption $option, Collection $rows): array
    {
        $snapshot = [];
        foreach ((array) ($option->modifiers ?? []) as $modifier) {
            $row = $rows->get((int) ($modifier['modifier_id']));
            if (!$row instanceof Modifier) {
                continue;
            }
            $snapshot[] = [
                'id' => (int) $row->id,
                'uuid' => (string) $row->uuid,
                'group_id' => (int) $row->modifier_group_id,
                'group_name' => $row->group?->name,
                'name' => (string) $row->name,
                'quantity' => max(1, (int) ($modifier['quantity'] ?? 1)),
                'price_delta' => Money::of($row->price_delta)->toString(),
            ];
        }

        return $snapshot;
    }
}
