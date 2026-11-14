<?php

namespace App\Domain\Pos\Ingredients;

use App\Enums\StopListSubject;
use App\Models\Branch;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\StopListEntry;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Catalog\StopListService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P6-55 / B9 (module ingredient_stoplist): the store's ingredients, which dishes use them, and the quick stop
 * ("Tovuq tugadi"). Stopping goes through StopListService (an `ingredient` entry: audited, broadcast, `until` and its
 * expiry as for a dish); the menu closes the linked dishes through IngredientStops.
 */
final class IngredientService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, unit?: string|null}  $data
     */
    public function create(int $storeId, array $data): Ingredient
    {
        $name = $this->name($storeId, $data['name']);

        return DB::transaction(function () use ($storeId, $name, $data) {
            $ingredient = Ingredient::create(['store_id' => $storeId, 'name' => $name, 'unit' => $data['unit'] ?? null]);
            $this->audit->record('ingredient.created', $ingredient, [], $ingredient->only(['name', 'unit']), ['ingredient' => $name]);

            return $ingredient;
        });
    }

    /**
     * @param  array<string, mixed>  $data  name, unit
     */
    public function update(Ingredient $ingredient, array $data): Ingredient
    {
        if (isset($data['name'])) {
            $data['name'] = $this->name((int) $ingredient->store_id, (string) $data['name'], (int) $ingredient->id);
        }

        return DB::transaction(function () use ($ingredient, $data) {
            $before = $ingredient->only(['name', 'unit']);
            $ingredient->fill(array_intersect_key($data, array_flip(['name', 'unit'])))->save();
            $after = $ingredient->only(['name', 'unit']);
            if ($before !== $after) {
                $this->audit->record('ingredient.updated', $ingredient, $before, $after, ['ingredient' => $ingredient->name]);
            }

            return $ingredient;
        });
    }

    /**
     * Deletes the ingredient, its links and its stop-list entries (the dishes reopen where it was stopped).
     */
    public function delete(Ingredient $ingredient, ?User $actor): void
    {
        $stopList = app(StopListService::class);
        DB::transaction(function () use ($ingredient, $actor, $stopList) {
            foreach ($this->entries($ingredient) as $entry) {
                $stopList->remove($entry, $actor);
            }
            $this->audit->record('ingredient.deleted', $ingredient, $ingredient->only(['name', 'unit']), [], ['ingredient' => $ingredient->name, 'links' => $ingredient->products()->count()]);
            $ingredient->products()->detach();
            $ingredient->delete();
        });
    }

    /**
     * Replaces the dishes that use the ingredient: [{product_id, is_critical}].
     *
     * @param  list<array{product_id: int, is_critical?: bool|null}>  $links
     */
    public function setLinks(Ingredient $ingredient, array $links): Ingredient
    {
        $sync = [];
        foreach ($links as $link) {
            $sync[(int) $link['product_id']] = ['is_critical' => (bool) ($link['is_critical'] ?? true)];
        }
        $this->assertProducts((int) $ingredient->store_id, array_keys($sync));

        return DB::transaction(function () use ($ingredient, $sync) {
            $before = $this->links($ingredient);
            $ingredient->products()->sync($sync);
            $after = $this->links($ingredient);
            if ($before !== $after) {
                $this->audit->record('ingredient.links_changed', $ingredient, ['links' => $before], ['links' => $after], ['ingredient' => $ingredient->name]);
                $this->broadcastWhereStopped($ingredient);
            }

            return $ingredient;
        });
    }

    /**
     * Links every dish of the categories (and their subcategories) to the ingredient, keeping the other links. Returns
     * how many were added.
     *
     * @param  list<int>  $categoryIds
     */
    public function linkCategories(Ingredient $ingredient, array $categoryIds, bool $critical): int
    {
        $children = \App\Models\Category::whereIn('parent_id', $categoryIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $productIds = Product::where('store_id', $ingredient->store_id)->whereIn('category_id', array_merge($categoryIds, $children))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $existing = $ingredient->products()->pluck('products.id')->map(fn ($id) => (int) $id)->all();
        $new = array_values(array_diff($productIds, $existing));
        if ($new === []) {
            return 0;
        }

        DB::transaction(function () use ($ingredient, $new, $critical, $categoryIds) {
            $ingredient->products()->attach(array_fill_keys($new, ['is_critical' => $critical]));
            $this->audit->record('ingredient.links_changed', $ingredient, [], ['added' => $new, 'is_critical' => $critical], ['ingredient' => $ingredient->name, 'category_ids' => $categoryIds]);
            $this->broadcastWhereStopped($ingredient);
        });

        return count($new);
    }

    /**
     * "Tovuq tugadi": stops the ingredient on the branch (or updates its reason and time).
     */
    public function stop(Branch $branch, Ingredient $ingredient, ?string $reason, ?CarbonInterface $until, ?User $actor): StopListEntry
    {
        return app(StopListService::class)->add($branch, StopListSubject::INGREDIENT, $ingredient, $reason, $until, $actor);
    }

    /**
     * Reopens the ingredient on the branch; false when it was not stopped there.
     */
    public function reopen(Branch $branch, Ingredient $ingredient, ?User $actor): bool
    {
        $entry = StopListEntry::where('branch_id', $branch->id)
            ->where('subject_type', StopListSubject::INGREDIENT->value)
            ->where('subject_id', $ingredient->id)
            ->first();
        if ($entry === null) {
            return false;
        }

        app(StopListService::class)->remove($entry, $actor);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Ingredient $ingredient, ?int $branchId = null): array
    {
        $entries = $this->entries($ingredient, true);

        return [
            'id' => (int) $ingredient->id,
            'uuid' => (string) $ingredient->uuid,
            'name' => (string) $ingredient->name,
            'unit' => $ingredient->unit,
            'links' => $this->links($ingredient),
            // Where it is stopped now: {branch_id, entry_id, reason, until}
            'stopped' => $entries->map(fn (StopListEntry $entry) => [
                'branch_id' => (int) $entry->branch_id,
                'entry_id' => (int) $entry->id,
                'reason' => $entry->reason,
                'until' => $entry->until?->toJSON(),
            ])->values()->all(),
            'stopped_here' => $branchId !== null && $entries->contains(fn (StopListEntry $entry) => (int) $entry->branch_id === $branchId),
        ];
    }

    /**
     * @return list<array{product_id: int, name: string, is_critical: bool}>
     */
    public function links(Ingredient $ingredient): array
    {
        return $ingredient->products()->orderBy('products.id')->get(['products.id', 'products.name'])
            ->map(fn (Product $product) => [
                'product_id' => (int) $product->id,
                'name' => (string) $product->name,
                'is_critical' => (bool) $product->getRelation('pivot')->getAttribute('is_critical'),
            ])->values()->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, StopListEntry>
     */
    private function entries(Ingredient $ingredient, bool $activeOnly = false): \Illuminate\Support\Collection
    {
        return StopListEntry::where('store_id', $ingredient->store_id)
            ->where('subject_type', StopListSubject::INGREDIENT->value)
            ->where('subject_id', $ingredient->id)
            ->when($activeOnly, fn ($query) => $query->active())
            ->orderBy('branch_id')
            ->get();
    }

    /**
     * Links changed while the ingredient is stopped somewhere: those branches' menus changed.
     */
    private function broadcastWhereStopped(Ingredient $ingredient): void
    {
        foreach ($this->entries($ingredient, true)->pluck('branch_id')->unique() as $branchId) {
            event(new \App\Events\Broadcast\MenuAvailabilityChangedBroadcast((int) $ingredient->store_id, (int) $branchId, [], 'stop_list'));
        }
    }

    /**
     * @param  list<int>  $productIds
     */
    private function assertProducts(int $storeId, array $productIds): void
    {
        if ($productIds !== [] && Product::where('store_id', $storeId)->whereIn('id', $productIds)->count() !== count($productIds)) {
            throw ValidationException::withMessages(['links' => 'Mahsulot topilmadi.']);
        }
    }

    private function name(int $storeId, string $name, ?int $except = null): string
    {
        $name = trim($name);
        $taken = Ingredient::where('store_id', $storeId)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['name' => 'Bunday ingredient bor.']);
        }

        return $name;
    }
}
