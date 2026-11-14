<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Ingredients\IngredientService;
use App\Domain\Pos\Ingredients\IngredientStops;
use App\Enums\Permission;
use App\Models\Ingredient;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P6-55 / B9 (module ingredient_stoplist, docs/API.md "Ingredient stop-list"): the store's ingredients, the dishes
 * that use them, and the quick stop "Tovuq tugadi": stopping an ingredient on a branch closes every critically linked
 * dish there at once, in every brand and on every channel (an ordinary stop-list entry of type `ingredient`, also
 * listed by GET /owner/stop-list; `until` ends it by itself); reopening restores them, a dish stopped by its own entry
 * stays stopped. Reading: menu.view, menu.manage or stoplist.manage; ingredients and links: menu.manage; stop and
 * reopen: stoplist.manage on that branch. Another store's ingredient: 404. Every change is audited.
 */
#[Group('Owner: ingredients', "Ingredient bo'yicha stop-list: \"Tovuq tugadi\" — bog'langan hamma taom bir vaqtda yopiladi (P6-55).")]
class IngredientController extends ManagementController
{
    /**
     * The store's ingredients
     *
     * Each: `id`, `uuid`, `name`, `unit`, `links` ({product_id, name, is_critical}), `stopped` (where it is stopped
     * now: {branch_id, entry_id, reason, until}) and `stopped_here` (on `branch_id`, when given).
     */
    #[QueryParameter('branch_id', description: 'The branch `stopped_here` is about (a branch-bound caller: their own).', type: 'int', required: false)]
    public function index(Request $request): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer|min:1']);
        $branch = $this->branchFilter($request, $this->permissions->allows($request->user(), Permission::STOPLIST_MANAGE) ? Permission::STOPLIST_MANAGE : Permission::MENU_VIEW);
        $service = app(IngredientService::class);
        $ingredients = Ingredient::where('store_id', $this->storeId($request))->orderBy('name')->get();

        return $this->success([
            'branch_id' => $branch?->id,
            'ingredients' => $ingredients->map(fn (Ingredient $ingredient) => $service->present($ingredient, $branch?->id))->values()->all(),
        ]);
    }

    /**
     * Create an ingredient
     *
     * `name` unique in the store (case-insensitive); `unit` optional (kg, dona…), shown only.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'unit' => 'nullable|string|max:16',
        ]);

        $service = app(IngredientService::class);

        return $this->success($service->present($service->create($this->storeId($request), $data)), "Ingredient qo'shildi.", 201);
    }

    /**
     * Edit an ingredient
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $ingredient = $this->ingredient($request, $id);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'unit' => 'sometimes|nullable|string|max:16',
        ]);

        $service = app(IngredientService::class);

        return $this->success($service->present($service->update($ingredient, $data)), 'Ingredient saqlandi.');
    }

    /**
     * Delete an ingredient
     *
     * Its links go, and where it was stopped the dishes reopen.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        app(IngredientService::class)->delete($this->ingredient($request, $id), $request->user());

        return $this->success(null, "Ingredient o'chirildi.");
    }

    /**
     * Replace the dishes that use an ingredient
     *
     * `links`: [{product_id, is_critical}] (up to 1 000; `is_critical` default true: stopping the ingredient closes
     * the dish; false: the dish stays on sale, marked in `limited_products` of the availability).
     */
    public function links(Request $request, int $id): JsonResponse
    {
        $ingredient = $this->ingredient($request, $id);
        $data = $request->validate([
            'links' => 'present|array|max:1000',
            'links.*.product_id' => 'required|integer|min:1|distinct',
            'links.*.is_critical' => 'nullable|boolean',
        ]);

        $service = app(IngredientService::class);

        return $this->success($service->present($service->setLinks($ingredient, $data['links'])), "Bog'lanishlar saqlandi.");
    }

    /**
     * Link whole categories to an ingredient
     *
     * Every dish of the categories (and their subcategories) is linked, keeping the other links; `added`: how many.
     */
    public function linkCategories(Request $request, int $id): JsonResponse
    {
        $ingredient = $this->ingredient($request, $id);
        $data = $request->validate([
            'category_ids' => 'required|array|min:1|max:100',
            'category_ids.*' => 'integer|min:1|distinct',
            'is_critical' => 'nullable|boolean',
        ]);

        $service = app(IngredientService::class);
        $added = $service->linkCategories($ingredient, array_map('intval', $data['category_ids']), (bool) ($data['is_critical'] ?? true));

        return $this->success(['added' => $added] + $service->present($ingredient), "Bog'lanishlar qo'shildi.");
    }

    /**
     * Stop an ingredient on a branch ("Tovuq tugadi")
     *
     * Closes every critically linked dish there at once (all brands, all channels); `until` (optional, a future ISO
     * time) reopens by itself; stopping it again updates the reason and time. `closed_product_ids`: the dishes it
     * closes. Broadcasts menu.availability_changed. Needs the ingredient_stoplist module on the branch. A till sends an
     * Idempotency-Key (optional): a retry with the same key gets the first answer.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Optional: a new UUID per action, the same one when retrying it.', type: 'string', required: false)]
    public function stop(Request $request, int $id): JsonResponse
    {
        $ingredient = $this->ingredient($request, $id);
        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1',
            'reason' => 'nullable|string|max:255',
            'until' => 'nullable|date',
        ]);

        $branch = $this->branch($request, Permission::STOPLIST_MANAGE);
        $entry = app(IngredientService::class)->stop($branch, $ingredient, $data['reason'] ?? null, isset($data['until']) ? Carbon::parse($data['until']) : null, $request->user());

        return $this->success([
            'entry_id' => (int) $entry->id,
            'branch_id' => (int) $branch->id,
            'ingredient_id' => (int) $ingredient->id,
            'reason' => $entry->reason,
            'until' => $entry->until?->toJSON(),
            'closed_product_ids' => IngredientStops::closedProductIds((int) $ingredient->id),
        ], 'Ingredient stop-listga qo\'shildi.', 201);
    }

    /**
     * Reopen an ingredient on a branch
     *
     * Its dishes come back (a dish stopped by its own entry stays stopped). 404 when it is not stopped there. An
     * Idempotency-Key is optional, as for the stop.
     */
    #[HeaderParameter('Idempotency-Key', description: 'Optional: a new UUID per action, the same one when retrying it.', type: 'string', required: false)]
    #[QueryParameter('branch_id', description: 'The branch (a branch-bound caller: their own).', type: 'int', required: false)]
    public function reopen(Request $request, int $id): JsonResponse
    {
        $ingredient = $this->ingredient($request, $id);
        $request->validate(['branch_id' => 'nullable|integer|min:1']);
        $branch = $this->branch($request, Permission::STOPLIST_MANAGE);

        if (!app(IngredientService::class)->reopen($branch, $ingredient, $request->user())) {
            return $this->error('Bu ingredient filialda stop-listda emas.', [], 404);
        }

        return $this->success(['branch_id' => (int) $branch->id, 'ingredient_id' => (int) $ingredient->id], 'Ingredient qayta ochildi.');
    }

    private function ingredient(Request $request, int $id): Ingredient
    {
        $ingredient = Ingredient::where('store_id', $this->storeId($request))->find($id);
        if (!$ingredient instanceof Ingredient) {
            throw new HttpResponseException($this->error('Ingredient topilmadi.', [], 404));
        }

        return $ingredient;
    }
}
