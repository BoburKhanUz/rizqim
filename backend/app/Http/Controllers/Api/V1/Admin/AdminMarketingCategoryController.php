<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Models\Category;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Marketing\MarketingCategories;
use App\Services\Admin\Marketing\MarketingException;
use App\Support\RowId;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * P4-31: Marketing -> Kategoriyalar (docs/API.md "Marketing"). Read: admin.marketing.view; change:
 * admin.marketing.manage and a `reason`; deleting also a step-up (admin.recent). Icons are multipart/form-data and go
 * through MenuImageService like every menu image.
 */
#[Group('Admin')]
class AdminMarketingCategoryController extends AdminMarketingController
{
    private const ROW = 'array{id: int, uuid: string, name: string, slug: string, parent: array{id: int, name: string}|null, image_url: string|null, image_preview: string|null, sort_order: int, is_active: bool, products_count: int, children_count: int, updated_at: string|null}';

    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    public function __construct(
        private readonly MarketingCategories $categories,
    ) {}

    /**
     * Categories
     *
     * Every category (top-level and inner) in the Mini App's order (`sort_order`), with its product and sub-category
     * counts. `is_active` false = hidden from the catalog (its products stay). `image_preview` is the admin path of the
     * icon (GET, same session), `image_url` its public URL.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::ROW . '>}}')]
    public function index(): JsonResponse
    {
        return $this->success(['data' => $this->categories->list()]);
    }

    /**
     * Category icon
     *
     * The stored icon (JPEG, PNG or WebP) for the admin screens. 404 `no_image` when there is none.
     */
    public function image(int $id): Response|JsonResponse
    {
        $category = Category::query()->find($id);
        if ($category === null) {
            return $this->refuse('Kategoriya topilmadi.', 'not_found', 404);
        }

        return $this->imageResponse($this->categories->imageFile($category));
    }

    /**
     * New category
     *
     * multipart/form-data: `name` (2-255), `parent_id` (a top-level category; empty = top-level itself), `image` (the
     * icon: JPEG, PNG or WebP, up to 4 MB, optional), `is_active` (1/0, default 1), `reason`. It goes to the end of the
     * list. Answers the row (201). 422 `invalid_parent`.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::ROW . '}')]
    #[ApiDocResponse(422, 'A rule refused it (invalid_parent) or a field is wrong', type: self::ERROR)]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(true), UzValidation::messages($this->imageMessages()), $this->attributeNames());

        try {
            $category = $this->categories->create($this->normalize($data), $request->file('image'));
        } catch (MarketingException $e) {
            return $this->refuseWith($e);
        }

        AdminAudit::for($request)->subject($category)->changes([], $this->categories->snapshot($category));

        return $this->success($this->categories->present($category), "Kategoriya qo'shildi.", 201);
    }

    /**
     * Change a category
     *
     * POST (multipart/form-data or JSON). Any of `name`, `parent_id` (empty = top-level; a category with sub-categories
     * cannot become one), `is_active` (hide or show), `image` (a new icon replaces the old one), `remove_image` (1:
     * drop the icon), and `reason` (required). Answers the row.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ROW . '}')]
    #[ApiDocResponse(404, 'No such category (not_found)', type: self::ERROR)]
    #[ApiDocResponse(422, 'A rule refused it (invalid_parent) or a field is wrong', type: self::ERROR)]
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate($this->rules(false), UzValidation::messages($this->imageMessages()), $this->attributeNames());
        $category = Category::query()->find($id);
        if ($category === null) {
            return $this->refuse('Kategoriya topilmadi.', 'not_found', 404);
        }

        $before = $this->categories->snapshot($category);
        try {
            $category = $this->categories->update($category, $this->normalize($data), $request->file('image'));
        } catch (MarketingException $e) {
            return $this->refuseWith($e);
        }

        $after = $this->categories->snapshot($category);
        AdminAudit::for($request)->subject($category)->changes($before, $after);
        if ($before === $after) {
            AdminAudit::for($request)->skip();
        }

        return $this->success($this->categories->present($category), 'Kategoriya saqlandi.');
    }

    /**
     * Order the categories
     *
     * `ids`: every category id, once, in the new order (the whole list; one that is out of date is refused with 409
     * `order_stale`), and `reason`. Answers the list.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::ROW . '>}}')]
    #[ApiDocResponse(409, 'The list changed meanwhile (order_stale)', type: self::ERROR)]
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:' . MarketingCategories::MAX,
            'ids.*' => UzValidation::rowId(true),
            'reason' => AdminRules::reason(),
        ], UzValidation::messages(), ['ids' => 'Tartib', 'reason' => 'Sabab']);

        try {
            $change = $this->categories->reorder(array_map('intval', $data['ids']));
        } catch (MarketingException $e) {
            return $this->refuseWith($e);
        }

        AdminAudit::for($request)->changes(['order' => $change['before']], ['order' => $change['after']]);
        if ($change['before'] === $change['after']) {
            AdminAudit::for($request)->skip();
        }

        return $this->success(['data' => $this->categories->list()], 'Tartib saqlandi.');
    }

    /**
     * Delete a category
     *
     * Only one with no products and no sub-categories (409 `category_in_use` otherwise: hide it instead). Needs a
     * step-up and a `reason`. Answers `{id}`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int}}')]
    #[ApiDocResponse(409, 'It has products or sub-categories (category_in_use)', type: self::ERROR)]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $category = Category::query()->find($id);
        if ($category === null) {
            return $this->refuse('Kategoriya topilmadi.', 'not_found', 404);
        }

        $before = $this->categories->snapshot($category);
        try {
            $this->categories->delete($category);
        } catch (MarketingException $e) {
            return $this->refuseWith($e);
        }
        AdminAudit::for($request)->subject($category)->changes($before, []);

        return $this->success(['id' => $id], "Kategoriya o'chirildi.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $creating): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'min:2', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999999999999999999'],
            'image' => $this->imageRules(false),
            'remove_image' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'reason' => AdminRules::reason(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return ['name' => 'Nomi', 'parent_id' => 'Ota kategoriya', 'image' => 'Ikonka', 'reason' => 'Sabab'];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        unset($data['image'], $data['reason']);
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $data['parent_id'] = RowId::parse($data['parent_id']);
        }

        return $data;
    }
}
