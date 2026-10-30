<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Labels\LabelScope;
use App\Domain\Pos\Labels\LabelSettings;
use App\Domain\Pos\Labels\LabelSize;
use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Enums\Permission;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P6-32 (B7): a branch's sticker and label settings for the partner web ("Stiker va yorliq"): the label roll size,
 * which items get a cup sticker, one sticker per piece, a bag label for takeaway orders. settings.manage; never from a
 * terminal PIN session; a branch-bound caller works on their own branch; the `labels` module (403 `module_disabled`,
 * or `plan_feature_missing`).
 */
#[Group('Owner: stickers and labels', "Filialning stakan stikeri va paket yorlig'i sozlamalari (P6-32).")]
class BranchLabelSettingsController extends ManagementController
{
    /**
     * The branch's sticker and label settings
     *
     * Every key with its default when nothing was saved (`configured: false`): `size` (a LabelSize value, default
     * 40x30; `width_mm` and `height_mm` follow), `scope` (a LabelScope value: all, or only `category_ids`), `per_unit`
     * (one sticker per piece, default true) and `bag_label` (a bag label for takeaway orders, default true). The till
     * reads the same block from GET /pos/bootstrap `labels`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . LabelSettings::VIEW . '}')]
    public function show(Request $request, int $id, LabelSettings $settings, PosModules $modules): JsonResponse
    {
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE, $id);
        $modules->assertEnabled($branch, PosModule::LABELS);

        return $this->success($settings->view($branch));
    }

    /**
     * Change the branch's sticker and label settings
     *
     * Send only what changes. `scope` `categories` needs at least one entry in `category_ids` (existing category ids);
     * `all` clears them. Audited (settings.label_settings_changed).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . LabelSettings::VIEW . '}')]
    public function update(Request $request, int $id, LabelSettings $settings, PosModules $modules): JsonResponse
    {
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE, $id);
        $modules->assertEnabled($branch, PosModule::LABELS);

        $data = $request->validate([
            'size' => ['sometimes', Rule::enum(LabelSize::class)],
            'scope' => ['sometimes', Rule::enum(LabelScope::class)],
            'category_ids' => ['sometimes', 'array', 'max:' . LabelSettings::MAX_CATEGORIES],
            'category_ids.*' => ['integer', 'min:1', 'distinct'],
            'per_unit' => ['sometimes', 'boolean'],
            'bag_label' => ['sometimes', 'boolean'],
        ], UzValidation::messages(), [
            'size' => "Yorliq o'lchami",
            'scope' => 'Stiker qaysi taomlarga',
            'category_ids' => 'Kategoriyalar',
            'per_unit' => 'Har dona uchun stiker',
            'bag_label' => 'Paket yorlig\'i',
        ]);

        return $this->success($settings->update($branch, $data, $request->user()), "Stiker va yorliq sozlamalari saqlandi.");
    }
}
