<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OwnerCatalogController extends Controller
{
    /**
     * Get global categories for the dropdown
     */
    public function getCategories(Request $request)
    {
        if ($request->user()->role !== \App\Enums\UserRole::SUPER_ADMIN && $request->user()->role !== \App\Enums\UserRole::OWNER) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $categories = Category::where('is_active', true)
            ->whereDoesntHave('children')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'image']);

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Create a new global category
     */
    public function storeCategory(Request $request)
    {
        if ($request->user()->role !== \App\Enums\UserRole::SUPER_ADMIN) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(6),
            'is_active' => true,
            'sort_order' => Category::max('sort_order') + 1,
            'uuid' => Str::uuid(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kategoriya qo\'shildi',
            'data' => $category
        ]);
    }

    /**
     * Get all products for the owner's store
     */
    public function getProducts(Request $request)
    {
        $user = $request->user();
        if ($user->role !== \App\Enums\UserRole::SUPER_ADMIN && $user->role !== \App\Enums\UserRole::OWNER) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$user->store_id) {
            return response()->json(['error' => 'No store assigned'], 400);
        }

        $products = Product::where('store_id', $user->store_id)
            ->with(['category', 'variants'])
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Create a new product with variants
     */
    public function storeProduct(Request $request)
    {
        $user = $request->user();
        if ($user->role !== \App\Enums\UserRole::SUPER_ADMIN && $user->role !== \App\Enums\UserRole::OWNER) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$user->store_id) {
            return response()->json(['error' => 'No store assigned'], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:5120', // Max 5MB
            'variants' => 'required|json', // Parse variants from JSON string
        ]);

        try {
            DB::beginTransaction();

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = \App\Services\ImageOptimizer::optimizeAndStore($request->file('image'), 'products', 800, 75);
            }

            $product = Product::create([
                'store_id' => $user->store_id,
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']) . '-' . Str::random(6),
                'description' => $validated['description'] ?? null,
                'status' => (isset($validated['is_active']) && $validated['is_active']) ? 'active' : 'inactive',
                'image' => $imagePath,
                'uuid' => Str::uuid(),
                'type' => 'physical',
                'has_variants' => true,
            ]);

            $variantsData = json_decode($validated['variants'], true);
            foreach ($variantsData as $variantData) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'name' => $variantData['name'],
                    'price' => $variantData['price'],
                    'stock' => 999,
                    'is_active' => true,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product->load('variants')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing product
     */
    public function updateProduct(Request $request, $id)
    {
        $user = $request->user();
        if ($user->role !== \App\Enums\UserRole::SUPER_ADMIN && $user->role !== \App\Enums\UserRole::OWNER) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $product = Product::where('store_id', $user->store_id)->findOrFail($id);

        // Allow toggling active status without full payload
        if ($request->has('toggle_active')) {
            $newStatus = $product->status === 'active' ? 'inactive' : 'active';
            $product->update(['status' => $newStatus]);
            return response()->json(['success' => true, 'data' => $product]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'is_active' => 'boolean',
            'image' => 'nullable|image|max:5120',
            'variants' => 'required|json',
        ]);

        try {
            DB::beginTransaction();

            if ($request->hasFile('image')) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $product->image = \App\Services\ImageOptimizer::optimizeAndStore($request->file('image'), 'products', 800, 75);
            }

            $updateData = [
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => (isset($validated['is_active']) && $validated['is_active']) ? 'active' : 'inactive',
            ];
            $product->update($updateData);

            // Sync variants
            $variantsData = json_decode($validated['variants'], true);
            $existingVariantIds = [];

            foreach ($variantsData as $variantData) {
                if (isset($variantData['id'])) {
                    // Update existing
                    $variant = ProductVariant::find($variantData['id']);
                    if ($variant && $variant->product_id === $product->id) {
                        $variant->update([
                            'name' => $variantData['name'],
                            'price' => $variantData['price'],
                        ]);
                        $existingVariantIds[] = $variant->id;
                    }
                } else {
                    // Create new
                    $newVariant = ProductVariant::create([
                        'product_id' => $product->id,
                        'name' => $variantData['name'],
                        'price' => $variantData['price'],
                        'stock' => 999,
                        'is_active' => true,
                    ]);
                    $existingVariantIds[] = $newVariant->id;
                }
            }

            // Delete removed variants
            ProductVariant::where('product_id', $product->id)
                ->whereNotIn('id', $existingVariantIds)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product->fresh('variants')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a product
     */
    public function deleteProduct(Request $request, $id)
    {
        $user = $request->user();
        if ($user->role !== \App\Enums\UserRole::SUPER_ADMIN && $user->role !== \App\Enums\UserRole::OWNER) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $product = Product::where('store_id', $user->store_id)->findOrFail($id);
        
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }
}
