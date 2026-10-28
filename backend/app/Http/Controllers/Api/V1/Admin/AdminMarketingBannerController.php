<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Models\Promo;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Marketing\MarketingException;
use App\Services\Admin\Marketing\PromoBanners;
use App\Support\RowId;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * P4-31: Marketing -> Bannerlar va aksiyalar (docs/API.md "Marketing"). Read: admin.marketing.view; change:
 * admin.marketing.manage and a `reason`; deleting also a step-up (admin.recent). Every change is audited with the
 * banner's fields before and after (the route name is the action). Images are multipart/form-data.
 */
#[Group('Admin')]
class AdminMarketingBannerController extends AdminMarketingController
{
    private const ROW = 'array{id: int, title: string, image_url: string|null, image_preview: string|null, store: array{id: int, name: string}|null, product: array{id: int, name: string, store_id: int}|null, is_active: bool, starts_at: string|null, ends_at: string|null, sort_order: int, status: string, status_label: string, created_at: string|null, updated_at: string|null}';

    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    public function __construct(
        private readonly PromoBanners $banners,
    ) {}

    /**
     * Banners and promotions
     *
     * Every banner in the Mini App's order (`sort_order`), at most 200. Filters: `status` (live, scheduled, ended,
     * inactive), `q` (part of the title). `status` is computed now (Asia/Tashkent): inactive = switched off; scheduled =
     * `starts_at` still ahead; ended = `ends_at` passed; live = what the Mini App shows. `image_preview` is the admin
     * path of the image (GET, same session), `image_url` the public URL the Mini App uses.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::ROW . '>, statuses: array<string, string>}}')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(PromoBanners::STATUSES))],
            'q' => 'nullable|string|max:100',
        ], UzValidation::messages());

        return $this->success([
            'data' => $this->banners->list($filters['status'] ?? null, $filters['q'] ?? null),
            'statuses' => PromoBanners::STATUSES,
        ]);
    }

    /**
     * What the Mini App shows
     *
     * The banners the home screen lists right now, in its order: the same query as `GET /catalog` without a store
     * (switched on and inside their schedule). `image_preview` as in the list.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<array{id: int, title: string, image_url: string|null, image_preview: string|null, store_uuid: string|null, product_id: int|null}>}}')]
    public function preview(): JsonResponse
    {
        return $this->success(['data' => $this->banners->preview()]);
    }

    /**
     * Banner image
     *
     * The stored image itself (JPEG, PNG or WebP) for the admin screens, which cannot load the public storage URL.
     * 404 `no_image` when there is none (or it is an outside URL).
     */
    public function image(int $id): Response|JsonResponse
    {
        $promo = Promo::query()->find($id);
        if ($promo === null) {
            return $this->refuse('Banner topilmadi.', 'not_found', 404);
        }

        return $this->imageResponse($this->banners->imageFile($promo));
    }

