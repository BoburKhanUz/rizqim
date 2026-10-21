<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Http\Requests\Menu\ModifierGroupRequest;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\ProductVariant;
use App\Support\Money;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Group('Owner: menu modifiers', "Modifikator guruhlari (sous, qo'shimcha, hajm) va ularni taomlarga ulash (P1-21).")]
class ModifierGroupController extends MenuStaffController
{
    /**
     * List the store's modifier groups
     *
     * Every group with its modifiers (inactive ones included) and the ids of the products that offer it.
     */
    public function index(Request $request): JsonResponse
    {
        $groups = ModifierGroup::where('store_id', $this->storeId($request))
            ->with(['modifiers', 'products:id'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ModifierGroup $group) => $this->present($group));

        return $this->success($groups);
    }

    /**
     * Create a modifier group with its modifiers
     *
     * `max_select` null means any number; `is_required` raises the minimum to 1. `price_delta` is added to the item's
     * price (per piece; once per line for items sold by weight). `product_variant_id` optionally links a modifier to a
     * variant of the store (for stock later).
     */
    public function store(ModifierGroupRequest $request): JsonResponse
    {
        $storeId = $this->storeId($request);
        $data = $this->validated($request->validated(), $storeId);

        $group = DB::transaction(function () use ($storeId, $data) {
            $group = ModifierGroup::create(['store_id' => $storeId] + $this->groupFields($data));
            $this->syncModifiers($group, $data['modifiers'] ?? []);

            return $group;
        });

        return $this->success($this->present($group->fresh(['modifiers', 'products:id'])), "Modifikator guruhi qo'shildi.", 201);
    }

    /**
     * Update a modifier group
     *
     * `modifiers` replaces the list: an entry with `id` updates that modifier, one without creates it, and a modifier
     * left out is deleted (order history keeps its snapshot).
     */
    public function update(ModifierGroupRequest $request, int $id): JsonResponse
    {
        $storeId = $this->storeId($request);
        $group = ModifierGroup::where('store_id', $storeId)->find($id);
        if (!$group) {
            return $this->error('Modifikator guruhi topilmadi.', [], 404);
        }

        $data = $this->validated($request->validated(), $storeId);

        DB::transaction(function () use ($group, $data) {
            $group->update($this->groupFields($data));
            if (array_key_exists('modifiers', $data)) {
                $this->syncModifiers($group, $data['modifiers'] ?? []);
            }
        });

        return $this->success($this->present($group->fresh(['modifiers', 'products:id'])), 'Modifikator guruhi yangilandi.');
    }

    /**
     * Delete a modifier group
     *
     * Products stop offering it; past orders keep their snapshot.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $group = ModifierGroup::where('store_id', $this->storeId($request))->find($id);
        if (!$group) {
            return $this->error('Modifikator guruhi topilmadi.', [], 404);
        }

        DB::transaction(function () use ($group) {
            $group->products()->detach();
            $group->modifiers()->delete();
            $group->delete();
        });

        return $this->success(null, "Modifikator guruhi o'chirildi.");
    }

    /**
     * Set the modifier groups a product offers
     *
     * Replaces the product's groups. Per product, `min_select`, `max_select` and `is_required` override the group's own
     * rules (null: the group's; P2-30: a rule left out of a row keeps the product's saved one); `sort_order` orders
     * them (left out: the list order). GET /owner/products returns them as `modifier_group_links`.
     */
    public function syncProduct(Request $request, int $id): JsonResponse
    {
        $storeId = $this->storeId($request);
        $product = $this->product($request, $id);

        $data = $request->validate([
            'groups' => 'present|array|max:30',
            'groups.*.modifier_group_id' => 'required|integer|distinct',
            'groups.*.min_select' => 'nullable|integer|min:0|max:50',
            'groups.*.max_select' => 'nullable|integer|min:1|max:50',
            'groups.*.is_required' => 'nullable|boolean',
            'groups.*.sort_order' => 'nullable|integer',
        ]);

        $ids = array_map(fn (array $row) => (int) $row['modifier_group_id'], $data['groups']);
        $owned = ModifierGroup::where('store_id', $storeId)->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($owned) !== count($ids)) {
            throw ValidationException::withMessages(['groups' => "Modifikator guruhi bu do'konga tegishli emas."]);
        }

        // P2-30: a rule left out of a row keeps what the product has for that group (an attach list saved without the
        // rules no longer wipes them); null resets it to the group's own
        $saved = DB::table('product_modifier_groups')->where('product_id', $product->id)->get()->keyBy('modifier_group_id');

        $sync = [];
        foreach ($data['groups'] as $position => $row) {
            $groupId = (int) $row['modifier_group_id'];
            $current = $saved->get($groupId);
            $rules = [];
            foreach (['min_select', 'max_select', 'is_required'] as $rule) {
                $rules[$rule] = array_key_exists($rule, $row) ? $row[$rule] : ($current !== null ? $current->{$rule} : null);
            }
            if ($rules['min_select'] !== null && $rules['max_select'] !== null && (int) $rules['max_select'] < (int) $rules['min_select']) {
                throw ValidationException::withMessages(["groups.{$position}.max_select" => "Eng ko'p tanlov eng kamidan kichik bo'lmasligi kerak."]);
            }
            $sync[$groupId] = [
                'min_select' => $rules['min_select'] !== null ? (int) $rules['min_select'] : null,
                'max_select' => $rules['max_select'] !== null ? (int) $rules['max_select'] : null,
                'is_required' => $rules['is_required'] !== null ? (bool) $rules['is_required'] : null,
                'sort_order' => $row['sort_order'] ?? $position,
            ];
        }

