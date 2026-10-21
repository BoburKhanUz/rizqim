<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\Catalog\MenuImageService;

#[Group('Owner: catalog', "Do'kon menyusini boshqarish (ega). Kategoriya yaratish faqat superadmin uchun.")]
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
            // Max 5MB
            'image' => 'nullable|image|max:5120',
            // JSON array of variants: [{"name": "...", "price": 25000}, ...]; P1-21 adds optional "unit" (piece|kg|l),
            // "quantity_step", "min_quantity" (kg/l) and "batch_consumption"
            'variants' => 'required|json',
            'batch_tracked' => 'boolean',
            // P2-12: position in the menu, as the panel's product form sets it (left out = 0 / kept)
            'sort_order' => 'nullable|integer|between:0,100000',
        ]);
        $this->validateVariantUnits($validated['variants']);

        try {
            DB::beginTransaction();

            // P1-21: on the menu image disk (S3 when configured, else public), thumbnails made by a queued job
            $image = $request->hasFile('image') ? app(MenuImageService::class)->store($request->file('image'), 'products') : null;

            $product = Product::create([
                'store_id' => $user->store_id,
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']) . '-' . Str::random(6),
                'description' => $validated['description'] ?? null,
                'status' => (isset($validated['is_active']) && $validated['is_active']) ? 'active' : 'inactive',
                'image' => $image['path'] ?? null,
                'image_disk' => $image['disk'] ?? null,
                'uuid' => Str::uuid(),
                'type' => 'physical',
                'has_variants' => true,
                'batch_tracked' => (bool) ($validated['batch_tracked'] ?? false),
            ] + (isset($validated['sort_order']) ? ['sort_order' => (int) $validated['sort_order']] : []));
            app(MenuImageService::class)->queueThumbnails($product);

            $variantsData = json_decode($validated['variants'], true);
            foreach ($variantsData as $variantData) {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'name' => $variantData['name'],
                    'price' => $variantData['price'],
                    'stock' => 999,
                    'is_active' => true,
                ] + $this->variantUnitFields($variantData));
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
     *
     * multipart/form-data (POST, so the image can be sent). With `toggle_active` (any value) only the product's
     * active status is flipped and the other fields are not needed.
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
            // JSON array of variants: [{"id": 1, "name": "...", "price": 25000}, ...]; one without an id is created,
            // an existing one left out is deleted. P1-21: optional "unit", "quantity_step", "min_quantity", "batch_consumption"
            'variants' => 'required|json',
            'batch_tracked' => 'boolean',
            'sort_order' => 'nullable|integer|between:0,100000', // P2-12
        ]);
        $this->validateVariantUnits($validated['variants'], $product);

        try {
            DB::beginTransaction();

            if ($request->hasFile('image')) {
                // P1-21: old image and thumbnails go, the new one gets thumbnails (MenuImageService)
                app(MenuImageService::class)->replace($product, $request->file('image'), 'products');
            }

            $updateData = [
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'status' => (isset($validated['is_active']) && $validated['is_active']) ? 'active' : 'inactive',
            ];
            if (array_key_exists('batch_tracked', $validated)) {
                $updateData['batch_tracked'] = (bool) $validated['batch_tracked'];
            }
            if (isset($validated['sort_order'])) {
                $updateData['sort_order'] = (int) $validated['sort_order']; // P2-12
            }
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
                        ] + $this->variantUnitFields($variantData, true));
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
                    ] + $this->variantUnitFields($variantData));
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
        
        app(MenuImageService::class)->delete($product);

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * P1-21: the optional sale-unit fields of a variants JSON row; 422 when one is invalid. The minimum of a weighed
     * variant must be a multiple of its step (a field left out of an update keeps the saved variant's value), or no
     * quantity at or above it would be accepted at checkout.
     */
    private function validateVariantUnits(string $json, ?Product $product = null): void
    {
        $rows = json_decode($json, true);
        if (!is_array($rows)) {
            return;
        }

        $validator = \Illuminate\Support\Facades\Validator::make(['variants' => $rows], [
            'variants.*.unit' => ['nullable', 'string', \Illuminate\Validation\Rule::in(\App\Enums\MenuUnit::values())],
            'variants.*.quantity_step' => 'nullable|numeric|gt:0|max:1000|decimal:0,3',
            'variants.*.min_quantity' => 'nullable|numeric|gt:0|max:1000|decimal:0,3',
            'variants.*.batch_consumption' => 'nullable|numeric|gt:0|max:1000|decimal:0,3',
        ]);
        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($rows, $product) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            foreach ($rows as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $saved = $product !== null && isset($row['id']) ? $product->variants()->whereKey($row['id'])->first() : null;
                // An empty unit keeps the saved one (variantUnitFields drops it); empty step/minimum reset to the defaults
                $unit = $row['unit'] ?? $saved?->unit->value;
                if ($unit === null || !\App\Enums\MenuUnit::from((string) $unit)->isMeasured()) {
                    continue;
                }
                $minimum = array_key_exists('min_quantity', $row) ? $row['min_quantity'] : $saved?->min_quantity;
                $step = array_key_exists('quantity_step', $row) ? $row['quantity_step'] : $saved?->quantity_step;
                $problem = \App\Support\QuantityRules::minimumProblem($minimum, $step);
                if ($problem !== null) {
                    $validator->errors()->add("variants.{$index}.min_quantity", $problem);
                }
            }
        });
        $validator->validate();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function variantUnitFields(array $row, bool $onlyGiven = false): array
    {
        $fields = [];
        foreach (['unit', 'quantity_step', 'min_quantity', 'batch_consumption'] as $field) {
            if (!$onlyGiven || array_key_exists($field, $row)) {
                $fields[$field] = $row[$field] ?? null;
            }
        }

        $fields = array_filter($fields, fn ($value, $field) => $value !== null || in_array($field, ['quantity_step', 'min_quantity'], true), ARRAY_FILTER_USE_BOTH);
        if (($fields['unit'] ?? null) === \App\Enums\MenuUnit::PIECE->value) {
            $fields['quantity_step'] = null;
            $fields['min_quantity'] = null;
        }

        return $fields;
    }
}
