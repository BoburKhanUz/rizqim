<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Brands\BrandService;
use App\Enums\OrderChannel;
use App\Models\Brand;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P6-55 (module multi_brand, docs/API.md "Ko'p brend"): the owner's brands sold from one kitchen: the list, create,
 * edit, delete, the brand × channel rules (hidden there, price percent), the branch switches and which dishes belong
 * to which brand. Reading: menu.view or menu.manage; changes: menu.manage (audited). Another store's brand: 404.
 */
#[Group('Owner: brands', "Bitta oshxonada bir nechta brend: kanal bo'yicha ko'rinish va narx, filialda yoqish/o'chirish (P6-55).")]
class BrandController extends ManagementController
{
    /**
     * The store's brands
     *
     * Each brand: `id`, `uuid`, `name`, `short_code` (on the kitchen ticket and the label), `color` (#RRGGBB),
     * `logo_url`, `is_active`, `sort_order`, `products_count`, `channels` ({channel, is_hidden, price_percent}; a
     * channel without a row: visible at the dishes' own prices) and `off_branch_ids` (branches it is switched off on).
     * `channels_available`: the channels a rule may name.
     */
    public function index(Request $request): JsonResponse
    {
        $service = app(BrandService::class);
        $brands = Brand::where('store_id', $this->storeId($request))->withCount('products')->orderBy('sort_order')->orderBy('id')->get();

        return $this->success([
            'brands' => $brands->map(fn (Brand $brand) => $service->present($brand))->values()->all(),
            'channels_available' => OrderChannel::values(),
        ]);
    }

    /**
     * Create a brand
     *
     * `short_code`: 1–8 letters or digits, unique in the store (upper-cased). `color`: #RRGGBB.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'short_code' => ['required', 'string', 'max:8', 'regex:/^[\pL\pN]+$/u'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_url' => 'nullable|url:https|max:500',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:65535',
        ]);

        $service = app(BrandService::class);
        $brand = $service->create($this->storeId($request), $data);

        return $this->success($service->present($brand), 'Brend yaratildi.', 201);
    }

    /**
     * Edit a brand
     *
     * Any of name, short_code, color, logo_url, is_active (an inactive brand's dishes are hidden on every channel),
     * sort_order.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $brand = $this->brand($request, $id);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'short_code' => ['sometimes', 'required', 'string', 'max:8', 'regex:/^[\pL\pN]+$/u'],
            'color' => ['sometimes', 'required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo_url' => 'sometimes|nullable|url:https|max:500',
            'is_active' => 'sometimes|required|boolean',
            'sort_order' => 'sometimes|required|integer|min:0|max:65535',
        ]);

        $service = app(BrandService::class);

        return $this->success($service->present($service->update($brand, $data)), 'Brend saqlandi.');
    }

    /**
     * Delete a brand
     *
     * Its dishes go back to the house brand; sold lines keep the brand they were sold under.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        app(BrandService::class)->delete($this->brand($request, $id));

        return $this->success(null, "Brend o'chirildi.");
    }

    /**
     * Replace a brand's channel rules
     *
     * `channels`: [{channel, is_hidden, price_percent}] — `is_hidden`: the brand is not sold on that channel;
     * `price_percent` (−90…+300, two decimals): its dishes cost that much more (or less) there, half up to whole so'm,
     * unless a variant has its own channel price (that one wins). A channel left out: visible at the own prices.
     */
    public function channels(Request $request, int $id): JsonResponse
    {
        $brand = $this->brand($request, $id);
        $data = $request->validate([
            'channels' => 'present|array|max:20',
            'channels.*.channel' => ['required', 'string', Rule::in(OrderChannel::values())],
            'channels.*.is_hidden' => 'nullable|boolean',
            'channels.*.price_percent' => 'nullable|numeric|min:-90|max:300|decimal:0,2',
        ]);

        $service = app(BrandService::class);

        return $this->success($service->present($service->setChannels($brand, $data['channels'])), 'Kanallar saqlandi.');
    }

    /**
     * Switch a brand on or off on branches
     *
     * `branches`: [{branch_id, is_active}] — off: its dishes are stopped on that branch (like the stop-list, every
     * channel); a branch left out keeps its state.
     */
    public function branches(Request $request, int $id): JsonResponse
    {
        $brand = $this->brand($request, $id);
        $data = $request->validate([
            'branches' => 'required|array|min:1|max:200',
            'branches.*.branch_id' => 'required|integer|min:1',
            'branches.*.is_active' => 'required|boolean',
        ]);

        $service = app(BrandService::class);

        return $this->success($service->present($service->setBranches($brand, $data['branches'])), 'Filiallar saqlandi.');
    }

    /**
     * Put dishes under a brand
     *
     * `brand_id`: the brand (null: the house brand); `product_ids`: the store's dishes (up to 500). Returns how many
     * changed.
     */
    public function assignProducts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'brand_id' => 'present|nullable|integer|min:1',
            'product_ids' => 'required|array|min:1|max:500',
            'product_ids.*' => 'integer|min:1|distinct',
        ]);

        $brand = $data['brand_id'] !== null ? $this->brand($request, (int) $data['brand_id']) : null;
        $changed = app(BrandService::class)->assignProducts($this->storeId($request), $brand, array_map('intval', $data['product_ids']));

        return $this->success(['changed' => $changed, 'brand_id' => $brand?->id], 'Mahsulotlar brendga biriktirildi.');
    }

    private function brand(Request $request, int $id): Brand
    {
        $brand = Brand::where('store_id', $this->storeId($request))->find($id);
        if (!$brand instanceof Brand) {
            throw new HttpResponseException($this->error('Brend topilmadi.', [], 404));
        }

        return $brand;
    }
}
