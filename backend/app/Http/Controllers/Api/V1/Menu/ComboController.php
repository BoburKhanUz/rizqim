<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Domain\Fiscal\ComboFiscalMode;
use App\Domain\Fiscal\FiscalSettings;
use App\Domain\Pos\Billing\Allocation;
use App\Domain\Pos\Menu\Combos;
use App\Enums\OrderChannel;
use App\Models\Combo;
use App\Models\ComboSlot;
use App\Models\ComboSlotOption;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\Catalog\ChannelMenu;
use App\Support\Money;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Owner: combos', "Kombo konstruktori (fast food, P6-31): slotlar, tanlovlar, narx farqi, standart tanlov va chek ko'rinishi.")]
class ComboController extends MenuStaffController
{
    private const OPTION = 'array{id: int, uuid: string, variant_id: int, variant_uuid: string, product_id: int|null, name: string, variant_name: string|null, quantity: int, price_delta: string, modifiers: list<array{id: int, uuid: string, group_id: int, group_name: string|null, name: string, quantity: int, price_delta: string}>, is_default: bool, label: string|null, receipt_label: string}';

    private const COMBO = 'array{id: int, uuid: string, product_id: int, product_name: string|null, prices: list<string>, is_active: bool, slots: list<array{id: int, uuid: string, name: string, min_picks: int, max_picks: int, is_required: bool, allow_modifiers: bool, options: list<' . self::OPTION . '>}>}';

    /**
     * Combo list
     *
     * P6-31, module `combo`: the store's combos. A combo is a normal menu product (its price, MXIK code, channel prices
     * and daily menu are set in the menu like any dish's) whose buyer picks its parts in ordered slots. menu.view or
     * menu.manage.
     */
    #[\Dedoc\Scramble\Attributes\Response(200, type: 'array{success: true, message: string, data: array{combos: list<' . self::COMBO . '>, fiscal_mode: ' . ComboFiscalMode::class . '}}')]
    public function index(Request $request, Combos $combos): JsonResponse
    {
        $storeId = $this->storeId($request);
        $rows = Combo::where('store_id', $storeId)->with(['product' => fn ($query) => $query->withTrashed()->with('variants'), ...Combos::RELATIONS])->orderBy('id')->get();

        return $this->success([
            'combos' => $rows->map(fn (Combo $combo) => $this->present($combos, $combo))->values(),
            'fiscal_mode' => FiscalSettings::of(Store::findOrFail($storeId))->comboFiscalMode(),
        ]);
    }

    /**
     * Make a product a combo
     *
     * `product_id`: a product of the store sold by the piece (its price is the combo's base price); `slots` in order,
     * each `name` ("Asosiy", "Ichimlik", "Sous"), `min_picks` (0: optional; default 1), `max_picks` (1–10, default
     * max(1, min)), `allow_modifiers` (the buyer may change the part's own modifiers; their added deltas are charged)
     * and 1–20 `options`: `variant_id` (a dish sold by the piece), `quantity` (pieces, default 1), `price_delta` (0 or
     * more, added to the combo's price), `modifiers` (a preset: [{modifier_id, quantity?}] of the dish's own modifiers,
     * included in the option's price), `is_default` (one per slot: taken when the slot is left out) and `label` (the
     * receipt text, e.g. "Ichimlik — 0,4 l Pepsi"; default "<slot> — <dish> (<variant>), <modifiers>"). Up to 8 slots.
     * A part cannot be the combo itself, another combo or a kompleks; a kompleks product cannot be a combo. Saving a
     * product that already is a combo replaces its slots. Sold lines keep the parts they had (`combo_parts`). Audited
     * (`menu.combo_saved`).
     */
    #[\Dedoc\Scramble\Attributes\Response(201, type: 'array{success: true, message: string, data: ' . self::COMBO . '}')]
    public function store(Request $request, Combos $combos): JsonResponse
    {
        $data = $this->validated($request, true);
        $product = $this->product($request, (int) $data['product_id']);

        $combo = $combos->save($this->storeId($request), $product, $data['slots'], (bool) ($data['is_active'] ?? true), $request->user());

        return $this->success($this->present($combos, $combo->load('product.variants')), 'Kombo saqlandi.', 201);
    }