    /**
     * New banner
     *
     * multipart/form-data: `title` (2-255), `image` (JPEG, PNG or WebP, up to 4 MB, required), `store_id` and/or
     * `product_id` (what a tap opens; a product must belong to the store when both are given), `is_active` (1/0,
     * default 1), `starts_at` / `ends_at` (ISO 8601, `ends_at` after `starts_at`, else 422 `invalid_schedule`; both empty = always), `reason`. It
     * goes to the end of the row. Answers the banner (201).
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::ROW . '}')]
    #[ApiDocResponse(422, 'A rule refused it (product_store_mismatch, invalid_schedule) or a field is wrong', type: self::ERROR)]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(true), UzValidation::messages($this->imageMessages()), $this->attributeNames());
        $data = $this->normalize($data);

        try {
            $promo = $this->banners->create($data, $request->file('image'));
        } catch (MarketingException $e) {
            return $this->refuseWith($e);
        }

        AdminAudit::for($request)->subject($promo)->changes([], $this->banners->snapshot($promo));

        return $this->success($this->banners->present($promo), 'Banner qo\'shildi.', 201);
    }

    /**
     * Change a banner
     *
     * POST (multipart/form-data or JSON; PHP does not read a multipart PATCH). Any of `title`, `image` (a new file
     * replaces the old one), `store_id`, `product_id` (empty clears), `is_active`, `starts_at`, `ends_at` (empty clears),
     * and `reason` (required). Answers the banner.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::ROW . '}')]
    #[ApiDocResponse(404, 'No such banner (not_found)', type: self::ERROR)]
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate($this->rules(false), UzValidation::messages($this->imageMessages()), $this->attributeNames());
        $promo = Promo::query()->find($id);
        if ($promo === null) {
            return $this->refuse('Banner topilmadi.', 'not_found', 404);
        }

        $before = $this->banners->snapshot($promo);
        try {
            $promo = $this->banners->update($promo, $this->normalize($data), $request->file('image'));
        } catch (MarketingException $e) {
            return $this->refuseWith($e);
        }

        $after = $this->banners->snapshot($promo);
        AdminAudit::for($request)->subject($promo)->changes($before, $after);
        if ($before === $after) {
            AdminAudit::for($request)->skip();
        }

        return $this->success($this->banners->present($promo), 'Banner saqlandi.');
    }

    /**
     * Order the banners
     *
     * `ids`: every banner id, once, in the new order (the whole row; a list that is out of date is refused with 409
     * `order_stale` so nobody overwrites a colleague's order), and `reason`. Answers the list.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . self::ROW . '>, statuses: array<string, string>}}')]
    #[ApiDocResponse(409, 'The list changed meanwhile (order_stale)', type: self::ERROR)]
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:' . PromoBanners::MAX,
            'ids.*' => UzValidation::rowId(true),
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());

        try {
            $change = $this->banners->reorder(array_map('intval', $data['ids']));
        } catch (MarketingException $e) {
            return $this->refuseWith($e);
        }

        AdminAudit::for($request)->changes(['order' => $change['before']], ['order' => $change['after']]);
        if ($change['before'] === $change['after']) {
            AdminAudit::for($request)->skip();
        }

        return $this->success(['data' => $this->banners->list(), 'statuses' => PromoBanners::STATUSES], 'Tartib saqlandi.');
    }

    /**
     * Delete a banner
     *
     * Needs a step-up and a `reason`. The banner and its stored image go; the audit entry keeps its fields. Answers
     * `{id}`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int}}')]
    #[ApiDocResponse(404, 'No such banner (not_found)', type: self::ERROR)]
    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $promo = Promo::query()->find($id);
        if ($promo === null) {
            return $this->refuse('Banner topilmadi.', 'not_found', 404);
        }

        $before = $this->banners->snapshot($promo);
        $this->banners->delete($promo);
        AdminAudit::for($request)->subject($promo)->changes($before, []);

        return $this->success(['id' => $id], "Banner o'chirildi.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $creating): array
    {
        $present = $creating ? 'required' : 'sometimes';

        return [
            'title' => [$present, 'string', 'min:2', 'max:255'],
            'image' => $this->imageRules($creating),
            'store_id' => ['sometimes', ...$this->storeRules()],
            'product_id' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999999999999999999', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'is_active' => 'sometimes|boolean',
            'starts_at' => 'sometimes|nullable|date',
            'ends_at' => 'sometimes|nullable|date',
            'reason' => AdminRules::reason(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        return [
            'title' => 'Sarlavha', 'image' => 'Rasm', 'store_id' => 'Ovqatxona', 'product_id' => 'Mahsulot',
            'starts_at' => 'Boshlanish vaqti', 'ends_at' => 'Tugash vaqti', 'ids' => 'Tartib', 'reason' => 'Sabab',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        unset($data['image'], $data['reason']);
        foreach (['starts_at', 'ends_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->utc($data[$field]);
            }
        }
        foreach (['store_id', 'product_id'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $data[$field] = RowId::parse($data[$field]);
            }
        }

        return $data;
    }
}
