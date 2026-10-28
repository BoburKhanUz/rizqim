<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Product;
use App\Models\Store;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P4-31: the store and product pickers of the Marketing forms (admin.marketing.view), so a member who may edit banners
 * and promo codes does not also need admin.stores.view.
 */
#[Group('Admin')]
class AdminMarketingLookupController extends AdminMarketingController
{
    /**
     * Store and product picker
     *
     * `q`: up to 20 stores whose name matches; `store_id` + `q`: up to 20 of that store's products by name;
     * `ids[]` / `product_ids[]`: those stores / products (to show a chosen value's name). Without anything: the first
     * 20 stores by name.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{stores: list<array{id: int, name: string}>, products: list<array{id: int, name: string, store_id: int}>}}')]
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'store_id' => UzValidation::rowId(),
            'ids' => 'nullable|array|max:20',
            'ids.*' => UzValidation::rowId(true),
            'product_ids' => 'nullable|array|max:20',
            'product_ids.*' => UzValidation::rowId(true),
        ], UzValidation::messages());

        $q = trim((string) ($data['q'] ?? ''));
        $like = '%' . addcslashes($q, '%_\\') . '%';

        $stores = Store::query()
            ->when(!empty($data['ids']), fn ($query) => $query->whereIn('id', array_map('intval', $data['ids'])))
            ->when(empty($data['ids']) && $q !== '' && empty($data['store_id']), fn ($query) => $query->where('name', 'ilike', $like))
            ->orderBy('name')->limit(20)
            ->get(['id', 'name'])
            ->map(fn (Store $store) => ['id' => (int) $store->id, 'name' => (string) $store->name])->all();

        $products = [];
        if (!empty($data['product_ids']) || !empty($data['store_id'])) {
            $products = Product::query()
                ->when(!empty($data['product_ids']), fn ($query) => $query->whereIn('id', array_map('intval', $data['product_ids'])))
                ->when(empty($data['product_ids']), fn ($query) => $query->where('store_id', (int) $data['store_id'])->when($q !== '', fn ($inner) => $inner->where('name', 'ilike', $like)))
                ->orderBy('name')->limit(20)
                ->get(['id', 'name', 'store_id'])
                ->map(fn (Product $product) => ['id' => (int) $product->id, 'name' => (string) $product->name, 'store_id' => (int) $product->store_id])->all();
        }

        return $this->success(['stores' => $stores, 'products' => $products]);
    }
}
