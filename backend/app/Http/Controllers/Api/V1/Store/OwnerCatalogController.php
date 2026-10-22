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
     * min_quantity?, batch_consumption?, stock?, unit_label?}; `stock` left out starts the variant at 999. P3-26:
     * `quick_add` (boolean) makes it a one-tap button at the till and on the waiter phone.
     * P3-11: optional `kind` (dish | service), `mxik_code` (17 digits), `package_code` (digits), `vat_rate` (0–100,
     * empty: the store's tax regime decides); a variant's `unit_label` is its unit on the receipt ("kg (guruch)").
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
            // P3-26: a one-tap button at the till and on the waiter phone (GET /pos/bootstrap quick_items)
            'quick_add' => 'boolean',
            // P2-12: position in the menu, as the panel's product form sets it (left out = 0 / kept)
            'sort_order' => 'nullable|integer|between:0,100000',
        ] + $this->fiscalRules(), $this->fiscalMessages());
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
                'quick_add' => (bool) ($validated['quick_add'] ?? false), // P3-26
            ] + (isset($validated['sort_order']) ? ['sort_order' => (int) $validated['sort_order']] : [])
                + $this->fiscalFields($validated)); // P3-11
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
     * here (orders move it); a new variant takes `stock` like on create. P3-11: `kind`, `mxik_code`,
     * `package_code`, `vat_rate` and a variant's `unit_label` as on create; left out = kept, empty = cleared. P3-26:
     * `quick_add` as on create (left out = kept). Only the fiscal fields: PATCH /owner/products/{id}/fiscal.
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
            'quick_add' => 'boolean', // P3-26
            'sort_order' => 'nullable|integer|between:0,100000', // P2-12
        ] + $this->fiscalRules(), $this->fiscalMessages());
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
            if (array_key_exists('quick_add', $validated)) {
                $updateData['quick_add'] = (bool) $validated['quick_add']; // P3-26: left out = kept
            }
            $updateData += $this->fiscalFields($validated); // P3-11: fields left out are kept
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
     * Update only a product's fiscal fields
     *
     * P3-26: JSON, menu.manage. The receipt fields alone, without resending the whole product (the panel's fiscal bulk
     * edit): `kind` (dish | service), `mxik_code` (17 digits), `package_code` (digits), `vat_rate` (0–100; empty: the
     * store's tax regime decides) and `variants`: [{id, unit_label}] (a variant's unit on the receipt, "kg (guruch)";
     * empty: the unit's own). A field left out is kept, an empty one cleared (`kind` then goes back to dish). Another
     * product's variant id: 422. Returns the product with its variants.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: Product}')]
    public function updateFiscal(Request $request, $id)
    {
        $product = $this->product($request, $id);
        $validated = $request->validate($this->fiscalRules() + [
            'variants' => 'sometimes|array|max:100',
            'variants.*.id' => 'required|integer|min:1|max:999999999999',
            'variants.*.unit_label' => 'present|nullable|string|max:64',
        ], $this->fiscalMessages() + [
            'variants.*.id.required' => "Variant identifikatori kerak.",
            'variants.*.unit_label.present' => "Chekdagi o'lchov birligini yuboring (bo'sh: o'lchovning o'zi).",
            'variants.*.unit_label.max' => "Chekdagi o'lchov birligi ko'pi bilan 64 belgi bo'lishi mumkin.",
        ]);

        $variantIds = array_map(fn (array $row) => (int) $row['id'], $validated['variants'] ?? []);
        $own = ProductVariant::where('product_id', $product->id)->whereIn('id', $variantIds)->pluck('id')->map(fn ($variantId) => (int) $variantId)->all();
        foreach ($variantIds as $index => $variantId) {
            if (!in_array($variantId, $own, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(["variants.{$index}.id" => "Bu variant shu mahsulotga tegishli emas."]);
            }
        }

        DB::transaction(function () use ($product, $validated) {
            $fields = $this->fiscalFields($validated);
            if ($fields !== []) {
                $product->update($fields);
            }
            foreach ($validated['variants'] ?? [] as $row) {
                $label = is_string($row['unit_label'] ?? null) && trim($row['unit_label']) !== '' ? trim($row['unit_label']) : null;
                ProductVariant::where('product_id', $product->id)->whereKey((int) $row['id'])->update(['unit_label' => $label]);
            }
        });

        return $this->success($product->fresh('variants'), "Fiskal ma'lumotlar saqlandi.");
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
            'variants.*.unit_label' => 'nullable|string|max:64', // P3-11
        ], [], ['variants.*.stock' => 'Qoldiq', 'variants.*.unit_label' => "Chekdagi o'lchov birligi"]);
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
        foreach (['unit', 'quantity_step', 'min_quantity', 'batch_consumption', 'unit_label'] as $field) {
            if (!$onlyGiven || array_key_exists($field, $row)) {
                $fields[$field] = $row[$field] ?? null;
            }
        }
        // P3-11: an empty receipt unit label goes back to the unit's own ("dona", "kg")
        if (array_key_exists('unit_label', $fields)) {
            $fields['unit_label'] = is_string($fields['unit_label']) && trim($fields['unit_label']) !== '' ? trim($fields['unit_label']) : null;
        }

        $fields = array_filter($fields, fn ($value, $field) => $value !== null || in_array($field, ['quantity_step', 'min_quantity', 'unit_label'], true), ARRAY_FILTER_USE_BOTH);
        if (($fields['unit'] ?? null) === \App\Enums\MenuUnit::PIECE->value) {
            $fields['quantity_step'] = null;
            $fields['min_quantity'] = null;
        }

        return $fields;
    }

    // --- P3-11: fiscal fields of a product (docs/API.md "Fiskal cheklar") ------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function fiscalRules(): array
    {
        return [
            'kind' => ['nullable', 'string', \Illuminate\Validation\Rule::in(\App\Enums\ProductKind::values())],
            'mxik_code' => ['nullable', 'string', 'regex:' . \App\Domain\Fiscal\FiscalSettings::MXIK_PATTERN],
            'package_code' => ['nullable', 'string', 'regex:' . \App\Domain\Fiscal\FiscalSettings::PACKAGE_CODE_PATTERN],
            'vat_rate' => 'nullable|numeric|min:0|max:100|decimal:0,2',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fiscalMessages(): array
    {
        return [
            'kind.in' => "Mahsulot turi noto'g'ri: dish (taom) yoki service (xizmat).",
            'mxik_code.regex' => "MXIK kodi 17 ta raqamdan iborat bo'lishi kerak.",
            'package_code.regex' => "Qadoq kodi faqat raqamlardan iborat bo'lishi kerak (ko'pi bilan 20 ta).",
            'vat_rate.numeric' => "QQS stavkasi son bo'lishi kerak.",
            'vat_rate.min' => "QQS stavkasi 0 dan 100 gacha bo'lishi kerak.",
            'vat_rate.max' => "QQS stavkasi 0 dan 100 gacha bo'lishi kerak.",
            'vat_rate.decimal' => "QQS stavkasi ko'pi bilan 2 xonali kasr bo'lishi mumkin.",
        ];
    }

    /**
     * The fiscal fields the request carried (a field left out is not touched; an empty one is cleared, `kind` then
     * goes back to dish).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function fiscalFields(array $validated): array
    {
        $fields = [];
        foreach (['kind', 'mxik_code', 'package_code', 'vat_rate'] as $field) {
            if (array_key_exists($field, $validated)) {
                $value = $validated[$field];
                $fields[$field] = $value === null || $value === '' ? null : (string) $value;
            }
        }
        if (array_key_exists('kind', $fields) && $fields['kind'] === null) {
            $fields['kind'] = \App\Enums\ProductKind::DISH->value;
        }

        return $fields;
    }
}
