<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Banquet\Ops\BanquetOpsSettings;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P6-54: the owner's banquet operations settings (partner web "Tadbirlar" → ishlab chiqarish va shartnoma): the
 * production sheet's reserve %, the osh calculator (persons per kg, ingredients per kg, kazans, the osh products),
 * portion weights, the final sale's MXIK codes (package, hall rent, extras) and the contract template. banquets.manage
 * with a web sign-in (never a terminal PIN session); every change audited with the optimistic `version`.
 */
#[Group('Owner: banquets', "Banket sozlamalari: paketlar, qo'shimchalar, zal ijarasi, vaqtlar va ogohlantirishlar (P6-51, P6-52).")]
class BanquetOpsSettingsController extends ManagementController
{
    /**
     * Banquet operations settings
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetOpsSettings::SHAPE . '}')]
    public function show(Request $request): JsonResponse
    {
        return $this->success(app(BanquetOpsSettings::class)->of((int) $this->currentStore($request)->id));
    }

    /**
     * Change the banquet operations settings
     *
     * Only the fields sent change. `reserve_percent` 0–50; `osh`: {persons_per_kg 1–20, ingredients: [{key, name,
     * unit kg|l|dona, per_kg}] (up to 30), kazans: kg sizes (up to 10), product_ids: the store's osh products};
     * `portion_weights`: [{product_id, grams}]; the MXIK codes (17 digits) and package codes of the package, hall rent
     * and extras lines of the final sale receipt (accountant to confirm); `contract_template` (up to 20 000
     * characters; empty: the default text). `version`: the one read (409 `version_conflict` when it moved). Audited.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . BanquetOpsSettings::SHAPE . '}')]
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'version' => 'nullable|integer|min:0',
            'reserve_percent' => 'sometimes|numeric|min:0|max:50|decimal:0,2',
            'osh' => 'sometimes|array',
            'osh.persons_per_kg' => 'required_with:osh|numeric|min:1|max:20|decimal:0,1',
            'osh.ingredients' => 'required_with:osh|array|max:30',
            'osh.ingredients.*.key' => 'nullable|string|max:32|regex:/^[a-z0-9_]+$/',
            'osh.ingredients.*.name' => 'required|string|max:60',
            'osh.ingredients.*.unit' => 'required|string|in:kg,l,dona',
            'osh.ingredients.*.per_kg' => 'required|numeric|min:0|max:100|decimal:0,3',
            'osh.kazans' => 'required_with:osh|array|min:1|max:10',
            'osh.kazans.*' => 'integer|min:1|max:1000',
            'osh.product_ids' => 'sometimes|array|max:50',
            'osh.product_ids.*' => 'integer|min:1|max:999999999999999999',
            'portion_weights' => 'sometimes|array|max:500',
            'portion_weights.*.product_id' => 'required|integer|min:1|max:999999999999999999',
            'portion_weights.*.grams' => 'required|integer|min:1|max:100000',
            'package_mxik' => 'sometimes|nullable|string|regex:/^\d{17}$/',
            'package_package_code' => 'sometimes|nullable|string|max:32|regex:/^\d+$/',
            'hall_mxik' => 'sometimes|nullable|string|regex:/^\d{17}$/',
            'hall_package_code' => 'sometimes|nullable|string|max:32|regex:/^\d+$/',
            'extra_mxik' => 'sometimes|nullable|string|regex:/^\d{17}$/',
            'extra_package_code' => 'sometimes|nullable|string|max:32|regex:/^\d+$/',
            'contract_template' => 'sometimes|nullable|string|max:20000',
        ]);
        $storeId = (int) $this->currentStore($request)->id;
        $settings = app(BanquetOpsSettings::class);
        $version = isset($data['version']) ? (int) $data['version'] : null;
        unset($data['version']);

        return $this->success($settings->update($storeId, $data, $version, $request->user()), 'Sozlamalar saqlandi.');
    }
}
