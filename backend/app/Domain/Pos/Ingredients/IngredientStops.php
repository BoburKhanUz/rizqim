<?php

namespace App\Domain\Pos\Ingredients;

use App\Enums\StopListSubject;
use App\Models\Ingredient;
use App\Models\StopListEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * P6-55 / B9: what an ingredient on a branch's stop-list closes ("Tovuq tugadi"). An ingredient entry is an ordinary
 * stop_list_entries row (subject_type = ingredient, `until` and expiry as for a product); the availability pipeline
 * (MenuAvailability for the catalogue and the till, OrderLineBuilder::stopList for checkout) turns it into the dishes
 * critically linked to it, in every brand and on every channel at once. Reopening the ingredient (the entry removed or
 * expired) reopens them; a dish stopped by its own entry stays stopped (that entry is separate). A non-critical link
 * only marks the dish (`limited`: still sold, "tovuqsiz").
 */
final class IngredientStops
{
    /**
     * The dishes the branch's active ingredient entries close and mark.
     *
     * @param  Collection<int, StopListEntry>  $entries  the branch's active entries (any type; others are ignored)
     * @return array{closed: array<int, array{reason: string, ingredient_id: int}>, limited: array<int, list<int>>, ingredients: list<array{id: int, ingredient_id: int, name: string, reason: string|null, until: string|null}>}
     */
    public static function resolve(Collection $entries): array
    {
        $ingredientEntries = $entries->filter(fn (StopListEntry $entry) => $entry->subject_type === StopListSubject::INGREDIENT)->values();
        if ($ingredientEntries->isEmpty()) {
            return ['closed' => [], 'limited' => [], 'ingredients' => []];
        }

        $ids = $ingredientEntries->pluck('subject_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $names = Ingredient::query()->whereIn('id', $ids)->pluck('name', 'id');
        $links = DB::table('product_ingredients')
            ->join('products', 'products.id', '=', 'product_ingredients.product_id')
            ->whereNull('products.deleted_at')
            ->whereIn('product_ingredients.ingredient_id', $ids)
            ->orderBy('product_ingredients.id')
            ->get(['product_ingredients.product_id', 'product_ingredients.ingredient_id', 'product_ingredients.is_critical']);

        $byIngredient = [];
        $list = [];
        foreach ($ingredientEntries as $entry) {
            $name = (string) ($names[(int) $entry->subject_id] ?? '');
            $byIngredient[(int) $entry->subject_id] = $entry->reason !== null && $entry->reason !== '' ? $entry->reason : self::reasonFor($name);
            $list[] = [
                'id' => (int) $entry->id,
                'ingredient_id' => (int) $entry->subject_id,
                'name' => $name,
                'reason' => $entry->reason,
                'until' => $entry->until?->toJSON(),
            ];
        }

        $closed = [];
        $limited = [];
        foreach ($links as $link) {
            $productId = (int) $link->product_id;
            $ingredientId = (int) $link->ingredient_id;
            if ((bool) $link->is_critical) {
                $closed[$productId] ??= ['reason' => $byIngredient[$ingredientId], 'ingredient_id' => $ingredientId];
            } else {
                $limited[$productId][] = $ingredientId;
            }
        }
        foreach (array_keys($closed) as $productId) {
            unset($limited[$productId]);
        }

        return ['closed' => $closed, 'limited' => $limited, 'ingredients' => $list];
    }

    /**
     * The dishes critically linked to an ingredient (for the broadcast and the owner's answer).
     *
     * @return list<int>
     */
    public static function closedProductIds(int $ingredientId): array
    {
        return DB::table('product_ingredients')
            ->join('products', 'products.id', '=', 'product_ingredients.product_id')
            ->whereNull('products.deleted_at')
            ->where('product_ingredients.ingredient_id', $ingredientId)
            ->where('product_ingredients.is_critical', true)
            ->orderBy('product_ingredients.product_id')
            ->pluck('product_ingredients.product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * The reason a dish shows while its ingredient is stopped without a reason of its own: "Tovuq tugadi".
     */
    public static function reasonFor(string $name): string
    {
        return trim($name) !== '' ? trim($name) . ' tugadi' : 'Ingredient tugadi';
    }
}
