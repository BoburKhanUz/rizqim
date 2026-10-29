<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Domain\Pos\Menu\MealSets;
use App\Enums\Permission;
use App\Models\DailySet;
use App\Models\DailySetItem;
use App\Support\Money;
use App\Support\Quantity;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Owner: daily menu', "Bugungi menyu (stolovaya): kunlik taomlar ro'yxati, kechagisini nusxalash va komplekslar (P6-15).")]
class MealSetController extends MenuStaffController
{
    /**
     * Kompleks list
     *
     * P6-15, module `combo_meal`: the store's complexes. A kompleks is a normal menu product sold at its own price as one
     * line; here are its parts (dishes the kitchen prepares for it). menu.view or menu.manage.
     */
    public function index(Request $request, MealSets $sets): JsonResponse
    {
        $storeId = $this->storeId($request);

        $rows = DailySet::where('store_id', $storeId)
            ->with(['product' => fn ($query) => $query->withTrashed()->with('variants')])
            ->orderBy('id')
            ->get();

        return $this->success([
            'sets' => $rows->map(fn (DailySet $set) => $this->present($sets, $set))->values(),
        ]);
    }

    /**
     * Make a product a kompleks
     *
     * `product_id` is a product of the store (its price is the set's price, set in the menu like any dish's) and `parts`
     * its dishes: `variant_id` (a dish's variant), `quantity` (default 1) and an optional `label` ("Birinchi", "Salat").
     * Up to 12 parts; a part cannot be the kompleks itself or another kompleks. Saving a product that already is a
     * kompleks replaces its parts. The sold line keeps the parts it had (`set_parts`).
     */
    public function store(Request $request, MealSets $sets): JsonResponse
    {
        $data = $this->validated($request, true);
        $product = $this->product($request, (int) $data['product_id']);

        $set = $sets->save($this->storeId($request), $product, $data['parts'], (bool) ($data['is_active'] ?? true), $request->user());

        return $this->success($this->present($sets, $set->load('product.variants')), 'Kompleks saqlandi.', 201);
    }

    /**
     * Change a kompleks
     *
     * Replaces the parts and/or switches the kompleks off (`is_active` false: it is sold as a plain product again).
     */
    public function update(Request $request, int $id, MealSets $sets): JsonResponse
    {
        $set = $this->set($request, $id);
        $data = $this->validated($request, false);

        $parts = $data['parts'] ?? $set->items()->get()->map(fn (DailySetItem $item) => [
            'variant_id' => (int) $item->product_variant_id,
            'quantity' => Quantity::normalize($item->quantity),
            'label' => $item->label,
        ])->all();

        $set = $sets->save($this->storeId($request), $set->product()->firstOrFail(), $parts, (bool) ($data['is_active'] ?? $set->is_active), $request->user());

        return $this->success($this->present($sets, $set->load('product.variants')), 'Kompleks saqlandi.');
    }

    /**
     * Delete a kompleks
     *
     * The product stays on the menu as a plain dish; lines already sold keep their parts.
     */
    public function destroy(Request $request, int $id, MealSets $sets): JsonResponse
    {
        $sets->delete($this->set($request, $id), $request->user());

        return $this->success(null, "Kompleks o'chirildi.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'product_id' => $creating ? 'required|integer' : 'prohibited',
            'is_active' => 'nullable|boolean',
            'parts' => ($creating ? 'required' : 'sometimes') . '|array|min:1|max:' . MealSets::MAX_PARTS,
            'parts.*.variant_id' => 'required|integer',
            'parts.*.quantity' => 'nullable|numeric|gt:0|max:99',
            'parts.*.label' => 'nullable|string|max:60',
        ]);
    }

    private function set(Request $request, int $id): DailySet
    {
        $set = DailySet::where('store_id', $this->storeId($request))->find($id);
        if (!$set) {
            throw new HttpResponseException($this->error('Kompleks topilmadi.', [], 404));
        }

        return $set;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(MealSets $sets, DailySet $set): array
    {
        $product = $set->product;
        $prices = $product?->variants->map(fn ($variant) => Money::of($variant->price)->toString())->values()->all() ?? [];

        return [
            'id' => (int) $set->id,
            'uuid' => $set->uuid,
            'product_id' => (int) $set->product_id,
            'product_name' => $product?->name,
            'prices' => $prices,
            'is_active' => (bool) $set->is_active,
            'parts' => $sets->snapshot($set),
        ];
    }
}
