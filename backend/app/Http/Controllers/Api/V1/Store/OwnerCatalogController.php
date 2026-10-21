<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductModifierGroup;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\Catalog\MenuImageService;

/**
 * The store's menu: products with their variants (P2-30: menu.view reads, menu.manage writes; the owner by default, an
 * owner may grant either to other roles), global categories (reading: menu.view; creating: super admin). Errors are
 * Uzbek in the standard shape ({success: false, message, data}).
 */
#[Group('Owner: catalog', "Do'kon menyusini boshqarish (menu.view — ko'rish, menu.manage — o'zgartirish). Kategoriya yaratish faqat superadmin uchun.")]
class OwnerCatalogController extends Controller
{
    use ApiResponse;

    /**
     * Get global categories for the dropdown
     *
     * menu.view (the route's).
     */
    public function getCategories(Request $request)
    {
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
            return $this->error('Ruxsat etilmagan', [], 403);
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
     *
     * menu.view. Each product with its category and variants, and `modifier_group_links`: the modifier groups it offers
     * in order, with this product's own rules (`min_select`, `max_select`, `is_required`; null: the group's), as
     * PUT /owner/products/{id}/modifier-groups saves them.
     */
    #[ApiDocResponse(200, type: 'array{success: true, data: list<Product&array{modifier_group_links: list<ProductModifierGroup>}>}')]
    public function getProducts(Request $request)
    {
        $storeId = $this->storeId($request);

        // P2-30: each product's links to its modifier groups (the pivot rows, in order), so the attach list can be saved
        // without losing the per-product rules
        $products = Product::where('store_id', $storeId)
            ->with(['category', 'variants', 'modifierGroupLinks' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
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
     *
     * multipart/form-data, menu.manage. `variants` is a JSON array of {name, price, unit?, quantity_step?,
     * min_quantity?, batch_consumption?, stock?}; `stock` left out starts the variant at 999.
     */
    public function storeProduct(Request $request)
    {
        $storeId = $this->storeId($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:categories,id',
            'is_active' => 'boolean',
            // Max 5MB
            'image' => 'nullable|image|max:5120',
            // JSON array of variants: [{"name": "...", "price": 25000}, ...]; P1-21 adds optional "unit" (piece|kg|l),
            // "quantity_step", "min_quantity" (kg/l) and "batch_consumption"; P2-30: optional "stock" (left out: 999)
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
                'store_id' => $storeId,
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
                    'stock' => $this->initialStock($variantData),
                    'is_active' => true,
                ] + $this->variantUnitFields($variantData));
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Mahsulot qo'shildi.",
                'data' => $product->load('variants')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            // The reason goes to the log (with the request id), never to the client
            Log::error('Product could not be created', ['store_id' => $storeId, 'error' => $e->getMessage()]);

            return $this->error("Mahsulotni saqlab bo'lmadi. Birozdan so'ng qayta urinib ko'ring.", [], 500);
        }
    }

    /**
     * Update an existing product
     *
     * multipart/form-data (POST, so the image can be sent). With `toggle_active` (any value) only the product's
     * active status is flipped and the other fields are not needed. menu.manage. A variant's stock is not changed
     * here (orders move it); a new variant takes `stock` like on create.
     */
    #[QueryParameter('toggle_active', description: 'Any value: only switch the product on/off (no other field needed).', type: 'int', required: false)]
    public function updateProduct(Request $request, $id)
    {
        $product = $this->product($request, $id);

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
                        'stock' => $this->initialStock($variantData),
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
                'message' => 'Mahsulot saqlandi.',
                'data' => $product->fresh('variants')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product could not be updated', ['product_id' => $product->id, 'error' => $e->getMessage()]);

            return $this->error("Mahsulotni saqlab bo'lmadi. Birozdan so'ng qayta urinib ko'ring.", [], 500);
        }
    }

    /**
     * Delete a product
     */
    public function deleteProduct(Request $request, $id)
    {
        $product = $this->product($request, $id);

        app(MenuImageService::class)->delete($product);

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => "Mahsulot o'chirildi."
        ]);
    }

    /**
     * Remove a product's image
     *
     * menu.manage. The image and its thumbnails are deleted; the product shows no picture until a new one is uploaded
     * (POST /owner/products/{id} with `image`). Without an image: 200, nothing changes.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: Product}')]
    public function deleteImage(Request $request, $id)
    {
        $product = $this->product($request, $id);

        if ($product->image !== null && $product->image !== '') {
            app(MenuImageService::class)->delete($product);
            $product->forceFill(['image' => null, 'image_disk' => null, 'image_thumbs' => null])->save();
        }

        return $this->success($product->fresh('variants'), "Rasm o'chirildi.");
    }

    /**
     * The caller's store (the route checked menu.view / menu.manage); an account without one (a super admin): 403.
     */
    private function storeId(Request $request): int
    {
        $storeId = $request->user()?->store_id;
        if ($storeId === null) {
            throw new HttpResponseException($this->error("Do'kon biriktirilmagan.", ['reason' => 'store_required'], 403));
        }

        return (int) $storeId;
    }

    /**
     * A product of the caller's store; another store's or a missing one: 404 (the id is bounded like every row id).
     */
    private function product(Request $request, mixed $id): Product
    {
        $storeId = $this->storeId($request);
        $rowId = \App\Support\RowId::parse($id);
        $product = $rowId !== null ? Product::where('store_id', $storeId)->find($rowId) : null;
        if (!$product instanceof Product) {
            throw new HttpResponseException($this->error('Mahsulot topilmadi.', [], 404));
        }

        return $product;
    }

    /**
     * P2-30: a new variant's stock: the row's `stock` when given (0–1 000 000, up to 3 decimals for kg/l), else 999.
     *
     * @param  array<string, mixed>  $row
     */
    private function initialStock(array $row): string|int
    {
        return isset($row['stock']) && $row['stock'] !== '' ? \App\Support\Quantity::of($row['stock'])->toString() : 999;
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
            'variants.*.stock' => 'nullable|numeric|min:0|max:1000000|decimal:0,3', // P2-30
        ], [], ['variants.*.stock' => 'Qoldiq']);
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
                // P2-30: a new variant sold by the piece starts with a whole number in stock
                if (!isset($row['id']) && isset($row['stock']) && $row['stock'] !== '' && ($unit === null || !\App\Enums\MenuUnit::from((string) $unit)->isMeasured())
                    && floor((float) $row['stock']) !== (float) $row['stock']) {
                    $validator->errors()->add("variants.{$index}.stock", "Donali taomning qoldig'i butun son bo'lishi kerak.");
                }
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