        $product->modifierGroups()->sync($sync);

        return $this->success([
            'product_id' => (int) $product->id,
            'modifier_groups' => $product->modifierGroups()->with('modifiers')->get()->map(fn (ModifierGroup $group) => $this->present($group, true))->values(),
        ], 'Taom modifikatorlari saqlandi.');
    }

    /**
     * The checks beyond the request's shape (ModifierGroupRequest): the bounds agree, linked variants are the store's.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validated(array $data, int $storeId): array
    {
        // A modifier's names in other languages (its own rule would hide their object shape from the OpenAPI document)
        foreach ($data['modifiers'] ?? [] as $position => $modifier) {
            foreach ((array) ($modifier['name_i18n'] ?? []) as $locale => $name) {
                if ($name !== null && (!is_string($name) || mb_strlen($name) > 255)) {
                    throw ValidationException::withMessages(["modifiers.{$position}.name_i18n.{$locale}" => "Nomi matn bo'lishi kerak (ko'pi bilan 255 belgi)."]);
                }
            }
        }

        if (isset($data['max_select']) && (int) $data['max_select'] < (int) ($data['min_select'] ?? 0)) {
            throw ValidationException::withMessages(['max_select' => "Eng ko'p tanlov eng kamidan kichik bo'lmasligi kerak."]);
        }

        $variantIds = collect($data['modifiers'] ?? [])->pluck('product_variant_id')->filter()->map(fn ($id) => (int) $id)->unique();
        if ($variantIds->isNotEmpty()) {
            $owned = ProductVariant::whereIn('id', $variantIds)->whereHas('product', fn ($query) => $query->where('store_id', $storeId))->count();
            if ($owned !== $variantIds->count()) {
                throw ValidationException::withMessages(['modifiers' => "Bog'langan variant bu do'konga tegishli emas."]);
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function groupFields(array $data): array
    {
        return [
            'name' => $data['name'],
            'name_i18n' => $this->i18n($data['name_i18n'] ?? null),
            'min_select' => (int) ($data['min_select'] ?? 0),
            'max_select' => isset($data['max_select']) ? (int) $data['max_select'] : null,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncModifiers(ModifierGroup $group, array $rows): void
    {
        $kept = [];

        foreach ($rows as $position => $row) {
            $fields = [
                'name' => $row['name'],
                'name_i18n' => $this->i18n($row['name_i18n'] ?? null),
                'price_delta' => Money::of($row['price_delta'] ?? 0)->toString(),
                'max_quantity' => (int) ($row['max_quantity'] ?? 1),
                'product_variant_id' => isset($row['product_variant_id']) ? (int) $row['product_variant_id'] : null,
                'sort_order' => (int) ($row['sort_order'] ?? $position),
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];

            $modifier = isset($row['id']) ? Modifier::where('modifier_group_id', $group->id)->find((int) $row['id']) : null;
            if (isset($row['id']) && !$modifier) {
                throw ValidationException::withMessages(["modifiers.{$position}.id" => 'Modifikator bu guruhga tegishli emas.']);
            }

            $modifier ? $modifier->update($fields) : $modifier = $group->modifiers()->create($fields);
            $kept[] = $modifier->id;
        }

        Modifier::where('modifier_group_id', $group->id)->whereNotIn('id', $kept)->delete();
    }

    /**
     * @param  array<string, mixed>|null  $names
     * @return array<string, string>|null
     */
    private function i18n(?array $names): ?array
    {
        $names = array_filter((array) $names, fn ($name) => is_string($name) && trim($name) !== '');

        return $names === [] ? null : array_map('trim', $names);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ModifierGroup $group, bool $withPivot = false): array
    {
        $data = [
            'id' => (int) $group->id,
            'uuid' => $group->uuid,
            'name' => $group->name,
            'name_i18n' => $group->name_i18n,
            'min_select' => $group->min_select,
            'max_select' => $group->max_select,
            'is_required' => $group->is_required,
            'sort_order' => (int) $group->sort_order,
            'is_active' => $group->is_active,
            'modifiers' => $group->modifiers->map(fn (Modifier $modifier) => [
                'id' => (int) $modifier->id,
                'uuid' => $modifier->uuid,
                'name' => $modifier->name,
                'name_i18n' => $modifier->name_i18n,
                'price_delta' => $modifier->price_delta,
                'max_quantity' => $modifier->max_quantity,
                'product_variant_id' => $modifier->product_variant_id !== null ? (int) $modifier->product_variant_id : null,
                'sort_order' => (int) $modifier->sort_order,
                'is_active' => $modifier->is_active,
            ])->values(),
        ];

        if ($group->relationLoaded('products')) {
            $data['product_ids'] = $group->products->pluck('id')->map(fn ($id) => (int) $id)->values();
        }

        $pivot = $withPivot && $group->relationLoaded('pivot') ? $group->getRelation('pivot') : null;
        if ($pivot instanceof \Illuminate\Database\Eloquent\Model) {
            $data['product_rules'] = [
                'min_select' => $pivot->getAttribute('min_select') !== null ? (int) $pivot->getAttribute('min_select') : null,
                'max_select' => $pivot->getAttribute('max_select') !== null ? (int) $pivot->getAttribute('max_select') : null,
                'is_required' => $pivot->getAttribute('is_required') !== null ? (bool) $pivot->getAttribute('is_required') : null,
                'sort_order' => (int) $pivot->getAttribute('sort_order'),
            ];
        }

        return $data;
    }
}
