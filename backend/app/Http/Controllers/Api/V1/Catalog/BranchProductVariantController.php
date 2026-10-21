<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Traits\ApiResponse;

#[Group('Owner: branch availability', "Filial bo'yicha mahsulot variantlarini yoqish/o'chirish (ega, menejer).")]
class BranchProductVariantController extends Controller
{
    use ApiResponse;

    /**
     * List all products and variants with their branch override settings.
     *
     * A manager always gets their own branch; an owner must send `branch_id` (422 without it).
     */
    #[QueryParameter('branch_id', description: 'Owner: the branch to list (required). Ignored for a manager.', type: 'int')]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Avtorizatsiyadan o\'tilmagan.', [], 401);
        }

        $userRole = $user->role->value ?? null; // null for an in-memory user created without a role
        if (!in_array($userRole, ['owner', 'manager'])) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        // An id past bigint reads as missing (a 422), never as a failing query
        $branchId = $userRole === 'manager' ? $user->branch_id : \App\Support\RowId::parse($request->query('branch_id'));
        if (!$branchId) {
            return $this->error('Filialni tanlang (branch_id).', [], 422);
        }

        // Ensure branch belongs to store
        $branch = \App\Models\Branch::where('store_id', $user->store_id)
            ->where('id', $branchId)
            ->first();

        if (!$branch) {
            return $this->error('Filial topilmadi.', [], 404);
        }

        // Fetch all products of the store with their variants
        $products = \App\Models\Product::where('store_id', $user->store_id)
            ->with(['variants'])
            ->get();

        // Fetch overrides for this branch
        $overrides = \App\Models\BranchProductVariant::where('branch_id', $branchId)->get()->keyBy('variant_id');

        $result = $products->map(function ($product) use ($overrides) {
            $variants = $product->variants->map(function ($variant) use ($overrides) {
                $override = $overrides->get($variant->id);

                return [
                    'variant_id' => $variant->id,
                    'variant_uuid' => $variant->uuid,
                    'name' => $variant->name,
                    'price' => $variant->price,
                    'is_active' => $override ? (bool)$override->is_active : true,
                    'hidden_reason' => $override ? $override->hidden_reason : null,
                    'sort_order' => $override ? (int)$override->sort_order : 0,
                ];
            });

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'image_url' => $product->image_url,
                // P2-30: sold from a batch (kazan): without an open batch on the branch it is sold out (docs/MENU.md §4)
                'batch_tracked' => (bool) $product->batch_tracked,
                'variants' => $variants,
            ];
        });

        return $this->success($result);
    }

    /**
     * Update or Create branch-specific overrides for a variant.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Avtorizatsiyadan o\'tilmagan.', [], 401);
        }

        $userRole = $user->role->value ?? null; // null for an in-memory user created without a role
        if (!in_array($userRole, ['owner', 'manager'])) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        $request->validate([
            'branch_id' => 'bail|required|integer|min:1|exists:branches,id',
            'product_id' => 'bail|required|integer|min:1|exists:products,id',
            'product_variant_id' => 'bail|required|integer|min:1|exists:product_variants,id',
            'is_active' => 'required|boolean',
            'hidden_reason' => 'nullable|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        $branchId = $userRole === 'manager' ? $user->branch_id : $request->branch_id;

        // Verify branch belongs to store
        $branch = \App\Models\Branch::where('store_id', $user->store_id)
            ->where('id', $branchId)
            ->first();

        if (!$branch) {
            return $this->error('Filial topilmadi.', [], 404);
        }

        // Verify product belongs to store
        $product = \App\Models\Product::where('store_id', $user->store_id)
            ->where('id', $request->product_id)
            ->first();

        if (!$product) {
            return $this->error('Mahsulot topilmadi.', [], 404);
        }

        // P1-21: the variant must be this product's (it could name another store's variant before)
        if (!\App\Models\ProductVariant::where('product_id', $product->id)->whereKey($request->product_variant_id)->exists()) {
            return $this->error('Mahsulot topilmadi.', [], 404);
        }

        $wasActive = \App\Models\BranchProductVariant::where('branch_id', $branchId)
            ->where('variant_id', $request->product_variant_id)
            ->value('is_active') ?? true;

        // Update or Create override
        $variantOverride = \App\Models\BranchProductVariant::updateOrCreate(
            [
                'branch_id' => $branchId,
                'variant_id' => $request->product_variant_id,
            ],
            [
                'is_active' => $request->is_active,
                'hidden_reason' => !$request->is_active ? $request->hidden_reason : null,
                'sort_order' => $request->sort_order ?? 0,
            ]
        );

        // P1-21: the menus listening on the branch see the switch at once
        if ((bool) $wasActive !== (bool) $variantOverride->is_active) {
            event(new \App\Events\Broadcast\MenuAvailabilityChangedBroadcast((int) $branch->store_id, (int) $branch->id, [
                ['type' => 'variant', 'id' => (int) $variantOverride->variant_id, 'available' => (bool) $variantOverride->is_active],
            ], 'branch_override'));
        }

        return $this->success($variantOverride, 'Filialdagi holati saqlandi.');
    }
}