    /**
     * Change a combo
     *
     * Replaces the slots (sent in full) and/or switches the combo off (`is_active` false: it is sold as a plain product).
     */
    #[\Dedoc\Scramble\Attributes\Response(200, type: 'array{success: true, message: string, data: ' . self::COMBO . '}')]
    public function update(Request $request, int $id, Combos $combos): JsonResponse
    {
        $combo = $this->combo($request, $id);
        $data = $this->validated($request, false);
        $slots = $data['slots'] ?? $this->currentSlots($combo);

        $combo = $combos->save($this->storeId($request), $combo->product()->firstOrFail(), $slots, (bool) ($data['is_active'] ?? $combo->is_active), $request->user());

        return $this->success($this->present($combos, $combo->load('product.variants')), 'Kombo saqlandi.');
    }

    /**
     * Delete a combo
     *
     * The product stays on the menu as a plain dish; lines already sold keep their parts. Audited (`menu.combo_deleted`).
     */
    public function destroy(Request $request, int $id, Combos $combos): JsonResponse
    {
        $combos->delete($this->combo($request, $id), $request->user());

        return $this->success(null, "Kombo o'chirildi.");
    }

    /**
     * Receipt preview of a combo
     *
     * What the guest's receipt and the fiscal receipt show for a pick (`picks` as on an order line: [{option_id,
     * quantity?, modifiers?}], left out: every slot's default) on a channel (`channel`, default `pos_takeaway`): the
     * ONE combo line (name, quantity 1, price = base + deltas), its parts without prices, and the fiscal lines in both
     * modes (`single`: the combo with its own MXIK; `split`: one line per part, the shares adding up to the price).
     * Availability is not asked (it is a preview). 422 like an order line's pick (`combo_slot_required`...).
     */
    #[\Dedoc\Scramble\Attributes\Response(200, type: 'array{success: true, message: string, data: array{name: string, quantity: int, base_price: string, delta: string, price: string, parts: list<array{slot_name: string, name: string, variant_name: string|null, quantity: int, label: string}>, fiscal_mode: ' . ComboFiscalMode::class . ', fiscal: array{single: list<array{name: string, mxik_code: string|null, quantity: int, amount: string}>, split: list<array{name: string, mxik_code: string|null, quantity: int, amount: string}>}}}')]
    public function preview(Request $request, int $id, Combos $combos): JsonResponse
    {
        $combo = $this->combo($request, $id);
        $data = $request->validate([
            'channel' => ['nullable', 'string', Rule::enum(OrderChannel::class)],
            'picks' => 'nullable|array|max:30',
            'picks.*.option_id' => 'required|uuid',
            'picks.*.quantity' => 'nullable|integer|min:1|max:10',
            'picks.*.modifiers' => 'nullable|array|max:20',
        ]);

        /** @var Product $product */
        $product = $combo->product()->with('variants')->firstOrFail();
        /** @var ProductVariant|null $variant */
        $variant = $product->variants->first();
        if ($variant === null) {
            throw new HttpResponseException($this->error("Kombo mahsulotida narx (variant) yo'q.", [], 422));
        }
        $variant->setRelation('product', $product);
        $channel = OrderChannel::tryFrom((string) ($data['channel'] ?? '')) ?? OrderChannel::POS_TAKEAWAY;
        $menu = ChannelMenu::for((int) $combo->store_id, $channel);
        $resolved = $combos->resolve($variant, (array) ($data['picks'] ?? []), ['products' => [], 'variants' => [], 'modifiers' => []], $menu, 0, false) ?? ['parts' => [], 'delta' => Money::zero()];

        $base = $menu->priceOf($variant);
        $price = $base->plus($resolved['delta']);
        $weights = array_map(fn (array $part) => Money::of($part['list_price'])->toTiyin(), $resolved['parts']);
        $shares = $resolved['parts'] === [] ? [] : Allocation::proportional($price->toTiyin(), $weights);
        $mxik = ProductVariant::withTrashed()->with(['product' => fn ($query) => $query->withTrashed()])
            ->whereIn('id', array_column($resolved['parts'], 'variant_id'))->get()->keyBy('id');

        return $this->success([
            'name' => (string) $product->name,
            'quantity' => 1,
            'base_price' => $base->toString(),
            'delta' => $resolved['delta']->toString(),
            'price' => $price->toString(),
            'parts' => Combos::receiptParts($resolved['parts']),
            'fiscal_mode' => FiscalSettings::of(Store::findOrFail($combo->store_id))->comboFiscalMode(),
            'fiscal' => [
                'single' => [['name' => (string) $product->name, 'mxik_code' => $product->mxik_code, 'quantity' => 1, 'amount' => $price->toString()]],
                'split' => array_map(fn (array $part, int $k) => [
                    'name' => trim($part['name'] . (filled($part['variant_name']) ? " ({$part['variant_name']})" : '')),
                    'mxik_code' => $mxik->get($part['variant_id'])?->product?->mxik_code,
                    'quantity' => (int) $part['quantity'],
                    'amount' => Money::fromTiyin($shares[$k])->toString(),
                ], $resolved['parts'], array_keys($resolved['parts'])),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'product_id' => $creating ? 'required|integer' : 'prohibited',
            'is_active' => 'nullable|boolean',
            'slots' => ($creating ? 'required' : 'sometimes') . '|array|min:1|max:' . Combos::MAX_SLOTS,
            'slots.*.name' => 'required|string|max:60',
            'slots.*.min_picks' => 'nullable|integer|min:0|max:' . Combos::MAX_PICKS,
            'slots.*.max_picks' => 'nullable|integer|min:1|max:' . Combos::MAX_PICKS,
            'slots.*.allow_modifiers' => 'nullable|boolean',
            'slots.*.options' => 'required|array|min:1|max:' . Combos::MAX_OPTIONS,
            'slots.*.options.*.variant_id' => 'required|integer',
            'slots.*.options.*.quantity' => 'nullable|integer|min:1|max:20',
            'slots.*.options.*.price_delta' => ['nullable', 'numeric', 'min:0', 'max:100000000', 'regex:/^\d+(\.\d{1,2})?$/'],
            'slots.*.options.*.modifiers' => 'nullable|array|max:20',
            'slots.*.options.*.modifiers.*.modifier_id' => 'required|integer',
            'slots.*.options.*.modifiers.*.quantity' => 'nullable|integer|min:1|max:20',
            'slots.*.options.*.is_default' => 'nullable|boolean',
            'slots.*.options.*.label' => 'nullable|string|max:80',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function currentSlots(Combo $combo): array
    {
        $combo->loadMissing(Combos::RELATIONS);

        return $combo->slots->map(fn (ComboSlot $slot) => [
            'name' => $slot->name,
            'min_picks' => (int) $slot->min_picks,
            'max_picks' => (int) $slot->max_picks,
            'allow_modifiers' => (bool) $slot->allow_modifiers,
            'options' => $slot->options->map(fn (ComboSlotOption $option) => [
                'variant_id' => (int) $option->product_variant_id,
                'quantity' => (int) $option->quantity,
                'price_delta' => Money::of($option->price_delta)->toString(),
                'modifiers' => $option->modifiers ?? [],
                'is_default' => (bool) $option->is_default,
                'label' => $option->label,
            ])->values()->all(),
        ])->values()->all();
    }

    private function combo(Request $request, int $id): Combo
    {
        $combo = Combo::where('store_id', $this->storeId($request))->find($id);
        if (!$combo) {
            throw new HttpResponseException($this->error('Kombo topilmadi.', [], 404));
        }

        return $combo;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Combos $combos, Combo $combo): array
    {
        $product = $combo->product;

        return [
            'product_name' => $product?->name,
            'prices' => $product?->variants->map(fn ($variant) => Money::of($variant->price)->toString())->values()->all() ?? [],
        ] + $combos->present($combo);
    }
}
