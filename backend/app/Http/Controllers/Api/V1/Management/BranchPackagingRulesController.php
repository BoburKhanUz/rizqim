<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Fiscal\FiscalSettings;
use App\Domain\Pos\Takeaway\PackagingBasis;
use App\Domain\Pos\Takeaway\PackagingOrderType;
use App\Domain\Pos\Takeaway\PackagingRules;
use App\Enums\Permission;
use App\Models\Branch;
use App\Models\BranchPackagingRule;
use App\Models\Category;
use App\Models\Product;
use App\Support\RowId;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * P6-34 (B8): a branch's packaging rules for the partner web ("Qadoq qoidalari"): each rule is one container (a bag, a
 * soup bowl, a cup lid) sold as its own line with its own price and MXIK, per order or per dish of one category or one
 * product, for POS takeaway and Telegram/Mini App pickup and delivery (never the hall). settings.manage; never from a
 * terminal PIN session; a branch-bound caller works on their own branch; the `packaging` module (403
 * `module_disabled`). Every change is audited and reaches open orders on their next recalculation.
 */
#[Group('Owner: packaging', "Qadoq qoidalari: olib ketish va yetkazishda qadoq qatori (o'z MXIK'i va narxi bilan), 24/7 va olib ketish vaqti (P6-34).")]
class BranchPackagingRulesController extends ManagementController
{
    /**
     * The branch's packaging rules
     *
     * In their order. `basis` (a PackagingBasis value): `order` — `quantity` per order; `item` — `quantity` per portion
     * of the dishes of `category_id` or `product_id`. `order_types`: PackagingOrderType values. `mxik` / `package_code`
     * null: the store's fiscal "packaging" code (fiscal settings).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . PackagingRules::RULE . '>}')]
    public function index(Request $request, string $branch, PackagingRules $rules): JsonResponse
    {
        $model = $this->branch($request, Permission::SETTINGS_MANAGE, RowId::parse($branch));

        return $this->success($rules->rules((int) $model->id)->map(fn (BranchPackagingRule $rule) => PackagingRules::view($rule))->values()->all());
    }

    /**
     * Add a packaging rule
     *
     * At most 50 rules per branch (422). An `item` rule names exactly one of `category_id` and `product_id` (the
     * store's); an `order` rule none. Audited.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . PackagingRules::RULE . '}')]
    public function store(Request $request, string $branch, PackagingRules $rules): JsonResponse
    {
        $model = $this->branch($request, Permission::SETTINGS_MANAGE, RowId::parse($branch));
        $data = $this->validated($request, $model, null);
        if (BranchPackagingRule::where('branch_id', $model->id)->count() >= PackagingRules::MAX_RULES) {
            throw ValidationException::withMessages(['name' => 'Filialda ko\'pi bilan ' . PackagingRules::MAX_RULES . ' ta qadoq qoidasi bo\'ladi.']);
        }

        return $this->success(PackagingRules::view($rules->create($model, $data, $request->user())), "Qadoq qoidasi qo'shildi.", 201);
    }

    /**
     * Change a packaging rule
     *
     * Send only what changes. Audited.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PackagingRules::RULE . '}')]
    public function update(Request $request, string $branch, string $rule, PackagingRules $rules): JsonResponse
    {
        $model = $this->branch($request, Permission::SETTINGS_MANAGE, RowId::parse($branch));
        $row = $this->rule($model, $rule);
        $data = $this->validated($request, $model, $row);

        return $this->success(PackagingRules::view($rules->update($row, $data, $request->user())), 'Qadoq qoidasi saqlandi.');
    }

    /**
     * Delete a packaging rule
     *
     * The orders that already have its line keep it as it was (their snapshot); open orders lose it on their next
     * recalculation. Audited.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int}}')]
    public function destroy(Request $request, string $branch, string $rule, PackagingRules $rules): JsonResponse
    {
        $model = $this->branch($request, Permission::SETTINGS_MANAGE, RowId::parse($branch));
        $row = $this->rule($model, $rule);
        $id = (int) $row->id;
        $rules->delete($row, $request->user());

        return $this->success(['id' => $id], "Qadoq qoidasi o'chirildi.");
    }

    private function rule(Branch $branch, string $id): BranchPackagingRule
    {
        $ruleId = RowId::parse($id);
        $rule = $ruleId !== null ? BranchPackagingRule::where('branch_id', $branch->id)->find($ruleId) : null;
        if (!$rule instanceof BranchPackagingRule) {
            throw new HttpResponseException($this->error('Qadoq qoidasi topilmadi.', ['reason' => 'packaging_rule_not_found'], 404));
        }

        return $rule;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Branch $branch, ?BranchPackagingRule $rule): array
    {
        $creating = $rule === null;
        $required = $creating ? 'required' : 'sometimes';
        $data = $request->validate([
            'name' => [$required, 'string', 'max:128'],
            'basis' => [$required, 'string', Rule::in(PackagingBasis::values())],
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'product_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:' . PackagingRules::MAX_QUANTITY],
            'unit_price' => [$required, 'numeric', 'min:0', 'max:' . PackagingRules::MAX_PRICE, 'decimal:0,2'],
            'mxik' => ['sometimes', 'nullable', 'string', 'regex:' . FiscalSettings::MXIK_PATTERN],
            'package_code' => ['sometimes', 'nullable', 'string', 'regex:' . FiscalSettings::PACKAGE_CODE_PATTERN],
            'unit' => ['sometimes', 'nullable', 'string', 'max:32'],
            'order_types' => [$required, 'array', 'min:1'],
            'order_types.*' => ['string', Rule::in(PackagingOrderType::values())],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ], UzValidation::messages([
            'mxik.regex' => "MXIK kodi 17 ta raqamdan iborat bo'lishi kerak.",
            'package_code.regex' => "Qadoq kodi faqat raqamlardan iborat bo'lishi kerak (ko'pi bilan 20 ta).",
        ]), [
            'name' => 'Qadoq nomi',
            'basis' => 'Hisob turi',
            'category_id' => 'Kategoriya',
            'product_id' => 'Taom',
            'quantity' => 'Soni',
            'unit_price' => 'Narxi',
            'mxik' => 'MXIK',
            'package_code' => 'Qadoq kodi',
            'unit' => "O'lchov birligi",
            'order_types' => 'Buyurtma turlari',
            'is_active' => 'Faol',
            'sort_order' => 'Tartib',
        ]);

        $basis = isset($data['basis']) ? PackagingBasis::from($data['basis']) : $rule?->basis;
        $categoryId = array_key_exists('category_id', $data) ? $data['category_id'] : $rule?->category_id;
        $productId = array_key_exists('product_id', $data) ? $data['product_id'] : $rule?->product_id;
        if ($basis === PackagingBasis::ITEM) {
            if (($categoryId === null) === ($productId === null)) {
                throw ValidationException::withMessages(['category_id' => 'Taomga qadoq uchun bitta kategoriya yoki bitta taomni tanlang.']);
            }
            // Categories are shared by every store (as the kitchen station routes read them)
            if ($categoryId !== null && !Category::whereKey((int) $categoryId)->exists()) {
                throw ValidationException::withMessages(['category_id' => 'Kategoriya topilmadi.']);
            }
            if ($productId !== null && !Product::where('store_id', $branch->store_id)->whereKey((int) $productId)->exists()) {
                throw ValidationException::withMessages(['product_id' => 'Taom topilmadi.']);
            }
        } else {
            $data['category_id'] = null;
            $data['product_id'] = null;
        }

        return $data;
    }
}
